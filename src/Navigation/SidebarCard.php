<?php

namespace Xelqun\Panels\Navigation;

use Xelqun\Forms\Support\SafeUrl;

/**
 * The card at the foot of the sidebar, for something worth keeping in view,
 * such as usage against a limit. Set it with Panel::sidebarFooter().
 *
 * @api
 */
final class SidebarCard
{
    private ?string $icon = null;

    private ?string $badge = null;

    private ?string $value = null;

    private ?string $note = null;

    private ?float $progress = null;

    private ?string $footer = null;

    /** @var array{label: string, url: string|null}|null */
    private ?array $link = null;

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(string $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function value(string $value, ?string $note = null): self
    {
        $this->value = $value;
        $this->note = $note;

        return $this;
    }

    /**
     * How full the meter is, from 0 to 1.
     */
    public function progress(float $progress): self
    {
        $this->progress = max(0.0, min(1.0, $progress));

        return $this;
    }

    public function footer(string $footer): self
    {
        $this->footer = $footer;

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->link = ['label' => $label, 'url' => SafeUrl::filter($url)];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'value' => $this->value,
            'note' => $this->note,
            'progress' => $this->progress,
            'footer' => $this->footer,
            'link' => $this->link === null || $this->link['url'] === null ? null : $this->link,
        ];
    }
}
