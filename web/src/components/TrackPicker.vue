<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import type { MapTrack, MapTrackThread } from '@/api/types'
import { characterShare, fitOf } from '@/features/run/trackCharacter'
import { useFlash } from '@/features/run/useFlash'
import { endSessionIfUnauthorized } from '@/features/session/unauthorized'
import { useUnits } from '@/game/useUnits'
import { formatDuration, formatLapTime } from '@/i18n/format'
import { useFormat } from '@/i18n/useFormat'
import { useMapStore } from '@/stores/map'
import { useRunStore } from '@/stores/run'

import TrackThumb from './TrackThumb.vue'

/**
 * The tracks of the city where the car stands, for the car that is played: what a lap takes, pays and burns on each, how long a tank lasts, and what an hour
 * earns after the fuel. A sprint burns little an hour and lets the car drive long unattended, an endurance track burns a tank in minutes and pays by the lap;
 * which is better depends on the car and on how long the driver is away, so the driver chooses. Nothing is shown for a city that has one track.
 *
 * A track that has a thread shows its line, one sentence on what it asks (corners, power, the limit), and what the car does better or worse than the car the track
 * is made for: the lap time on some tracks is set by hand, and then the sentence says it is about the shape of the track.
 */
const { t } = useI18n()
const { money, number } = useFormat()
const { distance, speed, fuelEconomy } = useUnits()
const map = useMapStore()
const run = useRunStore()

const busy = ref<string | null>(null)
const { message: done, show: showDone, clear: clearDone } = useFlash()
const error = ref<string | null>(null)

onMounted(() => {
  map.load().catch(() => undefined)
})

const tracks = computed<MapTrack[]>(() => map.map?.cities.find((city) => city.here)?.tracks ?? [])
/** A car on the road is between two cities: there is no track to choose. */
const shown = computed(() => run.run !== null && run.run.stop_reason !== 'travel' && tracks.value.length > 1)

/** What the car averages on a lap, in km/h: the length of the lap (metres) over the time of the lap (ms). */
const meanSpeedKmh = (lengthM: number, lapMs: number): number => (lapMs > 0 ? (lengthM * 3.6) / (lapMs / 1000) : 0)

/** The sentence that tells a track by the share of its lap that gives it its character. */
function characterText(thread: MapTrackThread): string | null {
  return thread.character === null ? null : t(`trackPicker.thread.character.${thread.character}`, { percent: characterShare(thread) ?? 0 })
}

/** What the car does well and badly on the track, as the strip shows it (null when there is no car to compare). */
function fit(track: MapTrack) {
  return track.thread ? fitOf(track.thread, track.base_lap_ms) : null
}

/** Seconds with a sign: the minus is the typographic one, and time gained is below zero. */
function signedSeconds(seconds: number): string {
  return t('trackPicker.thread.seconds', { value: `${seconds < 0 ? '−' : '+'}${number(Math.abs(seconds), 1)}` })
}

function disabled(track: MapTrack): boolean {
  return busy.value !== null || !track.open || (track.car !== null && !track.car.fits_tank)
}

async function choose(track: MapTrack): Promise<void> {
  if (busy.value !== null) {
    return
  }

  busy.value = track.slug
  clearDone()
  error.value = null

  try {
    await run.changeTrack(track.slug)
    await map.load()
    showDone(t('trackPicker.done', { name: track.name }))
  } catch (failure) {
    if (endSessionIfUnauthorized(failure)) {
      return
    }

    error.value = failure instanceof ApiError ? failure.message : t('trackPicker.error')
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <section v-if="shown" class="flex flex-col gap-2.5" aria-labelledby="tracks-title" data-test="track-picker">
    <div class="flex flex-col gap-1">
      <h2 id="tracks-title" class="kick !mb-0">{{ t('trackPicker.title') }}</h2>
      <p class="sub" data-test="track-note">{{ t('trackPicker.note') }}</p>
    </div>

    <p v-if="done" class="text-ok" role="status" data-test="track-done">{{ done }}</p>
    <p v-if="error" class="text-danger" role="alert" data-test="track-error">{{ error }}</p>

    <ul class="flex flex-col gap-2.5">
      <li v-for="track in tracks" :key="track.slug" class="card flex flex-col gap-2 !p-3" :class="{ 'card-hi': track.current }" :data-test="`track-${track.slug}`">
        <div class="flex items-start justify-between gap-2">
          <p class="min-w-0 font-display text-base font-bold text-ink" :data-test="`track-name-${track.slug}`">{{ track.name }}</p>
          <div class="flex flex-none gap-1">
            <span class="chip !h-6 !px-2" :data-test="`track-kind-${track.slug}`">{{ t(`trackPicker.kind.${track.kind}`) }}</span>
            <span v-if="track.current" class="chip chip-am !h-6 !px-2" :data-test="`track-current-${track.slug}`">{{ t('trackPicker.current') }}</span>
          </div>
        </div>

        <div v-if="track.thread" class="flex items-center gap-3" :data-test="`track-thread-${track.slug}`">
          <TrackThumb :points="track.thread.points" :label="t('trackPicker.thread.line', { name: track.name })" class="h-[60px] w-[88px] flex-none text-amber" />
          <p v-if="characterText(track.thread)" class="sub" data-test="character">{{ characterText(track.thread) }}</p>
        </div>

        <dl v-if="track.car" class="grid grid-cols-2 gap-x-3 gap-y-1 text-[13px]" :data-test="`track-numbers-${track.slug}`">
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.lap') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="lap">{{ formatLapTime(track.car.lap_ms) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.pays') }}</dt>
            <dd class="mono whitespace-nowrap text-amber" data-test="pays">{{ money(track.car.cash_cents, 2) }}</dd>
          </div>
          <template v-if="track.length_m > 0">
            <div class="flex justify-between gap-2">
              <dt class="text-muted">{{ t('trackPicker.length') }}</dt>
              <dd class="mono whitespace-nowrap text-ink" data-test="length">{{ distance('lap', track.length_m) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-muted">{{ t('trackPicker.speed') }}</dt>
              <dd class="mono whitespace-nowrap text-ink" data-test="speed">{{ speed(meanSpeedKmh(track.length_m, track.car.lap_ms)) }}</dd>
            </div>
          </template>
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.fuelLap') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="fuel">{{ t('car.liters', { value: number(track.car.fuel_ml / 1000, 1) }) }}</dd>
          </div>
          <div v-if="track.length_m > 0" class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.economy') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="economy">{{ fuelEconomy(track.car.fuel_ml, track.length_m) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.tank') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="tank">{{ formatDuration(track.car.tank_ms) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.lapsPerTank') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="laps">{{ number(track.car.laps_per_tank) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.net') }}</dt>
            <dd class="mono whitespace-nowrap text-amber" data-test="net">{{ money(track.car.net_cents_per_hour) }}</dd>
          </div>
          <div v-if="track.car.position !== null && track.car.field_size !== null" class="flex justify-between gap-2">
            <dt class="text-muted">{{ t('trackPicker.position') }}</dt>
            <dd class="mono whitespace-nowrap text-ink" data-test="position">{{ t('trackPicker.positionValue', { position: track.car.position, size: track.car.field_size }) }}</dd>
          </div>
        </dl>

        <div v-if="track.car && fit(track)" class="flex flex-col gap-1.5" :data-test="`track-fit-${track.slug}`">
          <p class="kick !mb-0">{{ t('trackPicker.thread.fitTitle') }}</p>
          <p class="text-[13px] text-ink" data-test="fit-verdict">
            <template v-if="fit(track)!.gain || fit(track)!.loss">
              <span v-if="fit(track)!.gain" class="text-ok">{{ t(`trackPicker.thread.gain.${fit(track)!.gain}`) }}</span>
              <span v-if="fit(track)!.gain && fit(track)!.loss">{{ ' ' }}</span>
              <span v-if="fit(track)!.loss" class="text-danger">{{ t(`trackPicker.thread.loss.${fit(track)!.loss}`) }}</span>
            </template>
            <template v-else>{{ t('trackPicker.thread.even') }}</template>
          </p>
          <ul v-if="fit(track)!.rows.length" class="flex flex-wrap gap-1.5">
            <li v-for="row in fit(track)!.rows" :key="row.group" class="chip !h-6 !px-2" :data-test="`fit-${row.group}`">
              {{ t(`trackPicker.thread.group.${row.group}`) }}
              <b class="mono" :class="row.seconds < 0 ? 'text-ok' : 'text-danger'">{{ signedSeconds(row.seconds) }}</b>
            </li>
          </ul>
          <p v-if="track.thread!.lap_from === 'stats' && fit(track)!.rows.length" class="sub" data-test="fit-by-hand">{{ t('trackPicker.thread.byHand') }}</p>
        </div>

        <p v-if="!track.open" class="sub text-amber" data-test="locked">{{ t('trackPicker.locked', { level: track.level_required }) }}</p>
        <p v-else-if="track.car && !track.car.fits_tank" class="sub text-danger" data-test="too-small">{{ t('trackPicker.tooSmall') }}</p>

        <button v-if="!track.current" type="button" class="btn btn-secondary" :disabled="disabled(track)" data-test="choose" @click="choose(track)">
          {{ t('trackPicker.choose') }}
        </button>
      </li>
    </ul>
  </section>
</template>
