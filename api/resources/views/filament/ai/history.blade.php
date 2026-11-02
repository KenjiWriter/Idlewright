@if ($history !== [])
    <div style="margin-top:1.5rem" data-ai-history>
        <h3 style="font-weight:600;margin-bottom:.5rem">{{ __('admin.ai.history') }}</h3>
        <table style="border-collapse:collapse;width:100%;font-size:.8125rem">
            <tbody>
                @foreach ($history as $entry)
                    <tr>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25);white-space:nowrap">{{ $entry['when'] }}</td>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25)">{{ $entry['who'] }}</td>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25)">{{ $entry['summary'] }} <span style="color:rgb(113 113 122)">({{ $entry['changes'] }})</span></td>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25)">{{ __('admin.ai.status_'.$entry['status']) }}</td>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid rgba(128,128,128,.25)">
                            @if ($entry['status'] === 'applied')
                                <x-filament::button color="gray" size="xs" wire:click="undo({{ $entry['id'] }})" wire:confirm="{{ __('admin.ai.undo_confirm') }}">{{ __('admin.ai.undo') }}</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
