<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Filament\Support\WorldBoard;
use Closure;
use Filament\Schemas\Components\Component;

/**
 * The map of the world on the world page, drawn as the game draws it: a city is moved by dragging it, two cities are joined by a road by clicking one and then the
 * other, and a road is chosen by clicking it (to change its length or take it away). What it does goes through the methods of the page (`moveCity`, `addRoad`,
 * `removeRoad`), so that the lists of the cities and the roads and the map are always the same state.
 */
class WorldMap extends Component
{
    protected string $view = 'filament.forms.world-map';

    protected ?Closure $board = null;

    /** The short name of the unit the roads are written in (km, nm, mi). */
    protected string|Closure $unit = 'km';

    /** @var array<string, string>|Closure the name of each surface, as the map writes it under a city */
    protected array|Closure $surfaces = [];

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    public function board(Closure $board): static
    {
        $this->board = $board;

        return $this;
    }

    public function getBoard(): WorldBoard
    {
        return $this->evaluate($this->board);
    }

    public function unit(string|Closure $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnit(): string
    {
        return (string) $this->evaluate($this->unit);
    }

    /** @param  array<string, string>|Closure  $surfaces */
    public function surfaces(array|Closure $surfaces): static
    {
        $this->surfaces = $surfaces;

        return $this;
    }

    /** @return array<string, string> */
    public function getSurfaces(): array
    {
        return (array) $this->evaluate($this->surfaces);
    }
}
