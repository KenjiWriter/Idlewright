<script setup lang="ts">
import { computed } from 'vue'

/**
 * The line of a track as a thumbnail: the points in metres (y up), fitted into a small box, the start marked. A circuit is closed by its own points, a run is open.
 * It has no words of its own: the track beside it says what it is.
 */
const props = defineProps<{ points: [number, number][]; label: string }>()

const WIDTH = 88
const HEIGHT = 60
const PAD = 6

const drawn = computed(() => {
  const xs = props.points.map((point) => point[0])
  const ys = props.points.map((point) => point[1])
  const minX = Math.min(...xs)
  const minY = Math.min(...ys)
  const scale = Math.min((WIDTH - 2 * PAD) / Math.max(1, Math.max(...xs) - minX), (HEIGHT - 2 * PAD) / Math.max(1, Math.max(...ys) - minY))
  // The line is centred in the box.
  const offsetX = (WIDTH - (Math.max(...xs) - minX) * scale) / 2
  const offsetY = (HEIGHT - (Math.max(...ys) - minY) * scale) / 2
  const at = (point: [number, number]): [number, number] => [offsetX + (point[0] - minX) * scale, HEIGHT - offsetY - (point[1] - minY) * scale]
  const line = props.points.map(at)

  return { path: line.map((point) => `${point[0].toFixed(1)},${point[1].toFixed(1)}`).join(' '), start: line[0] ?? [0, 0] }
})
</script>

<template>
  <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" role="img" :aria-label="label" data-test="track-thumb">
    <polyline :points="drawn.path" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" data-test="thumb-line" />
    <circle :cx="drawn.start[0]" :cy="drawn.start[1]" r="3" fill="currentColor" stroke="#ffffff" stroke-width="1.2" data-test="thumb-start" />
  </svg>
</template>
