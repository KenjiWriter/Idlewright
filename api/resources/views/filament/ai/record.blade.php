<div
    style="display:grid;gap:.75rem"
    x-data="{ note: '', shown: false,
        async copy() {
            try { await navigator.clipboard.writeText(this.$refs.text.value); this.note = @js(__('admin.ai.copied')); }
            catch (e) { this.shown = true; this.note = @js(__('admin.ai.select_by_hand')); this.$nextTick(() => this.$refs.text.select()); }
        } }"
>
    <p style="font-size:.8125rem;color:rgb(113 113 122)" data-ai-record-size>
        {{ __('admin.ai.size_value', ['tokens' => number_format($tokens, 0, ',', ' '), 'kb' => number_format($bytes / 1024, 0, ',', ' ')]) }}
    </p>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem">
        <x-filament::button x-on:click="copy()" icon="heroicon-m-clipboard-document" data-ai-record-copy>{{ __('admin.ai.copy') }}</x-filament::button>
        <x-filament::button color="gray" x-on:click="shown = ! shown" icon="heroicon-m-eye">{{ __('admin.ai.show') }}</x-filament::button>
        <x-filament::link :href="$bridgeUrl" target="_blank">{{ __('admin.ai.record_open') }}</x-filament::link>
        <span style="font-size:.875rem" x-text="note"></span>
    </div>

    <textarea x-ref="text" x-show="shown" x-cloak readonly rows="14" style="width:100%;font-family:monospace;font-size:.75rem" class="rounded-lg border border-gray-300 bg-white p-2 dark:border-white/10 dark:bg-white/5" data-ai-record-text>{{ $text }}</textarea>
</div>
