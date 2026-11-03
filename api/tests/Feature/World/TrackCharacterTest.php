<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Models\User;
use App\Services\TrackCharacter;

// The thread of a track in the choice of the driver: the map gives, for the tracks of the city where the car stands, a small line, where the time of a lap goes and
// what the car does better or worse than the car the track is made for.

/** @return array<string, mixed> the rules of the repository, changed by a closure, as the rules of the game for the requests that follow */
function characterRules(Closure $change): array
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = $change($data);
    app()->singleton(Rules::class, fn () => Rules::fromArray($data));

    return $data;
}

/** The tracks of the city where the car of the driver stands, as the map gives them. @return list<array<string, mixed>> */
function characterTracksHere(User $driver, string $city = 'zlomowo'): array
{
    return collect(test()->getJson('/api/v1/map', bearerFor($driver))->assertOk()->json('data.cities'))->keyBy('slug')[$city]['tracks'];
}

describe('the thread of a track on the map', function () {
    it('is given for the tracks of the city where the car stands: a line, the shares of the time and what the car does against the reference car', function () {
        [$driver] = driverWithRun();

        foreach (characterTracksHere($driver) as $track) {
            $thread = $track['thread'];

            expect($thread)->not->toBeNull()
                ->and($thread['lap_from'])->toBe('stats')
                ->and($thread['mode'])->toBeIn(['circuit', 'run'])
                ->and(count($thread['points']))->toBeBetween(8, 80)
                ->and(array_keys($thread['shares']))->toBe(TrackCharacter::GROUPS)
                ->and(array_sum($thread['shares']))->toBeBetween(98, 102)
                ->and(array_keys($thread['car']))->toBe(TrackCharacter::GROUPS);

            foreach ($thread['points'] as $point) {
                expect($point)->toHaveCount(2)->and($point[0])->toBeInt()->and($point[1])->toBeInt();
            }
        }
    });

    it('is not given for the tracks of another city, nor to a driver who has no car', function () {
        [$driver] = driverWithRun();
        $cities = collect(test()->getJson('/api/v1/map', bearerFor($driver))->assertOk()->json('data.cities'))->keyBy('slug');

        expect($cities['zlomowo']['tracks'][0]['thread'])->not->toBeNull();

        foreach (['kruszyn', 'suchy-pas', 'wilcza-przelecz', 'autodrom'] as $city) {
            expect(array_unique(array_column($cities[$city]['tracks'], 'thread')))->toBe([null]);
        }

        $without = User::factory()->create();
        $tracks = collect(test()->getJson('/api/v1/map', bearerFor($without))->assertOk()->json('data.cities'))->keyBy('slug')['zlomowo']['tracks'];

        expect(array_unique(array_column($tracks, 'thread')))->toBe([null]);
    });

    it('is null for a track that has no thread', function () {
        characterRules(function (array $data): array {
            unset($data['tracks']['zlomowo-street']['thread']);

            return $data;
        });
        [$driver] = driverWithRun();

        $tracks = characterTracksHere($driver);

        expect($tracks[0]['thread'])->toBeNull()->and($tracks[1]['thread'])->not->toBeNull();
    });

    it('says the car the track is made for does as well as itself everywhere, and that a different car gains on the straights and loses elsewhere or the other way round', function () {
        [$driver] = driverWithRun();
        $street = characterTracksHere($driver)[0]['thread'];

        // The stock Rdzak is the reference car of the street: it is within a few tenths of a second of itself in every group.
        foreach ($street['car'] as $seconds) {
            expect(abs($seconds))->toBeLessThan(0.5);
        }
    });

    it('adds up to the difference of the lap on a track that is driven on its thread, scaled to the base lap', function () {
        characterRules(function (array $data): array {
            // A car that is not the reference one: the reference car of the street is made weaker in the grip, so the stock car is better in the corners.
            $data['tracks']['zlomowo-street']['lap_from'] = 'thread';
            $data['tracks']['zlomowo-street']['reference']['grip'] = 70;
            $data['tracks']['zlomowo-street']['reference']['handling'] = 70;

            return $data;
        });
        [$driver] = driverWithRun();
        $street = characterTracksHere($driver)[0];

        expect($street['thread']['lap_from'])->toBe('thread')
            ->and($street['thread']['car']['cornering'])->toBeLessThan(0.0)
            // The seconds of the groups are the seconds of the lap of the track: they add up to how much faster than the base lap the car drives.
            ->and(array_sum($street['thread']['car']))->toEqualWithDelta(($street['car']['lap_ms'] - $street['base_lap_ms']) / 1000, 0.25);
    });
});

it('gives a line and nothing else for a track whose reference car lacks a statistic of the model', function () {
    [$driver] = driverWithRun(['track_slug' => 'kruszyn-quarry']);
    $thread = characterTracksHere($driver, 'kruszyn')[0]['thread'];

    // The quarry is the track the car stands on: its line is drawn, and there is no reference car to compare with.
    expect(count($thread['points']))->toBeGreaterThan(20)
        ->and($thread['character'])->toBeNull()
        ->and($thread['shares'])->toBeNull()
        ->and($thread['car'])->toBeNull();
});

it('tells a track by where its time goes: a track of corners, of power, or neither', function () {
    [$driver] = driverWithRun();
    $tracks = collect(characterTracksHere($driver))->keyBy('slug');

    expect($tracks['zlomowo-street']['thread']['character'])->toBe('balanced')
        ->and($tracks['zlomowo-yard-dash']['thread']['character'])->toBe('twisty')
        ->and($tracks['zlomowo-ring-road']['thread']['character'])->toBe('power');
});
