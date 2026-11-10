<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Support\Str;

/**
 * One attribute an import can fill, as in Filament. A CSV header matches it by
 * its name, its label or one of its guesses, with case, spaces, dashes and
 * underscores ignored, so "Product name" fills product_name.
 *
 * @api
 */
class ImportColumn
{
    protected ?string $label = null;

    /** @var array<int, string> */
    protected array $guesses = [];

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected bool $isMappingRequired = false;

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Other headers that mean this column, as in ['sku', 'item code'].
     *
     * @param  array<int, string>  $guesses
     */
    public function guesses(array $guesses): static
    {
        $this->guesses = $guesses;

        return $this;
    }

    /**
     * Rules each row's value must pass, on top of the form's own. A row that
     * fails counts as failed and the rest go on.
     *
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Refuse a file with no header for this column, before any row is read.
     */
    public function requiredMapping(bool $condition = true): static
    {
        $this->isMappingRequired = $condition;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function isMappingRequired(): bool
    {
        return $this->isMappingRequired;
    }

    public function matches(string $header): bool
    {
        $header = self::normalize($header);

        return array_any([$this->name, $this->getLabel(), ...$this->guesses], fn (string $name): bool => self::normalize($name) === $header);
    }

    private static function normalize(string $name): string
    {
        return trim((string) preg_replace('/[\s_-]+/', '_', mb_strtolower(trim($name))), '_');
    }
}
