<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\PremiumForm;
use App\Filament\Forms\PremiumFields;
use App\Filament\Pages\PremiumPage;
use App\Models\Admin;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use Livewire\Livewire;

// The page of the paid features: it starts from the draft or from what was published, shows the switches as switches, the bonuses of the VIP as percents and the prices as
// money, keeps a draft only when the schema and the rules of the pack accept it, and leaves the rest of the rules as they were.

/** @return array<string, mixed> */
function premiumRules(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
}

function premiumDefinition(): ContentDefinition
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();
}

/** The state of the form as the page shows it, changed by a function. @return array<string, mixed> */
function premiumState(?Closure $change = null): array
{
    $state = PremiumForm::toState(premiumRules());

    if ($change !== null) {
        $state = $change($state) ?? $state;
    }

    return $state;
}

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

describe('the form of the paid features', function () {
    it('shows the numbers of the rules in the units that a person writes: percents, money, switches', function () {
        $state = premiumState();

        expect($state['vip'])->toBe(['enabled' => true, 'days' => 30, 'coins' => 150, 'cash_percent' => 20, 'xp_percent' => 20])
            ->and($state['infinity'])->toBe(['enabled' => true, 'days' => 30, 'price' => 19.99])
            ->and($state['autorefill']['enabled'])->toBeTrue()
            ->and($state['autorefill']['stock_cap'])->toBe(100)
            ->and(array_column($state['autorefill']['packs'], 'slug'))->toBe(['refill-1', 'refill-5', 'refill-20'])
            ->and(array_column($state['products'], 'price'))->toBe([4.99, 19.99, 49.99, 99.99])
            ->and($state['speedup_minutes'])->toBe(5)
            ->and($state['price_currency'])->toBe('PLN');
    });

    it('is the document again when nothing was changed, to the last key and in the same order', function () {
        $rules = premiumRules();

        expect(PremiumForm::apply($rules, PremiumForm::toState($rules)))->toBe($rules);
    });

    it('turns the percents and the money back into parts per million and the smallest unit', function () {
        $document = PremiumForm::apply(premiumRules(), premiumState(function (array $state): array {
            $state['vip']['cash_percent'] = 12.5;
            $state['vip']['xp_percent'] = 0;
            $state['infinity']['price'] = '24,50';
            $state['products'][0]['price'] = 5;

            return $state;
        }));

        expect($document['premium']['vip']['cash_bonus_ppm'])->toBe(125_000)
            ->and($document['premium']['vip']['xp_bonus_ppm'])->toBe(0)
            ->and($document['premium']['infinity']['price_minor'])->toBe(2450)
            ->and($document['premium']['products']['coins-small']['price_minor'])->toBe(500);
    });

    it('leaves a feature that the game does not sell out of the document while it stays off, and writes it with the starting numbers when it is switched on', function () {
        $rules = premiumRules();
        unset($rules['premium']['vip'], $rules['premium']['infinity'], $rules['premium']['autorefill']);

        $state = PremiumForm::toState($rules);

        expect($state['vip']['enabled'])->toBeFalse()
            ->and(PremiumForm::apply($rules, $state)['premium'])->not->toHaveKeys(['vip', 'infinity', 'autorefill']);

        $state['vip']['enabled'] = true;
        $document = PremiumForm::apply($rules, $state);

        expect($document['premium']['vip'])->toBe(['enabled' => true, 'days' => 30, 'coins' => 150, 'cash_bonus_ppm' => 200_000, 'xp_bonus_ppm' => 200_000])
            ->and($document['premium'])->not->toHaveKeys(['infinity', 'autorefill'])
            // The features are in the order of the file wherever they are written.
            ->and(array_keys($document['premium']))->toBe(['currency', 'price_currency', 'speedup', 'vip', 'products']);
    });

    it('keeps a game with no paid features without them when nothing is given to sell, and makes the section when something is', function () {
        $rules = premiumRules();
        unset($rules['premium']);
        $empty = PremiumForm::toState($rules);

        expect($empty['products'])->toBe([])
            ->and(PremiumForm::apply($rules, $empty))->toBe($rules);

        $empty['currency_code'] = 'doubloon';
        $empty['currency_name']['en'] = 'Doubloon';
        $empty['price_currency'] = 'eur';
        $empty['products'] = [['slug' => 'chest', 'name' => ['en' => 'Chest', 'pl' => 'Skrzynia'], 'coins' => 100, 'price' => 9.99]];

        expect(PremiumForm::apply($rules, $empty)['premium'])->toBe([
            'currency' => ['code' => 'doubloon', 'name' => ['en' => 'Doubloon']],
            'price_currency' => 'eur',
            'products' => ['chest' => ['name' => ['en' => 'Chest', 'pl' => 'Skrzynia'], 'coins' => 100, 'price_minor' => 999]],
        ]);
    });

    it('works out what the other prices come to in money at the price of a coin of the first pack, with the bonus of each pack', function () {
        $worth = PremiumForm::worth(premiumState());

        expect($worth['per_coin'])->toEqualWithDelta(0.0998, 0.00001)
            ->and(array_map(fn (array $product): int => $product['bonus'], $worth['products']))->toBe(['coins-small' => 0, 'coins-medium' => 10, 'coins-large' => 20, 'coins-huge' => 30])
            ->and($worth['vip'])->toEqualWithDelta(14.97, 0.001)
            ->and($worth['refills']['refill-5']['money'])->toEqualWithDelta(0.898, 0.001)
            ->and($worth['refills']['refill-20']['coins_per_charge'])->toBe(1.6)
            ->and($worth['pass_per_day'])->toEqualWithDelta(0.6663, 0.0001)
            // Nothing to measure by without a first pack that has its coins and its price.
            ->and(PremiumForm::worth(['products' => [['slug' => 'x', 'coins' => null, 'price' => null]]])['per_coin'])->toBeNull();

        expect(PremiumFields::worthText(premiumState()))->toContain('+10%')->toContain('+30%')->toContain('14,97 PLN')
            ->and(PremiumFields::worthText([]))->toBe(__('admin.premium.worth_wait'));
    });
});

describe('the page', function () {
    it('is for any admin to open, starts from what is published, and saving it as it is keeps no draft', function () {
        signedInAdmin(Admin::ROLE_EDITOR);

        $this->get('/admin/premium')->assertOk()->assertSee(__('admin.premium.title'));

        $page = Livewire::test(PremiumPage::class);

        // A box of a number that is not necessarily whole holds a float: 20 and 20.0 are the same bonus.
        expect($page->get('data.vip.cash_percent'))->toEqual(20)
            ->and(array_column(array_values($page->get('data.products')), 'slug'))->toBe(['coins-small', 'coins-medium', 'coins-large', 'coins-huge']);

        $page->call('save')->assertHasNoFormErrors();

        expect(premiumDefinition()->hasDraft())->toBeFalse();
    });

    it('says that no payment provider is connected, until one is', function () {
        signedInAdmin();
        config(['ride.premium.provider' => null]);

        Livewire::test(PremiumPage::class)->assertSee(__('admin.premium.no_provider'));

        config(['ride.premium.provider' => 'stripe']);

        Livewire::test(PremiumPage::class)->assertDontSee(__('admin.premium.no_provider'));
    });

    it('keeps a draft with the bonus and the price that were changed, and leaves the rest of the rules as they were', function () {
        $admin = signedInAdmin();
        $rules = premiumRules();

        Livewire::test(PremiumPage::class)
            ->fillForm(premiumState(function (array $state): array {
                $state['vip']['cash_percent'] = 25;
                $state['infinity']['price'] = 24.99;

                return $state;
            }))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.definitions.saved'));

        $draft = premiumDefinition()->draftDocument();
        $changed = $rules;
        $changed['premium']['vip']['cash_bonus_ppm'] = 250_000;
        $changed['premium']['infinity']['price_minor'] = 2499;

        expect($draft)->toEqual($changed)
            ->and(premiumDefinition()->publishedDocument())->toEqual($rules)
            ->and(premiumDefinition()->draft_admin_id)->toBe($admin->id);
    });

    it('switches a feature off and keeps its numbers for when it is switched on again', function () {
        signedInAdmin();
        $rules = premiumRules();

        Livewire::test(PremiumPage::class)
            ->fillForm(premiumState(function (array $state): array {
                $state['autorefill']['enabled'] = false;
                $state['infinity']['enabled'] = false;

                return $state;
            }))
            ->call('save')
            ->assertHasNoFormErrors();

        $premium = premiumDefinition()->draftDocument()['premium'];

        expect($premium['autorefill'])->toBe(['enabled' => false] + $rules['premium']['autorefill'])
            ->and($premium['infinity'])->toBe(['enabled' => false] + $rules['premium']['infinity']);
    });

    it('does not keep a pack of charges that a driver could never buy, and says so', function () {
        signedInAdmin();

        Livewire::test(PremiumPage::class)
            ->fillForm(premiumState(function (array $state): array {
                $state['autorefill']['packs'][2]['charges'] = 200;

                return $state;
            }))
            ->call('save')
            ->assertNotified(__('admin.definitions.invalid'));

        expect(premiumDefinition()->hasDraft())->toBeFalse();
    });

    it('does not keep a currency that is not written as a code or a price that is not a price', function () {
        signedInAdmin();

        Livewire::test(PremiumPage::class)
            ->fillForm(premiumState(function (array $state): array {
                $state['price_currency'] = 'zloty';
                $state['products'][1]['price'] = 0;

                return $state;
            }))
            ->call('save')
            ->assertHasFormErrors(['price_currency', 'products.1.price']);

        expect(premiumDefinition()->hasDraft())->toBeFalse();
    });
});

describe('the check of the pack', function () {
    it('refuses a pack of charges over the largest stock, and warns about a pack that has no bonus', function () {
        $rules = premiumRules();
        $rules['premium']['autorefill']['packs']['refill-20']['charges'] = 101;
        // Fewer coins for the price than the first pack (50 for 4.99): it only costs more.
        $rules['premium']['products']['coins-medium']['coins'] = 100;

        $result = app(ContentDrafts::class)->validate('rules', $rules, 'starter');

        expect(array_column($result->errors(), 'pointer'))->toBe(['/premium/autorefill/packs/refill-20/charges'])
            ->and(array_column($result->warnings(), 'pointer'))->toContain('/premium/products/coins-medium');

        expect(app(ContentDrafts::class)->validate('rules', premiumRules(), 'starter')->isValid())->toBeTrue();
    });
});
