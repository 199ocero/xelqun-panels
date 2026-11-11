<?php

namespace Xelqun\Panels\Http\Controllers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;
use Xelqun\Actions\ActionGroup;
use Xelqun\Panels\Actions\ExportAction;
use Xelqun\Panels\Pages\PageActionSet;
use Xelqun\Panels\Resources\Pages\ResourcePage;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Tables\Columns\Column;

/**
 * Sends a resource's list as CSV. It reaches what the list reaches: the resource's
 * query, the tab in the URL, the list page's access check, and the export action's
 * own authorize() and visibility. A table column exports what it shows.
 */
final class ExportController
{
    private const int CHUNK = 500;

    public function __invoke(Request $request): StreamedResponse
    {
        $resource = $request->route()->parameter('resource');

        abort_unless(is_string($resource) && is_subclass_of($resource, Resource::class), 404);

        $page = app($resource::getPages()['index'] ?? abort(404));
        abort_unless($page instanceof ResourcePage, 404);
        $page->forResource($resource);
        abort_unless($page->isAccessible(), 403);

        // Only a list that offers the button has this, and only to whoever may press it.
        $export = collect(ActionGroup::flatten(PageActionSet::for($page)->getComponents()))->first(fn (mixed $action): bool => $action instanceof ExportAction);
        abort_unless($export instanceof ExportAction, 404);
        abort_unless($export->isShown() && ! $export->isDisabled(), 403);

        $tableColumns = collect($resource::columns())->reject(fn (Column $column): bool => $column->isHidden())->keyBy(fn (Column $column): string => $column->getName());
        $columns = $resource::getExportColumns();
        $tabs = $resource::getTabs();
        $tabKey = $request->query('tab');
        $tab = is_string($tabKey) && isset($tabs[$tabKey]) ? $tabs[$tabKey] : ($tabs[array_key_first($tabs)] ?? null);
        $query = $tab === null ? $resource::getEloquentQuery() : $tab->applyTo($resource::getEloquentQuery());

        return response()->streamDownload(function () use ($columns, $query, $tableColumns): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, array_keys($columns), escape: '');

            $query->chunkById(self::CHUNK, function ($records) use ($columns, $output, $tableColumns): void {
                foreach ($records as $record) {
                    fputcsv($output, array_map(
                        fn (string $attribute): string => self::neutralize($tableColumns->has($attribute) ? $tableColumns->get($attribute)->toCell($record)['state'] : data_get($record, $attribute)),
                        array_values($columns),
                    ), escape: '');
                }
            });

            fclose($output);
        }, Str::slug($resource::getPluralModelLabel()).'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * A spreadsheet runs a cell that starts like a formula. A leading quote keeps it
     * as text, or a customer's name could run on the admin's computer.
     */
    private static function neutralize(mixed $value): string
    {
        $value = match (true) {
            $value instanceof Model => $value->getKey(),
            $value instanceof Collection => $value->map(fn (mixed $item): mixed => $item instanceof Model ? $item->getKey() : $item)->implode(', '),
            is_array($value) && array_is_list($value) && array_filter($value, fn (mixed $item): bool => ! is_scalar($item) && $item !== null) === [] => implode(', ', $value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            default => $value,
        };
        $text = $value === null || is_scalar($value) || $value instanceof Stringable ? (string) $value : (string) json_encode($value);

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'{$text}" : $text;
    }
}
