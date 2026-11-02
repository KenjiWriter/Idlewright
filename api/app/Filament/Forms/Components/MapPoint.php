<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Where a city is on the map: the board of the game (390 units wide, and the 300 from the 60th that the map screen shows) drawn with the other cities and the roads,
 * and a point that is put by a click or dragged, next to the two numbers it stands for. The state is `{x, y}`.
 */
class MapPoint extends Field
{
    public const WIDTH = 390;

    public const TOP = 60;

    public const HEIGHT = 300;

    /** The map screen shows the strip of the board from the 60th to the 360th unit, and the schema lets a city be in it and nowhere else: a city outside would be valid and not seen. */
    public const MIN_Y = self::TOP;

    public const MAX_Y = self::TOP + self::HEIGHT;

    protected string $view = 'filament.forms.map-point';

    /** @var array{cities?: array<string, array{x: int, y: int, name: string}>, roads?: list<array{from: string, to: string}>, own?: string|null}|Closure */
    protected array|Closure $context = [];

    /**
     * What is drawn on the board besides the point: the other cities by slug, the roads between them, and the slug of the city that the point is (whose roads
     * follow the point).
     *
     * @param  array{cities?: array<string, array{x: int, y: int, name: string}>, roads?: list<array{from: string, to: string}>, own?: string|null}|Closure  $context
     */
    public function context(array|Closure $context): static
    {
        $this->context = $context;

        return $this;
    }

    /** @return array{cities?: array<string, array{x: int, y: int, name: string}>, roads?: list<array{from: string, to: string}>, own?: string|null} */
    public function getContext(): array
    {
        return $this->evaluate($this->context);
    }
}
