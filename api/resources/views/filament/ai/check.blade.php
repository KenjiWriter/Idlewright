@php
    $cell = 'padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25);vertical-align:top;';
    $head = $cell.'text-align:start;font-weight:600;white-space:nowrap;';
@endphp

@if ($result !== null)
    <div style="display:grid;gap:.75rem;margin-top:1rem" data-ai-result>
        <p style="font-weight:600;color:{{ $result['ok'] ? 'rgb(22 163 74)' : 'rgb(220 38 38)' }}" data-ai-verdict="{{ $result['ok'] ? 'ok' : 'refused' }}">
            {{ $result['ok']
                ? __('admin.ai.verdict_ok', ['changes' => count($result['rows']), 'documents' => $result['documents']])
                : __('admin.ai.verdict_refused', ['errors' => $result['errors']]) }}
        </p>

        @if ($result['issues'] !== [])
            <ul style="display:grid;gap:.25rem;list-style:none;padding:0" data-ai-issues>
                @foreach ($result['issues'] as $issue)
                    <li style="font-size:.875rem">
                        <strong style="color:{{ $issue['error'] ? 'rgb(220 38 38)' : 'rgb(202 138 4)' }}">{{ $issue['error'] ? __('admin.ai.error') : __('admin.ai.remark') }}</strong>
                        @if ($issue['op'] !== null) <span>({{ __('admin.ai.op') }} {{ $issue['op'] }}{{ $issue['id'] ? ', '.$issue['id'] : '' }})</span> @endif
                        <span>{{ $issue['label'] }}</span>
                        @if ($issue['where'] !== null) <code style="font-size:.75rem">{{ $issue['where'] }}</code> @endif
                        <div style="color:rgb(113 113 122)">{{ $issue['message'] }}</div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($result['repair'] !== '')
            <div x-data="{ text: @js($result['repair']), shown: false, note: '',
                async copy() { try { await navigator.clipboard.writeText(this.text); this.note = @js(__('admin.ai.copied')); } catch (e) { this.shown = true; this.note = @js(__('admin.ai.select_by_hand')); } } }">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-clipboard-document" x-on:click="copy()">{{ __('admin.ai.copy_errors') }}</x-filament::button>
                <span style="font-size:.875rem" x-text="note"></span>
                <textarea x-show="shown" x-cloak readonly rows="8" x-model="text" style="width:100%;margin-top:.5rem;font-family:monospace;font-size:.75rem" class="rounded-lg border border-gray-300 bg-white p-2 dark:border-white/10 dark:bg-white/5"></textarea>
            </div>
        @endif

        @if ($result['rows'] !== [])
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;width:100%;font-size:.8125rem" data-ai-table>
                    <thead>
                        <tr>
                            <th style="{{ $head }}">{{ __('admin.ai.col_op') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_what') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_live') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_now') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_becomes') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_change') }}</th>
                            <th style="{{ $head }}">{{ __('admin.ai.col_notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr>
                                <td style="{{ $cell }}">{{ $row['op'] }}</td>
                                <td style="{{ $cell }}">
                                    <div>{{ $row['label'] }}</div>
                                    <code style="font-size:.7rem;color:rgb(113 113 122)">{{ $row['document'] }} {{ $row['pointer'] }}</code>
                                    @if ($row['what'] !== null) <div style="font-size:.75rem;color:rgb(113 113 122)">{{ $row['what'] }}</div> @endif
                                </td>
                                <td style="{{ $cell }}">{{ $row['published'] }}</td>
                                <td style="{{ $cell }}">{{ $row['before'] }}</td>
                                <td style="{{ $cell }}font-weight:600">{{ $row['after'] }}</td>
                                <td style="{{ $cell }}white-space:nowrap">{{ $row['percent'] }}</td>
                                <td style="{{ $cell }}">{{ implode(', ', array_map(fn (string $note): string => __('admin.ai.note_'.$note), $row['notes'])) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($result['ok'])
            <div style="display:grid;gap:.5rem">
                @if ($result['warnings'] > 0)
                    <label style="font-size:.875rem"><input type="checkbox" wire:model.live="acknowledged"> {{ __('admin.ai.acknowledge') }}</label>
                @endif
                @if ($result['applied_before'])
                    <label style="font-size:.875rem"><input type="checkbox" wire:model.live="repeat"> {{ __('admin.ai.repeat') }}</label>
                @endif
                <div>
                    <x-filament::button wire:click="apply" :disabled="($result['warnings'] > 0 && ! $acknowledged) || ($result['applied_before'] && ! $repeat)" icon="heroicon-m-pencil-square" data-ai-apply>{{ __('admin.ai.apply') }}</x-filament::button>
                    <span style="font-size:.8125rem;color:rgb(113 113 122)">{{ __('admin.ai.apply_hint') }}</span>
                    <x-filament::button color="gray" wire:click="mountAction('balance')" icon="heroicon-m-chart-bar" data-ai-balance>{{ __('admin.ai.balance') }}</x-filament::button>
                </div>
            </div>
        @endif
    </div>
@endif
