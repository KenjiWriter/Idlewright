<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import type { GigBlocked, GigOffer } from '@/api/types'
import CityTabs from '@/components/CityTabs.vue'
import GameShell from '@/components/GameShell.vue'
import { useFlash } from '@/features/run/useFlash'
import { useLeaveOnSignOut } from '@/features/session/leaveOnSignOut'
import { endSessionIfUnauthorized } from '@/features/session/unauthorized'
import { useUnits } from '@/game/useUnits'
import { formatDuration } from '@/i18n/format'
import { useFormat } from '@/i18n/useFormat'
import { useGigsStore } from '@/stores/gigs'

/** The board is asked again at the earliest this long after the last answer, so a wave that is late cannot cause a storm of requests. */
const MIN_ASK_MS = 3_000

const { t, te } = useI18n()
const { money, number } = useFormat()
const { distanceKm, speed } = useUnits()
const store = useGigsStore()
const { message: done, show: showDone, clear: clearDone } = useFlash()

useLeaveOnSignOut()

const loadError = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const now = ref(store.serverNow())
let ticker: ReturnType<typeof setInterval> | undefined
let lastAsk = 0

const board = computed(() => store.board)
const offers = computed(() => board.value?.offers ?? [])
const active = computed(() => board.value?.active ?? null)
const waveLeftMs = computed(() => (board.value ? board.value.wave.ends_ms - now.value : 0))
const activeLeftMs = computed(() => (active.value ? active.value.finish_ms - now.value : 0))
/** Taking a gig back is for the first seconds only. */
const canCancel = computed(() => active.value !== null && now.value <= active.value.cancel_until_ms)

async function load(): Promise<void> {
  lastAsk = Date.now()

  try {
    await store.load()
    loadError.value = false
  } catch (failure) {
    if (!endSessionIfUnauthorized(failure)) {
      loadError.value = !store.loaded
    }
  }
}

function tick(): void {
  now.value = store.serverNow()

  // The server makes a new board when the wave is over, and brings the car back when its time is over; it says so only when it is asked.
  if (store.board && (waveLeftMs.value <= 0 || (active.value !== null && activeLeftMs.value <= 0)) && Date.now() - lastAsk >= MIN_ASK_MS) {
    void load()
  }
}

onMounted(() => {
  void load()
  ticker = setInterval(tick, 1_000)
})

onBeforeUnmount(() => clearInterval(ticker))

/** Makes one call: what the server refuses is shown in its own words, and the board is asked for again when it did. */
async function attempt(action: () => Promise<string | null>): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null
  clearDone()

  try {
    const said = await action()

    if (said !== null) {
      showDone(said)
    }
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

const take = (offer: GigOffer) =>
  attempt(async () => {
    await store.take(offer.key)

    return t('gigs.taken', { title: offer.title })
  })

const cancel = () =>
  attempt(async () => {
    if (active.value) {
      await store.cancel(active.value.id)
    }

    return t('gigs.cancelled')
  })

/** Where the trip goes, in words: a trip in the city or there and back. */
function routeText(item: { region: string; to_name: string | null; km: number }, cityName: string): string {
  return item.to_name === null ? t('gigs.local', { km: distanceKm(item.km) }) : t('gigs.route', { from: cityName, to: item.to_name, km: distanceKm(item.km) })
}

/** What the offer carries: people or kilograms. */
function amountText(offer: Pick<GigOffer, 'capacity' | 'amount'>): string {
  return offer.capacity === 'seats' ? t('gigs.people', { count: offer.amount }, offer.amount) : t('gigs.load', { count: number(offer.amount) })
}

/** Why the car cannot take the offer, in words; the server's own text is not sent with the board, so the code says it. */
function blockedText(blocked: GigBlocked): string {
  const details = blocked.details
  const key = blocked.code === 'gig_requirements' ? String(details.what ?? '') : blocked.code

  return te(`gigs.blocked.${key}`) ? t(`gigs.blocked.${key}`, details) : blocked.code
}

const rarityText = (rarity: string): string | null => (te(`gigs.rarity.${rarity}`) ? t(`gigs.rarity.${rarity}`) : null)
/** The first offer that the car can take: the one the tutorial points at. */
const firstTakeable = computed(() => offers.value.find((offer) => offer.eligible)?.key ?? null)
const phaseText = computed(() => (board.value ? t(`gigs.phase.${board.value.phase}`) : ''))
</script>

<template>
  <GameShell>
    <header class="flex flex-col gap-1">
      <h1 class="ttl">{{ t('gigs.title') }}</h1>
      <p v-if="board && !board.locked" class="sub" data-test="sub">{{ t('gigs.sub', { city: board.city_name, phase: phaseText, time: formatDuration(waveLeftMs) }) }}</p>
    </header>

    <CityTabs />

    <div v-if="loadError" class="flex flex-col items-start gap-3" data-test="load-error">
      <p class="text-danger" role="alert">{{ t('gigs.loadError') }}</p>
      <button type="button" class="btn btn-secondary" data-test="retry" @click="load">{{ t('common.retry') }}</button>
    </div>

    <p v-else-if="!board" class="text-muted" data-test="loading">{{ t('common.loading') }}</p>

    <p v-else-if="board.locked" class="card card-hi text-sm" role="status" data-test="locked">{{ t(`gigs.locked.${board.locked}`) }}</p>

    <template v-else>
      <p v-if="error" class="text-danger" role="alert" data-test="error">{{ error }}</p>
      <p v-if="done" class="card card-cy text-sm" role="status" data-test="done">{{ done }}</p>

      <!-- The gig the car is out on looks like a journey: where it goes, how long until it is back, and what it pays. -->
      <section v-if="active" class="card card-hi flex flex-col gap-2 !p-3" role="status" data-test="active">
        <p class="text-sm">{{ t('gigs.active', { route: routeText(active, board.city_name), time: formatDuration(activeLeftMs), pay: money(active.reward_cents) }) }}</p>
        <button v-if="canCancel" type="button" class="btn btn-secondary btn-sm self-start" :disabled="busy" data-test="cancel" @click="cancel">{{ t('gigs.cancel') }}</button>
      </section>

      <div v-if="board.car" class="flex flex-wrap items-center justify-between gap-2">
        <span class="sub" data-test="car">{{ t('gigs.car', { seats: board.car.seats, cargo: number(board.car.cargo_kg), speed: speed(board.car.speed_kmh) }) }}</span>
        <span v-if="board.limits" class="chip" data-test="limit">{{ t('gigs.limit', { used: board.limits.daily_used, limit: board.limits.daily_limit }) }}</span>
      </div>

      <p v-if="offers.length === 0" class="text-muted" data-test="empty">{{ t('gigs.empty', { time: formatDuration(waveLeftMs) }) }}</p>

      <article v-for="(offer, index) in offers" :key="offer.key" class="card flex flex-col gap-2 !p-3" :class="{ 'opacity-85': !offer.eligible }" :data-test="`offer-${index}`" data-offer>
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="tag flex-none" data-test="kind">{{ offer.kind_name }}</span>
              <span v-if="rarityText(offer.rarity)" class="chip chip-am flex-none" data-test="rarity">{{ rarityText(offer.rarity) }}</span>
            </div>
            <b class="mt-1 block text-sm" data-test="title">{{ offer.title }}</b>
            <p class="sub" data-test="route">{{ routeText(offer, board.city_name) }}</p>
          </div>
          <div class="flex-none text-right">
            <div class="font-display text-[26px] font-bold leading-none text-amber" data-test="pay">{{ money(offer.reward_cents) }}</div>
            <button type="button" class="btn btn-primary btn-sm mt-1" :disabled="busy || !offer.eligible || active !== null" :data-tutorial="offer.key === firstTakeable ? 'gig' : undefined" data-test="take" @click="take(offer)">{{ t('gigs.take') }}</button>
          </div>
        </div>

        <div class="flex flex-wrap gap-1.5">
          <span class="chip" data-test="amount">{{ amountText(offer) }}</span>
          <span class="chip" data-test="duration">{{ t('gigs.duration', { time: formatDuration(offer.duration_ms) }) }}</span>
          <span class="chip" data-test="fuel">{{ t('gigs.fuel', { liters: `${number(offer.fuel_ml / 1000, 1)} l` }) }}</span>
          <span class="chip" data-test="until">{{ t('gigs.until', { time: formatDuration(offer.until_ms - now) }) }}</span>
        </div>

        <p v-if="offer.blocked" class="text-xs text-danger" data-test="blocked">{{ blockedText(offer.blocked) }}</p>
      </article>
    </template>
  </GameShell>
</template>
