<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Parameter;
use App\Content\Vocabulary\MessageCatalog;
use App\Filament\Support\FieldHelp;
use Illuminate\Support\Str;

/**
 * The tutorial as the instruction for an AI gives it: the steps in order with their keys (a step is named by its key), the numbers of the first run, of the rewards and
 * of the story in the units of the studio, the choices with the values each may take, and every text, the words of the app among them (marked, so the AI knows the game
 * has not said them in its own way). Every place is written with the key of its step.
 */
final class TutorialBrief
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR;

    private const EXPLAIN_LIMIT = 360;

    /** The catalog of help that says what a kind of number of the tutorial is for, by the pattern of its place. */
    private const HELP = [
        '/script/first_run_fuel_ml' => 'tutorial.first_run_fuel_ml',
        '/script/breakdown_lap' => 'tutorial.breakdown_lap',
        '/script/first_mount_ms' => 'tutorial.first_mount_ms',
        '/script/covers_up_to_cents' => 'tutorial.covers_up_to',
        '/script/exchange_listing/age_years' => 'tutorial.listing_age_years',
        '/script/exchange_listing/mileage_km' => 'tutorial.listing_mileage_km',
        '/script/exchange_listing/condition_ppm' => 'tutorial.listing_condition',
        '/script/exchange_listing/price_cents' => 'tutorial.listing_price',
        '/steps/*/phase' => 'tutorial.phase',
        '/steps/*/gift/count' => 'tutorial.gift_count',
        '/steps/*/reward/cash_cents' => 'tutorial.reward_cash',
        '/steps/*/reward/car_xp' => 'tutorial.reward_car_xp',
        '/steps/*/reward/xp' => 'tutorial.reward_xp',
        '/messenger/picture/screen/top' => 'tutorial.screen_top',
        '/messenger/picture/screen/right' => 'tutorial.screen_right',
        '/messenger/picture/screen/bottom' => 'tutorial.screen_bottom',
        '/messenger/picture/screen/left' => 'tutorial.screen_left',
        '/story/scenes/*/ms' => 'tutorial.scene_seconds',
        '/story/scenes/*/focus/x' => 'tutorial.scene_focus_x',
        '/story/scenes/*/focus/y' => 'tutorial.scene_focus_y',
    ];

    /** The texts of a step, in the order the panel shows them. */
    private const STEP_TEXTS = ['goal', 'waiting', 'sms', 'reply', 'decline', 'sender'];

    public function __construct(private readonly ?MessageCatalog $catalog = null) {}

    /**
     * The lines of the data for the tutorial.
     *
     * @param  array<string, mixed>  $document  the tutorial as it is being worked on
     * @param  bool  $allLanguages  every language of every text; otherwise the language of the descriptions and English
     * @return list<string>
     */
    public function lines(array $document, string $locale, bool $allLanguages): array
    {
        $languages = $allLanguages ? TextIndex::LOCALES : array_values(array_unique([$locale, 'en']));

        return [
            ...$this->steps($document),
            ...$this->numbers($document, $locale),
            ...$this->choices($document),
            ...$this->texts($document, $languages),
        ];
    }

    /** @return list<string> */
    private function steps(array $document): array
    {
        $schema = self::schema();
        $condition = $schema['$defs']['condition'] ?? [];

        $lines = ['STEPS '.$this->json([
            'document' => BriefSelection::TUTORIAL,
            'columns' => ['key', 'phase', 'trigger', 'completion', 'target', 'optional'],
            'conditions' => array_values($condition['oneOf'][0]['enum'] ?? []),
            'condition_pattern' => (string) ($condition['oneOf'][1]['pattern'] ?? ''),
            'conditions_explained' => (string) ($condition['description'] ?? ''),
            'targets' => array_values($schema['properties']['steps']['items']['properties']['target']['enum'] ?? []),
        ])];

        foreach ($document['steps'] ?? [] as $step) {
            $lines[] = $this->json([$step['key'] ?? null, $step['phase'] ?? null, $step['trigger'] ?? null, $step['completion'] ?? null, $step['target'] ?? null, (bool) ($step['optional'] ?? false)]);
        }

        return $lines;
    }

    /** @return list<string> */
    private function numbers(array $document, string $locale): array
    {
        $parameters = (new ParameterIndex(TutorialBridge::KEY, $document))->all();

        if ($parameters === []) {
            return [];
        }

        $lines = ['RULES '.$this->json(['document' => BriefSelection::TUTORIAL])];
        $explain = [];

        foreach ($parameters as $parameter) {
            $lines[] = $this->json([
                DocumentPointer::join(TutorialBridge::toKeys($document, $parameter->path)),
                BridgeUnits::token($parameter->unit),
                $parameter->display(),
                $parameter->minDisplay(),
                $parameter->maxDisplay(),
            ]);

            $explain[$parameter->pattern] ??= $this->explanation($parameter, $locale);
        }

        $lines[] = 'EXPLAIN '.$this->json($explain);

        return $lines;
    }

    /** @return list<string> */
    private function choices(array $document): array
    {
        $lines = ['CHOICES '.$this->json(['document' => BriefSelection::TUTORIAL])];

        foreach (TutorialBridge::choices($document) as $line) {
            $lines[] = $this->json($line);
        }

        return $lines;
    }

    /**
     * Every text of the tutorial with its place: those of each step, of the messenger and of the story, and every word of the app that the game may say in its own way,
     * the ones it has not said marked "app".
     *
     * @param  list<string>  $languages
     * @return list<string>
     */
    private function texts(array $document, array $languages): array
    {
        $lines = ['TEXTS '.$this->json(['document' => BriefSelection::TUTORIAL])];
        $add = function (string $pointer, mixed $text, bool $ofTheApp = false) use (&$lines, $languages): void {
            if (is_string($text)) {
                $lines[] = $this->json([$pointer, $text]);
            } elseif (TextIndex::isText($text)) {
                $kept = array_intersect_key($text, array_flip($languages));
                $lines[] = $this->json($ofTheApp ? [$pointer, $kept === [] ? $text : $kept, 'app'] : [$pointer, $kept === [] ? $text : $kept]);
            }
        };

        foreach ($document['steps'] ?? [] as $step) {
            $key = (string) ($step['key'] ?? '');

            foreach (self::STEP_TEXTS as $field) {
                if ($field === 'sms') {
                    foreach (array_values((array) ($step['sms'] ?? [])) as $position => $message) {
                        $add("/steps/{$key}/sms/{$position}", $message);
                    }

                    continue;
                }

                $add("/steps/{$key}/{$field}", $step[$field] ?? null);
            }
        }

        $add('/messenger/sender', $document['messenger']['sender'] ?? null);

        foreach (array_values((array) ($document['story']['scenes'] ?? [])) as $index => $scene) {
            $add("/story/scenes/{$index}/text", $scene['text'] ?? null);
        }

        $add('/story/sms', $document['story']['sms'] ?? null);
        $add('/story/sender', $document['story']['sender'] ?? null);

        $catalog = $this->catalog ?? MessageCatalog::fromConfig();

        foreach (['messenger' => 'tutorial', 'story' => 'story'] as $section => $prefix) {
            $own = (array) ($document[$section]['labels'] ?? []);

            foreach (array_keys($catalog->all()) as $message) {
                if (preg_match('/^'.$prefix.'\.([a-z][A-Za-z0-9]*)$/', $message, $name) !== 1) {
                    continue;
                }

                if (isset($own[$name[1]])) {
                    $add("/{$section}/labels/{$name[1]}", $own[$name[1]]);

                    continue;
                }

                $app = [];

                foreach ($languages as $language) {
                    $app[$language] = (string) ($catalog->text($message, $language) ?? $catalog->all()[$message]);
                }

                $add("/{$section}/labels/{$name[1]}", $app, ofTheApp: true);
            }
        }

        return $lines;
    }

    /** What a kind of number is for: the entry of the catalog of help in the language, else what the schema says of it. */
    private function explanation(Parameter $parameter, string $locale): string
    {
        $help = self::HELP[$parameter->pattern] ?? null;
        $entry = $help === null ? null : FieldHelp::entry($help, $locale);
        $text = $entry !== null ? trim((string) ($entry['label'] ?? '').': '.(string) ($entry['help'] ?? ''), ': ') : ($parameter->description ?? $parameter->groupDescription ?? $parameter->group);

        return Str::limit((string) $text, self::EXPLAIN_LIMIT);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return json_decode((string) file_get_contents(rtrim((string) config('ride.content_path'), '/\\').'/schema/tutorial.schema.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, self::JSON);
    }
}
