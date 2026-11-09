<?php

namespace Xelqun\Panels\Infolists;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\MarkdownEditor;
use Xelqun\Forms\Support\RichTextSanitizer;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * It takes TextColumn's options, so description(), placeholder(), limit(),
 * words(), lineClamp(), copyable(), tooltip(), numeric(), since() and the list
 * options work here as they do in a table.
 *
 * @method static badge(bool $condition = true)
 * @method static money(string $currency, ?string $locale = null)
 * @method static date(string $format = 'M j, Y', ?string $timezone = null)
 * @method static dateTime(string $format = 'M j, Y H:i', ?string $timezone = null)
 * @method static description(string|\Closure|null $description, string $position = 'below')
 * @method static placeholder(string|\Closure|null $placeholder)
 * @method static limit(?int $length, string $end = '...')
 * @method static words(?int $words, string $end = '...')
 * @method static lineClamp(?int $lines)
 * @method static copyable(bool $condition = true)
 * @method static tooltip(string|\Closure|null $tooltip)
 *
 * @api
 */
class TextEntry extends Entry
{
    protected bool $isHtml = false;

    protected bool $isMarkdown = false;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name);
    }

    /**
     * Show the value as HTML, cleaned the way a rich editor's content is.
     */
    public function html(bool $condition = true): static
    {
        $this->isHtml = $condition;

        return $this;
    }

    /**
     * Show the value as Markdown, turned into clean HTML.
     */
    public function markdown(bool $condition = true): static
    {
        $this->isMarkdown = $condition;

        return $this;
    }

    /**
     * Text before the value, such as a currency sign.
     */
    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Text after the value, such as a unit.
     */
    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function toProps(Model $record): array
    {
        $props = parent::toProps($record);
        $state = $props['cell']['state'] ?? null;

        if (($this->isHtml || $this->isMarkdown) && is_string($state)) {
            $props['html'] = $this->isMarkdown ? MarkdownEditor::render($state) : app(RichTextSanitizer::class)->sanitize($state);
        }

        return [...$props, ...array_filter(['prefix' => $this->prefix, 'suffix' => $this->suffix])];
    }
}
