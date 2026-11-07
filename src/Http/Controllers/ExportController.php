<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Xelqun\Panels\Resources\Resource;

/**
 * Sends a resource's list as CSV. It reaches what the list reaches: the resource's
 * query, the tab in the URL, and viewAny.
 */
final class ExportController
{
    private const int CHUNK = 500;

    public function __invoke(Request $request): StreamedResponse
    {
        $resource = $request->route()->parameter('resource');

        abort_unless(is_string($resource) && is_subclass_of($resource, Resource::class), 404);
        abort_unless($resource::can('viewAny'), 403);

        $columns = $resource::getExportColumns();
        $tabKey = $request->query('tab');
        $tab = is_string($tabKey) ? ($resource::getTabs()[$tabKey] ?? null) : null;
        $query = $tab === null ? $resource::getEloquentQuery() : $tab->applyTo($resource::getEloquentQuery());

        return response()->streamDownload(function () use ($columns, $query): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, array_keys($columns), escape: '');

            $query->chunkById(self::CHUNK, function ($records) use ($columns, $output): void {
                foreach ($records as $record) {
                    fputcsv($output, array_map(
                        fn (string $attribute): string => self::neutralize(data_get($record, $attribute)),
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
        $text = is_scalar($value) || $value instanceof \Stringable ? (string) $value : (string) json_encode($value);

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'{$text}" : $text;
    }
}
