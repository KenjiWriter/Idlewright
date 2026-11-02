<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import type { RunProfile } from '@/api/types'
import { trackSpot } from '@/features/run/trackPosition'

interface Rival {
  name: string
  /** How far through its lap the rival is, 0 up to but not including 1: asked for at every frame. */
  fraction: () => number
}

const props = defineProps<{
  profile: RunProfile
  /** How far through its lap the car of the driver is, 0 up to but not including 1: asked for at every frame, so the car moves with the clock and not with the polling. */
  player: () => number
  rivals?: Rival[]
}>()

const { t } = useI18n()

const WIDTH = 360
const PAD = 18
/** The slowest of the line is drawn warm and the fastest green: this is the hue of each. */
const SLOW_HUE = 8
const FAST_HUE = 150

const frame = ref(0)
let handle: number | undefined
let timer: ReturnType<typeof setInterval> | undefined

/** A person who asked the system for less motion gets the car moved once a second, not sixty times. */
function prefersLessMotion(): boolean {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function loop(): void {
  frame.value++
  handle = requestAnimationFrame(loop)
}

onMounted(() => {
  if (prefersLessMotion() || typeof requestAnimationFrame !== 'function') {
    timer = setInterval(() => frame.value++, 1_000)
  } else {
    handle = requestAnimationFrame(loop)
  }
})

onBeforeUnmount(() => {
  if (handle !== undefined) {
    cancelAnimationFrame(handle)
  }

  clearInterval(timer)
})

const bounds = computed(() => {
  const xs = props.profile.points.map((point) => point[0])
  const ys = props.profile.points.map((point) => point[1])

  return { minX: Math.min(...xs), maxX: Math.max(...xs), minY: Math.min(...ys), maxY: Math.max(...ys) }
})

const scale = computed(() => (WIDTH - 2 * PAD) / Math.max(1, bounds.value.maxX - bounds.value.minX, bounds.value.maxY - bounds.value.minY))
const height = computed(() => Math.round((bounds.value.maxY - bounds.value.minY) * scale.value + 2 * PAD))

function px(x: number): number {
  return PAD + (x - bounds.value.minX) * scale.value
}

function py(y: number): number {
  return PAD + (bounds.value.maxY - y) * scale.value
}

/** The line in short pieces, each as warm or as green as the car is slow or fast there. */
const pieces = computed(() => {
  const { points, speed_kmh: speeds } = props.profile
  const top = Math.max(1, ...speeds)
  const low = Math.min(...speeds)
  const span = Math.max(1, top - low)

  return points.slice(1).map((point, index) => {
    const before = points[index]!
    const share = (((speeds[index]! + speeds[index + 1]!) / 2) - low) / span

    return { x1: px(before[0]), y1: py(before[1]), x2: px(point[0]), y2: py(point[1]), colour: `hsl(${SLOW_HUE + (FAST_HUE - SLOW_HUE) * share} 70% 52%)` }
  })
})

const spot = computed(() => {
  void frame.value

  return trackSpot(props.profile, props.player())
})

const rivalSpots = computed(() => {
  void frame.value

  return (props.rivals ?? []).map((rival) => {
    const at = trackSpot(props.profile, rival.fraction())

    return { name: rival.name, x: px(at.x), y: py(at.y) }
  })
})

const section = computed(() => props.profile.sections[spot.value.section] ?? null)

const sectionText = computed(() => {
  if (spot.value.returning) {
    return t('race.track.returning')
  }

  if (!section.value) {
    return ''
  }

  return section.value.type === 'corner'
    ? t('race.track.corner', { radius: Math.round(section.value.radius_m ?? 0), angle: Math.round(section.value.angle_deg ?? 0) })
    : t('race.track.straight', { length: Math.round(section.value.to_m - section.value.from_m) })
})
</script>

<template>
  <div class="flex flex-col gap-1.5" data-test="track-map">
    <svg :viewBox="`0 0 ${WIDTH} ${height}`" class="w-full" role="img" :aria-label="t('race.track.label')">
      <line v-for="(piece, index) in pieces" :key="index" :x1="piece.x1" :y1="piece.y1" :x2="piece.x2" :y2="piece.y2" :stroke="piece.colour" stroke-width="4" stroke-linecap="round" />
      <circle v-for="rival in rivalSpots" :key="rival.name" :cx="rival.x" :cy="rival.y" r="3.5" fill="#9ca3af" opacity="0.85" data-test="track-rival">
        <title>{{ rival.name }}</title>
      </circle>
      <circle :cx="px(spot.x)" :cy="py(spot.y)" r="6" fill="#22d3ee" stroke="#ffffff" stroke-width="2" data-test="track-car" />
    </svg>
    <div class="flex justify-between gap-2 text-[13px]">
      <span class="text-muted" data-test="track-section">{{ sectionText }}</span>
      <b class="mono text-ink" data-test="track-speed">{{ t('race.track.speed', { speed: Math.round(spot.speedKmh) }) }}</b>
    </div>
  </div>
</template>
