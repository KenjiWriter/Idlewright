<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { endSessionIfUnauthorized } from '@/features/session/unauthorized'
import { TARGET_SCREEN } from '@/features/tutorial/targets'
import { useFormat } from '@/i18n/useFormat'
import { useTutorialStore } from '@/stores/tutorial'

/**
 * The goal in front of the driver, at every screen of the game: which step of the tutorial is next, what to do, what it waits for before it starts and what
 * it pays. It is there while the phone is put away, and while a step has not started, so the driver always knows what to do in the game.
 */
const { t } = useI18n()
const { money, number } = useFormat()
const store = useTutorialStore()
const route = useRoute()

const goal = computed(() => (store.showing ? null : store.goal))
const screen = computed(() => (goal.value?.target ? TARGET_SCREEN[goal.value.target] : null))
/** The driver is on the screen already: there is nowhere to lead them. */
const here = computed(() => screen.value === null || String(route.name) === screen.value)

/**
 * The words of the button that leaves the step for later, when it is one that may be left and has started. The phone has the same button, but it is put away once the
 * driver has answered it, and the step goes on waiting for them: without this one an optional step could not be left from then on.
 */
const decline = computed(() => (goal.value?.optional && store.current?.key === goal.value.key ? store.current.decline : null))
const busy = ref(false)

async function leave(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true

  try {
    await store.decline()
  } catch (failure) {
    endSessionIfUnauthorized(failure)
  } finally {
    busy.value = false
  }
}

const reward = computed(() => {
  const paid = goal.value?.reward

  return paid
    ? [
        paid.cash_cents > 0 ? money(paid.cash_cents) : null,
        paid.xp > 0 ? t('tutorial.goalXp', { xp: number(paid.xp) }) : null,
        paid.car_xp > 0 ? t('tutorial.goalCarXp', { xp: number(paid.car_xp) }) : null,
        paid.part,
        paid.garage ? t('tutorial.goalGarage', { city: paid.garage }) : null,
      ].filter((line): line is string => line !== null)
    : []
})
</script>

<template>
  <section v-if="goal" class="goal" :class="{ 'goal-wait': goal.waiting }" :aria-label="t('tutorial.goalAria')" data-test="goal">
    <div class="goal-top">
      <span class="goal-step" data-test="goal-step">{{ t('tutorial.goalStep', { number: goal.number, total: store.tutorial?.total ?? 0 }) }}</span>
      <span v-if="goal.optional" class="goal-opt" data-test="goal-optional">{{ t('tutorial.goalOptional') }}</span>
      <span class="goal-dots" aria-hidden="true">
        <i v-for="phase in 4" :key="phase" :class="{ on: phase <= goal.phase }" />
      </span>
    </div>
    <p class="goal-title" data-test="goal-title">
      <small>{{ goal.waiting ? t('tutorial.goalNext') : t('tutorial.goalLabel') }}</small>
      {{ goal.title }}
    </p>
    <p v-if="goal.waiting" class="goal-note" data-test="goal-waiting">{{ goal.waiting }}</p>
    <div v-if="reward.length > 0 || (screen && !here && !goal.waiting) || decline" class="goal-foot">
      <p v-if="reward.length > 0" class="goal-reward" data-test="goal-reward">
        <span>{{ t('tutorial.goalReward') }}</span>
        <b v-for="line in reward" :key="line" class="chip chip-am !h-6 !px-2 !text-[11.5px]">{{ line }}</b>
      </p>
      <RouterLink v-if="screen && !here && !goal.waiting" :to="{ name: screen }" class="btn btn-secondary btn-sm flex-none" data-test="goal-show">{{ t('tutorial.goalShow') }}</RouterLink>
      <button v-if="decline" type="button" class="btn btn-secondary btn-sm flex-none" :disabled="busy" data-test="goal-decline" @click="leave">{{ decline }}</button>
    </div>
  </section>
</template>
