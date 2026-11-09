<?php

namespace Xelqun\Panels\Resources;

/**
 * Relation managers under one tab, one table after another. List it in
 * Resource::getRelations() beside the managers that have a tab of their own.
 *
 * @api
 */
final class RelationGroup
{
    /**
     * @param  array<int, class-string<RelationManager>>  $managers
     */
    private function __construct(private readonly string $label, private readonly array $managers) {}

    /**
     * @param  array<int, class-string<RelationManager>>  $managers
     */
    public static function make(string $label, array $managers): self
    {
        return new self($label, $managers);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public function getManagers(): array
    {
        return $this->managers;
    }
}
