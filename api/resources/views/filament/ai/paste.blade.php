<div style="display:grid;gap:.75rem">
    <p style="font-size:.875rem">{{ __('admin.ai.answer_hint') }}</p>
    <label for="ai-answer" style="font-weight:600;font-size:.875rem">{{ __('admin.ai.answer_label') }}</label>
    <textarea id="ai-answer" wire:model="answer" rows="10" style="width:100%;font-family:monospace;font-size:.75rem" class="rounded-lg border border-gray-300 bg-white p-2 dark:border-white/10 dark:bg-white/5" data-ai-answer></textarea>
    <div>
        <x-filament::button wire:click="check" icon="heroicon-m-check-circle" data-ai-check>{{ __('admin.ai.check') }}</x-filament::button>
    </div>
</div>

@include('filament.ai.check', ['result' => $result, 'acknowledged' => $acknowledged, 'repeat' => $repeat])
@include('filament.ai.history', ['history' => $history])
