<?php

declare(strict_types=1);

use App\Content\Definitions\ThreadCannotClose;
use App\Content\Definitions\ThreadCloser;
use App\Content\Definitions\ThreadForm;
use App\Content\ThreadDocument;
use App\Domain\Run\ThreadPath;

// "Close the loop": the angle of the last corner and two straights are moved until the corners turn a full circle and the line comes back to the start.

/** @return array<string, mixed> the rules of the repository */
function closerRules(): array
{
    return json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** The lines of the form for the thread of a track of the repository. @return list<array<string, mixed>> */
function closerSections(string $track): array
{
    return ThreadForm::toState(closerRules(), $track)['sections'];
}

/** How far the line of the sections of the form is from meeting itself, as the page draws it. */
function closerGap(array $sections): float
{
    $rules = closerRules();
    $made = ThreadForm::apply($rules, ['track' => 'zlomowo-street', 'mode' => 'circuit', 'lap_from' => 'stats', 'return_s' => null, 'sections' => $sections]);

    return ThreadPath::outline(ThreadDocument::thread($made['tracks']['zlomowo-street']['thread'], 'asphalt'), close: false)['gap_m'];
}

/** What the corners turn the car in all, in degrees (left is positive). */
function closerTurn(array $sections): float
{
    return array_sum(array_map(static fn (array $section): float => ($section['type'] ?? '') === 'corner' ? (($section['turn'] ?? 'right') === 'left' ? 1 : -1) * (float) $section['angle_deg'] : 0.0, $sections));
}

describe('closing a loop', function () {
    it('brings a loop that was opened back to the start by moving the last corner and two straights only', function () {
        $sections = closerSections('zlomowo-street');
        $opened = $sections;
        $opened[0]['length_m'] += 120;
        $opened[3]['angle_deg'] += 17;

        expect(closerGap($opened))->toBeGreaterThan(50.0);

        $closed = ThreadCloser::close($opened);

        expect(closerGap($closed['sections']))->toBeLessThan(1.5)
            ->and($closed['gap_m'])->toEqualWithDelta(closerGap($closed['sections']), 0.01)
            ->and(abs(closerTurn($closed['sections'])))->toEqualWithDelta(360.0, 0.001);

        // Three numbers moved, nothing else: the last corner and two straights.
        $changed = [];

        foreach ($closed['sections'] as $index => $section) {
            if ($section != $opened[$index]) {
                $changed[] = $index + 1;
            }
        }

        expect($changed)->toHaveCount(3)
            ->and($changed)->toContain($closed['moved']['corner'])
            ->and($changed)->toContain(...$closed['moved']['straights']);
    });

    it('leaves a loop that is closed as it is, to a metre', function () {
        foreach (array_keys(closerRules()['tracks']) as $track) {
            $thread = closerRules()['tracks'][$track]['thread'] ?? null;

            if ($thread === null || $thread['mode'] !== 'circuit') {
                continue;
            }

            $sections = closerSections($track);

            // A line that was drawn without closing (the rally tracks are, by a kilometre or three) is moved by hundreds of metres to close: that is for its author to redraw.
            if (closerGap($sections) > 5.0) {
                continue;
            }

            $closed = ThreadCloser::close($sections);

            expect($closed['gap_m'])->toBeLessThan(1.5, $track)
                ->and(abs(closerTurn($closed['sections'])))->toEqualWithDelta(360.0, 0.05, $track);

            foreach ($closed['sections'] as $index => $section) {
                if (($section['type'] ?? '') === 'straight') {
                    expect(abs($section['length_m'] - $sections[$index]['length_m']))->toBeLessThan(8, "{$track} section {$index}");
                }
            }
        }
    });

    it('takes the pair of straights that changes the least', function () {
        $opened = closerSections('zlomowo-street');
        $opened[0]['length_m'] += 40;
        $closed = ThreadCloser::close($opened);
        $change = array_sum(array_map(static fn (array $was, array $now): float => abs(($now['length_m'] ?? 0) - ($was['length_m'] ?? 0)), $opened, $closed['sections']));

        // The cheapest way to take 40 m back is not to move a straight by more than a few times that.
        expect($change)->toBeLessThan(200.0);
    });

    it('closes an oval that is not closed because the sides differ', function () {
        $sections = [
            ['type' => 'straight', 'length_m' => 600, 'turn' => null],
            ['type' => 'corner', 'radius_m' => 150, 'angle_deg' => 180, 'turn' => 'right'],
            ['type' => 'straight', 'length_m' => 500, 'turn' => null],
            ['type' => 'corner', 'radius_m' => 150, 'angle_deg' => 170, 'turn' => 'right'],
        ];

        $closed = ThreadCloser::close($sections);

        // The two sides are the same line walked in opposite ways, so they meet half way: 600 and 500 become 550 and 550.
        expect($closed['sections'][3]['angle_deg'])->toBe(180)
            ->and($closed['sections'][0]['length_m'])->toBe(550)
            ->and($closed['sections'][2]['length_m'])->toBe(550)
            ->and($closed['gap_m'])->toBeLessThan(0.5);
    });
});

describe('what cannot be closed', function () {
    it('says a corner is needed, two straights are needed, and that no number may be missing', function () {
        $straight = fn (int $length): array => ['type' => 'straight', 'length_m' => $length];
        $corner = fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];
        $reason = function (array $sections): string {
            try {
                ThreadCloser::close($sections);
            } catch (ThreadCannotClose $why) {
                return $why->reason;
            }

            return 'closed';
        };

        expect($reason([$straight(100), $straight(200)]))->toBe('no_corner')
            ->and($reason([$straight(100), $corner(50, 90), $corner(50, 90)]))->toBe('few_straights')
            ->and($reason([$straight(100), ['type' => 'corner', 'radius_m' => 50, 'angle_deg' => null, 'turn' => 'right'], $straight(100)]))->toBe('incomplete')
            ->and($reason([$straight(100), ['type' => 'straight', 'length_m' => null], $corner(50, 90)]))->toBe('incomplete');
    });

    it('says what the last corner would have to turn when the corners cannot make a full circle', function () {
        $sections = [
            ['type' => 'straight', 'length_m' => 300],
            ['type' => 'corner', 'radius_m' => 50, 'angle_deg' => 90, 'turn' => 'right'],
            ['type' => 'corner', 'radius_m' => 50, 'angle_deg' => 90, 'turn' => 'right'],
            ['type' => 'corner', 'radius_m' => 50, 'angle_deg' => 90, 'turn' => 'right'],
            ['type' => 'straight', 'length_m' => 300],
            ['type' => 'corner', 'radius_m' => 50, 'angle_deg' => 20, 'turn' => 'left'],
        ];

        try {
            ThreadCloser::close($sections);
            $thrown = null;
        } catch (ThreadCannotClose $why) {
            $thrown = $why;
        }

        expect($thrown?->reason)->toBe('turn')->and($thrown?->replace['angle'])->toBe(630);
    });

    it('says the lengths cannot be found when the straights run the same line and the sides cannot meet', function () {
        $sections = [
            ['type' => 'straight', 'length_m' => 50],
            ['type' => 'corner', 'radius_m' => 300, 'angle_deg' => 180, 'turn' => 'right'],
            ['type' => 'straight', 'length_m' => 50],
            ['type' => 'corner', 'radius_m' => 100, 'angle_deg' => 180, 'turn' => 'right'],
        ];

        try {
            ThreadCloser::close($sections);
            $reason = null;
        } catch (ThreadCannotClose $why) {
            $reason = $why->reason;
        }

        expect($reason)->toBe('lengths');
    });
});
