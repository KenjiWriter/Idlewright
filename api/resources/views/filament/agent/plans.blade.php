@php
    /**
     * @var \Illuminate\Support\Collection<int, \App\Models\AgentPlan> $plans
     * @var \App\Models\AgentPlan|null $open
     * @var array<string, mixed>|null $status
     */
    $box = 'border: 1px solid rgba(128, 128, 128, .3); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px;';
    $muted = 'font-size: 13px; opacity: .7;';
    $cell = 'padding: 6px 10px; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, .2); font-size: .875rem; vertical-align: top;';
    $red = 'background: rgba(220, 38, 38, .15);';
    $stateColor = fn (string $state): string => match ($state) { 'approved' => 'success', 'rejected' => 'danger', 'submitted' => 'warning', default => 'gray' };
    $ok = fn (?bool $value): string => $value === null ? '-' : ($value ? __('admin.plans.met') : __('admin.plans.not_met'));
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;" data-test="agent-plans" @if ($open?->measure_status === 'measuring') wire:poll.5s @endif>
        @if ($open === null)
            <div style="{{ $box }}">
                <div style="{{ $muted }}">{{ __('admin.plans.intro') }}</div>
                @if ($plans->isEmpty())
                    <div style="{{ $muted }}">{{ __('admin.plans.none') }}</div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    @foreach (['number', 'title', 'version', 'state', 'measure', 'on_behalf_of', 'submitted_at'] as $column)
                                        <th style="{{ $cell }}">{{ __('admin.plans.columns.'.$column) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plans as $plan)
                                    <tr data-test="agent-plan" data-plan="{{ $plan->id }}">
                                        <td style="{{ $cell }}">{{ $plan->id }}</td>
                                        <td style="{{ $cell }}"><a href="#" wire:click.prevent="open({{ $plan->id }})" style="text-decoration: underline;">{{ $plan->title }}</a></td>
                                        <td style="{{ $cell }}">{{ $plan->version }}</td>
                                        <td style="{{ $cell }}"><x-filament::badge size="sm" :color="$stateColor($plan->status)">{{ __('admin.plans.states.'.$plan->status) }}</x-filament::badge></td>
                                        <td style="{{ $cell }}">{{ __('admin.plans.measures.'.$plan->measure_status) }}</td>
                                        <td style="{{ $cell }}">{{ $plan->admin?->email }}</td>
                                        <td style="{{ $cell }}">{{ $plan->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            <div style="{{ $box }}" data-test="agent-plan-head">
                <div><a href="#" wire:click.prevent="$set('plan', null)" style="text-decoration: underline;">{{ __('admin.plans.back') }}</a></div>
                <strong style="font-size: 1.1rem;">{{ __('admin.plans.heading', ['number' => $open->id, 'version' => $open->version]) }}: {{ $open->title }}</strong>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <x-filament::badge :color="$stateColor($status['state'] === 'approval_expired' ? 'superseded' : $open->status)">{{ __('admin.plans.states.'.$status['state']) }}</x-filament::badge>
                    @if ($status['outdated'])
                        <x-filament::badge color="danger" data-test="agent-plan-outdated">{{ __('admin.plans.outdated') }}</x-filament::badge>
                    @endif
                    <span style="{{ $muted }}">{{ __('admin.plans.by', ['client' => $open->client_name, 'email' => $open->admin?->email ?? '-', 'when' => $open->created_at?->format('Y-m-d H:i')]) }}</span>
                    @if ($open->approved_until !== null && $open->status === 'approved')
                        <span style="{{ $muted }}">{{ __('admin.plans.approved_until', ['who' => $open->decider?->email ?? '-', 'until' => $open->approved_until->format('Y-m-d H:i')]) }}</span>
                    @endif
                    @if ($status['replaced_by'] !== null)
                        <a href="#" wire:click.prevent="open({{ $status['replaced_by'] }})" style="text-decoration: underline;">{{ __('admin.plans.replaced_by', ['number' => $status['replaced_by']]) }}</a>
                    @endif
                    @if ($open->previous_id !== null)
                        <a href="#" wire:click.prevent="open({{ $open->previous_id }})" style="text-decoration: underline;">{{ __('admin.plans.previous', ['number' => $open->previous_id]) }}</a>
                    @endif
                </div>
                @if ($status['outdated'])
                    <div style="{{ $muted }}">{{ __('admin.plans.outdated_help') }}</div>
                @endif
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    @if ($open->status === 'submitted')
                        <x-filament::button color="success" wire:click="mountAction('approve')" data-test="agent-plan-approve">{{ __('admin.plans.approve') }}</x-filament::button>
                    @endif
                    @if (in_array($open->status, ['submitted', 'approved'], true))
                        <x-filament::button color="danger" wire:click="mountAction('reject')" data-test="agent-plan-reject">{{ __('admin.plans.reject') }}</x-filament::button>
                    @endif
                    @if ($open->status !== 'superseded')
                        <x-filament::button color="gray" wire:click="mountAction('note')" data-test="agent-plan-note">{{ __('admin.plans.note') }}</x-filament::button>
                    @endif
                </div>
                <div style="{{ $muted }}">{{ __('admin.plans.approve_hint', ['hours' => \App\Agent\AgentPlans::APPROVAL_HOURS]) }}</div>
            </div>

            <div style="{{ $box }}">
                <strong>{{ __('admin.plans.sections.goal') }}</strong>
                <div style="white-space: pre-line;">{{ $open->goal }}</div>
            </div>

            <div style="{{ $box }}" data-test="agent-plan-targets">
                <strong>{{ __('admin.plans.sections.targets') }}</strong>
                @if ($open->measure_status === 'measuring')
                    <div style="{{ $muted }}">{{ __('admin.plans.measuring') }}</div>
                @elseif ($open->measure_status === 'failed')
                    <div style="color: rgb(220, 38, 38);">{{ __('admin.plans.measure_failed') }} {{ $open->measure_error }}</div>
                @else
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                @foreach (['target', 'live', 'plan'] as $column)
                                    <th style="{{ $cell }}">{{ __('admin.plans.columns.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($open->measured['targets'] ?? [] as $row)
                                <tr style="{{ $row['plan_ok'] ? '' : $red }}">
                                    <td style="{{ $cell }}">{{ $row['target'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['live'] ?? '-' }} ({{ $ok($row['live_ok']) }})</td>
                                    <td style="{{ $cell }}"><strong>{{ $row['plan'] }}</strong> ({{ $ok($row['plan_ok']) }})</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @foreach (['assumptions', 'strategy'] as $section)
                @if (filled($open->{$section}))
                    <div style="{{ $box }}">
                        <strong>{{ __('admin.plans.sections.'.$section) }}</strong>
                        <div style="white-space: pre-line;">{{ $open->{$section} }}</div>
                    </div>
                @endif
            @endforeach

            @if (! empty($open->alternatives))
                <div style="{{ $box }}">
                    <strong>{{ __('admin.plans.sections.alternatives') }}</strong>
                    <ul style="margin: 0; padding-left: 20px; list-style: disc;">
                        @foreach ($open->alternatives as $alternative)
                            <li><strong>{{ $alternative['option'] ?? '' }}</strong>: {{ $alternative['why'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="{{ $box }}" data-test="agent-plan-changes">
                <strong>{{ __('admin.plans.sections.changes', ['count' => count($open->changes)]) }}</strong>
                <div style="{{ $muted }}">{{ __('admin.plans.levers', ['levers' => implode(', ', $open->levers)]) }}</div>
                @if (! empty($open->outside_levers))
                    <div style="color: rgb(220, 38, 38);" data-test="agent-plan-outside">{{ __('admin.plans.outside_levers', ['where' => implode(', ', $open->outside_levers)]) }}</div>
                @endif
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                @foreach (['where', 'what', 'published', 'before', 'after'] as $column)
                                    <th style="{{ $cell }}">{{ __('admin.plans.columns.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($open->changes as $change)
                                <tr style="{{ in_array(trim($change['document'].' '.$change['pointer']), $open->outside_levers ?? [], true) ? $red : '' }}">
                                    <td style="{{ $cell }}"><code>{{ $change['document'] }} {{ $change['pointer'] }}</code></td>
                                    <td style="{{ $cell }}">{{ $change['what'] }}</td>
                                    <td style="{{ $cell }}">{{ $change['published'] }}</td>
                                    <td style="{{ $cell }}">{{ $change['before'] }}</td>
                                    <td style="{{ $cell }}"><strong>{{ $change['after'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($open->measure_status === 'ready')
                <div style="{{ $box }}" data-test="agent-plan-gates">
                    <strong>{{ __('admin.plans.sections.gates') }}</strong>
                    <ul style="margin: 0; padding-left: 20px; list-style: disc;">
                        @foreach ($open->measured['gates'] ?? [] as $gate)
                            <li style="{{ $gate['passed'] ? '' : 'color: rgb(220, 38, 38);' }}"><code>{{ $gate['name'] }}</code>: {{ $gate['passed'] ? __('admin.plans.passes') : __('admin.plans.fails') }}
                                @if (! $gate['passed'] && filled($gate['excerpt']))
                                    <pre style="white-space: pre-wrap; font-size: .75rem;">{{ $gate['excerpt'] }}</pre>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div style="{{ $box }}" data-test="agent-plan-pace">
                    <strong>{{ __('admin.plans.sections.pace') }}</strong>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                @foreach (['player', 'car', 'target_day', 'live_day', 'plan_day'] as $column)
                                    <th style="{{ $cell }}">{{ __('admin.plans.columns.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($open->measured['pace'] ?? [] as $row)
                                <tr style="{{ $row['plan_ok'] ? '' : $red }}">
                                    <td style="{{ $cell }}">{{ $row['player'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['car'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['target_day'] }}</td>
                                    <td style="{{ $cell }}">{{ $row['live_day'] ?? '-' }}</td>
                                    <td style="{{ $cell }}"><strong>{{ $row['plan_day'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @foreach (['risks', 'unverified'] as $section)
                @if (filled($open->{$section}))
                    <div style="{{ $box }}">
                        <strong>{{ __('admin.plans.sections.'.$section) }}</strong>
                        <div style="white-space: pre-line;">{{ $open->{$section} }}</div>
                    </div>
                @endif
            @endforeach

            @if (filled($open->note) || filled($open->reject_reason))
                <div style="{{ $box }}" data-test="agent-plan-decision">
                    @if (filled($open->reject_reason))
                        <div><strong>{{ __('admin.plans.sections.reject_reason') }}</strong> ({{ $open->decider?->email }}): {{ $open->reject_reason }}</div>
                    @endif
                    @if (filled($open->note))
                        <div><strong>{{ __('admin.plans.sections.note') }}</strong> ({{ $open->noter?->email }}, {{ $open->noted_at?->format('Y-m-d H:i') }}): {{ $open->note }}</div>
                    @endif
                </div>
            @endif

            <div style="{{ $muted }}">{{ __('admin.plans.hash', ['hash' => substr($open->content_hash, 0, 16), 'snapshot' => $open->drafts_snapshot]) }}</div>
        @endif
    </div>
</x-filament-panels::page>
