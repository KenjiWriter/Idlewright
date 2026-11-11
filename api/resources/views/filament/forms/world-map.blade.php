@php
    use App\Filament\Support\WorldBoard;

    $board = $getBoard();
    $unit = $getUnit();
    $surfaces = $getSurfaces();
    $warnings = $board->warnings($surfaces);
    // The cities and the roads that a warning is about, marked on the map (red where it is an error).
    $marked = ['cities' => [], 'roads' => []];

    foreach ($warnings as $warning) {
        foreach (['cities', 'roads'] as $what) {
            foreach ($warning[$what] as $item) {
                $marked[$what][$item] = ($marked[$what][$item] ?? '') === 'is-error' || $warning['error'] ? 'is-error' : 'is-warn';
            }
        }
    }
    $icons = [
        'home' => 'M3 20V9l9-5 9 5v11M7 20v-7h10v7M7 16h10',
        'flag' => 'M5 21V4M5 4h12l-2 4 2 4H5',
        'lock' => 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3',
    ];
@endphp

{{-- The choice (a city that waits for the second end of a road, or a chosen road) lives here, outside the board, which is drawn anew whenever the world changes. --}}
<div
    class="ri-world"
    wire:key="world-map-panel"
    x-data="{ picked: null, road: null }"
    x-on:world-board-pick.window="picked = $event.detail.slug; road = null"
>
    <style>
        .ri-world { display: grid; gap: .75rem; }
        .ri-world-hint, .ri-world-muted { margin: 0; font-size: .875rem; opacity: .8; }
        .ri-world-board { position: relative; width: 100%; max-width: 39rem; aspect-ratio: 390 / 300; container-type: inline-size; overflow: hidden; border-radius: .75rem; border: 1px solid #2c323d; background: #151920; touch-action: none; user-select: none; }
        .ri-world-board > svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .ri-world-grid { stroke: rgba(255, 255, 255, .045); stroke-width: 1; vector-effect: non-scaling-stroke; }
        .ri-world-safe { fill: none; stroke: rgba(255, 255, 255, .14); stroke-width: 1; stroke-dasharray: 4 4; vector-effect: non-scaling-stroke; }
        .ri-world-bed { stroke: #262c37; stroke-width: 12; stroke-linecap: round; }
        .ri-world-line { stroke: #3b4252; stroke-width: 2; stroke-dasharray: 6 8; stroke-linecap: round; }
        .ri-world-hit { stroke: transparent; stroke-width: 22; pointer-events: stroke; cursor: pointer; }
        .is-chosen .ri-world-bed { stroke: #0a1e25; }
        .is-chosen .ri-world-line { stroke: #42c9e8; stroke-dasharray: none; }
        .ri-world-node { position: absolute; z-index: 2; display: grid; place-items: center; width: calc(44 / 390 * 100%); aspect-ratio: 1; transform: translate(-50%, -50%); border-radius: 9999px; border: 2px solid #2c323d; background: #14181f; color: #eceef2; cursor: grab; touch-action: none; }
        .ri-world-node svg { width: 50%; height: 50%; }
        .ri-world-node.is-lock { opacity: .6; }
        .ri-world-node.is-home { border-color: #ffb224; }
        .ri-world-node.is-picked { box-shadow: 0 0 0 2px #42c9e8; opacity: 1; }
        .ri-world-node.is-dragged { cursor: grabbing; z-index: 3; }
        .ri-world-label { position: absolute; z-index: 2; pointer-events: none; white-space: nowrap; text-align: center; font-size: calc(11 / 390 * 100cqw); font-weight: 600; line-height: 1.2; color: #eceef2; }
        .ri-world-label small { display: block; font-family: ui-monospace, monospace; font-size: calc(9 / 390 * 100cqw); font-weight: 400; color: #939bab; }
        .ri-world-panel { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; font-size: .875rem; }
        .ri-world-panel input { width: 6rem; padding: .25rem .5rem; border-radius: .375rem; border: 1px solid rgba(127, 127, 127, .4); background: transparent; }
        .ri-world-panel button { padding: .25rem .625rem; border-radius: .375rem; border: 1px solid rgba(127, 127, 127, .4); }
        .ri-world-panel .ri-world-danger { color: #ef4444; border-color: rgba(239, 68, 68, .5); }
        .ri-world-node.is-warn { border-color: #ffb224; border-style: dashed; opacity: 1; }
        .ri-world-node.is-error { border-color: #ff7a6b; border-style: dashed; opacity: 1; }
        .is-warn .ri-world-line { stroke: #ffb224; }
        .is-error .ri-world-line { stroke: #ff7a6b; }
        .ri-world-notes { margin: 0; padding: 0; list-style: none; display: grid; gap: .375rem; font-size: .875rem; }
        .ri-world-notes li { padding-left: 1.25rem; position: relative; }
        .ri-world-notes li::before { content: '!'; position: absolute; left: 0; top: 0; width: .9rem; text-align: center; font-weight: 700; color: #d97706; }
        .ri-world-notes li.is-error::before { color: #ef4444; }
    </style>

    <p class="ri-world-hint">{{ __('admin.world.board_hint') }}</p>

    @if ($board->cities === [])
        <p class="ri-world-muted">{{ __('admin.world.board_empty') }}</p>
    @else
        <div
            wire:key="world-map-{{ $board->hash() }}"
            style="display: grid; gap: .75rem;"
            x-data="{
                drag: null,
                {{-- Counts the changes of the size of the board (a tab that is opened, a window that is resized), so that the names are placed again. --}}
                resized: 0,
                at(slug, axis, value) {
                    return this.drag && this.drag.slug === slug && this.drag.moved ? this.drag[axis] : value
                },
                place(slug, x, y) {
                    return { left: (this.at(slug, 'x', x) / {{ WorldBoard::WIDTH }} * 100) + '%', top: ((this.at(slug, 'y', y) - {{ WorldBoard::TOP }}) / {{ WorldBoard::HEIGHT }} * 100) + '%' }
                },
                point(event) {
                    const box = this.$refs.board.getBoundingClientRect()

                    return {
                        x: Math.round(Math.min({{ WorldBoard::WIDTH }}, Math.max(0, (event.clientX - box.left) / box.width * {{ WorldBoard::WIDTH }}))),
                        y: Math.round(Math.min({{ WorldBoard::MAX_Y }}, Math.max({{ WorldBoard::MIN_Y }}, {{ WorldBoard::TOP }} + (event.clientY - box.top) / box.height * {{ WorldBoard::HEIGHT }}))),
                    }
                },
                grab(event, slug, x, y) {
                    this.drag = { slug, x, y, fromX: event.clientX, fromY: event.clientY, moved: false }
                },
                move(event) {
                    if (! this.drag || this.drag.dropped || (! this.drag.moved && Math.hypot(event.clientX - this.drag.fromX, event.clientY - this.drag.fromY) < 5)) {
                        return
                    }

                    const point = this.point(event)
                    this.drag.moved = true
                    this.drag.x = point.x
                    this.drag.y = point.y
                },
                async drop() {
                    const drag = this.drag

                    if (! drag || drag.dropped) {
                        return
                    }

                    {{-- A moved city stays where it was dropped until the server has drawn the map again with its new place. --}}
                    if (drag.moved) {
                        drag.dropped = true

                        try {
                            await this.$wire.moveCity(drag.slug, drag.x, drag.y)
                        } finally {
                            if (this.drag === drag) {
                                this.drag = null
                            }
                        }

                        return
                    }

                    this.drag = null

                    if (this.picked === null || this.picked === drag.slug) {
                        this.picked = this.picked === drag.slug ? null : drag.slug
                        this.road = null

                        return
                    }

                    const from = this.picked
                    this.picked = null
                    this.road = await this.$wire.addRoad(from, drag.slug)
                },
                {{-- Where the name of a city goes, as in the game: under the city, moved in from the side when it would be cut, and above the city at the bottom of the map. --}}
                label(slug, x, y, element) {
                    this.resized
                    const board = this.$refs.board

                    if (! board || ! element || board.clientWidth === 0) {
                        return {}
                    }

                    const width = board.clientWidth
                    const height = board.clientHeight
                    const gap = width * 28 / {{ WorldBoard::WIDTH }}
                    const centre = this.at(slug, 'x', x) / {{ WorldBoard::WIDTH }} * width
                    const middle = (this.at(slug, 'y', y) - {{ WorldBoard::TOP }}) / {{ WorldBoard::HEIGHT }} * height
                    const below = middle + gap

                    return {
                        left: Math.min(Math.max(centre - element.offsetWidth / 2, 2), width - element.offsetWidth - 2) + 'px',
                        top: (below + element.offsetHeight > height - 2 ? middle - gap - element.offsetHeight : below) + 'px',
                    }
                },
            }"
            x-init="new ResizeObserver(() => resized++).observe($refs.board)"
            x-on:pointermove.window="move($event)"
            x-on:pointerup.window="drop()"
        >
            <div class="ri-world-board" x-ref="board" data-test="world-board" x-on:pointerdown.self="picked = null; road = null">
                <svg viewBox="0 {{ WorldBoard::TOP }} {{ WorldBoard::WIDTH }} {{ WorldBoard::HEIGHT }}" preserveAspectRatio="none" aria-hidden="true">
                    @for ($x = 30; $x < WorldBoard::WIDTH; $x += 30)
                        <line class="ri-world-grid" x1="{{ $x }}" y1="{{ WorldBoard::MIN_Y }}" x2="{{ $x }}" y2="{{ WorldBoard::MAX_Y }}" />
                    @endfor
                    @for ($y = WorldBoard::MIN_Y + 30; $y < WorldBoard::MAX_Y; $y += 30)
                        <line class="ri-world-grid" x1="0" y1="{{ $y }}" x2="{{ WorldBoard::WIDTH }}" y2="{{ $y }}" />
                    @endfor
                    <rect class="ri-world-safe" x="{{ WorldBoard::RADIUS }}" y="{{ WorldBoard::MIN_Y + WorldBoard::RADIUS }}" width="{{ WorldBoard::WIDTH - 2 * WorldBoard::RADIUS }}" height="{{ WorldBoard::HEIGHT - 2 * WorldBoard::RADIUS }}" />

                    @foreach ($board->roads as $road)
                        @php
                            $from = $board->cities[$road['from']];
                            $to = $board->cities[$road['to']];
                            $a = \Illuminate\Support\Js::from($from['slug']);
                            $b = \Illuminate\Support\Js::from($to['slug']);
                            $ends = "x-bind:x1=\"at({$a}, 'x', {$from['x']})\" x-bind:y1=\"at({$a}, 'y', {$from['y']})\" x-bind:x2=\"at({$b}, 'x', {$to['x']})\" x-bind:y2=\"at({$b}, 'y', {$to['y']})\"";
                        @endphp
                        <g class="{{ $marked['roads'][$road['key']] ?? '' }}" data-test="world-road" data-road="{{ $road['from'] }}-{{ $road['to'] }}" x-bind:class="{ 'is-chosen': road === @js($road['key']) }">
                            <line class="ri-world-bed" x1="{{ $from['x'] }}" y1="{{ $from['y'] }}" x2="{{ $to['x'] }}" y2="{{ $to['y'] }}" {!! $ends !!} />
                            <line class="ri-world-line" x1="{{ $from['x'] }}" y1="{{ $from['y'] }}" x2="{{ $to['x'] }}" y2="{{ $to['y'] }}" {!! $ends !!} />
                            <line class="ri-world-hit" x1="{{ $from['x'] }}" y1="{{ $from['y'] }}" x2="{{ $to['x'] }}" y2="{{ $to['y'] }}" {!! $ends !!} x-on:pointerdown.stop="road = @js($road['key']); picked = null" />
                        </g>
                    @endforeach
                </svg>

                @foreach ($board->cities as $city)
                    @php
                        $icon = $board->icon($city['slug']);
                        $under = __('admin.world.board_level', ['level' => $city['level'] ?? 1]).($city['surface'] === null ? '' : ' · '.($surfaces[$city['surface']] ?? $city['surface']));
                    @endphp
                    <button
                        type="button"
                        class="ri-world-node is-{{ $icon }} {{ $marked['cities'][$city['slug']] ?? '' }}"
                        style="left: {{ $city['x'] / WorldBoard::WIDTH * 100 }}%; top: {{ ($city['y'] - WorldBoard::TOP) / WorldBoard::HEIGHT * 100 }}%;"
                        x-bind:style="place(@js($city['slug']), {{ $city['x'] }}, {{ $city['y'] }})"
                        x-bind:class="{ 'is-picked': picked === @js($city['slug']), 'is-dragged': drag && drag.slug === @js($city['slug']) && drag.moved }"
                        x-on:pointerdown.prevent.stop="grab($event, @js($city['slug']), {{ $city['x'] }}, {{ $city['y'] }})"
                        aria-label="{{ $city['name'] }}"
                        data-test="world-city-{{ $city['slug'] }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}" /></svg>
                    </button>
                    {{-- The length of the name is in the binding so that a renamed city is placed again. --}}
                    <div class="ri-world-label" data-label="{{ $city['slug'] }}" x-bind:style="label(@js($city['slug']), {{ $city['x'] }}, {{ $city['y'] }}, $el, {{ mb_strlen($city['name'].$under) }})">
                        {{ $city['name'] }}
                        <small>{{ $under }}</small>
                    </div>
                @endforeach
            </div>

            @foreach ($board->cities as $city)
                <div class="ri-world-panel" x-show="picked === @js($city['slug'])" x-cloak data-test="world-picked-{{ $city['slug'] }}">
                    <strong>{{ $city['name'] }}</strong>
                    <label>x <input type="number" min="0" max="{{ WorldBoard::WIDTH }}" step="1" wire:model.blur="data.regions.{{ $city['key'] }}.map.x" /></label>
                    <label>y <input type="number" min="{{ WorldBoard::MIN_Y }}" max="{{ WorldBoard::MAX_Y }}" step="1" wire:model.blur="data.regions.{{ $city['key'] }}.map.y" /></label>
                    <span>{{ __('admin.world.board_pick_second') }}</span>
                    <button type="button" x-on:click="picked = null">{{ __('admin.world.board_cancel') }}</button>
                </div>
            @endforeach

            @foreach ($board->roads as $road)
                <div class="ri-world-panel" x-show="road === @js($road['key'])" x-cloak data-test="world-chosen-{{ $road['from'] }}-{{ $road['to'] }}">
                    <strong>{{ __('admin.world.board_road', ['from' => $board->cities[$road['from']]['name'], 'to' => $board->cities[$road['to']]['name']]) }}</strong>
                    <label>{{ __('admin.world.board_length') }} <input type="number" min="0" step="any" wire:model.blur="data.roads.{{ $road['key'] }}.km" /> {{ $unit }}</label>
                    <button type="button" class="ri-world-danger" x-on:click="road = null; $wire.removeRoad(@js($road['key']))">{{ __('admin.world.board_remove') }}</button>
                </div>
            @endforeach

            <p class="ri-world-muted" x-show="picked === null && road === null">{{ __('admin.world.board_idle') }}</p>

            <div data-test="world-notes">
                <p style="margin: 0 0 .375rem; font-weight: 600; font-size: .875rem;">{{ __('admin.world.warn_title') }}</p>
                @if ($warnings === [])
                    <p class="ri-world-muted">{{ __('admin.world.warn_none') }}</p>
                @else
                    <ul class="ri-world-notes">
                        @foreach ($warnings as $warning)
                            <li class="{{ $warning['error'] ? 'is-error' : '' }}" data-test="world-note-{{ $warning['kind'] }}">{{ __('admin.world.warn.'.$warning['kind'], $warning['params']) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
