<?php

declare(strict_types=1);

namespace App\Content\Vocabulary;

use App\Content\ContentSource;
use App\Support\Locale;
use Closure;

/**
 * The vocabulary of a pack (content/rules/vocabulary.json): the messages of the web app and the texts of the server that the pack says in its own words. A pack that
 * has no such document, or an empty one, changes nothing, and the game speaks with its own texts.
 */
final class Vocabulary
{
    /** @param array<string, array<string, string>> $messages key => language => text */
    private function __construct(private readonly string $version, private readonly array $messages) {}

    public static function fromConfig(): self
    {
        return self::fromSource(app(ContentSource::class));
    }

    /**
     * The words of the pack: those of its vocabulary, and the words of its tutorial (its labels, as the messages tutorial.* and story.* of the app), which win
     * over the vocabulary, because the page of the tutorial is where they are set now.
     */
    public static function fromSource(ContentSource $source): self
    {
        $messages = $source->has('rules/vocabulary') ? (array) ($source->document('rules/vocabulary')['messages'] ?? []) : [];

        if ($source->has('rules/tutorial')) {
            $tutorial = $source->document('rules/tutorial');

            foreach (['messenger' => 'tutorial', 'story' => 'story'] as $section => $prefix) {
                foreach ((array) ($tutorial[$section]['labels'] ?? []) as $label => $texts) {
                    $messages["{$prefix}.{$label}"] = (array) $texts;
                }
            }
        }

        if ($messages === [] && ! $source->has('rules/vocabulary')) {
            return new self('', []);
        }

        return new self($source->has('pack') ? (string) ($source->manifest()['version'] ?? '') : '', $messages);
    }

    /** The number of the pack that the words are from: what a copy of them is called. */
    public function number(): string
    {
        return $this->version;
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * What each language of the game says of the web app, key by key: the text of the pack in that language, or its English text when it has none in that language
     * (a French driver of a game about ships does not read 'Tires' beside the 'Hull' of the others). Empty when the pack changes no message of the app.
     *
     * @return array<string, array<string, string>> language => key => text
     */
    public function web(): array
    {
        $byLocale = $this->byLocale(static fn (string $key): bool => ! MessageCatalog::isServer($key));

        return array_filter($byLocale) === [] ? [] : $byLocale;
    }

    /**
     * What the server says in a language in the words of the pack, as the lines the translator takes: `game.errors.no_run` => text. The English text of the pack
     * stands in for a language that has none.
     *
     * @return array<string, string>
     */
    public function serverLines(string $locale): array
    {
        $lines = [];

        foreach ($this->byLocale(static fn (string $key): bool => MessageCatalog::isServer($key))[$locale] ?? [] as $key => $text) {
            $lines[substr($key, strlen(ServerMessages::PREFIX))] = $text;
        }

        return $lines;
    }

    /**
     * @param  Closure(string): bool  $wanted  which messages are asked for, by key
     * @return array<string, array<string, string>> language => key => text
     */
    private function byLocale(Closure $wanted): array
    {
        $byLocale = [];

        foreach (Locale::supported() as $locale) {
            $byLocale[$locale] = [];

            foreach ($this->messages as $key => $texts) {
                $text = $texts[$locale] ?? $texts['en'] ?? null;

                if ($text !== null && $wanted($key)) {
                    $byLocale[$locale][$key] = $text;
                }
            }
        }

        return $byLocale;
    }
}
