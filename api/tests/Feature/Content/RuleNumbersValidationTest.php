<?php

declare(strict_types=1);

use App\Content\PackValidationResult;
use App\Content\PackValidator;

/**
 * What the validator says of the numbers of the rules that only make sense next to each other: each of these used to get through, and break a draw, a run or a
 * journey of a player.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $change
 */
function validatedRules(Closure $change, string $document = 'rules/starter'): PackValidationResult
{
    return PackValidator::fromConfig()->validateSource(contentWith([$document => $change]));
}

/** @return list<string> the places of the errors, file and pointer */
function errorPlaces(PackValidationResult $result): array
{
    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", $result->errors());
}

it('finds nothing wrong in the numbers of the game', function () {
    expect(errorPlaces(PackValidator::fromConfig()->validateSource(repositoryContent())))->toBe([]);
});

describe('the market', function () {
    it('refuses a range that runs from the highest to the lowest', function (string $pointer, array $path) {
        $result = validatedRules(function (array $rules) use ($path): array {
            $range = &$rules;

            foreach ($path as $key) {
                $range = &$range[$key];
            }

            $range = ['min' => 50, 'max' => 5];

            return $rules;
        });

        expect(errorPlaces($result))->toBe(["rules/starter.json {$pointer}"]);
    })->with([
        'age of the exchange' => ['/market/exchange/age_years', ['market', 'exchange', 'age_years']],
        'mileage of a storage class' => ['/market/storage/classes/junk/mileage_km', ['market', 'storage', 'classes', 'junk', 'mileage_km']],
    ]);

    it('refuses a condition of a storage class above a whole car, which would make a negative number of defects', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['market']['storage']['classes']['good']['condition_ppm']['max'] = 1_200_000;

            return $rules;
        });

        expect($result->isValid())->toBeFalse()
            ->and(implode(' ', array_column($result->errors(), 'pointer')))->toContain('/market/storage/classes/good/condition_ppm/max');
    });

    it('refuses bands of defects that do not follow the mileage, or give no chance to any number of defects', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['market']['exchange']['defects'][1]['up_to_km'] = 50_000;
            $rules['market']['exchange']['defects'][2]['weights'] = [0, 0, 0];

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /market/exchange/defects/1/up_to_km', 'rules/starter.json /market/exchange/defects/2/weights']);
    });

    it('refuses a word of the listing that is good from less than it is fair from', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['market']['exchange']['description']['good_from_ppm'] = 400_000;

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /market/exchange/description/good_from_ppm']);
    });

    it('refuses a unit with no odds at all, and one that the guarantee could not draw from', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['market']['storage']['units']['small']['odds'] = ['scrap' => 0, 'junk' => 0, 'good' => 0, 'gem' => 0];
            $rules['market']['storage']['units']['medium']['odds'] = ['scrap' => 500, 'junk' => 500, 'good' => 0, 'gem' => 0];

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /market/storage/units/small/odds', 'rules/starter.json /market/storage/units/medium/odds']);
    });

    it('says so when the draft of the rules is saved, and not only when the pack is published', function () {
        // Decoded to objects, so that the empty ones stay what they are.
        $rules = json_decode((string) repositoryContent()->raw('rules/starter'));
        $rules->market->exchange->description->good_from_ppm = 400_000;

        $result = PackValidator::fromConfig()->validateDefinition('rules', json_encode($rules), 'starter');

        expect(errorPlaces($result))->toBe(['rules/starter.json /market/exchange/description/good_from_ppm']);
    });
});

describe('the journey with the police', function () {
    it('refuses a stop that is to be before it can be', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['travel']['boost']['police_from_ppm'] = 900_000;
            $rules['travel']['boost']['police_to_ppm'] = 100_000;

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /travel/boost/police_to_ppm']);
    });

    it('refuses a bribe that costs more when it works than when it does not', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['travel']['boost']['bribe_ppm'] = 3_000_000;
            $rules['travel']['boost']['bribe_fail_fine_ppm'] = 2_000_000;

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /travel/boost/bribe_fail_fine_ppm']);
    });
});

describe('the bots', function () {
    it('refuses a reward that is not given for every place of the field', function () {
        $result = validatedRules(function (array $rules): array {
            array_pop($rules['bots']['reward_ppm_by_position']);

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /bots/reward_ppm_by_position']);
    });

    it('refuses a worse place that pays more, and fewer names than bots', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['bots']['reward_ppm_by_position'][3] = 1_400_000;
            $rules['bots']['names'] = ['Bruk Racing'];

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /bots/reward_ppm_by_position/3', 'rules/starter.json /bots/names']);
    });
});

describe('the levels of the driver', function () {
    it('refuses a catch-up level that is not above the end of the table', function () {
        $result = validatedRules(function (array $rules): array {
            // Nine places in the table reach level 10.
            $rules['player']['catch_up_level'] = 10;

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /player/catch_up_level']);
    });

    it('accepts the first level above the table', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['player']['catch_up_level'] = 11;

            return $rules;
        });

        expect(errorPlaces($result))->toBe([]);
    });

    it('refuses a table that does not rise', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['player']['early_xp'][4] = 100;

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /player/early_xp/4']);
    });

    it('says the same of the copy of the economy', function () {
        $result = validatedRules(function (array $economy): array {
            $economy['model']['xp']['catch_up_level'] = 3;

            return $economy;
        }, 'rules/economy');

        expect(errorPlaces($result))->toBe(['rules/economy.json /model/xp/catch_up_level']);
    });
});

describe('the workshop', function () {
    it('refuses bays and mechanics that do not get dearer one after the other', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['workshop']['bays']['price_cents'] = [0, 600_000, 200_000, 1_500_000];

            return $rules;
        });

        expect(errorPlaces($result))->toBe(['rules/starter.json /workshop/bays/price_cents/2']);
    });

    it('warns that the price of the first bay, of the first mechanic and of the first level of a piece of equipment is never charged', function () {
        $result = validatedRules(function (array $rules): array {
            $rules['workshop']['bays']['price_cents'][0] = 50_000;
            $rules['workshop']['equipment']['lift']['levels'][0]['price_cents'] = 10_000;

            return $rules;
        });

        expect($result->errors())->toBe([])
            ->and(array_map(fn (array $issue): string => $issue['pointer'], array_filter($result->warnings(), fn (array $issue): bool => str_contains($issue['message'], 'never charged'))))
            ->toBe(['/workshop/bays/price_cents/0', '/workshop/equipment/lift/levels/0/price_cents']);
    });
});

describe('the parts', function () {
    it('warns that the game does not keep a part off the cars it does not fit', function () {
        $result = validatedRules(function (array $part): array {
            $part['fits'] = ['classes' => ['super']];

            return $part;
        }, 'parts/street-tires-t2');

        expect($result->errors())->toBe([])
            ->and(array_column($result->warnings(), 'pointer'))->toContain('/fits');
    });

    it('warns when the power of an engine is not the one the rules of the engines give, and the game uses the number of the part', function () {
        $result = validatedRules(function (array $part): array {
            $part['stats']['power_kw'] += 20;

            return $part;
        }, 'parts/engine-r6-3-0-t6');

        expect($result->errors())->toBe([])
            ->and(array_values(array_filter($result->warnings(), fn (array $issue): bool => $issue['pointer'] === '/stats/power_kw')))->toHaveCount(1);
    });

    it('has nothing to say of the parts of the game', function () {
        $result = PackValidator::fromConfig()->validateSource(repositoryContent());

        expect(array_filter($result->warnings(), fn (array $issue): bool => str_starts_with($issue['file'], 'parts/') || str_contains($issue['message'], 'never charged')))->toBe([]);
    });
});
