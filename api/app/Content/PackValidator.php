<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Branding\Branding;
use App\Content\Branding\CssPolicy;
use App\Content\Vocabulary\MessageCatalog;
use App\Domain\Run\TrackModel;
use App\Services\ContentImages;
use App\Support\Locale;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates a content pack directory against the schemas in content/schema.
 *
 * Layout of a pack:
 *   pack.json          manifest
 *   vehicles/<slug>.json
 *   parts/<slug>.json
 *
 * Beyond the schema it checks what a schema cannot: a file is named after the
 * slug inside it, and derived values agree with the rules of the game.
 */
final class PackValidator
{
    /** How far a number that was said by hand may be from the one the model makes before it is worth a warning. */
    private const HAND_NUMBER_TOLERANCE = 0.02;

    /** How much of the experience of a lap the rounding to a whole number may take before it is worth a warning. */
    private const ROUNDED_EXPERIENCE_TOLERANCE = 0.1;

    /** How far a number may be from one with fewer decimals before it is said to have more (the noise of the arithmetic of whoever made it is not a decimal). */
    private const DECIMALS_TOLERANCE = 1e-9;

    private const SCHEMA_BASE = 'https://rideidle.local/schema/';

    /** Folder name => schema file. */
    private const KINDS = [
        'vehicles' => 'vehicle.schema.json',
        'parts' => 'part.schema.json',
    ];

    /** The documents of the rules that a person edits, by name => schema file. */
    public const RULES = [
        'starter' => 'rules.schema.json',
        'tutorial' => 'tutorial.schema.json',
        'vocabulary' => 'vocabulary.schema.json',
        'model' => 'model.schema.json',
        'branding' => 'branding.schema.json',
        'missions' => 'missions.schema.json',
        'gigs' => 'gigs.schema.json',
    ];

    /**
     * The keys of steps of the tutorial that the server knows by name (TutorialScript), and what it arranges for each.
     *
     * @var array<string, string>
     */
    private const TUTORIAL_KEYS_OF_THE_SERVER = [
        'repair' => 'the breakdown of the first run, until the step is done',
        'mount' => 'the short first job of mounting, and with it the free first crate',
        'crate' => 'the free first crate, until the step is done',
    ];

    private readonly Validator $validator;

    public function __construct(string $schemaPath)
    {
        $this->validator = new Validator;
        $this->validator->resolver()?->registerPrefix(self::SCHEMA_BASE, rtrim($schemaPath, '/\\'));
    }

    public static function fromConfig(): self
    {
        return new self((string) config('ride.content_path').'/schema');
    }

    public function validate(string $packPath): PackValidationResult
    {
        $result = new PackValidationResult;

        if (! is_dir($packPath)) {
            $result->addError($packPath, '', 'The pack directory does not exist.');

            return $result;
        }

        $this->validateManifest($packPath, $result);

        foreach (scandir($packPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_dir("{$packPath}/{$entry}")) {
                continue;
            }

            if (! isset(self::KINDS[$entry])) {
                $result->addError($entry, '', 'Unknown content folder. Known folders: '.implode(', ', array_keys(self::KINDS)).'.');
            }
        }

        foreach (self::KINDS as $folder => $schemaFile) {
            $this->validateFolder($packPath, $folder, $schemaFile, $result);
        }

        return $result;
    }

    /**
     * Validates all the content of a source, not only the cars and the parts: the manifest, every car and part, the rules, the steps of the tutorial and the
     * anchors of the economy against their schemas, and what the schemas cannot say: the names of the documents are their slugs, and the things that the
     * rules point to (the stock tires, the parts of the shop, the cars with a wreck price, the regions of the tracks and the roads) are there. The tables that
     * commands make only have to be JSON.
     */
    public function validateSource(ContentSource $source): PackValidationResult
    {
        $result = new PackValidationResult;

        $this->validateDocument($source, 'pack', 'pack.json', 'pack.schema.json', $result);

        /** @var array<string, string> $pictures the picture that a car or a part names, by the file it is in */
        $pictures = [];

        foreach (self::KINDS as $folder => $schemaFile) {
            foreach ($source->slugs($folder) as $slug) {
                $relative = "{$folder}/{$slug}.json";
                $data = $this->validateDocument($source, "{$folder}/{$slug}", $relative, $schemaFile, $result);

                if ($data === null) {
                    continue;
                }

                $result->count($folder);

                if (($data['slug'] ?? null) !== $slug) {
                    $result->addError($relative, '/slug', "The document must be named after its slug: {$slug}.");
                }

                $this->warnAboutMissingTranslations($relative, $data, $result);

                if (is_string($data['image'] ?? null)) {
                    $pictures[$relative] = $data['image'];
                }

                if ($folder === 'vehicles') {
                    $this->checkVehicleRules($relative, $data, $result);
                }
            }
        }

        $this->warnAboutMissingPictures($pictures, $result);

        foreach (['starter' => 'rules.schema.json', 'tutorial' => 'tutorial.schema.json', 'economy' => 'economy.schema.json', 'vocabulary' => 'vocabulary.schema.json', 'model' => 'model.schema.json', 'branding' => 'branding.schema.json', 'missions' => 'missions.schema.json', 'gigs' => 'gigs.schema.json'] as $name => $schemaFile) {
            if (! $source->has("rules/{$name}")) {
                // A pack with no vocabulary is a pack that does not change the words of the app, one with no model is a game of the cars of the first pack, and one
                // with no branding is the app as it looks.
                match ($name) {
                    'economy' => $result->addWarning("rules/{$name}.json", '', 'The anchors of the economy are missing: the simulators cannot be run on this content.'),
                    'vocabulary', 'model', 'branding', 'missions', 'gigs' => null,
                    default => $result->addError("rules/{$name}.json", '', 'The document is missing.'),
                };

                continue;
            }

            $data = $this->validateDocument($source, "rules/{$name}", "rules/{$name}.json", $schemaFile, $result);

            if ($data !== null) {
                $this->warnAboutMissingRulesTranslations("rules/{$name}.json", $name, $data, $result);
                $this->checkVocabulary("rules/{$name}.json", $name, $data, $result, GameModel::fromSource($source));
                $this->checkModelDocument("rules/{$name}.json", $name, $data, $result);
                $this->checkBrandingDocument("rules/{$name}.json", $name, $data, $result);
            }
        }

        foreach ($source->slugs('tables') as $name) {
            try {
                $source->document("tables/{$name}");
            } catch (ContentException $problem) {
                $result->addError("tables/{$name}.json", '', $problem->getMessage());
            }
        }

        if ($result->isValid()) {
            $this->checkReferences($source, $result);
        }

        return $result;
    }

    /**
     * Validates one car or part on its own, as the panel does with a draft: its schema, that its slug is the one it is meant to have, the dealer level of a car,
     * and a warning for every language that is missing. What the rest of the content has to say about it (a shop that sells it, a tire that the rules call
     * the stock ones) is for a pack to check.
     *
     * @param  string  $folder  vehicles or parts
     * @param  string|null  $slug  what the slug of the document has to be, when it is already known
     * @param  GameModel|null  $model  the model of the content that the draft is made in: the slots a part may be in, and the words that may name a slot
     */
    public function validateDefinition(string $folder, string $json, ?string $slug = null, ?GameModel $model = null): PackValidationResult
    {
        $result = new PackValidationResult;
        $relative = rtrim($folder, 's').($slug === null ? '' : " {$slug}");
        $schema = $folder === 'rules' ? (self::RULES[$slug ?? ''] ?? null) : (self::KINDS[$folder] ?? null);
        $data = $this->validateJson($json, $relative, $schema ?? throw new InvalidArgumentException("There are no definitions in {$folder}."), $result);

        if ($data === null) {
            return $result;
        }

        $result->count($folder);

        if ($folder === 'rules') {
            if ($slug === 'starter') {
                $this->checkRuleNumbers($data, $result);
            }

            $this->warnAboutMissingRulesTranslations($relative, (string) $slug, $data, $result);
            $this->checkVocabulary($relative, (string) $slug, $data, $result, $model);
            $this->checkModelDocument($relative, (string) $slug, $data, $result);
            $this->checkBrandingDocument($relative, (string) $slug, $data, $result);

            return $result;
        }

        if ($slug !== null && ($data['slug'] ?? null) !== $slug) {
            $result->addError($relative, '/slug', "The slug of a definition is never changed: it is {$slug}.");
        }

        $this->warnAboutMissingTranslations($relative, $data, $result);

        if ($folder === 'vehicles') {
            $this->checkVehicleRules($relative, $data, $result);
        }

        if ($folder === 'parts' && $model !== null) {
            $this->checkPartAgainstModel($relative, $data, $model, $result);
        }

        return $result;
    }

    /**
     * A picture that a car or a part names and the store of images does not have: the pack is whole without it (the game shows the car as it did before), but the
     * person who made it live should know. The bytes of a picture travel in a bundle, not in the files of the content.
     *
     * @param  array<string, string>  $pictures  the name of the picture by the file that names it
     */
    private function warnAboutMissingPictures(array $pictures, PackValidationResult $result): void
    {
        $missing = array_flip(app(ContentImages::class)->missing(array_values($pictures)));

        foreach ($pictures as $relative => $name) {
            if (isset($missing[$name])) {
                $result->addWarning($relative, '/image', "The picture {$name} is not in the store of images of this environment: upload it in the panel, or import the bundle that carries it.");
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function validateDocument(ContentSource $source, string $key, string $relative, string $schemaFile, PackValidationResult $result): ?array
    {
        $json = $source->raw($key);

        if ($json === null) {
            $result->addError($relative, '', 'The document is missing.');

            return null;
        }

        return $this->validateJson($json, $relative, $schemaFile, $result);
    }

    /**
     * What the rules and the tutorial point to, and what the world they describe needs to work, checked on content that is whole enough to be asked: a part
     * that the shop sells is there, every city has a track and is reached by a road, and so on. A source that has no rules gives nothing to check.
     */
    public function validateReferences(ContentSource $source): PackValidationResult
    {
        $result = new PackValidationResult;

        if ($source->has('rules/starter')) {
            $this->checkReferences($source, $result);
        }

        return $result;
    }

    /** What the rules point to has to exist in the pack, and the world has to work. */
    private function checkReferences(ContentSource $source, PackValidationResult $result): void
    {
        $rules = $source->document('rules/starter');
        $parts = $source->slugs('parts');
        $vehicles = $source->slugs('vehicles');
        $regions = array_keys($rules['regions'] ?? []);

        if (! in_array($rules['stock_tires'], $parts, true)) {
            $result->addError('rules/starter.json', '/stock_tires', "There is no part {$rules['stock_tires']}.");
        }

        foreach ($rules['shop']['parts'] ?? [] as $index => $slug) {
            if (! in_array($slug, $parts, true)) {
                $result->addError('rules/starter.json', "/shop/parts/{$index}", "The shop sells {$slug}, which is not a part of the pack.");
            }
        }

        foreach (['wreck_price_cents', 'wreck_mileage_km'] as $field) {
            foreach (array_keys($rules['garage'][$field] ?? []) as $slug) {
                if (! in_array($slug, $vehicles, true)) {
                    $result->addError('rules/starter.json', "/garage/{$field}/{$slug}", "There is no car {$slug} in the pack.");
                }
            }
        }

        foreach ($rules['tracks'] ?? [] as $slug => $track) {
            if (! in_array($track['region'] ?? null, $regions, true)) {
                $result->addError('rules/starter.json', "/tracks/{$slug}/region", "The region {$track['region']} does not exist.");
            }
        }

        foreach ($rules['roads'] ?? [] as $index => $road) {
            foreach (['from', 'to'] as $end) {
                if (! in_array($road[$end] ?? null, $regions, true)) {
                    $result->addError('rules/starter.json', "/roads/{$index}/{$end}", "The road leads to a region that does not exist: {$road[$end]}.");
                }
            }
        }

        $this->checkModel($source, $rules, $result);
        $this->checkWorld($rules, $result);
        $this->checkRuleNumbers($rules, $result);
        $this->checkEconomyCurve($source, $result);
        $this->checkParts($source, $result);
        $this->checkTutorial($source, $parts, $vehicles, $regions, $result);
        $this->checkMissions($source, $rules, $result);
        $this->checkGigs($source, $vehicles, $result);
        $this->checkPremium($rules, $result);
        $this->checkCompetition($source, $rules, $vehicles, $result);
        $this->checkStreaks($rules, $result);
        $this->checkWithdrawn($source, $parts, $vehicles, $result);
        $this->checkAffixes($rules, $result);
        $this->checkTuning($rules, $result);
    }

    /**
     * What the schema cannot say about the affixes: a rarity gives at least as many as it asks, and the amount of an affix is a range, not the other way round.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkAffixes(array $rules, PackValidationResult $result): void
    {
        foreach ($rules['affixes']['per_rarity'] ?? [] as $rarity => $count) {
            if (($count['max'] ?? 0) < ($count['min'] ?? 0)) {
                $result->addError('rules/starter.json', "/affixes/per_rarity/{$rarity}", 'A part gets at most fewer affixes than it gets at least.');
            }
        }

        foreach ($rules['affixes']['definitions'] ?? [] as $key => $affix) {
            if (($affix['max_ppm'] ?? 0) < ($affix['min_ppm'] ?? 0)) {
                $result->addError('rules/starter.json', "/affixes/definitions/{$key}/max_ppm", 'The most an affix gives is less than the least.');
            }
        }
    }

    /**
     * What the schema cannot say about the tuning: the amounts of a bonus keep their order (the least, the mean, the most), a bonus never gives more at its best than the
     * rules allow for one with its kind of context (none, or a kind of track, a surface, a weather).
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkTuning(array $rules, PackValidationResult $result): void
    {
        $tuning = $rules['tuning'] ?? null;

        if (! is_array($tuning)) {
            return;
        }

        foreach ($tuning['definitions'] ?? [] as $key => $bonus) {
            $where = "/tuning/definitions/{$key}";

            if (($bonus['mean_ppm'] ?? 0) < ($bonus['min_ppm'] ?? 0) || ($bonus['mean_ppm'] ?? 0) > ($bonus['max_ppm'] ?? 0)) {
                $result->addError('rules/starter.json', "{$where}/mean_ppm", 'The mean of a bonus lies between the least it gives and the most it gives.');
            }

            // A bonus works as an affix of the same effect, so it is found by its key: no key is the key of an affix too.
            if (isset($rules['affixes']['definitions'][$key])) {
                $result->addError('rules/starter.json', $where, 'The key of a bonus is the key of an affix too.');
            }

            $limit = (int) ($tuning[empty($bonus['context']) ? 'max_ppm_plain' : 'max_ppm_context'] ?? 1_000_000);

            if (($bonus['max_ppm'] ?? 0) > $limit) {
                $result->addError('rules/starter.json', "{$where}/max_ppm", 'The most a bonus gives is more than the rules allow for a bonus '.(empty($bonus['context']) ? 'with no context' : 'with a context').' ('.$limit.' ppm).');
            }
        }
    }

    /**
     * What a pack withdraws is in the pack, and a new driver still has a car to start with.
     *
     * @param  list<string>  $parts
     * @param  list<string>  $vehicles
     */
    private function checkWithdrawn(ContentSource $source, array $parts, array $vehicles, PackValidationResult $result): void
    {
        $withdrawn = $source->withdrawn();

        foreach ($withdrawn as $index => $slug) {
            if (! in_array($slug, [...$parts, ...$vehicles], true)) {
                $result->addError('pack.json', "/withdrawn/{$index}", "The pack withdraws {$slug}, which is neither a car nor a part of it.");
            }
        }

        if ($withdrawn === []) {
            return;
        }

        $starting = array_filter($source->documents('vehicles'), static fn (array $vehicle, string $slug): bool => in_array('start', $vehicle['sources'] ?? [], true) && ! in_array($slug, $withdrawn, true), ARRAY_FILTER_USE_BOTH);

        if ($starting === []) {
            $result->addError('pack.json', '/withdrawn', 'Every car that a new driver can start with is withdrawn.');
        }
    }

    /**
     * What the world of the rules needs in order to be played: the demands of a track add up to 100 and have a reference each, the chances of the weather of a
     * city add up to 1 000 000 and name weather that the rules know, every city has a track and a road to it, no road leads from a city to itself, and the first
     * track, where a new driver starts, is open from the first level.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkWorld(array $rules, PackValidationResult $result): void
    {
        $file = 'rules/starter.json';

        foreach ($rules['tracks'] ?? [] as $slug => $track) {
            $demand = $track['demand'] ?? [];

            if (array_sum($demand) !== 100) {
                $result->addError($file, "/tracks/{$slug}/demand", 'The demands add up to '.array_sum($demand).', not 100.');
            }

            foreach (array_diff(array_keys($demand), array_keys($track['reference'] ?? [])) as $stat) {
                $result->addError($file, "/tracks/{$slug}/reference", "The track asks for {$stat} and has no reference for it.");
            }
        }

        $this->checkTrackNumbers($rules, $result);
        $this->checkThreads($rules, $result);

        foreach ($rules['regions'] ?? [] as $slug => $region) {
            $chances = $region['weather_ppm'] ?? [];

            if (array_sum($chances) !== 1_000_000) {
                $result->addError($file, "/regions/{$slug}/weather_ppm", 'The chances add up to '.array_sum($chances).', not 1 000 000.');
            }

            foreach (array_diff(array_keys($chances), array_keys($rules['weather'] ?? [])) as $weather) {
                $result->addError($file, "/regions/{$slug}/weather_ppm/{$weather}", "The rules have no weather {$weather}.");
            }

            if (array_filter($rules['tracks'] ?? [], fn (array $track): bool => ($track['region'] ?? null) === $slug) === []) {
                $result->addError($file, "/regions/{$slug}", 'There is no track in this region, so there is nothing to drive there.');
            }
        }

        // The roads have to lead from the city where a new driver starts (the city of the first track), or the cars of a driver cannot reach the rest of the map.
        $home = $rules['tracks'][array_key_first($rules['tracks'] ?? [])]['region'] ?? array_key_first($rules['regions'] ?? []);
        $reached = $home === null || ! isset($rules['regions'][$home]) ? [] : [$home];

        for ($index = 0; $index < count($reached); $index++) {
            foreach ($rules['roads'] ?? [] as $road) {
                foreach ([[$road['from'] ?? null, $road['to'] ?? null], [$road['to'] ?? null, $road['from'] ?? null]] as [$near, $far]) {
                    if ($near === $reached[$index] && is_string($far) && ! in_array($far, $reached, true)) {
                        $reached[] = $far;
                    }
                }
            }
        }

        foreach (array_diff(array_keys($rules['regions'] ?? []), $reached) as $slug) {
            $result->addError($file, "/regions/{$slug}", 'No road leads there from the city where a new driver starts (the city of the first track).');
        }

        foreach ($rules['roads'] ?? [] as $index => $road) {
            if (($road['from'] ?? null) === ($road['to'] ?? 0)) {
                $result->addError($file, "/roads/{$index}", 'A road leads from a city to itself.');
            }
        }

        $first = array_key_first($rules['tracks'] ?? []);

        if ($first !== null && ($rules['tracks'][$first]['min_player_level'] ?? 1) !== 1) {
            $result->addError($file, "/tracks/{$first}/min_player_level", 'The first track is where a new driver starts: it is open from level 1.');
        }
    }

    /**
     * What the schema cannot say about the numbers of the rules, because it compares one number with another: a range runs from its lowest to its highest, the bands of
     * the defects follow the mileage, the odds of a unit can be drawn, the stop of the police is somewhere on the way and a bribe that fails is no cheaper than one that
     * works, the bots have a reward for every place, and the curve of the levels is a table that is followed by the plain curve. Each is a thing that breaks a draw or a
     * run at a player, and not at the person who writes the number, when it is let through.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkRuleNumbers(array $rules, PackValidationResult $result): void
    {
        $this->checkMarket($rules['market'] ?? [], $result);
        $this->checkWorkshopPrices($rules['workshop'] ?? [], $result);
        $this->checkBoost($rules['travel']['boost'] ?? null, $result);
        $this->checkBots($rules['bots'] ?? null, $result);
        $this->checkPlayerCurve($rules['player'] ?? null, 'rules/starter.json', '/player', 'early_xp', $result);
    }

    /**
     * The bays and the mechanics are bought one after the other, each dearer than the last, and the first of them is there from the start: its price is never charged. The
     * same for the first level of a piece of equipment, which is the state that the workshop starts in.
     *
     * @param  array<string, mixed>  $workshop
     */
    private function checkWorkshopPrices(array $workshop, PackValidationResult $result): void
    {
        $file = 'rules/starter.json';

        foreach (['bays', 'mechanics'] as $kind) {
            $prices = array_values($workshop[$kind]['price_cents'] ?? []);

            foreach ($prices as $index => $price) {
                if ($index > 0 && $price <= $prices[$index - 1]) {
                    $result->addError($file, "/workshop/{$kind}/price_cents/{$index}", 'Each one costs more than the one before it, and this is not more than the last.');
                }
            }

            if (($prices[0] ?? 0) !== 0) {
                $result->addWarning($file, "/workshop/{$kind}/price_cents/0", 'The first one is there from the start, so its price is never charged. Put 0 there.');
            }
        }

        foreach ($workshop['equipment'] ?? [] as $name => $item) {
            if (($item['levels'][0]['price_cents'] ?? 0) !== 0) {
                $result->addWarning($file, "/workshop/equipment/{$name}/levels/0/price_cents", 'The first level is the state that the workshop starts in, so its price is never charged. Put 0 there.');
            }
        }
    }

    /**
     * What the parts say that the game does not hold them to: `fits` is kept by the simulator of the tracks only (the workshop, the shop and the preview put the part on any
     * car), and the power and the mass of an engine are the part's own numbers, which the rules of the engines (the layout, the litres and the tier) would give another.
     */
    private function checkParts(ContentSource $source, PackValidationResult $result): void
    {
        $engineSlot = GameModel::fromSource($source)->slotOf('engine');
        $rules = Rules::fromSource($source);

        foreach ($source->slugs('parts') as $slug) {
            $part = $source->document("parts/{$slug}");
            $file = "parts/{$slug}.json";

            if (isset($part['fits'])) {
                $result->addWarning($file, '/fits', 'The simulator of the tracks keeps the part off the cars it does not fit, but the workshop, the shop and the preview do not: a driver can put it on any car.');
            }

            if ($engineSlot === null || ($part['slot'] ?? null) !== $engineSlot || ! isset($part['engine'])) {
                continue;
            }

            try {
                $power = $rules->enginePowerKw($part['engine']['layout'], (float) $part['engine']['displacement_l'], (int) $part['tier']);
                $mass = $rules->engineMassKg($part['engine']['layout'], (float) $part['engine']['displacement_l']);
            } catch (Throwable) {
                $result->addError($file, '/engine/layout', "The rules of the engines have no layout {$part['engine']['layout']}.");

                continue;
            }

            if (isset($part['stats']['power_kw']) && abs($part['stats']['power_kw'] - $power) > 0.01) {
                $result->addWarning($file, '/stats/power_kw', sprintf('The rules of the engines give %s kW for this layout, displacement and tier, and the part says %s. The game uses the number of the part.', round($power, 2), $part['stats']['power_kw']));
            }

            if (isset($part['stats']['engine_mass_kg']) && abs($part['stats']['engine_mass_kg'] - $mass) > 0.01) {
                $result->addWarning($file, '/stats/engine_mass_kg', sprintf('The rules of the engines give %s kg for this layout and displacement, and the part says %s. The game uses the number of the part.', round($mass, 2), $part['stats']['engine_mass_kg']));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $market
     */
    private function checkMarket(array $market, PackValidationResult $result): void
    {
        $file = 'rules/starter.json';
        $exchange = $market['exchange'] ?? [];

        foreach (['mileage_km', 'km_per_year', 'age_years', 'defect_ppm'] as $key) {
            $this->checkRange($file, "/market/exchange/{$key}", $exchange[$key] ?? null, $result);
        }

        $before = 0;

        foreach ($exchange['defects'] ?? [] as $index => $band) {
            if ($index > 0 && ($band['up_to_km'] ?? 0) <= $before) {
                $result->addError($file, "/market/exchange/defects/{$index}/up_to_km", 'The bands go by the mileage, from the lowest: this one ends at or before the end of the band above it, so no car would ever fall in it.');
            }

            $before = (int) ($band['up_to_km'] ?? 0);

            if (array_sum($band['weights'] ?? []) <= 0) {
                $result->addError($file, "/market/exchange/defects/{$index}/weights", 'All the weights are 0, so there is no chance for any number of defects, and a car of this band cannot be made.');
            }
        }

        $description = $exchange['description'] ?? null;

        if ($description !== null && $description['good_from_ppm'] < $description['fair_from_ppm']) {
            $result->addError($file, '/market/exchange/description/good_from_ppm', 'A car is good from a higher condition than the one it is fair from, and here it is the other way round.');
        }

        $storage = $market['storage'] ?? [];

        foreach ($storage['classes'] ?? [] as $class => $ranges) {
            foreach (['age_years', 'condition_ppm', 'mileage_km'] as $key) {
                $this->checkRange($file, "/market/storage/classes/{$class}/{$key}", $ranges[$key] ?? null, $result);
            }
        }

        foreach ($storage['units'] ?? [] as $unit => $definition) {
            $odds = $definition['odds'] ?? [];

            if (array_sum($odds) <= 0) {
                $result->addError($file, "/market/storage/units/{$unit}/odds", 'All the odds are 0, so nothing could be in the unit.');
            } elseif (($odds['good'] ?? 0) + ($odds['gem'] ?? 0) <= 0) {
                $result->addError($file, "/market/storage/units/{$unit}/odds", 'The guarantee gives a good car or a gem after the units that gave none, and this unit has no odds for either of them.');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $range
     */
    private function checkRange(string $file, string $pointer, ?array $range, PackValidationResult $result): void
    {
        if ($range !== null && ($range['min'] ?? 0) > ($range['max'] ?? 0)) {
            $result->addError($file, $pointer, 'The lowest value is above the highest.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $boost
     */
    private function checkBoost(?array $boost, PackValidationResult $result): void
    {
        if ($boost === null) {
            return;
        }

        $file = 'rules/starter.json';

        if ($boost['police_from_ppm'] > $boost['police_to_ppm']) {
            $result->addError($file, '/travel/boost/police_to_ppm', 'The latest place of the stop is before the earliest, so the police have nowhere on the way to stop the car.');
        }

        if ($boost['bribe_fail_fine_ppm'] < $boost['bribe_ppm']) {
            $result->addError($file, '/travel/boost/bribe_fail_fine_ppm', 'A bribe that did not work costs the driver the bribe and the fine in one sum, so it cannot be less than a bribe that worked: then the driver would try it every time.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $bots
     */
    private function checkBots(?array $bots, PackValidationResult $result): void
    {
        if ($bots === null) {
            return;
        }

        $file = 'rules/starter.json';
        $rewards = array_values($bots['reward_ppm_by_position'] ?? []);

        if (count($rewards) !== count($bots['offsets_ppm'] ?? []) + 1) {
            $result->addError($file, '/bots/reward_ppm_by_position', 'The field is the bots and the car, so there is a reward for every place: one more than there are bots in offsets_ppm. A run cannot be started with the wrong number.');
        }

        foreach ($rewards as $place => $reward) {
            if ($place > 0 && $reward > $rewards[$place - 1]) {
                $result->addError($file, "/bots/reward_ppm_by_position/{$place}", 'A worse place pays more than a better one, and the reward never rises with the place.');
            }
        }

        if (count($bots['names'] ?? []) < count($bots['offsets_ppm'] ?? [])) {
            $result->addError($file, '/bots/names', 'A track takes a name for every bot, and there are fewer names than bots.');
        }
    }

    /**
     * The first levels are a table and the plain curve starts at the catch-up level, which has to be above the end of the table, and the table has to rise.
     *
     * @param  array<string, mixed>|null  $player  the rules of the levels (the player of the rules, the xp of the economy)
     */
    private function checkPlayerCurve(?array $player, string $file, string $pointer, string $tableKey, PackValidationResult $result): void
    {
        if ($player === null || ! isset($player[$tableKey], $player['catch_up_level'])) {
            return;
        }

        $table = array_values($player[$tableKey]);

        foreach ($table as $index => $xp) {
            if ($index > 0 && $xp <= $table[$index - 1]) {
                $result->addError($file, "{$pointer}/{$tableKey}/{$index}", 'The experience that reaches a level has to be more than the one before it.');
            }
        }

        // The table reaches level count + 1: the plain curve has to start above it.
        if ($player['catch_up_level'] <= count($table) + 1) {
            $result->addError($file, "{$pointer}/catch_up_level", 'The table reaches level '.(count($table) + 1).', and the catch-up level has to be above it: from it the plain curve starts, and the levels between are on a straight line to it.');
        }
    }

    /** The economy keeps its own copy of the table of the levels, and it follows the same rule. */
    private function checkEconomyCurve(ContentSource $source, PackValidationResult $result): void
    {
        if ($source->has('rules/economy')) {
            $this->checkPlayerCurve($source->document('rules/economy')['model']['xp'] ?? null, 'rules/economy.json', '/model/xp', 'early', $result);
        }
    }

    /**
     * What the threads of the tracks and the dynamics of the rules come to together, which the schema cannot say (`ThreadDocument::problems`): the sections add up to the
     * length of the track, a lap that is driven on the thread has a reference car that can be driven, and the handling is not less of a corner in a hairpin than in a
     * wide bend.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkThreads(array $rules, PackValidationResult $result): void
    {
        $file = 'rules/starter.json';

        foreach ($rules['tracks'] ?? [] as $slug => $track) {
            if (! is_array($track)) {
                continue;
            }

            try {
                $problems = ThreadDocument::problems($track);
            } catch (InvalidArgumentException $problem) {
                $problems = [['pointer' => '/thread', 'message' => 'The thread cannot be driven: '.$problem->getMessage(), 'error' => true]];
            }

            foreach ($problems as $problem) {
                $problem['error']
                    ? $result->addError($file, "/tracks/{$slug}{$problem['pointer']}", $problem['message'])
                    : $result->addWarning($file, "/tracks/{$slug}{$problem['pointer']}", $problem['message']);
            }
        }

        $agility = $rules['dynamics']['agility'] ?? null;

        if (is_array($agility) && ($agility['min_ppm'] ?? 0) > ($agility['max_ppm'] ?? 0)) {
            $result->addError($file, '/dynamics/agility', 'The share of handling in a wide bend is more than in a hairpin; it is the other way round.');
        }
    }

    /**
     * What the numbers of a track come to, which the schema cannot say: the speed has at most four decimals and the experience of an hour two, the track is no stunt (a
     * length of 200 m and a lap of 8 s at least: below that the experience of a lap cannot be rounded fairly, and a track that is made by the model must not be shorter,
     * while one that was said by hand only gets a warning), and a number that is said by hand is believed over the model, so that it is worth saying when it is not what
     * the model would make. The experience of a lap is a whole number, so it is also said when the rounding takes a tenth of it.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkTrackNumbers(array $rules, PackValidationResult $result): void
    {
        $file = 'rules/starter.json';
        $loads = TrackNumbers::fuelLoads($rules);

        foreach ($rules['tracks'] ?? [] as $slug => $track) {
            $length = $track['length_m'] ?? null;

            if (! is_array($track) || ! is_int($length)) {
                continue;
            }

            $modelled = isset($track['speed_kmh']);

            if ($modelled && is_numeric($track['speed_kmh']) && abs(round((float) $track['speed_kmh'], 4) - $track['speed_kmh']) > self::DECIMALS_TOLERANCE) {
                $result->addError($file, "/tracks/{$slug}/speed_kmh", 'The speed has more than four decimals.');
            }

            if (isset($track['xp_per_hour']) && is_numeric($track['xp_per_hour']) && abs(round((float) $track['xp_per_hour'], 2) - $track['xp_per_hour']) > self::DECIMALS_TOLERANCE) {
                $result->addError($file, "/tracks/{$slug}/xp_per_hour", 'The experience of an hour has more than two decimals.');
            }

            $numbers = TrackNumbers::derive($track, $loads);
            $lap = $numbers['base_lap_ms'] ?? null;

            if (! is_int($lap)) {
                continue;
            }

            $flag = $modelled ? $result->addError(...) : $result->addWarning(...);

            if ($length < TrackModel::MIN_LENGTH_M) {
                $flag($file, "/tracks/{$slug}/length_m", 'The track is shorter than '.TrackModel::MIN_LENGTH_M.' m.');
            }

            if ($lap < TrackModel::MIN_LAP_MS) {
                $flag($file, "/tracks/{$slug}/".($modelled ? 'speed_kmh' : 'base_lap_ms'), 'The lap of the track takes '.round($lap / 1000, 1).' s, less than '.(TrackModel::MIN_LAP_MS / 1000).' s.');
            }

            $this->warnAboutHandNumbers($file, (string) $slug, $track, $loads, $lap, $result);
            $this->warnAboutRoundedExperience($file, (string) $slug, $track, $lap, $result);
        }
    }

    /**
     * A number that was said by hand and is not what the model would make of the length and the speed, the kind of the track and what an hour pays and gives.
     *
     * @param  array<string, mixed>  $track
     * @param  array<string, int>  $loads
     */
    private function warnAboutHandNumbers(string $file, string $slug, array $track, array $loads, int $lap, PackValidationResult $result): void
    {
        $load = $loads[(string) ($track['kind'] ?? TrackNumbers::DEFAULT_KIND)] ?? $loads[TrackNumbers::DEFAULT_KIND];
        $made = [
            'base_lap_ms' => isset($track['speed_kmh']) && is_numeric($track['speed_kmh']) ? TrackModel::lapMs((int) $track['length_m'], TrackNumbers::speedScaled($track['speed_kmh'])) : null,
            'fuel_per_lap_ml' => TrackModel::fuelMl($load, $lap),
            'reward_cents_per_lap' => isset($track['pay_cents_per_hour']) ? TrackModel::cashCents((int) $track['pay_cents_per_hour'], $lap) : null,
            'xp_per_lap' => isset($track['xp_per_hour']) && is_numeric($track['xp_per_hour']) ? TrackModel::xp(TrackNumbers::centiXp($track['xp_per_hour']), $lap) : null,
        ];

        foreach ($made as $field => $expected) {
            $said = $track[$field] ?? null;

            if ($expected === null || ! is_int($said) || $expected === 0 || abs($said - $expected) / $expected <= self::HAND_NUMBER_TOLERANCE) {
                continue;
            }

            $result->addWarning($file, "/tracks/{$slug}/{$field}", sprintf('The track says %d by hand, and the model would make %d (%+.1f%%): the number that is said is the one that counts.', $said, $expected, 100 * ($said - $expected) / $expected));
        }
    }

    /**
     * The experience of a lap is a whole number, so a short lap of a track that gives experience by the hour is paid more or less than the hour says: said when the
     * rounding is more than a tenth.
     *
     * @param  array<string, mixed>  $track
     */
    private function warnAboutRoundedExperience(string $file, string $slug, array $track, int $lap, PackValidationResult $result): void
    {
        if (isset($track['xp_per_lap']) || ! isset($track['xp_per_hour']) || ! is_numeric($track['xp_per_hour']) || $track['xp_per_hour'] <= 0) {
            return;
        }

        $exact = $track['xp_per_hour'] * $lap / 3_600_000;
        $given = TrackModel::xp(TrackNumbers::centiXp($track['xp_per_hour']), $lap);

        if (abs($given - $exact) / $exact > self::ROUNDED_EXPERIENCE_TOLERANCE) {
            $result->addWarning($file, "/tracks/{$slug}/xp_per_hour", sprintf('A lap of this track is worth %.2f XP, and the game gives %d: a lap gives a whole number, so an hour gives %+.0f%% of what it says. A longer track rounds less.', $exact, $given, 100 * ($given - $exact) / $exact));
        }
    }

    /**
     * The steps of the tutorial have keys of their own, and what they give away is in the pack, as is the car of the listing of the tutorial.
     *
     * @param  list<string>  $parts
     * @param  list<string>  $vehicles
     * @param  list<string>  $regions
     */
    private function checkTutorial(ContentSource $source, array $parts, array $vehicles, array $regions, PackValidationResult $result): void
    {
        if (! $source->has('rules/tutorial')) {
            return;
        }

        $file = 'rules/tutorial.json';
        $keys = [];

        $tutorial = $source->document('rules/tutorial');

        foreach ($tutorial['steps'] ?? [] as $index => $step) {
            $key = (string) ($step['key'] ?? '');

            if (isset($keys[$key])) {
                $result->addError($file, "/steps/{$index}/key", "The key {$key} is used by two steps.");
            }

            $keys[$key] = true;

            if (isset($step['reward']['part']) && ! in_array($step['reward']['part'], $parts, true)) {
                $result->addError($file, "/steps/{$index}/reward/part", "The step gives away {$step['reward']['part']}, which is not a part of the pack.");
            }

            if (isset($step['reward']['garage']) && ! in_array($step['reward']['garage'], $regions, true)) {
                $result->addError($file, "/steps/{$index}/reward/garage", "The step gives a garage in {$step['reward']['garage']}, which is not a city.");
            }

            if (isset($step['gift']['part']) && ! in_array($step['gift']['part'], $parts, true)) {
                $result->addError($file, "/steps/{$index}/gift/part", "The step gives away {$step['gift']['part']} when it starts, which is not a part of the pack.");
            }

            if (($step['optional'] ?? false) && ! isset($step['decline'])) {
                $result->addError($file, "/steps/{$index}/decline", 'An optional step needs the words of the button that leaves it for later.');
            }

            if (($step['sms'] ?? []) !== [] && ! isset($step['reply'])) {
                $result->addError($file, "/steps/{$index}/reply", 'The step shows messages, and it has no words for the button that answers them.');
            }

            $trigger = TutorialSteps::conditions($step['trigger'] ?? []);
            $completion = TutorialSteps::conditions($step['completion'] ?? []);

            if (in_array('acknowledged', $trigger, true)) {
                $result->addError($file, "/steps/{$index}/trigger", 'The messages of a step are shown after it has started, so the driver cannot answer them before it starts, and the step would never start. Use acknowledged as the completion.');
            }

            if (in_array('recovered', $trigger, true)) {
                $result->addError($file, "/steps/{$index}/trigger", 'recovered holds for every car that does not stand broken down, so a step that starts on it starts at once. It is for ending a step that starts on breakdown.');
            }

            if (in_array('recovered', $completion, true) && ! in_array('breakdown', $trigger, true)) {
                $result->addError($file, "/steps/{$index}/completion", 'recovered holds for every car that does not stand broken down, so the step would be done the moment it started. Start the step on breakdown.');
            }

            if (in_array('car_bought', $completion, true) && ! isset($tutorial['script']['exchange_listing'])) {
                $result->addError($file, "/steps/{$index}/completion", 'The step waits for a car bought from the exchange, and the script has no listing of the tutorial to buy.');
            }

            if (in_array('car_bought', $trigger, true)) {
                $result->addWarning($file, "/steps/{$index}/trigger", 'The step starts when the driver has bought a car from the exchange, any car. The listing of the tutorial is offered only to a driver whose waiting step has car_bought as its completion.');
            }
        }

        foreach (self::TUTORIAL_KEYS_OF_THE_SERVER as $step => $arranges) {
            if (($tutorial['steps'] ?? []) !== [] && ! isset($keys[$step])) {
                $result->addWarning($file, '/steps', "The server arranges {$arranges} for the step with the key {$step}, and the tutorial has no such step: it is not arranged.");
            }
        }

        $listing = $tutorial['script']['exchange_listing']['vehicle'] ?? null;

        if ($listing !== null && ! in_array($listing, $vehicles, true)) {
            $result->addError($file, '/script/exchange_listing/vehicle', "The listing of the tutorial is {$listing}, which is not a car of the pack.");
        }
    }

    /**
     * The paid features hold together: a pack of refills that gives more charges than a driver may keep can never be bought, and a pack of coins that gives fewer coins for
     * its price than the first pack (which is the price of a coin that the others are measured by) has no bonus, so that it only costs more.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkPremium(array $rules, PackValidationResult $result): void
    {
        $premium = $rules['premium'] ?? null;

        if (! is_array($premium)) {
            return;
        }

        $cap = $premium['autorefill']['stock_cap'] ?? null;

        foreach ((array) ($premium['autorefill']['packs'] ?? []) as $slug => $pack) {
            if (is_int($cap) && ($pack['charges'] ?? 0) > $cap) {
                $result->addError('rules/starter.json', "/premium/autorefill/packs/{$slug}/charges", "The pack gives {$pack['charges']} charges and a driver may keep {$cap}: it could never be bought.");
            }
        }

        $products = (array) ($premium['products'] ?? []);
        $first = $products === [] ? null : $products[array_key_first($products)];

        if ($first === null || ! is_numeric($first['coins'] ?? null) || ! is_numeric($first['price_minor'] ?? null)) {
            return;
        }

        foreach (array_slice($products, 1, null, true) as $slug => $product) {
            if (is_numeric($product['coins'] ?? null) && is_numeric($product['price_minor'] ?? null) && $product['coins'] * $first['price_minor'] < $product['price_minor'] * $first['coins']) {
                $result->addWarning('rules/starter.json', "/premium/products/{$slug}", 'The pack gives fewer coins for its price than the first pack, so it has no bonus (the first pack is the reference price of a coin).');
            }
        }
    }

    /**
     * What the schema cannot say about the competition: the reference car is a car of the pack, the weights are for the statistics of the model of the game and add up
     * to a million, the limits of the classes grow and the top class is not one of them, the numbers of Respekt agree (the divisions grow, the minimum is not above the
     * start) and a match has an odd number of runs.
     *
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $vehicles  the slugs of the cars of the pack
     */
    private function checkCompetition(ContentSource $source, array $rules, array $vehicles, PackValidationResult $result): void
    {
        $section = $rules['competition'] ?? null;

        if ($section === null) {
            return;
        }

        $file = 'rules/starter.json';
        $pi = $section['pi'];

        if (! in_array($pi['reference_car'], $vehicles, true)) {
            $result->addError($file, '/competition/pi/reference_car', "There is no car {$pi['reference_car']} in the pack.");
        }

        $stats = GameModel::fromSource($source)->stats();

        foreach (array_keys($pi['weights_ppm']) as $stat) {
            if (! in_array($stat, $stats, true)) {
                $result->addError($file, "/competition/pi/weights_ppm/{$stat}", "{$stat} is not a statistic of the model of the game.");
            }
        }

        if (array_sum($pi['weights_ppm']) !== 1_000_000) {
            $result->addError($file, '/competition/pi/weights_ppm', 'The weights of the statistics add up to '.array_sum($pi['weights_ppm']).', and they must add up to 1 000 000.');
        }

        $previous = 0;

        foreach ($pi['class_max_pi'] as $class => $limit) {
            if ($limit <= $previous) {
                $result->addError($file, "/competition/pi/class_max_pi/{$class}", "The limit of the class {$class} must be above the one of the class before it ({$previous}).");
            }

            $previous = $limit;
        }

        if ($previous >= $pi['max_pi']) {
            $result->addError($file, '/competition/pi/max_pi', "The highest PI shown ({$pi['max_pi']}) must be above the limit of the last class with a limit ({$previous}), or the top class would be empty.");
        }

        if (array_key_exists($pi['top_class'], $pi['class_max_pi'])) {
            $result->addError($file, '/competition/pi/top_class', "The top class {$pi['top_class']} is also a class with a limit.");
        }

        $rating = $section['rating'];

        if ($rating['minimum'] > $rating['start']) {
            $result->addError($file, '/competition/rating/minimum', 'The lowest Respekt is above the Respekt of a new driver.');
        }

        if (! ($rating['club_from'] < $rating['regional_from'] && $rating['regional_from'] < $rating['elite_from'])) {
            $result->addError($file, '/competition/rating', 'The divisions must begin at a higher Respekt one after the other: club, regional, elite.');
        }

        if ($section['match']['runs'] % 2 === 0) {
            $result->addError($file, '/competition/match/runs', 'A match is played in an odd number of runs, so that one side wins most of them.');
        }
    }

    /**
     * What the schema cannot say about the streak of days: a milestone is named once, the crates it and the cycle give are crates of the game, and the streak is counted
     * out of the missions, so a game that has one and no missions pays nothing.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkStreaks(array $rules, PackValidationResult $result): void
    {
        $section = $rules['streaks'] ?? null;

        if ($section === null) {
            return;
        }

        if ($section['enabled'] && ! ($rules['missions']['enabled'] ?? false)) {
            $result->addWarning('rules/starter.json', '/streaks/enabled', 'The streak is on, and it is counted out of the missions, which are off or missing: nothing is counted.');
        }

        $days = array_column($section['milestones'], 'day');

        foreach ($days as $index => $day) {
            if (array_search($day, $days, true) !== $index) {
                $result->addError('rules/starter.json', "/streaks/milestones/{$index}/day", "The milestone of {$day} days is named twice.");
            }
        }

        $kinds = array_keys($rules['shop']['crates']['kinds'] ?? []);

        foreach (['cycle' => $section['cycle'], 'milestones' => $section['milestones']] as $where => $entries) {
            foreach ($entries as $index => $entry) {
                foreach ($entry['items'] ?? [] as $position => $item) {
                    if (! in_array($item['kind'], $kinds, true)) {
                        $result->addError('rules/starter.json', "/streaks/{$where}/{$index}/items/{$position}/kind", "The streak gives a crate of a kind the shop does not have: {$item['kind']}.");
                    }
                }
            }
        }
    }

    /**
     * What the schema cannot say about the gigs: the kind of every template is a kind of the document, a trip in the city has a length and one between cities has not, the
     * smallest offer of a template is one that some car of the pack can take, every phase that has offers has a template to draw them from, the levels and the rungs of the
     * pay are in order, the step of the tutorial exists, and every band of levels has a trip in the city that fits its longest gig.
     *
     * @param  list<string>  $vehicles  the slugs of the cars of the pack
     */
    private function checkGigs(ContentSource $source, array $vehicles, PackValidationResult $result): void
    {
        if (! $source->has('rules/gigs')) {
            return;
        }

        $file = 'rules/gigs.json';
        $gigs = $source->document('rules/gigs');

        if (! in_array($gigs['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $result->addError($file, '/timezone', "{$gigs['timezone']} is not a time zone.");
        }

        if ($source->has('rules/tutorial') && ! in_array($gigs['unlock_after_step'], array_column($source->document('rules/tutorial')['steps'] ?? [], 'key'), true)) {
            $result->addError($file, '/unlock_after_step', "The tutorial has no step {$gigs['unlock_after_step']}.");
        }

        foreach (['/bands' => $gigs['bands'], '/pay/anchors' => $gigs['pay']['anchors']] as $path => $rows) {
            $levels = array_column($rows, 'from_level');
            $sorted = $levels;
            sort($sorted);

            if ($levels !== $sorted || count(array_unique($levels)) !== count($levels) || ($levels[0] ?? 1) !== 1) {
                $result->addError($file, $path, 'The rows have to start at level 1 and go up, each at a higher level than the one before.');
            }
        }

        if (array_sum(array_column($gigs['rarities'], 'weight')) <= 0 || ($gigs['rarities'][0]['weight'] ?? 0) <= 0 || count(array_unique(array_column($gigs['rarities'], 'key'))) !== count($gigs['rarities'])) {
            $result->addError($file, '/rarities', 'The first rarity (the ordinary one) needs a weight, and the keys of the rarities are different.');
        }

        $cars = [];

        foreach ($vehicles as $slug) {
            $cars[] = $source->document("vehicles/{$slug}");
        }

        $classes = array_unique(array_column($cars, 'class'));
        $weighted = array_fill_keys(GigCatalog::PHASES, false);

        foreach ($gigs['templates'] as $key => $template) {
            $where = "/templates/{$key}";

            if (! isset($gigs['kinds'][$template['kind']])) {
                $result->addError($file, "{$where}/kind", "The kind {$template['kind']} is not one of the kinds of the document.");
            }

            if ($template['mode'] === 'local' && ! isset($template['local_km'])) {
                $result->addError($file, "{$where}/local_km", 'A trip in the city needs its length.');
            }

            if ($template['mode'] === 'intercity' && isset($template['local_km'])) {
                $result->addWarning($file, "{$where}/local_km", 'A trip between cities is as long as the road, so its local length is not used.');
            }

            foreach (['amount' => $template['amount'], 'local_km' => $template['local_km'] ?? null] as $field => $range) {
                if ($range !== null && $range['min'] > $range['max']) {
                    $result->addError($file, "{$where}/{$field}", 'The smallest is more than the biggest.');
                }
            }

            foreach ($template['class_in'] ?? [] as $position => $class) {
                if (! in_array($class, $classes, true)) {
                    $result->addWarning($file, "{$where}/class_in/{$position}", "No car of the pack is of the class {$class}.");
                }
            }

            $fits = array_filter($cars, static fn (array $car): bool => ($car[$template['capacity']] ?? 0) >= $template['amount']['min'] && (! isset($template['class_in']) || in_array($car['class'], $template['class_in'], true)));

            if ($fits === []) {
                $result->addError($file, "{$where}/amount", 'No car of the pack can take even the smallest offer of this template.');
            }

            foreach (GigCatalog::PHASES as $phase) {
                $weighted[$phase] = $weighted[$phase] || $template['phases'][$phase] > 0;
            }
        }

        foreach ($weighted as $phase => $has) {
            if (! $has && $gigs['slots'][$phase] > 0) {
                $result->addError($file, "/slots/{$phase}", "The board has offers in the {$phase}, and no template has a weight in it.");
            }
        }

        foreach ($gigs['bands'] as $index => $band) {
            $fitting = array_filter($gigs['templates'], static fn (array $template): bool => $template['mode'] === 'local' && $template['min_player_level'] <= $band['from_level'] && $template['local_km']['min'] * 60 <= $band['max_ref_minutes'] * $gigs['pay']['ref_speed_kmh']);

            if ($fitting === []) {
                $result->addWarning($file, "/bands/{$index}", "No trip in the city fits the longest gig of level {$band['from_level']}: a driver of that level has no gig in a city with no other city open.");
            }
        }
    }

    /**
     * What the schema cannot say about the missions: the numbers of the rules and the pools of the document agree, a goal is the kind of goal its template says it
     * is (a part of a tank, or a number of times), the keys of the templates are the keys of one template, the crates they give are crates of the game, and every
     * level of the game can draw a mission for every slot, so that a set is never short of one.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkMissions(ContentSource $source, array $rules, PackValidationResult $result): void
    {
        $file = 'rules/missions.json';
        $has = $source->has('rules/missions');
        $section = $rules['missions'] ?? null;

        if ($section === null) {
            if ($has) {
                $result->addWarning($file, '', 'The pack has missions, but the rules have no missions section: the game does not draw them.');
            }

            return;
        }

        if (! in_array($section['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $result->addError('rules/starter.json', '/missions/timezone', "{$section['timezone']} is not a time zone.");
        }

        if ($source->has('rules/tutorial') && ! in_array($section['unlock_after_step'], array_column($source->document('rules/tutorial')['steps'] ?? [], 'key'), true)) {
            $result->addError('rules/starter.json', '/missions/unlock_after_step', "The tutorial has no step {$section['unlock_after_step']}.");
        }

        if (! $has) {
            if ($section['enabled']) {
                $result->addWarning('rules/starter.json', '/missions/enabled', 'The missions are on, but the pack has no rules/missions.json to draw them from.');
            }

            return;
        }

        if (($section['periods'] ?? []) !== [] && ! isset($rules['premium']) && array_sum(array_column($section['periods'], 'bonus_coins')) > 0) {
            $result->addWarning('rules/starter.json', '/missions/periods', 'The sets pay premium coins, and the rules have no premium currency: nothing is paid.');
        }

        $missions = $source->document('rules/missions');
        $asksGigs = false;

        foreach ([...$missions['periodic']['daily'] ?? [], ...$missions['periodic']['weekly'] ?? [], ...$missions['periodic']['monthly'] ?? []] as $slot) {
            $asksGigs = $asksGigs || in_array('gig', array_column($slot['templates'], 'objective'), true);
        }

        foreach ($missions['chains'] ?? [] as $chain) {
            $asksGigs = $asksGigs || in_array('gig', array_column($chain['missions'], 'objective'), true);
        }

        if ($asksGigs && (! $source->has('rules/gigs') || ! ($source->document('rules/gigs')['enabled'] ?? false))) {
            $result->addWarning($file, '', 'The missions ask for gigs, and the pack has none that are switched on: a driver cannot do them.');
        }

        $effort = $missions['effort'] ?? [];
        $levels = (int) ($rules['player']['max_level'] ?? 30);
        $kinds = array_keys($rules['shop']['crates']['kinds'] ?? []);
        $keys = [];

        foreach (MissionCatalog::PERIODS as $period) {
            foreach ($missions['periodic'][$period] ?? [] as $index => $slot) {
                $where = "/periodic/{$period}/{$index}";
                $covered = array_fill(1, $levels, false);

                foreach ($slot['templates'] as $key => $template) {
                    $pointer = "{$where}/templates/{$key}";

                    if (isset($keys[$key])) {
                        $result->addError($file, $pointer, "The key {$key} is used by two templates.");
                    }

                    $keys[$key] = true;
                    $objective = $template['objective'];

                    if (! isset($effort[$objective])) {
                        $result->addError($file, '/effort', "The goal {$objective} is used by the template {$key}, and the effort has no entry for it: nothing can say what it is worth.");
                    }

                    if (! in_array($objective, MissionCatalog::PERIODIC_OBJECTIVES, true)) {
                        $result->addError($file, "{$pointer}/objective", "{$objective} is a goal of the main missions: a set cannot count it over a period.");
                    } elseif (in_array($objective, MissionCatalog::TANK_OBJECTIVES, true) && ! isset($template['tanks_ppm'])) {
                        $result->addError($file, $pointer, "The goal {$objective} is a part of a tank, and the template has no tanks_ppm.");
                    } elseif (! in_array($objective, MissionCatalog::TANK_OBJECTIVES, true) && ! isset($template['count'])) {
                        $result->addError($file, $pointer, "The goal {$objective} is a number of times, and the template has no count.");
                    }

                    if (isset($template['max_player_level'], $template['min_player_level']) && $template['max_player_level'] < $template['min_player_level']) {
                        $result->addError($file, "{$pointer}/max_player_level", 'The highest level is lower than the lowest.');
                    }

                    foreach ($template['items'] ?? [] as $position => $item) {
                        if ($item['type'] === 'crate' && ! in_array($item['kind'], $kinds, true)) {
                            $result->addError($file, "{$pointer}/items/{$position}/kind", "The game has no crate {$item['kind']}.");
                        }
                    }

                    for ($level = (int) ($template['min_player_level'] ?? 1); $level <= min($levels, (int) ($template['max_player_level'] ?? $levels)); $level++) {
                        $covered[$level] = true;
                    }
                }

                $gap = array_search(false, $covered, true);

                if ($gap !== false) {
                    $result->addError($file, $where, "No template of the slot {$slot['key']} fits a driver of level {$gap}: the set would have no mission for it.");
                }
            }
        }

        $rarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
        $tires = array_keys($rules['tire_matrix'] ?? []);
        $chainKeys = [];
        $missionKeys = [];

        foreach ($missions['chains'] ?? [] as $chainIndex => $chain) {
            if (isset($chainKeys[$chain['key']])) {
                $result->addError($file, "/chains/{$chainIndex}/key", "The key {$chain['key']} is used by two chains.");
            }

            $chainKeys[$chain['key']] = true;

            foreach ($chain['missions'] as $index => $mission) {
                $pointer = "/chains/{$chainIndex}/missions/{$index}";

                if (isset($missionKeys[$mission['key']])) {
                    $result->addError($file, "{$pointer}/key", "The key {$mission['key']} is used by two missions.");
                }

                $missionKeys[$mission['key']] = true;
                $objective = $mission['objective'];
                $param = $mission['param'] ?? null;

                if (! isset($effort[$objective]) && ! isset($effort["{$objective}:{$param}"])) {
                    $result->addError($file, '/effort', "The goal {$objective} is used by the mission {$mission['key']}, and the effort has no entry for it: nothing can say what it is worth.");
                }

                if ($objective === 'rarity_owned' && ! in_array($param, $rarities, true)) {
                    $result->addError($file, "{$pointer}/param", 'The mission counts the parts of a rarity, and param is not one of: '.implode(', ', $rarities).'.');
                } elseif ($objective === 'tires_mounted' && ! in_array($param, $tires, true)) {
                    $result->addError($file, "{$pointer}/param", 'The mission is about a kind of tires, and param is not one of: '.implode(', ', $tires).'.');
                } elseif (! in_array($objective, ['rarity_owned', 'tires_mounted'], true) && $param !== null) {
                    $result->addError($file, "{$pointer}/param", "The goal {$objective} takes no param.");
                }

                if (isset($mission['min_player_level']) && $mission['min_player_level'] > $levels) {
                    $result->addError($file, "{$pointer}/min_player_level", "No driver reaches level {$mission['min_player_level']}: the highest level is {$levels}.");
                }

                foreach ($mission['reward']['items'] ?? [] as $position => $item) {
                    if ($item['type'] === 'crate' && ! in_array($item['kind'], $kinds, true)) {
                        $result->addError($file, "{$pointer}/reward/items/{$position}/kind", "The game has no crate {$item['kind']}.");
                    }
                }
            }
        }
    }

    private function validateManifest(string $packPath, PackValidationResult $result): void
    {
        $file = "{$packPath}/pack.json";

        if (! is_file($file)) {
            $result->addError('pack.json', '', 'The manifest pack.json is missing.');

            return;
        }

        $this->validateFile($file, 'pack.json', 'pack.schema.json', $result);
    }

    private function validateFolder(string $packPath, string $folder, string $schemaFile, PackValidationResult $result): void
    {
        $directory = "{$packPath}/{$folder}";

        if (! is_dir($directory)) {
            return;
        }

        $files = glob("{$directory}/*.json") ?: [];
        sort($files);

        foreach ($files as $path) {
            $relative = "{$folder}/".basename($path);
            $data = $this->validateFile($path, $relative, $schemaFile, $result);

            if ($data === null) {
                continue;
            }

            $result->count($folder);

            $slug = $data['slug'] ?? null;

            if (is_string($slug) && $slug !== basename($path, '.json')) {
                $result->addError($relative, '/slug', "The file must be named after its slug: {$slug}.json.");
            }

            $this->warnAboutMissingTranslations($relative, $data, $result);

            if ($folder === 'vehicles') {
                $this->checkVehicleRules($relative, $data, $result);
            }

            // A folder of a pack has no model of its own: the parts are judged against the one of the car game.
            if ($folder === 'parts') {
                $this->checkPartAgainstModel($relative, $data, GameModel::default(), $result);
            }
        }
    }

    /**
     * Validates one file against a schema. Returns the decoded data when the
     * file is readable JSON that passes the schema, otherwise null.
     *
     * @return array<string, mixed>|null
     */
    private function validateFile(string $path, string $relative, string $schemaFile, PackValidationResult $result): ?array
    {
        return $this->validateJson((string) file_get_contents($path), $relative, $schemaFile, $result);
    }

    /**
     * Validates the text of one document against a schema. Returns the decoded data when it is JSON that passes the schema, otherwise null.
     *
     * @return array<string, mixed>|null
     */
    private function validateJson(string $json, string $relative, string $schemaFile, PackValidationResult $result): ?array
    {
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $result->addError($relative, '', 'Invalid JSON: '.$exception->getMessage());

            return null;
        }

        $outcome = $this->validator->validate($object, self::SCHEMA_BASE.$schemaFile);

        if (! $outcome->isValid()) {
            foreach ((new ErrorFormatter)->formatKeyed($outcome->error()) as $pointer => $messages) {
                foreach ($messages as $message) {
                    $result->addError($relative, (string) $pointer, $message);
                }
            }

            return null;
        }

        return is_array($array) ? $array : null;
    }

    /** @param array<string, mixed> $data */
    private function warnAboutMissingTranslations(string $relative, array $data, PackValidationResult $result): void
    {
        foreach (['name', 'description'] as $field) {
            $text = $data[$field] ?? null;

            if (! is_array($text)) {
                continue;
            }

            foreach (Locale::supported() as $locale) {
                if (! isset($text[$locale])) {
                    $result->addWarning($relative, "/{$field}/{$locale}", 'Missing translation, English is used instead.');
                }
            }
        }
    }

    /**
     * The texts of the world (the names of the cities and of the tracks) and of the tutorial (the messages and the button that answers them) that a language of the
     * game has none of.
     *
     * @param  array<string, mixed>  $data
     */
    private function warnAboutMissingRulesTranslations(string $relative, string $name, array $data, PackValidationResult $result): void
    {
        $texts = [];

        if ($name === 'starter') {
            foreach (['regions', 'tracks'] as $section) {
                foreach ($data[$section] ?? [] as $slug => $item) {
                    $texts["/{$section}/{$slug}/name"] = $item['name'] ?? null;
                }
            }
        }

        if ($name === 'vocabulary') {
            foreach ($data['messages'] ?? [] as $key => $message) {
                $texts["/messages/{$key}"] = $message;
            }
        }

        if ($name === 'branding') {
            foreach ($data['landing']['blocks'] ?? [] as $index => $block) {
                foreach (['eyebrow', 'title', 'subtitle', 'text', 'body', 'alt', 'caption'] as $field) {
                    $texts["/landing/blocks/{$index}/{$field}"] = $block[$field] ?? null;
                }

                foreach ($block['items'] ?? [] as $position => $item) {
                    foreach (['kicker', 'title', 'text'] as $field) {
                        $texts["/landing/blocks/{$index}/items/{$position}/{$field}"] = $item[$field] ?? null;
                    }
                }
            }

            $texts['/share/description'] = $data['share']['description'] ?? null;
        }

        if ($name === 'missions') {
            foreach ($data['chains'] ?? [] as $chainIndex => $chain) {
                $texts["/chains/{$chainIndex}/title"] = $chain['title'] ?? null;

                foreach ($chain['missions'] ?? [] as $index => $mission) {
                    $texts["/chains/{$chainIndex}/missions/{$index}/title"] = $mission['title'] ?? null;
                    $texts["/chains/{$chainIndex}/missions/{$index}/text"] = $mission['text'] ?? null;
                }
            }
        }

        if ($name === 'gigs') {
            foreach ($data['kinds'] ?? [] as $key => $kind) {
                $texts["/kinds/{$key}/name"] = $kind['name'] ?? null;
            }

            foreach ($data['templates'] ?? [] as $key => $template) {
                $texts["/templates/{$key}/title"] = $template['title'] ?? null;
            }
        }

        if ($name === 'tutorial') {
            foreach ($data['steps'] ?? [] as $index => $step) {
                foreach ($step['sms'] ?? [] as $position => $sms) {
                    $texts["/steps/{$index}/sms/{$position}"] = $sms;
                }

                $texts["/steps/{$index}/reply"] = $step['reply'] ?? null;
            }
        }

        foreach ($texts as $pointer => $text) {
            if (! is_array($text)) {
                continue;
            }

            foreach (Locale::supported() as $locale) {
                if (! isset($text[$locale])) {
                    $result->addWarning($relative, "{$pointer}/{$locale}", 'Missing translation, English is used instead.');
                }
            }
        }
    }

    /**
     * The words of a pack against what speaks them: a message of the vocabulary has to be one that the web app or the server has, and its text has to fill in the
     * same names (the program gives the value, a text that leaves one out shows nothing, one that adds one shows an empty place), keep the forms of a plural that the
     * message has (the number of | in it), and in the web app not use a brace that is not a name or the syntax of a link to another message. Without the texts of the
     * web app to ask, its messages are not checked; the texts of the server always can be.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkVocabulary(string $relative, string $name, array $data, PackValidationResult $result, ?GameModel $model = null): void
    {
        if ($name !== 'vocabulary') {
            return;
        }

        $catalog = MessageCatalog::fromConfig();

        foreach ($data['messages'] ?? [] as $key => $texts) {
            if (! $catalog->canJudge($key)) {
                continue;
            }

            $original = $catalog->all()[$key] ?? null;

            // The name of a slot of the model that the app does not know (a game that has a sail where the car game has tires) is a message of the pack: it has no names to fill in.
            if ($original === null && $model !== null && preg_match('/^slot\.([a-z][a-z0-9_]*)$/', $key, $slot) === 1 && $model->has($slot[1])) {
                $original = '';
            }

            if ($original === null) {
                $result->addError($relative, "/messages/{$key}", MessageCatalog::isServer($key) ? "The server has no text {$key}." : "The web app has no message {$key}.");

                continue;
            }

            $expected = MessageCatalog::placeholders($key, $original);
            $isServer = MessageCatalog::isServer($key);

            foreach ($texts as $locale => $text) {
                $pointer = "/messages/{$key}/{$locale}";
                $left = preg_replace('/\{[A-Za-z0-9_]+\}/', '', $text) ?? $text;

                if (MessageCatalog::placeholders($key, $text) !== $expected) {
                    $written = implode(', ', array_map(static fn (string $placeholder): string => MessageCatalog::written($key, $placeholder), $expected));
                    $result->addError($relative, $pointer, 'The text must fill in the same names as the original: '.($expected === [] ? 'none' : $written).'.');
                } elseif (! $isServer && (str_contains($left, '{') || str_contains($left, '}'))) {
                    $result->addError($relative, $pointer, 'A brace that is not a {name} cannot be shown: the app reads it as a part of a message.');
                } elseif (! $isServer && (str_contains($text, '@:') || str_contains($text, '@.'))) {
                    $result->addError($relative, $pointer, 'A link to another message (@:key) is not allowed in the vocabulary.');
                } elseif (substr_count($text, '|') !== substr_count($catalog->text($key, $locale) ?? $original, '|')) {
                    // The forms of a plural are the ones of the language: Polish has three where English has two.
                    $forms = substr_count($catalog->text($key, $locale) ?? $original, '|') + 1;
                    $result->addError($relative, $pointer, "The message has {$forms} form(s) of a plural in this language, separated by |: the text must have the same number.");
                }
            }
        }
    }

    /**
     * What the schema cannot say about the look of a game: the style sheet of its own is read the way a browser reads it and refused for anything that would load
     * something from outside (each reason is an error of its own, so that the person sees what to take out), and the pictures that the front page, the preview of a
     * link and the sheet name are looked for in the store (the pack is whole without one, but the person who makes it live should know).
     *
     * @param  array<string, mixed>  $data
     */
    private function checkBrandingDocument(string $relative, string $name, array $data, PackValidationResult $result): void
    {
        if ($name !== 'branding') {
            return;
        }

        foreach (CssPolicy::problems((string) ($data['css'] ?? '')) as $problem) {
            $result->addError($relative, '/css', $problem);
        }

        $missing = app(ContentImages::class)->missing(Branding::pictures($data));

        foreach ($missing as $picture) {
            $result->addWarning($relative, '', "The picture {$picture} is not in the store of images of this environment: upload it in the panel, or import the bundle that carries it.");
        }
    }

    /**
     * What the schema cannot say about the model: a slot is listed once, a role belongs to one slot, and the game has tires, because the matrix of the tires decides
     * how a car grips on a surface.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkModelDocument(string $relative, string $name, array $data, PackValidationResult $result): void
    {
        if ($name !== 'model') {
            return;
        }

        $keys = [];
        $roles = [];

        foreach ($data['slots'] ?? [] as $index => $slot) {
            if (isset($keys[$slot['key']])) {
                $result->addError($relative, "/slots/{$index}/key", "The slot {$slot['key']} is listed twice.");
            }

            $keys[$slot['key']] = true;

            if (isset($slot['role'])) {
                if (isset($roles[$slot['role']])) {
                    $result->addError($relative, "/slots/{$index}/role", "The role {$slot['role']} is the one of the slot {$roles[$slot['role']]} already: a role belongs to one slot.");
                }

                $roles[$slot['role']] ??= $slot['key'];
            }
        }

        if (! isset($roles['tires'])) {
            $result->addError($relative, '/slots', 'A game needs a slot with the role tires: how a car grips on a surface is decided by its tires.');
        }
    }

    /**
     * A part against the model of the game: its slot is one of the model, the parts of the slot that is the tires have a kind of tires, and the parts of the engine
     * slot an engine; no other part has either. (The schema cannot say it: which slot is which is the model's to say.)
     *
     * @param  array<string, mixed>  $part
     */
    public function checkPartAgainstModel(string $relative, array $part, GameModel $model, PackValidationResult $result): void
    {
        $slot = (string) ($part['slot'] ?? '');

        if (! $model->has($slot)) {
            $result->addError($relative, '/slot', "The slot {$slot} is not one of the slots of the game: ".implode(', ', $model->slotKeys()).'.');

            return;
        }

        foreach (['tires' => 'tire_kind', 'engine' => 'engine'] as $role => $field) {
            $has = array_key_exists($field, $part);

            if ($model->is($slot, $role) && ! $has) {
                $result->addError($relative, '', "A part of the slot {$slot}, which is the {$role} of the game, needs {$field}.");
            } elseif (! $model->is($slot, $role) && $has) {
                $result->addError($relative, "/{$field}", "Only the parts of the slot that has the role {$role} have {$field}: {$slot} is not that slot.");
            }
        }
    }

    /**
     * The model against the rest of the content: every part is in a slot of the model (and has what its role asks), the rules that are by slot (the life, the time of
     * a job, the effects of the legendary parts, the crates, the equipment, the affixes, the shares of the economy) name slots of the model, the life and the time of
     * a job are given for every slot that has parts (a part of one that has none would divide by nothing), the stock tires are the tires of the game, and a slot that the
     * app has no word for is named in the vocabulary.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkModel(ContentSource $source, array $rules, PackValidationResult $result): void
    {
        $model = GameModel::fromSource($source);
        $slots = $model->slotKeys();

        foreach ($source->documents('parts') as $slug => $part) {
            $this->checkPartAgainstModel("parts/{$slug}.json", $part, $model, $result);
        }

        // A stock pair that is not a part of the pack is said by the references already.
        $stock = $source->has('parts/'.$rules['stock_tires']) ? ($source->document('parts/'.$rules['stock_tires'])['slot'] ?? null) : null;

        if ($stock !== null && ! $model->is((string) $stock, 'tires')) {
            $result->addError('rules/starter.json', '/stock_tires', "The stock tires {$rules['stock_tires']} are in the slot {$stock}, which is not the slot with the role tires ({$model->slotOf('tires')}).");
        }

        $byKey = [
            '/wear/life_km' => $rules['wear']['life_km'] ?? [],
            '/wear/rough_ppm' => $rules['wear']['rough_ppm'] ?? [],
            '/affixes/unique' => $rules['affixes']['unique'] ?? [],
            '/workshop/base_time_ms' => $rules['workshop']['base_time_ms'] ?? [],
        ];

        if ($source->has('rules/economy')) {
            $byKey['/anchors/parts/slot_share'] = $source->document('rules/economy')['anchors']['parts']['slot_share'] ?? [];
        }

        foreach ($byKey as $pointer => $map) {
            $file = str_starts_with($pointer, '/anchors') ? 'rules/economy.json' : 'rules/starter.json';

            foreach (array_keys($map) as $slot) {
                if (! in_array($slot, $slots, true)) {
                    $result->addError($file, "{$pointer}/{$slot}", "The slot {$slot} is not one of the slots of the game: ".implode(', ', $slots).'.');
                }
            }
        }

        // A slot that has parts needs the life of its parts and the time of a job (a part of it would divide by nothing); one that has none does not yet, which is what lets a
        // slot be added to the model first and to the rules after (each document is saved on its own, and the rules may only name slots of the model).
        $withParts = array_unique(array_column($source->documents('parts'), 'slot'));

        foreach (['/wear/life_km' => 'life of the parts in km', '/workshop/base_time_ms' => 'time of a job'] as $pointer => $what) {
            foreach (array_intersect(array_diff($slots, array_keys($byKey[$pointer])), $withParts) as $slot) {
                $result->addError('rules/starter.json', $pointer, "The slot {$slot} has parts, but no {$what}: the rules give it for every slot that has parts.");
            }
        }

        $lists = ['/shop/crates/slots' => $rules['shop']['crates']['slots'] ?? []];

        foreach ($rules['affixes']['definitions'] ?? [] as $key => $definition) {
            $lists["/affixes/definitions/{$key}/slots"] = $definition['slots'] ?? [];
        }

        foreach ($rules['workshop']['equipment'] ?? [] as $key => $item) {
            $lists["/workshop/equipment/{$key}/slots"] = $item['slots'] ?? [];
        }

        foreach ($lists as $pointer => $named) {
            foreach ($named as $index => $slot) {
                if (! in_array($slot, $slots, true)) {
                    $result->addError('rules/starter.json', "{$pointer}/{$index}", "The slot {$slot} is not one of the slots of the game: ".implode(', ', $slots).'.');
                }
            }
        }

        $this->warnAboutUnnamedSlots($source, $model, $result);
    }

    /** A slot that neither the app nor the vocabulary of the pack has a name for would be shown as its key in the texts of the game (a hidden one is not shown). */
    private function warnAboutUnnamedSlots(ContentSource $source, GameModel $model, PackValidationResult $result): void
    {
        $catalog = MessageCatalog::fromConfig();

        if (! $catalog->available()) {
            return;
        }

        $named = $source->has('rules/vocabulary') ? array_keys($source->document('rules/vocabulary')['messages'] ?? []) : [];

        foreach ($model->slots() as $index => ['key' => $slot, 'hidden' => $hidden]) {
            // A hidden slot is not shown, so it needs no name.
            if (! $hidden && ! $catalog->has("slot.{$slot}") && ! in_array("slot.{$slot}", $named, true)) {
                $result->addWarning('rules/model.json', "/slots/{$index}/key", "The slot {$slot} has no name: the app knows the names of the slots of the car game only. Add slot.{$slot} to the vocabulary.");
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function checkVehicleRules(string $relative, array $data, PackValidationResult $result): void
    {
        // The dealer level follows the tier: max(1, 3 * tier - 3) (Katalog aut).
        $expected = max(1, 3 * (int) $data['tier'] - 3);

        if ((int) $data['min_player_level'] !== $expected) {
            $result->addError($relative, '/min_player_level', "For tier {$data['tier']} the level must be {$expected}.");
        }
    }
}
