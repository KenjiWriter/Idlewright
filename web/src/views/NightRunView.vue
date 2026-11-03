<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import GameShell from '@/components/GameShell.vue'
import { useLeaveOnSignOut } from '@/features/session/leaveOnSignOut'
import { endSessionIfUnauthorized } from '@/features/session/unauthorized'
import { useUnits } from '@/game/useUnits'
import { formatDuration } from '@/i18n/format'
import { useFormat } from '@/i18n/useFormat'
import { useGigsStore } from '@/stores/gigs'

const { t, te } = useI18n()
const { money, number, percent } = useFormat()
const { distanceKm } = useUnits()
const router = useRouter()
const store = useGigsStore()

useLeaveOnSignOut()

const loadError = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const now = ref(store.serverNow())
let ticker: ReturnType<typeof setInterval> | undefined

const board = computed(() => store.board)
const opportunity = computed(() => board.value?.opportunity ?? null)
const night = computed(() => opportunity.value?.night ?? null)
const odds = computed(() => night.value?.odds ?? null)
const leftMs = computed(() => (opportunity.value ? opportunity.value.until_ms - now.value : 0))

async function load(): Promise<void> {
  try {
    await store.load()
    // The clock of the server is known now: the countdowns do not start from the clock of this device.
    now.value = store.serverNow()
    loadError.value = false
  } catch (failure) {
    if (!endSessionIfUnauthorized(failure)) {
      loadError.value = !store.loaded
    }
  }
}

onMounted(() => {
  void load()
  ticker = setInterval(() => {
    now.value = store.serverNow()
  }, 1_000)
})

onBeforeUnmount(() => clearInterval(ticker))

/** Takes the risk: the car leaves, and the board of the gigs shows it on the road. What the server refuses is shown in its own words. */
async function take(): Promise<void> {
  if (busy.value || !opportunity.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    await store.take(opportunity.value.key)
    await router.push({ name: 'gigs' })
  } catch (failure) {
    if (endSessionIfUnauthorized(failure)) {
      return
    }

    error.value = failure instanceof ApiError && failure.status === 429 ? t('common.tooManyRequests') : failure instanceof ApiError && failure.status === 422 ? failure.message : t('common.error')

    if (failure instanceof ApiError && failure.status === 422) {
      void load()
    }
  } finally {
    busy.value = false
  }
}

const skip = () => router.push({ name: 'gigs' })

/** A part per million as a percent of the form `12,8%`. */
const pct = (ppm: number): string => `${percent(ppm / 10_000)}%`

/** The words for the thing that stands in the way of the offer: the server's own text is not sent with the board, so the code says it. */
const blockedText = computed(() => {
  const blocked = opportunity.value?.blocked

  if (!blocked) {
    return null
  }

  const key = blocked.code === 'gig_requirements' ? String(blocked.details.what ?? '') : blocked.code

  return te(`gigs.blocked.${key}`) ? t(`gigs.blocked.${key}`, blocked.details) : blocked.code
})

const route = computed(() => {
  const offer = opportunity.value

  return offer && board.value ? t('gigs.route', { from: board.value.city_name, to: offer.to_name ?? '', km: distanceKm(offer.km) }) : ''
})
</script>

<template>
  <GameShell>
    <p v-if="loadError" class="text-danger" role="alert" data-test="load-error">{{ t('gigs.loadError') }}</p>

    <p v-else-if="!board" class="text-muted" data-test="loading">{{ t('common.loading') }}</p>

    <template v-else-if="!opportunity || !night">
      <header class="flex flex-col gap-1">
        <h1 class="ttl">{{ t('gigs.night.title') }}</h1>
      </header>
      <p class="card card-hi text-sm" role="status" data-test="none">{{ t('gigs.night.none') }}</p>
      <button type="button" class="btn btn-secondary self-start" data-test="back" @click="skip">{{ t('gigs.night.back') }}</button>
    </template>

    <template v-else>
      <div class="flex items-center justify-between gap-2.5">
        <span class="chip chip-cy" data-test="chip">{{ t('gigs.night.chip') }}</span>
        <span class="font-mono text-[13px] text-ink" data-test="dawn">{{ t('gigs.night.untilDawn', { time: formatDuration(leftMs) }) }}</span>
      </div>

      <header class="flex flex-col gap-1">
        <h1 class="ttl" data-test="title">{{ opportunity.kind_name }}</h1>
        <p class="sub" data-test="route">{{ opportunity.title }} · {{ route }}</p>
      </header>

      <section class="card card-hi flex items-center justify-between gap-3 !p-3" data-test="pay">
        <div>
          <div class="kick mb-0.5">{{ t('gigs.night.pay') }}</div>
          <div class="font-display text-[36px] font-bold leading-none text-amber" data-test="reward">{{ money(opportunity.reward_cents) }}</div>
        </div>
        <div class="text-right text-xs leading-snug text-muted">
          {{ t('gigs.night.multiplier', { factor: number(night.multiplier_ppm / 1_000_000, 1) }) }}<br />
          {{ t('gigs.night.plain', { pay: money(night.plain_reward_cents) }) }}<br />
          {{ t('gigs.night.load', { kg: number(opportunity.amount), time: formatDuration(opportunity.duration_ms) }) }}
        </div>
      </section>

      <section v-if="odds" class="card flex flex-col !p-3" data-test="risk">
        <div class="kick mb-1.5">{{ t('gigs.night.risk') }}</div>
        <div class="flex justify-between py-[3px] text-[13px] text-muted">
          <span>{{ t('gigs.night.patrols') }}</span>
          <b class="font-mono text-[13px]" data-test="patrols">{{ pct(odds.patrol_ppm) }}</b>
        </div>
        <div v-if="odds.class_ppm !== 0" class="flex justify-between py-[3px] text-[13px] text-muted" data-test="class-row">
          <span>{{ odds.class_ppm < 0 ? t('gigs.night.classLow') : t('gigs.night.classHigh') }}</span>
          <b class="font-mono text-[13px]" :class="odds.class_ppm < 0 ? 'text-ok' : 'text-danger'">{{ odds.class_ppm < 0 ? '−' : '+' }}{{ number(Math.abs(odds.class_ppm) / 10_000, 0) }} {{ t('gigs.night.points') }}</b>
        </div>
        <div v-if="odds.heat_ppm > 0" class="flex justify-between py-[3px] text-[13px] text-muted" data-test="heat-row">
          <span>{{ t('gigs.night.heat') }}</span>
          <b class="font-mono text-[13px] text-danger">+{{ number(odds.heat_ppm / 10_000, 0) }} {{ t('gigs.night.points') }}</b>
        </div>
        <div class="mt-[3px] flex justify-between border-t border-line py-[3px] text-sm text-ink">
          <span>{{ t('gigs.night.control') }}</span>
          <b class="font-mono text-sm text-amber" data-test="control">{{ pct(odds.control_ppm) }}</b>
        </div>
        <div class="flex justify-between py-[3px] text-[13px] text-muted">
          <span>{{ t('gigs.night.escape') }}</span>
          <b class="font-mono text-[13px]" data-test="escape-share">{{ pct(odds.escape_share_ppm) }}</b>
        </div>
      </section>

      <div v-if="odds" class="lb" data-test="ends">
        <div class="lbr !min-h-[38px]" style="grid-template-columns: 104px 52px 1fr" data-test="end-clean">
          <b class="!font-body !text-[13px] !text-ink">{{ t('gigs.night.clean') }}</b>
          <span class="tm !text-left">{{ pct(odds.clean_ppm) }}</span>
          <span>{{ t('gigs.night.cleanText') }}</span>
        </div>
        <div class="lbr !min-h-[38px]" style="grid-template-columns: 104px 52px 1fr" data-test="end-escaped">
          <b class="!font-body !text-[13px] !text-ink">{{ t('gigs.night.escaped') }}</b>
          <span class="tm !text-left">{{ pct(odds.escaped_ppm) }}</span>
          <span>{{ t('gigs.night.escapedText', { percent: number(100 - night.escape_pay_ppm / 10_000, 0) }) }}</span>
        </div>
        <div class="lbr !min-h-[38px]" style="grid-template-columns: 104px 52px 1fr" data-test="end-arrested">
          <b class="!font-body !text-[13px] !text-danger">{{ t('gigs.night.arrested') }}</b>
          <span class="tm !text-left">{{ pct(odds.arrested_ppm) }}</span>
          <span>{{ t('gigs.night.arrestedText', { fine: money(night.fine_cents), minutes: number(night.arrest_ms / 60_000, 0) }) }}</span>
        </div>
      </div>

      <p v-if="board.car && odds" class="text-xs text-muted" data-test="vehicle">{{ t('gigs.night.vehicle', { kg: number(board.car.cargo_kg), noise: t(`gigs.night.noise.${odds.noise}`) }) }}</p>
      <p class="sub leading-snug">{{ t('gigs.night.note', { bail: money(night.bail_cents) }) }}</p>

      <p v-if="blockedText" class="text-xs text-danger" data-test="blocked">{{ blockedText }}</p>
      <p v-if="error" class="text-danger" role="alert" data-test="error">{{ error }}</p>

      <div class="grid gap-2" style="grid-template-columns: 1.4fr 1fr">
        <button type="button" class="btn btn-primary" :disabled="busy || !opportunity.eligible" data-test="take" @click="take">{{ t('gigs.night.take') }}</button>
        <button type="button" class="btn btn-secondary" data-test="skip" @click="skip">{{ t('gigs.night.skip') }}</button>
      </div>
    </template>
  </GameShell>
</template>
