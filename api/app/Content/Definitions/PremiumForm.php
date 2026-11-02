<?php

declare(strict_types=1);

namespace App\Content\Definitions;

/**
 * What the page of the paid features holds and what it does to the rules: the premium section (the currency and its packs, the speed-up, the auto-refill, the VIP account
 * and the pass) as the state of the form, with the switches as switches, the bonuses of the VIP as percents and the prices of the pass as money, and back into the
 * section in the order of the file. Everything else in the document is left as it is.
 *
 * A paid feature that the rules say nothing about is not sold; the form shows it switched off with the numbers that the game starts it with, and leaves it out of the
 * document while it stays off, so that saving a page does not write what nobody asked for.
 */
final class PremiumForm
{
    /** What a feature that the rules do not have yet starts from when it is switched on. */
    public const STARTING = [
        'autorefill' => ['enabled' => false, 'stock_cap' => 100, 'packs' => [['slug' => 'refill-1', 'charges' => 1, 'coins' => 2], ['slug' => 'refill-5', 'charges' => 5, 'coins' => 9], ['slug' => 'refill-20', 'charges' => 20, 'coins' => 32]]],
        'vip' => ['enabled' => false, 'days' => 30, 'coins' => 150, 'cash_percent' => 20, 'xp_percent' => 20],
        'infinity' => ['enabled' => false, 'days' => 30, 'price' => 19.99],
    ];

    /** A bonus is entered as a percent and kept as parts per million. */
    private const PERCENT = 10_000;

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function toState(array $rules): array
    {
        $premium = (array) ($rules['premium'] ?? []);
        $autorefill = $premium['autorefill'] ?? null;
        $vip = $premium['vip'] ?? null;
        $infinity = $premium['infinity'] ?? null;

        $products = [];

        foreach ((array) ($premium['products'] ?? []) as $slug => $product) {
            $products[] = [
                'slug' => $slug,
                'name' => DefinitionForm::texts((array) ($product['name'] ?? [])),
                'coins' => $product['coins'] ?? null,
                'price' => isset($product['price_minor']) ? $product['price_minor'] / 100 : null,
            ];
        }

        $packs = [];

        foreach ((array) ($autorefill['packs'] ?? []) as $slug => $pack) {
            $packs[] = ['slug' => $slug, 'charges' => $pack['charges'] ?? null, 'coins' => $pack['coins'] ?? null];
        }

        return [
            'currency_code' => $premium['currency']['code'] ?? null,
            'currency_name' => DefinitionForm::texts((array) ($premium['currency']['name'] ?? [])),
            'price_currency' => $premium['price_currency'] ?? null,
            'speedup_minutes' => $premium['speedup']['minutes_per_coin'] ?? null,
            'products' => $products,
            'autorefill' => $autorefill === null ? self::STARTING['autorefill'] : [
                'enabled' => (bool) ($autorefill['enabled'] ?? false),
                'stock_cap' => $autorefill['stock_cap'] ?? null,
                'packs' => $packs,
            ],
            'vip' => $vip === null ? self::STARTING['vip'] : [
                'enabled' => (bool) ($vip['enabled'] ?? false),
                'days' => $vip['days'] ?? null,
                'coins' => $vip['coins'] ?? null,
                'cash_percent' => self::percent($vip['cash_bonus_ppm'] ?? null),
                'xp_percent' => self::percent($vip['xp_bonus_ppm'] ?? null),
            ],
            'infinity' => $infinity === null ? self::STARTING['infinity'] : [
                'enabled' => (bool) ($infinity['enabled'] ?? false),
                'days' => $infinity['days'] ?? null,
                'price' => isset($infinity['price_minor']) ? $infinity['price_minor'] / 100 : null,
            ],
        ];
    }

    /**
     * The rules with the premium section of a form. A game that has none and is given nothing to sell keeps having none.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function apply(array $rules, array $state): array
    {
        $existing = $rules['premium'] ?? null;
        $base = self::toState($rules);

        // The boxes of a feature that is switched off are hidden, and a hidden box is not part of what the form gives back: they keep what they had.
        foreach (['autorefill', 'vip', 'infinity'] as $extra) {
            $state[$extra] = (array) ($state[$extra] ?? []) + $base[$extra];
        }

        $products = [];

        foreach ((array) ($state['products'] ?? []) as $line) {
            $slug = DefinitionForm::text($line['slug'] ?? null);

            if ($slug !== null) {
                $products[$slug] = ['name' => DefinitionForm::i18n((array) ($line['name'] ?? [])), 'coins' => DefinitionForm::number($line['coins'] ?? null), 'price_minor' => DefinitionForm::cents($line['price'] ?? null)];
            }
        }

        $code = DefinitionForm::text($state['currency_code'] ?? null);
        $name = DefinitionForm::i18n((array) ($state['currency_name'] ?? []));
        $priceCurrency = DefinitionForm::text($state['price_currency'] ?? null);

        // A game that sold nothing and is asked to sell nothing (no currency, no packs, nothing switched on) has no section.
        $sells = $code !== null || $name !== [] || $products !== [] || ! empty($state['autorefill']['enabled']) || ! empty($state['vip']['enabled']) || ! empty($state['infinity']['enabled']);

        if ($existing === null && ! $sells) {
            return $rules;
        }

        $section = ['currency' => ['code' => $code, 'name' => $name], 'price_currency' => $priceCurrency];

        $minutes = DefinitionForm::number($state['speedup_minutes'] ?? null);

        if ($minutes !== null) {
            $section['speedup'] = ['minutes_per_coin' => $minutes];
        }

        $autorefill = self::autorefill((array) ($state['autorefill'] ?? []), isset($existing['autorefill']));

        if ($autorefill !== null) {
            $section['autorefill'] = $autorefill;
        }

        $vip = self::vip((array) ($state['vip'] ?? []), isset($existing['vip']));

        if ($vip !== null) {
            $section['vip'] = $vip;
        }

        $infinity = self::infinity((array) ($state['infinity'] ?? []), isset($existing['infinity']));

        if ($infinity !== null) {
            $section['infinity'] = $infinity;
        }

        $section['products'] = $products;

        // What the form does not know about (a key that a later version of the rules adds) stays where it was.
        $rules['premium'] = $section + array_diff_key((array) $existing, $section);

        return $rules;
    }

    /**
     * What a pack of coins is worth next to the first pack, which is the price of a coin that the others are measured by, and what the other paid features come to at that
     * price: the numbers a person setting the prices wants in front of them. A number that is not there yet (a box left empty) leaves its line out.
     *
     * @param  array<string, mixed>  $state
     * @return array{per_coin: float|null, products: array<string, array{bonus: int, per_coin: float}>, refills: array<string, array{charges: int, coins: int, money: float, coins_per_charge: float}>, vip: float|null, pass_per_day: float|null}
     */
    public static function worth(array $state): array
    {
        $number = static fn (mixed $value): ?float => is_numeric($value) && (float) $value > 0 ? (float) $value : null;

        $products = array_values(array_filter((array) ($state['products'] ?? []), static fn (mixed $line): bool => is_array($line) && $number($line['coins'] ?? null) !== null && $number($line['price'] ?? null) !== null));
        $first = $products[0] ?? null;
        $perCoin = $first === null ? null : (float) $first['price'] / (float) $first['coins'];

        $listed = [];

        foreach ($products as $line) {
            $own = (float) $line['price'] / (float) $line['coins'];
            $slug = DefinitionForm::text($line['slug'] ?? null);

            if ($slug !== null) {
                $listed[$slug] = ['bonus' => max(0, (int) round(($perCoin / $own - 1) * 100)), 'per_coin' => $own];
            }
        }

        $refills = [];

        foreach ((array) ($state['autorefill']['packs'] ?? []) as $line) {
            $slug = DefinitionForm::text($line['slug'] ?? null);
            $charges = $number($line['charges'] ?? null);
            $coins = $number($line['coins'] ?? null);

            if ($slug !== null && $charges !== null && $coins !== null) {
                $refills[$slug] = ['charges' => (int) $charges, 'coins' => (int) $coins, 'money' => $perCoin === null ? 0.0 : $coins * $perCoin, 'coins_per_charge' => $coins / $charges];
            }
        }

        $vipCoins = $number($state['vip']['coins'] ?? null);
        $passPrice = $number($state['infinity']['price'] ?? null);
        $passDays = $number($state['infinity']['days'] ?? null);

        return [
            'per_coin' => $perCoin,
            'products' => $listed,
            'refills' => $refills,
            'vip' => $vipCoins === null || $perCoin === null ? null : $vipCoins * $perCoin,
            'pass_per_day' => $passPrice === null || $passDays === null ? null : $passPrice / $passDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private static function autorefill(array $state, bool $exists): ?array
    {
        if (! $exists && empty($state['enabled'])) {
            return null;
        }

        $packs = [];

        foreach ((array) ($state['packs'] ?? []) as $line) {
            $slug = DefinitionForm::text($line['slug'] ?? null);

            if ($slug !== null) {
                $packs[$slug] = ['charges' => DefinitionForm::number($line['charges'] ?? null), 'coins' => DefinitionForm::number($line['coins'] ?? null)];
            }
        }

        return ['enabled' => ! empty($state['enabled']), 'stock_cap' => DefinitionForm::number($state['stock_cap'] ?? null), 'packs' => $packs];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private static function vip(array $state, bool $exists): ?array
    {
        if (! $exists && empty($state['enabled'])) {
            return null;
        }

        return [
            'enabled' => ! empty($state['enabled']),
            'days' => DefinitionForm::number($state['days'] ?? null),
            'coins' => DefinitionForm::number($state['coins'] ?? null),
            'cash_bonus_ppm' => self::ppm($state['cash_percent'] ?? null),
            'xp_bonus_ppm' => self::ppm($state['xp_percent'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private static function infinity(array $state, bool $exists): ?array
    {
        if (! $exists && empty($state['enabled'])) {
            return null;
        }

        return ['enabled' => ! empty($state['enabled']), 'days' => DefinitionForm::number($state['days'] ?? null), 'price_minor' => DefinitionForm::cents($state['price'] ?? null)];
    }

    /** A bonus of the document as the percent of the form. */
    private static function percent(mixed $ppm): mixed
    {
        return is_numeric($ppm) ? $ppm / self::PERCENT : $ppm;
    }

    /** The percent of the form as the bonus of the document (a box that holds no number is left for the schema to say). */
    private static function ppm(mixed $percent): mixed
    {
        $number = DefinitionForm::number($percent);

        return is_int($number) || is_float($number) ? (int) round($number * self::PERCENT) : $number;
    }
}
