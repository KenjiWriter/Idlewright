<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Definitions\JsonDocument;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Takes the block of changes out of what a person pasted (the whole answer of the AI, or the block alone) and checks it against its schema. What is pasted is not to be
 * trusted: it is read as data and nothing more (no code is run, nothing is fetched), it has a size and a depth it may not go over, and only one block is taken.
 */
final class ChangeBlockParser
{
    /** The longest text that is read, in bytes. */
    public const MAX_BYTES = 262_144;

    public const MAX_DEPTH = 16;

    /** The formats that are understood. */
    public const FORMATS = ['ride-changes/1'];

    private const SCHEMA_BASE = 'https://rideidle.local/schema/';

    private readonly Validator $validator;

    public function __construct(?string $schemaDirectory = null)
    {
        $this->validator = new Validator;
        $this->validator->resolver()?->registerPrefix(self::SCHEMA_BASE, rtrim($schemaDirectory ?? (string) config('ride.content_path').'/schema', '/\\'));
    }

    /**
     * @return array{block: array<string, mixed>|null, issues: list<BridgeIssue>}
     */
    public function parse(string $text): array
    {
        if (strlen($text) > self::MAX_BYTES) {
            return $this->refused('too_large', 'The text is longer than '.(self::MAX_BYTES / 1024).' KB, which is more than a block of changes needs.');
        }

        $json = $this->extract($text, $issue);

        if ($json === null) {
            return ['block' => null, 'issues' => [$issue]];
        }

        try {
            $object = json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return $this->refused('bad_json', 'The block is not valid JSON: '.$exception->getMessage().'.');
        }

        // The block as arrays, except an empty object, which stays an object (a car that is made with `{}` in a field has to keep it).
        $block = JsonDocument::arrays($object);

        if (! $object instanceof stdClass || ! is_array($block)) {
            return $this->refused('bad_block', 'The block has to be one JSON object.');
        }

        $format = $object->format ?? null;

        if (is_string($format) && str_starts_with($format, 'ride-changes/') && ! in_array($format, self::FORMATS, true)) {
            return $this->refused('unknown_format', "The format {$format} is not understood. Use ".implode(' or ', self::FORMATS).', as the instruction says.');
        }

        $outcome = $this->validator->validate($object, self::SCHEMA_BASE.'changes.schema.json');

        if (! $outcome->isValid()) {
            $issues = [];

            foreach ((new ErrorFormatter)->formatKeyed($outcome->error()) as $pointer => $messages) {
                foreach ($messages as $message) {
                    $issues[] = BridgeIssue::error('schema', (string) $message, where: $pointer === '' ? 'the block' : 'block '.$pointer);
                }
            }

            return ['block' => null, 'issues' => $issues];
        }

        return ['block' => $block, 'issues' => []];
    }

    /**
     * The text of the one block in what was pasted: a fenced block that is called `ride-changes` or that holds the format, or the whole text when it is JSON.
     */
    private function extract(string $text, ?BridgeIssue &$issue): ?string
    {
        preg_match_all('/^[ \t]*(`{3,}|~{3,})[ \t]*([^\n`]*)\n(.*?)\n[ \t]*\1[ \t]*$/ms', $text, $fences, PREG_SET_ORDER);

        $blocks = [];

        foreach ($fences as $fence) {
            if (preg_match('/^ride-changes\b/i', trim($fence[2])) === 1 || str_contains($fence[3], '"ride-changes/')) {
                $blocks[] = $fence[3];
            }
        }

        if (count($blocks) > 1) {
            $issue = BridgeIssue::error('bad_block', 'There is more than one block of changes. Send exactly one.');

            return null;
        }

        if (count($blocks) === 1) {
            return $blocks[0];
        }

        $trimmed = trim($text);

        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $trimmed;
        }

        $issue = BridgeIssue::error('bad_block', 'There is no block of changes. It is a fenced code block called ride-changes, with the JSON of the format in it.');

        return null;
    }

    /** @return array{block: null, issues: list<BridgeIssue>} */
    private function refused(string $code, string $message): array
    {
        return ['block' => null, 'issues' => [BridgeIssue::error($code, $message)]];
    }
}
