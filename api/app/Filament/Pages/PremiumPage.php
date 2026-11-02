<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Definitions\PremiumForm;
use App\Filament\Forms\PremiumFields;
use Illuminate\Contracts\Support\Htmlable;

/** The paid features of the game: the premium currency and its packs, the speed-up, the auto-refill, the VIP account and the pass of the endless refill. */
class PremiumPage extends RulesDocumentPage
{
    protected static ?string $slug = 'premium';

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return __('admin.premium.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.premium.title');
    }

    protected function documentSlug(): string
    {
        return 'starter';
    }

    protected function fields(array $document): array
    {
        return PremiumFields::schema();
    }

    protected function toState(array $document): array
    {
        return PremiumForm::toState($document);
    }

    protected function toDocument(array $document, array $state): array
    {
        return PremiumForm::apply($document, $state);
    }
}
