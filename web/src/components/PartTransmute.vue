<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api, ApiError } from '@/api/client'
import type { OwnedPart, TransmuteOffer, TransmutePreview, TransmuteResponse } from '@/api/types'
import PartAffixes from '@/components/PartAffixes.vue'
import PartStats from '@/components/PartStats.vue'
import RarityMark from '@/components/RarityMark.vue'
import { newRequestId } from '@/features/run/requestId'
import { useFormat } from '@/i18n/useFormat'

/**
 * Transmutation of the rarity of a part: the part is made one rarity better for cash and premium coins, at once. The server knows what the part will be before it is paid (the
 * affixes come from a seed that is made of the part and the rarity), so the card shows it: the result is certain. The server does it, once for a request id; the screen asks what it
 * would be when the card opens, and says what it did.
 */
const props = defineProps<{ part: OwnedPart; offer: TransmuteOffer; disabled: boolean }>()

const emit = defineEmits<{ done: [part: OwnedPart]; close: [] }>()

const { t } = useI18n()
const { money, number } = useFormat()

const preview = ref<TransmutePreview['data'] | null>(null)
const loading = ref(true)
const sending = ref(false)
const error = ref<string | null>(null)
/** One intention has one request id, so a request that is sent again charges once. */
let requestId = newRequestId()

function failureText(failure: unknown): string {
  return failure instanceof ApiError && failure.status === 422 ? failure.message : t('common.error')
}

onMounted(async () => {
  try {
    preview.value = (await api<TransmutePreview>(`/v1/parts/${props.part.id}/transmute`)).data
  } catch (failure) {
    error.value = failureText(failure)
  } finally {
    loading.value = false
  }
})

async function confirm(): Promise<void> {
  if (sending.value || props.disabled || props.offer.blocked !== null) {
    return
  }

  sending.value = true
  error.value = null

  try {
    const done = await api<TransmuteResponse>(`/v1/parts/${props.part.id}/transmute`, { method: 'POST', body: JSON.stringify({ request_id: requestId }) })

    requestId = newRequestId()
    emit('done', done.data)
  } catch (failure) {
    error.value = failureText(failure)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="flex w-full basis-full flex-col gap-2 border-t border-line pt-2" :data-test="`transmute-${part.id}`">
    <p class="text-[13px] font-semibold text-ink">{{ t('workshop.transmute.title') }}</p>

    <div class="flex flex-wrap items-center gap-2" data-test="transmute-steps">
      <RarityMark :rarity="part.rarity" />
      <span class="mono text-muted" aria-hidden="true">→</span>
      <RarityMark :rarity="offer.to" />
      <span class="tag tag-ok" data-test="transmute-sure">{{ t('workshop.transmute.sure') }}</span>
    </div>

    <ul class="flex flex-wrap gap-1.5" :aria-label="t('workshop.transmute.cost')" data-test="transmute-cost">
      <li v-if="offer.coins > 0" class="chip chip-cy chip-gem !h-6 !px-2" data-test="transmute-coins">{{ number(offer.coins) }}</li>
      <li class="chip chip-am !h-6 !px-2" data-test="transmute-cash">{{ money(offer.cash_cents, 2) }}</li>
    </ul>

    <p v-if="loading" class="sub" role="status">{{ t('common.loading') }}</p>

    <!-- What the part will be: the same card as an owned part has, with the rarity and the affixes it will get. -->
    <div v-else-if="preview" class="card flex flex-col gap-1.5 !p-2.5" data-test="transmute-after">
      <p class="kick !mb-0">{{ t('workshop.transmute.after') }}</p>
      <PartStats :part="preview.after" />
      <PartAffixes :part="preview.after" />
    </div>

    <p class="sub" data-test="transmute-note">{{ t('workshop.transmute.note') }}</p>
    <p v-if="offer.blocked" class="sub text-danger" data-test="transmute-blocked">{{ t(`workshop.transmute.blocked.${offer.blocked}`) }}</p>
    <p v-if="error" class="text-danger text-[13px]" role="alert" data-test="transmute-error">{{ error }}</p>

    <div class="flex gap-2">
      <button type="button" class="btn btn-primary btn-sm" :disabled="disabled || sending || loading || offer.blocked !== null" data-test="transmute-confirm" @click="confirm">
        {{ t('workshop.transmute.confirm') }}
      </button>
      <button type="button" class="btn btn-secondary btn-sm" data-test="transmute-close" @click="emit('close')">{{ t('workshop.transmute.close') }}</button>
    </div>
  </div>
</template>
