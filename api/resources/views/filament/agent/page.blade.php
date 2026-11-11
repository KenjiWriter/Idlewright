@php
    /**
     * @var bool $enabled
     * @var string|null $address
     * @var \Illuminate\Support\Collection<int, \App\Models\AgentGrant> $grants
     * @var \Illuminate\Support\Collection<int, \App\Models\AgentAction> $trail
     */
    $box = 'border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;';
    $muted = 'font-size: 13px; opacity: .7;';
    $cell = 'padding: 6px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); font-size: .875rem; vertical-align: top;';
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;" data-test="agent-page">
        <div style="{{ $box }}">
            <div style="{{ $muted }}">{{ __('admin.agent.page.intro') }}</div>
            @unless ($enabled)
                <x-filament::badge color="warning" data-test="agent-disabled">{{ __('admin.agent.page.disabled') }}</x-filament::badge>
            @endunless
        </div>

        @if ($enabled)
            <div style="{{ $box }}" data-test="agent-connect">
                <strong>{{ __('admin.agent.page.connect_title') }}</strong>
                <ol style="margin: 0; padding-left: 20px; list-style: decimal; display: flex; flex-direction: column; gap: 4px;">
                    <li>{{ __('admin.agent.page.connect_step_1') }}</li>
                    <li>{{ __('admin.agent.page.connect_step_2') }}</li>
                    <li>{{ __('admin.agent.page.connect_step_3') }}</li>
                </ol>
                <div>{{ __('admin.agent.page.address') }}: <code style="word-break: break-all;" data-test="agent-address">{{ $address }}</code></div>
                <div style="{{ $muted }}">{{ __('admin.agent.page.claude_code') }}</div>
                <code style="word-break: break-all; font-size: .8rem;" data-test="agent-claude-code">claude mcp add --transport http idlewright {{ $address }}</code>
                <div style="{{ $muted }}">{{ __('admin.agent.page.outside_repo') }}</div>
            </div>

            <div style="{{ $box }}"
                 x-data="{
                     text: '',
                     open: false,
                     note: '',
                     async copy() {
                         this.text = await $wire.starter();
                         try { await navigator.clipboard.writeText(this.text); this.note = @js(__('admin.agent.page.copied')); }
                         catch (e) { this.open = true; this.note = @js(__('admin.agent.page.select_by_hand')); }
                     },
                 }"
                 data-test="agent-starter">
                <strong>{{ __('admin.agent.page.starter_title') }}</strong>
                {{ $this->form }}
                <div style="display: flex; align-items: center; gap: 12px;">
                    <x-filament::button size="sm" x-on:click="copy()" data-test="agent-copy">{{ __('admin.agent.page.copy') }}</x-filament::button>
                    <span style="{{ $muted }}" x-text="note"></span>
                </div>
                <textarea x-show="open" x-model="text" readonly rows="5" style="width: 100%; font-family: monospace; font-size: .8rem;" data-test="agent-starter-text"></textarea>
            </div>
        @endif

        <div style="{{ $box }}" data-test="agent-grants">
            <strong>{{ __('admin.agent.page.connections') }}</strong>
            @if ($grants->isEmpty())
                <div style="{{ $muted }}">{{ __('admin.agent.page.none') }}</div>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.who') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.client') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.level') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.until') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.last_used') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.calls') }}</th>
                                <th style="{{ $cell }}"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grants as $grant)
                                <tr data-test="agent-grant" data-grant="{{ $grant->id }}">
                                    <td style="{{ $cell }}">{{ $grant->admin?->email }}</td>
                                    <td style="{{ $cell }}">{{ $grant->client_name }}</td>
                                    <td style="{{ $cell }}">{{ $grant->level->value }}. {{ __('admin.agent.levels.'.$grant->level->value.'.label') }}</td>
                                    <td style="{{ $cell }}">{{ $grant->expires_at->format('Y-m-d H:i') }}</td>
                                    <td style="{{ $cell }}">{{ $grant->last_used_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td style="{{ $cell }}">{{ $grant->actions_count }}</td>
                                    <td style="{{ $cell }}">
                                        <x-filament::button size="xs" color="danger" wire:click="disconnect({{ $grant->id }})" wire:confirm="{{ __('admin.agent.page.disconnect_confirm') }}" data-test="agent-disconnect">{{ __('admin.agent.page.disconnect') }}</x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div style="{{ $box }}" data-test="agent-trail">
            <strong>{{ __('admin.agent.page.trail') }}</strong>
            @if ($trail->isEmpty())
                <div style="{{ $muted }}">{{ __('admin.agent.page.trail_none') }}</div>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.when') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.tool') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.what') }}</th>
                                <th style="{{ $cell }}">{{ __('admin.agent.page.ms') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trail as $action)
                                <tr data-test="agent-action">
                                    <td style="{{ $cell }}">{{ $action->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td style="{{ $cell }}"><code>{{ $action->tool }}</code></td>
                                    <td style="{{ $cell }}">
                                        @unless ($action->ok)
                                            <x-filament::badge size="sm" color="danger">{{ __('admin.agent.page.failed') }}</x-filament::badge>
                                        @endunless
                                        {{ $action->summary }}
                                    </td>
                                    <td style="{{ $cell }}">{{ $action->ms }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
