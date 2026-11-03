<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ForAGame;
use App\Services\PushStats;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * How the push notifications of the game stand: whether it can send them (the rules and the keys of the game; never the private key), how many devices and accounts have agreed and
 * what was sent in the last seven days. It changes nothing: the limits, the quiet hours and the group that is never told are numbers of the Studio (the section push), and the keys are
 * made on the server (`php artisan push:keys`).
 */
class PushPage extends Page
{
    use ForAGame;

    protected static ?string $slug = 'push';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.push.page';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.push_page.nav');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return 'heroicon-o-bell-alert';
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.push_page.title');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['stats' => app(PushStats::class)->report()];
    }
}
