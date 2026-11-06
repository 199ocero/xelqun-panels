<?php

namespace Xelqun\Panels\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Select;
use Xelqun\Tables\Filters\BaseFilter;

/**
 * Soft-deleted records are hidden until this says otherwise. The hiding itself is
 * in ResourceTable, so a row's actions can still find a trashed record.
 *
 * @api
 */
class TrashedFilter extends BaseFilter
{
    public const string WITH = '1';

    public const string ONLY = '0';

    public function getFormComponents(): array
    {
        return [
            Select::make($this->name)
                ->label($this->getLabel())
                ->placeholder(__('xelqun-panels::resources.without_trashed'))
                ->options([self::WITH => __('xelqun-panels::resources.with_trashed'), self::ONLY => __('xelqun-panels::resources.only_trashed')]),
        ];
    }

    public function apply(Builder $query, array $data): void
    {
        if ((string) $data[$this->name] === self::ONLY) {
            $query->whereNotNull($query->qualifyColumn($this->getDeletedAtColumn($query)));
        }
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function getDeletedAtColumn(Builder $query): string
    {
        $model = $query->getModel();

        return method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : 'deleted_at';
    }

    public function isActive(array $data): bool
    {
        return in_array((string) ($data[$this->name] ?? ''), [self::WITH, self::ONLY], true);
    }
}
