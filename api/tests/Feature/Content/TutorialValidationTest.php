<?php

declare(strict_types=1);

use App\Content\PackValidationResult;
use App\Content\PackValidator;

/**
 * What the validator says about the steps of a tutorial that the schema cannot: facts that cannot hold where a step puts them, the words of the button, the
 * keys the server knows by name.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $change
 */
function validatedTutorial(Closure $change): PackValidationResult
{
    return PackValidator::fromConfig()->validateSource(contentWith(['rules/tutorial' => $change]));
}

/** The place of a step in the tutorial. */
function stepIndex(array $tutorial, string $key): int
{
    return array_search($key, array_column($tutorial['steps'], 'key'), true);
}

/** @return list<string> file and pointer of every issue, errors or warnings */
function issuesAt(array $issues): array
{
    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", $issues);
}

it('finds nothing to say about the tutorial of the game', function () {
    $result = PackValidator::fromConfig()->validateSource(repositoryContent());

    expect(array_filter(issuesAt($result->errors()), fn (string $issue): bool => str_contains($issue, 'tutorial')))->toBe([])
        ->and(array_filter(issuesAt($result->warnings()), fn (string $issue): bool => str_contains($issue, 'tutorial')))->toBe([]);
});

it('refuses a step that shows messages and has no words for the button that answers them', function () {
    $result = validatedTutorial(function (array $tutorial): array {
        unset($tutorial['steps'][stepIndex($tutorial, 'start')]['reply']);

        return $tutorial;
    });

    expect($result->isValid())->toBeFalse()
        ->and(implode(' ', array_column($result->errors(), 'file')))->toContain('rules/tutorial.json');
});

it('accepts a step with no messages and no button, which is carried out without being shown', function () {
    $result = validatedTutorial(function (array $tutorial): array {
        $index = stepIndex($tutorial, 'start');
        $tutorial['steps'][$index]['sms'] = [];
        unset($tutorial['steps'][$index]['reply']);

        return $tutorial;
    });

    expect(issuesAt($result->errors()))->toBe([]);
});

it('refuses a step that starts when the driver has answered its messages, which are shown after it starts', function () {
    $index = 0;
    $result = validatedTutorial(function (array $tutorial) use (&$index): array {
        $index = stepIndex($tutorial, 'first-lap');
        $tutorial['steps'][$index]['trigger'] = ['run_started', 'acknowledged'];

        return $tutorial;
    });

    expect(issuesAt($result->errors()))->toBe(["rules/tutorial.json /steps/{$index}/trigger"])
        ->and($result->errors()[0]['message'])->toContain('acknowledged');
});

it('refuses recovered as a trigger, and as a completion of a step that does not start on a breakdown', function () {
    $indices = [];
    $result = validatedTutorial(function (array $tutorial) use (&$indices): array {
        $indices = [stepIndex($tutorial, 'first-lap'), stepIndex($tutorial, 'refuel')];
        $tutorial['steps'][$indices[0]]['trigger'] = 'recovered';
        $tutorial['steps'][$indices[1]]['completion'] = 'recovered';

        return $tutorial;
    });

    expect(issuesAt($result->errors()))->toBe(["rules/tutorial.json /steps/{$indices[0]}/trigger", "rules/tutorial.json /steps/{$indices[1]}/completion"]);
});

it('lets the repair end on recovered, because it starts on a breakdown', function () {
    expect(validatedTutorial(fn (array $tutorial): array => $tutorial)->errors())->toBe([]);
});

it('warns that a step which starts on a bought car does not get the listing of the tutorial, and refuses a bought car with no listing to buy', function () {
    $index = 0;
    $warned = validatedTutorial(function (array $tutorial) use (&$index): array {
        $index = stepIndex($tutorial, 'first-lap');
        $tutorial['steps'][$index]['trigger'] = ['run_started', 'car_bought'];

        return $tutorial;
    });

    expect($warned->errors())->toBe([])
        ->and(issuesAt($warned->warnings()))->toContain("rules/tutorial.json /steps/{$index}/trigger");

    $refused = validatedTutorial(function (array $tutorial): array {
        unset($tutorial['script']['exchange_listing']);

        return $tutorial;
    });

    expect(issuesAt($refused->errors()))->toHaveCount(1)->and(issuesAt($refused->errors())[0])->toContain('/completion');
});

it('warns about each of the three keys the server knows by name when the tutorial has no such step', function (string $key) {
    $result = validatedTutorial(function (array $tutorial) use ($key): array {
        $tutorial['steps'][stepIndex($tutorial, $key)]['key'] = "{$key}-renamed";

        return $tutorial;
    });

    expect(array_values(array_filter($result->warnings(), fn (array $issue): bool => str_contains($issue['message'], "key {$key},"))))->toHaveCount(1);
})->with(['repair', 'mount', 'crate']);
