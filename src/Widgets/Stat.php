<?php

namespace Xelqun\Panels\Widgets;

/**
 * One number on a StatsOverview.
 *
 * @api
 */
final class Stat
{
    private ?string $description = null;

    private ?string $icon = null;

    /** @var array<int, int|float> */
    private array $chart = [];

    private function __construct(private readonly string $label, private readonly string $value) {}

    public static function make(string $label, string|int|float $value): self
    {
        return new self($label, (string) $value);
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * A small line behind the number, oldest value first.
     *
     * @param  array<int, int|float>  $values
     */
    public function chart(array $values): self
    {
        $this->chart = array_values($values);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'description' => $this->description,
            'icon' => $this->icon,
            'chart' => $this->chart,
        ];
    }
}
