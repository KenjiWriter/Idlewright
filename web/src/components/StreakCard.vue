<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { StreakReward } from '@/api/types'
import { useMissionText } from '@/features/missions/useMissionText'
import { useMissionsStore } from '@/stores/missions'

/**
 * The streak of days: how many days in a row the driver did the goal of the day (a number of the missions of the daily set), the shields that save a missed day, a
 * week of rewards with the day the driver is at, and the button that takes what today pays. Everything is the server's; the card only shows it.
 */
defineProps<{ busy?: boolean }>()

defineEmits<{ claim: [] }>()

const { t } = useI18n()
const { rewards } = useMissionText()
const missions = useMissionsStore()

const streak = computed(() => missions.streak)

/** Whether today counted: its cell and the ones before it in the cycle are done. */
const counted = computed(() => streak.value?.goal.reached === true)

/** The cell of the cycle that is today's (when it counted), or the one the driver is working for. */
const focus = computed(() => {
  if (streak.value === null) {
    return 0
  }

  return counted.value ? streak.value.cycle_day : streak.value.next.cycle_day
})

function lines(reward: StreakReward | null | undefined): string[] {
  return reward == null ? [] : rewards({ cash_cents: reward.cash_cents, xp: reward.xp, car_xp: 0, items: reward.items })
}

function state(day: number): 'done' | 'today' | 'later' {
  if (streak.value === null) {
    return 'later'
  }

  if (day === focus.value) {
    return counted.value ? 'done' : 'today'
  }

  // The days of the cycle before the one the driver is at are the days of the streak that were done; with no streak yet none is.
  return day < focus.value && (counted.value || streak.value.current > 0) ? 'done' : 'later'
}

const goalPercent = computed(() => (streak.value === null || streak.value.goal.needed <= 0 ? 0 : Math.min(100, Math.round((streak.value.goal.done / streak.value.goal.needed) * 100))))
</script>

<template>
  <section v-if="streak" class="card flex flex-col gap-2.5" :class="{ 'card-hi': streak.today?.state === 'ready' }" :aria-label="t('missions.streak.title')" data-test="streak">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="kick !mb-0">{{ t('missions.streak.title') }}</p>
        <h2 class="text-lg font-bold leading-tight" data-test="streak-days">{{ t('missions.streak.days', { count: streak.current }, streak.current) }}</h2>
      </div>
      <div class="flex flex-wrap justify-end gap-1.5">
        <span class="chip !h-6 !px-2" :title="t('missions.streak.shieldHint', { days: streak.shield_every_days })" data-test="streak-shields">{{ t('missions.streak.shields', { count: streak.shields, cap: streak.shield_cap }) }}</span>
        <span v-if="streak.best > streak.current" class="chip !h-6 !px-2" data-test="streak-best">{{ t('missions.streak.best', { count: streak.best }) }}</span>
      </div>
    </div>

    <p v-if="streak.lost" class="text-[12.5px] text-muted" data-test="streak-lost">{{ t('missions.streak.lost', { count: streak.lost.streak }, streak.lost.streak) }}</p>

    <div class="flex items-center gap-2">
      <div class="bar bar-cy flex-1" role="progressbar" :aria-label="t('missions.streak.goal', { done: streak.goal.done, needed: streak.goal.needed })" :aria-valuenow="goalPercent" aria-valuemin="0" aria-valuemax="100">
        <i :style="{ width: `${goalPercent}%` }" />
      </div>
      <span class="mono whitespace-nowrap text-[12px] text-muted" data-test="streak-goal">{{ streak.goal.reached ? t('missions.streak.goalReached') : t('missions.streak.goal', { done: streak.goal.done, needed: streak.goal.needed }) }}</span>
    </div>

    <ol class="grid grid-cols-7 gap-1" :aria-label="t('missions.streak.cycle')" data-test="streak-cycle">
      <li
        v-for="cell in streak.cycle"
        :key="cell.day"
        class="flex min-h-[44px] flex-col items-center justify-center rounded-md border px-0.5 py-1 text-center"
        :class="state(cell.day) === 'done' ? 'border-[var(--color-amber)] bg-[color-mix(in_srgb,var(--color-amber)_14%,transparent)]' : state(cell.day) === 'today' ? 'border-[var(--color-cyan)]' : 'border-[var(--color-line)] opacity-70'"
        :data-state="state(cell.day)"
        :data-test="`streak-cell-${cell.day}`"
      >
        <span class="mono text-[11px] font-bold">{{ cell.day }}</span>
        <span v-if="cell.items.length > 0" class="text-[10px] leading-none" :title="t('missions.item.crate.' + (cell.items[0].type === 'crate' ? cell.items[0].kind : 'slot'))" aria-hidden="true">▣</span>
      </li>
    </ol>

    <div v-if="streak.today?.state === 'ready'" class="flex flex-wrap items-center justify-between gap-2" data-test="streak-ready">
      <ul class="flex flex-wrap gap-1.5" :aria-label="t('missions.reward.label')">
        <li v-for="line in lines(streak.today.reward)" :key="line" class="chip chip-am !h-6 !px-2">{{ line }}</li>
      </ul>
      <button type="button" class="btn btn-primary" :disabled="busy" data-test="streak-claim" @click="$emit('claim')">{{ t('missions.streak.claim') }}</button>
    </div>
    <p v-else-if="streak.today?.state === 'claimed'" class="flex items-center gap-2 text-[12.5px] text-muted" data-test="streak-claimed">
      <span class="tag tag-ok">{{ t('missions.claimed') }}</span>
      {{ t('missions.streak.tomorrow', { reward: lines(streak.next).join(', ') }) }}
    </p>
    <p v-else class="text-[12.5px] text-muted" data-test="streak-next">{{ t('missions.streak.next', { reward: lines(streak.next).join(', ') }) }}</p>

    <p v-if="streak.milestone" class="text-[12px] text-muted" data-test="streak-milestone">{{ t('missions.streak.milestone', { day: streak.milestone.day }) }}</p>
  </section>
</template>
