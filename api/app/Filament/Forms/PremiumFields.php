<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Content\Definitions\PremiumForm;
use App\Filament\Resources\Definitions\Forms\DefinitionFields;
use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The paid features of the rules as a form: the premium currency and the packs of it that are sold, and the three extras (the auto-refill, the VIP account and the pass),
 * each with a switch. A bonus is a percent and a price is money in the currency of the packs. What the schema and the rules of the pack ask is theirs to say, and they say
 * it when the draft is saved.
 */
final class PremiumFields
{
    private const SLUG = '/^[a-z][a-z0-9-]{1,63}$/';

    /** @return list<mixed> */
    public static function schema(): array
    {
        return [
            FieldHelp::guide('premium'),
            Text::make(fn (): string => __('admin.premium.no_provider'))->color('warning')->visible(fn (): bool => blank(config('ride.premium.provider'))),
            Tabs::make('premium')->persistTabInQueryString()->tabs([
                Tab::make(__('admin.premium.coins'))->schema(self::coins()),
                Tab::make(__('admin.premium.autorefill'))->schema(self::autorefill()),
                Tab::make(__('admin.premium.vip'))->schema(self::vip()),
                Tab::make(__('admin.premium.pass'))->schema(self::pass()),
            ]),
            Text::make(fn (Get $get): string => self::worthText(self::stateOf($get)))->color('gray'),
        ];
    }

    /** @return list<mixed> */
    private static function coins(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('currency_code')->helpKey('premium.currency_code')->required()->maxLength(32)->regex('/^[a-z][a-z0-9-]{1,31}$/'),
                TextInput::make('price_currency')
                    ->helpKey('premium.price_currency')
                    ->required()
                    ->length(3)
                    ->regex('/^[A-Z]{3}$/')
                    ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? strtoupper(trim($state)) : $state)
                    ->live(onBlur: true),
                TextInput::make('speedup_minutes')->helpKey('premium.speedup_minutes')->numeric()->integer()->minValue(1)->maxValue(1440)->suffix('min'),
            ]),
            DefinitionFields::texts('currency_name', __('admin.premium.currency_name'), 'premium.currency_name'),
            Repeater::make('products')
                ->helpKey('premium.products')
                ->minItems(1)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(__('admin.premium.product_add'))
                ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('slug')->helpKey('premium.product_slug')->required()->maxLength(64)->regex(self::SLUG)->distinct(),
                        TextInput::make('coins')->helpKey('premium.product_coins')->numeric()->integer()->minValue(1)->maxValue(1_000_000)->required()->live(onBlur: true),
                        TextInput::make('price')
                            ->helpKey('premium.product_price')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(1_000_000)
                            ->suffix(fn (Get $get): ?string => self::currencyOf($get))
                            ->required()
                            ->live(onBlur: true),
                    ]),
                    DefinitionFields::texts('name', __('admin.definitions.name'), 'premium.product_name'),
                ]),
        ];
    }

    /** @return list<mixed> */
    private static function autorefill(): array
    {
        return [
            Toggle::make('autorefill.enabled')->helpKey('premium.autorefill_enabled')->inline(false)->live(),
            Grid::make(1)->visible(fn (Get $get): bool => (bool) $get('autorefill.enabled'))->schema([
                TextInput::make('autorefill.stock_cap')->helpKey('premium.stock_cap')->numeric()->integer()->minValue(1)->maxValue(100_000)->required(),
                Repeater::make('autorefill.packs')
                    ->helpKey('premium.packs')
                    ->minItems(1)
                    ->reorderableWithButtons()
                    ->addActionLabel(__('admin.premium.pack_add'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('slug')->helpKey('premium.pack_slug')->required()->maxLength(64)->regex(self::SLUG)->distinct(),
                        TextInput::make('charges')->helpKey('premium.pack_charges')->numeric()->integer()->minValue(1)->maxValue(100_000)->required()->live(onBlur: true),
                        TextInput::make('coins')->helpKey('premium.pack_coins')->numeric()->integer()->minValue(1)->maxValue(1_000_000)->required()->live(onBlur: true),
                    ]),
            ]),
        ];
    }

    /** @return list<mixed> */
    private static function vip(): array
    {
        return [
            Toggle::make('vip.enabled')->helpKey('premium.vip_enabled')->inline(false)->live(),
            Grid::make(2)->visible(fn (Get $get): bool => (bool) $get('vip.enabled'))->schema([
                TextInput::make('vip.days')->helpKey('premium.vip_days')->numeric()->integer()->minValue(1)->maxValue(3650)->suffix(__('admin.premium.days'))->required(),
                TextInput::make('vip.coins')->helpKey('premium.vip_coins')->numeric()->integer()->minValue(1)->maxValue(1_000_000)->required()->live(onBlur: true),
                TextInput::make('vip.cash_percent')->helpKey('premium.vip_cash_percent')->numeric()->minValue(0)->maxValue(500)->suffix('%')->required(),
                TextInput::make('vip.xp_percent')->helpKey('premium.vip_xp_percent')->numeric()->minValue(0)->maxValue(500)->suffix('%')->required(),
            ]),
        ];
    }

    /** @return list<mixed> */
    private static function pass(): array
    {
        return [
            Toggle::make('infinity.enabled')->helpKey('premium.infinity_enabled')->inline(false)->live(),
            Grid::make(2)->visible(fn (Get $get): bool => (bool) $get('infinity.enabled'))->schema([
                TextInput::make('infinity.days')->helpKey('premium.infinity_days')->numeric()->integer()->minValue(1)->maxValue(3650)->suffix(__('admin.premium.days'))->required()->live(onBlur: true),
                TextInput::make('infinity.price')
                    ->helpKey('premium.infinity_price')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(1_000_000)
                    ->suffix(fn (Get $get): ?string => self::currencyOf($get))
                    ->required()
                    ->live(onBlur: true),
            ]),
        ];
    }

    /**
     * The prices of the page in money, at the price of a coin in the first pack: what a pack of coins gives over the first one, and what the VIP account, the packs of
     * refills and the pass come to. Empty while the first pack is not filled in.
     *
     * @param  array<string, mixed>  $state
     */
    public static function worthText(array $state): string
    {
        $worth = PremiumForm::worth($state);

        if ($worth['per_coin'] === null) {
            return __('admin.premium.worth_wait');
        }

        $money = fn (int|float $value): string => self::decimal($value, 2).' '.self::currency((string) ($state['price_currency'] ?? ''));
        $lines = [__('admin.premium.worth_coin', ['price' => self::decimal($worth['per_coin'], 4).' '.self::currency((string) ($state['price_currency'] ?? ''))])];

        $bonuses = [];

        foreach ($worth['products'] as $slug => $product) {
            $bonuses[] = "{$slug} +{$product['bonus']}%";
        }

        if ($bonuses !== []) {
            $lines[] = __('admin.premium.worth_bonus', ['packs' => implode(', ', $bonuses)]);
        }

        if (! empty($state['autorefill']['enabled']) && $worth['refills'] !== []) {
            $refills = [];

            foreach ($worth['refills'] as $slug => $pack) {
                $refills[] = "{$slug}: {$pack['coins']} ".__('admin.premium.coins_short').' ('.$money($pack['money']).', '.self::decimal($pack['coins_per_charge'], 2).' '.__('admin.premium.per_refill').')';
            }

            $lines[] = __('admin.premium.worth_refills', ['packs' => implode('; ', $refills)]);
        }

        if (! empty($state['vip']['enabled']) && $worth['vip'] !== null) {
            $lines[] = __('admin.premium.worth_vip', ['price' => $money($worth['vip'])]);
        }

        if (! empty($state['infinity']['enabled']) && $worth['pass_per_day'] !== null) {
            $lines[] = __('admin.premium.worth_pass', ['price' => $money($worth['pass_per_day'])]);
        }

        return implode(' ', $lines);
    }

    /** @return array<string, mixed> the part of the form that the prices are worked out from */
    private static function stateOf(Get $get): array
    {
        return [
            'price_currency' => $get('price_currency'),
            'products' => $get('products'),
            'autorefill' => $get('autorefill'),
            'vip' => $get('vip'),
            'infinity' => $get('infinity'),
        ];
    }

    /** The currency of the prices as the box of it holds it now, from inside a pack or at the top of the form. */
    private static function currencyOf(Get $get): ?string
    {
        $currency = $get('price_currency') ?? $get('../../price_currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : null;
    }

    private static function currency(string $code): string
    {
        return $code === '' ? '' : strtoupper($code);
    }

    /** @param  array<string, mixed>  $state */
    private static function itemLabel(array $state): ?string
    {
        $slug = (string) ($state['slug'] ?? '');
        $name = (string) ($state['name']['pl'] ?? $state['name']['en'] ?? '');

        return $name === '' ? ($slug === '' ? null : $slug) : "{$name} ({$slug})";
    }

    /** A number with its decimals, with the comma of the languages that write it so. */
    private static function decimal(int|float $value, int $decimals): string
    {
        $text = number_format($value, $decimals, '.', '');

        return app()->getLocale() === 'en' ? $text : str_replace('.', ',', $text);
    }
}
