<?php

namespace Xelqun\Panels\Tests\Fixtures\Pages;

use Xelqun\Actions\Action;
use Xelqun\Panels\Pages\Page;

class Home extends Page
{
    protected static ?string $slug = '';

    public static bool $allowed = true;

    public static array $calls = [];

    public static function canAccess(): bool
    {
        return self::$allowed;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('publish')->authorize(true)->action(function (): void {
                self::$calls[] = auth()->getDefaultDriver();
            }),
        ];
    }
}
