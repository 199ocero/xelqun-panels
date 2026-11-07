<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * Creates records from a CSV whose first row names the columns. Each row goes
 * through the resource's form, so it gets the same validation, hooks and tenant
 * as one typed in. Columns not in getImportColumns() are ignored.
 *
 * shortcut: this runs in the request, so it stops at MAX_ROWS. Queue it if an app imports more.
 *
 * @api
 */
class ImportAction extends Action
{
    private const int MAX_ROWS = 1000;

    private const string DISK = 'local';

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
            ->modalDescription(__('xelqun-panels::resources.import_description', ['columns' => implode(', ', $resource::getImportColumns())]))
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
            ->action(function (array $data) use ($resource): void {
                $path = (string) $data['file'];

                try {
                    static::report(static::importRows($resource, Storage::disk(self::DISK)->path($path)));
                } finally {
                    Storage::disk(self::DISK)->delete($path);
                }
            });
    }

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @return array{imported: int, failed: int}
     */
    protected static function importRows(string $resource, string $path): array
    {
        $file = fopen($path, 'r');
        $columns = array_map(fn (mixed $name): string => trim((string) $name, " \t\n\r\0\x0B\xEF\xBB\xBF"), fgetcsv($file, escape: '') ?: []);
        $counts = ['imported' => 0, 'failed' => 0];

        for ($row = 0; $row < self::MAX_ROWS && ($values = fgetcsv($file, escape: '')) !== false; $row++) {
            if (count($values) !== count($columns)) {
                $counts['failed']++;

                continue;
            }

            $attributes = array_intersect_key(array_combine($columns, $values), array_flip($resource::getImportColumns()));

            try {
                ResourceForm::for($resource, ResourceForm::CREATE)->save(['data' => $attributes]);
                $counts['imported']++;
            } catch (ValidationException) {
                $counts['failed']++;
            }
        }

        fclose($file);

        return $counts;
    }

    /**
     * @param  array{imported: int, failed: int}  $counts
     */
    protected static function report(array $counts): void
    {
        $notification = Notification::make()->title(__('xelqun-panels::resources.imported', $counts));

        ($counts['failed'] > 0 ? $notification->warning() : $notification->success())->send();
    }
}
