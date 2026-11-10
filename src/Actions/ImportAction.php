<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Notifications\DatabaseNotification;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * Creates records from a CSV whose first row names the columns. Each row goes
 * through the resource's form, so it gets the same validation, hooks and tenant
 * as one typed in. Headers match getImportColumns() by name, label or guesses,
 * and other columns are ignored.
 *
 * In the request it stops at MAX_ROWS and says how many it skipped. queue()
 * reads the whole file in a job and tells the user in their bell.
 *
 * @api
 */
class ImportAction extends Action
{
    public const int MAX_ROWS = 1000;

    public const string DISK = 'local';

    protected bool $isQueued = false;

    protected ?string $queueName = null;

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('import')
            ->label(__('xelqun-panels::resources.import'))
            ->icon('upload')
            ->color('gray')
            ->authorize('create')
            ->modalDescription(__('xelqun-panels::resources.import_description', ['columns' => implode(', ', array_map(
                fn (ImportColumn $column): string => $column->getLabel(),
                static::getColumns($resource),
            ))]))
            ->schema(fn (): array => [
                FileUpload::make('file')
                    ->label(__('xelqun-panels::resources.import_file'))
                    ->required()
                    ->disk(self::DISK)
                    ->directory('xelqun-imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                    ->maxSize(10_240),
            ])
            ->successRedirectUrl(fn (): string => $resource::getUrl())
            ->action(fn (array $data, ImportAction $action) => $action->import($resource, (string) $data['file']));
    }

    /**
     * Read the file in a queued job instead of the request, with no row limit.
     * The panel needs databaseNotifications(), because the result goes to the
     * user's bell.
     */
    public function queue(bool $condition = true, ?string $queue = null): static
    {
        $this->isQueued = $condition;
        $this->queueName = $queue;

        return $this;
    }

    /**
     * @internal
     *
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public function import(string $resource, string $path): void
    {
        $missing = self::getMissingColumns($resource, Storage::disk(self::DISK)->path($path));

        if ($missing !== []) {
            Storage::disk(self::DISK)->delete($path);
            Notification::make()
                ->title(__('xelqun-panels::resources.import_missing_columns', ['columns' => implode(', ', $missing)]))
                ->danger()
                ->send();

            $this->halt();
        }

        if (! $this->isQueued) {
            try {
                static::report(static::importRows($resource, Storage::disk(self::DISK)->path($path)));
            } finally {
                Storage::disk(self::DISK)->delete($path);
            }

            return;
        }

        $panels = app(Panels::class);
        $panel = $panels->current() ?? throw new PanelDefinitionException('A queued import runs from a panel.');

        if (! $panel->hasDatabaseNotifications()) {
            Storage::disk(self::DISK)->delete($path);

            throw new PanelDefinitionException("A queued import tells the user when it's done in their bell, so the panel [{$panel->getId()}] needs databaseNotifications().");
        }

        // After the action's transaction, so a worker never starts on a run that was rolled back.
        ImportRecords::dispatch(static::class, $resource, $path, $panel->getId(), auth()->id(), $panels->tenant()?->getKey(), $panels->parentKey())
            ->onQueue($this->queueName)
            ->afterCommit();

        Notification::make()->title(__('xelqun-panels::resources.import_queued'))->success()->send();
    }

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @return array<int, ImportColumn>
     */
    public static function getColumns(string $resource): array
    {
        return array_map(
            fn (string|ImportColumn $column): ImportColumn => is_string($column) ? ImportColumn::make($column) : $column,
            $resource::getImportColumns(),
        );
    }

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @return array{imported: int, failed: int, skipped: int}
     */
    protected static function importRows(string $resource, string $path): array
    {
        return static::importRowsUpTo($resource, $path, self::MAX_ROWS);
    }

    /**
     * Each row through the resource's form, and past $maxRows only counted.
     *
     * @internal
     *
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @return array{imported: int, failed: int, skipped: int}
     */
    public static function importRowsUpTo(string $resource, string $path, ?int $maxRows): array
    {
        $file = fopen($path, 'r') ?: throw new RuntimeException("The import file [{$path}] can't be read.");
        $columns = self::mapHeader($resource, self::cleanHeader(fgetcsv($file, escape: '')));
        $counts = ['imported' => 0, 'failed' => 0, 'skipped' => 0];

        for ($row = 0; ($maxRows === null || $row < $maxRows) && ($values = fgetcsv($file, escape: '')) !== false; $row++) {
            if (count($values) !== count($columns)) {
                $counts['failed']++;

                continue;
            }

            $attributes = [];
            $rules = [];

            foreach ($columns as $position => $column) {
                if ($column !== null) {
                    $attributes[$column->getName()] = $values[$position];
                    $rules[$column->getName()] = $column->getRules();
                }
            }

            try {
                Validator::make($attributes, array_filter($rules))->validate();
                ResourceForm::for($resource, ResourceForm::CREATE)->save(['data' => $attributes]);
                $counts['imported']++;
            } catch (ValidationException) {
                $counts['failed']++;
            }
        }

        // Counted, so the viewer learns the file was cut short instead of thinking it all went in.
        while (fgetcsv($file, escape: '') !== false) {
            $counts['skipped']++;
        }

        fclose($file);

        return $counts;
    }

    /**
     * @param  array{imported: int, failed: int, skipped?: int}  $counts
     */
    protected static function report(array $counts): void
    {
        static::makeReport($counts)->send();
    }

    /**
     * What the user learns once the rows are in, on the page or in their bell.
     *
     * @internal
     *
     * @param  array{imported: int, failed: int, skipped?: int}  $counts
     */
    public static function makeReport(array $counts): DatabaseNotification
    {
        $skipped = $counts['skipped'] ?? 0;
        $notification = DatabaseNotification::make()->title(__('xelqun-panels::resources.imported', ['imported' => $counts['imported'], 'failed' => $counts['failed']]));

        if ($skipped > 0) {
            $notification->body(__('xelqun-panels::resources.import_skipped', ['skipped' => $skipped, 'max' => self::MAX_ROWS]));
        }

        return $counts['failed'] > 0 || $skipped > 0 ? $notification->warning() : $notification->success();
    }

    /**
     * @param  array<int, string|null>|false  $row
     * @return array<int, string>
     */
    private static function cleanHeader(array|false $row): array
    {
        return array_map(fn (?string $name): string => trim((string) $name, " \t\n\r\0\x0B\xEF\xBB\xBF"), $row ?: []);
    }

    /**
     * Each header's column, or null for one that fills nothing. The first
     * header to match a column takes it.
     *
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @param  array<int, string>  $header
     * @return array<int, ImportColumn|null>
     */
    private static function mapHeader(string $resource, array $header): array
    {
        $available = static::getColumns($resource);
        $mapped = [];

        foreach ($header as $position => $name) {
            $index = array_find_key($available, fn (ImportColumn $column): bool => $column->matches($name));
            $mapped[$position] = $index === null ? null : $available[$index];
            unset($available[$index ?? -1]);
        }

        return $mapped;
    }

    /**
     * The labels of required columns no header matches.
     *
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @return array<int, string>
     */
    private static function getMissingColumns(string $resource, string $path): array
    {
        $file = fopen($path, 'r') ?: throw new RuntimeException("The import file [{$path}] can't be read.");
        $mapped = array_filter(self::mapHeader($resource, self::cleanHeader(fgetcsv($file, escape: ''))));
        fclose($file);

        return array_values(array_map(
            fn (ImportColumn $column): string => $column->getLabel(),
            array_filter(
                static::getColumns($resource),
                fn (ImportColumn $column): bool => $column->isMappingRequired() && ! in_array($column->getName(), array_map(fn (ImportColumn $found): string => $found->getName(), $mapped), true),
            ),
        ));
    }
}
