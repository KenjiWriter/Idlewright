@php
    $tokens = $size['tokens'] ?? 0;
    $level = $tokens <= 30000 ? 'ok' : ($tokens <= 80000 ? 'warn' : 'big');
    $color = ['ok' => 'text-success-600 dark:text-success-400', 'warn' => 'text-warning-600 dark:text-warning-400', 'big' => 'text-danger-600 dark:text-danger-400'][$level];
@endphp

<div
    style="margin-top:1rem;display:grid;gap:.75rem"
    x-data="{
        text: '',
        open: false,
        busy: false,
        note: '',
        async make() {
            this.busy = true;
            this.note = @js(__('admin.ai.preparing'));
            try { this.text = await $wire.brief(); this.note = ''; } finally { this.busy = false; }
        },
        async copy() {
            await this.make();
            try { await navigator.clipboard.writeText(this.text); this.note = @js(__('admin.ai.copied')); }
            catch (e) { this.open = true; this.note = @js(__('admin.ai.select_by_hand')); }
        },
        async show() { await this.make(); this.open = true; },
        async download() {
            await this.make();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([this.text], { type: 'text/plain;charset=utf-8' }));
            link.download = 'ride-ai-instruction.txt';
            link.click();
            URL.revokeObjectURL(link.href);
        },
    }"
>
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem" data-ai-size>
        <span class="text-sm font-medium">{{ __('admin.ai.size') }}</span>
        <span class="text-sm {{ $color }}" data-ai-tokens="{{ $tokens }}">
            {{ __('admin.ai.size_value', ['tokens' => number_format($tokens, 0, ',', ' '), 'kb' => number_format(($size['bytes'] ?? 0) / 1024, 0, ',', ' ')]) }}
        </span>
        <span class="text-xs text-gray-500">{{ __('admin.ai.size_'.$level) }}</span>
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem">
        <x-filament::button x-on:click="copy()" x-bind:disabled="busy" icon="heroicon-m-clipboard-document">{{ __('admin.ai.copy') }}</x-filament::button>
        <x-filament::button color="gray" x-on:click="open ? open = false : show()" x-bind:disabled="busy" icon="heroicon-m-eye">
            <span x-text="open ? @js(__('admin.ai.hide')) : @js(__('admin.ai.show'))"></span>
        </x-filament::button>
        <x-filament::button color="gray" x-on:click="download()" x-bind:disabled="busy" icon="heroicon-m-arrow-down-tray">{{ __('admin.ai.download') }}</x-filament::button>
        <span class="text-sm text-gray-500" x-text="note" data-ai-note></span>
    </div>

    <p class="text-xs text-gray-500">{{ __('admin.ai.paste_later') }}</p>

    <textarea x-show="open" x-cloak readonly rows="18" x-model="text" class="w-full rounded-lg border border-gray-300 bg-white p-2 font-mono text-xs dark:border-white/10 dark:bg-white/5" data-ai-text></textarea>
</div>
