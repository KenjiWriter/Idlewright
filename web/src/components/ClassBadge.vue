<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { CarPerformance } from '@/api/types'
import { useRatingClass } from '@/features/car/useRatingClass'

defineProps<{
  /** The PI of a car and its class, which the badge shows as one: "B 612". */
  performance: CarPerformance
}>()

const { t } = useI18n()
const { ratingClassName } = useRatingClass()
</script>

<template>
  <span class="tag flex-none" :data-class="performance.class" :title="t('car.classTitle', { class: ratingClassName(performance.class), pi: performance.pi })" data-test="car-class">{{ ratingClassName(performance.class) }} {{ performance.pi }}</span>
</template>
