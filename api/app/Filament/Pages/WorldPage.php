<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Content\Definitions\WorldForm;
use App\Content\Units\Measures;
use App\Filament\Forms\WorldFields;
use App\Filament\Support\WorldBoard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/** The cities (and where they are on the map), the roads between them, and the tracks: the part of the rules that makes the world. */
class WorldPage extends RulesDocumentPage
{
    protected static ?string $slug = 'world';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('admin.world.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.world.title');
    }

    protected function documentSlug(): string
    {
        return 'starter';
    }

    protected function fields(array $document): array
    {
        return WorldFields::schema(array_map('strval', array_keys($document['weather'] ?? [])), $document);
    }

    protected function toState(array $document): array
    {
        return WorldForm::toState($document);
    }

    protected function toDocument(array $document, array $state): array
    {
        return WorldForm::apply($document, $state);
    }

    /** Puts a city at a place of the map (what the map does when a city is dragged); a place off the map is brought to its edge. */
    public function moveCity(string $slug, int $x, int $y): void
    {
        foreach ((array) ($this->data['regions'] ?? []) as $key => $region) {
            if (is_string($region['slug'] ?? null) && trim($region['slug']) === $slug) {
                $this->data['regions'][$key]['map'] = WorldBoard::clamp($x, $y);

                return;
            }
        }
    }

    /**
     * Joins two cities with a road (what the map does when a city and then another are clicked), with the kilometres that the scale of the map gives it, written in the
     * unit of the game. Two cities that a road already joins keep their one road; a city is not joined to itself.
     *
     * @return string|null the key of the road in the list of the roads (the new one, or the one that was there), null when there is none to make
     */
    public function addRoad(string $from, string $to): ?string
    {
        $measures = $this->measures();
        $board = WorldBoard::of((array) $this->data, $measures);

        if ($from === $to || ! isset($board->cities[$from], $board->cities[$to])) {
            return null;
        }

        $existing = $board->road($from, $to);

        if ($existing !== null) {
            return $existing['key'];
        }

        $key = (string) Str::uuid();
        $this->data['roads'][$key] = ['from' => $from, 'to' => $to, 'km' => $measures->travel->inBox($board->suggestedKm($from, $to) * 1000)];

        return $key;
    }

    /** Takes a road off the list of the roads (what the map does with "Usuń drogę"). */
    public function removeRoad(string $key): void
    {
        unset($this->data['roads'][$key]);
    }

    /** The units of the game, by the rules that are being worked on. */
    private function measures(): Measures
    {
        return Measures::fromRules($this->definition()?->workingDocument() ?? []);
    }
}
