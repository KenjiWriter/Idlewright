<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import type { OwnedPart, PartBonus, TuneOption } from '@/api/types'
import { formatDuration } from '@/i18n/format'
import { useFormat } from '@/i18n/useFormat'

const props = defineProps<{
  /** The part whose places for bonuses are shown; the garage says what it holds (`tuning`) and what a try would be (`tune_next`). */
  part: OwnedPart
  /** The places (from 1) where a try waits for the mechanic: the server takes no second one there. */
  waiting: number[]
  /** A request is on its way, or the part waits in another job. */
  disabled: boolean
}>()

const emit = defineEmits<{ tune: [place: number, key: string | null] }>()

const { t } = useI18n()
const { money, percent } = useFormat()

/** The places of the part, as many as it has: what each holds, or null for an empty one. */
const places = computed<(PartBonus | null)[]>(() => Array.from({ length: props.part.tuning_slots ?? 0 }, (_, index) => props.part.tuning?.[index] ?? null))
const next = computed(() => props.part.tune_next ?? null)
/** The driver can try (the level is there and the model has a price), which is what the editor needs. */
const open = ref(false)
/** The place whose list of bonuses to pick from is open. */
const choosing = ref<number | null>(null)

const canTune = computed(() => next.value !== null && next.value.locked === null)
/** The part has bonuses worth showing, or a try is possible. */
const visible = computed(() => places.value.some((place) => place !== null) || (next.value !== null && next.value.locked?.reason !== 'unavailable'))

/** What a bonus says, in words: "Grip on gravel +6.2%", "Fuel −5%", the way the affixes of a part are told. */
function words(bonus: PartBonus): string {
  return t(`part.affix.${bonus.effect}`, {
    name: bonus.name,
    percent: percent(bonus.percent),
    surfaces: (bonus.context.surfaces ?? []).map((surface) => t(`part.surface.${surface}`)).join(', '),
  })
}

/** The bonuses that can be put at a place: the ones the garage listed, without those the part has already (the one a place holds is "try again", not a pick). */
const options = computed<TuneOption[]>(() => {
  const held = new Set(places.value.flatMap((bonus) => (bonus ? [bonus.key] : [])))

  return (next.value?.bonuses ?? []).filter((option) => !held.has(option.key))
})

function pick(place: number, key: string): void {
  choosing.value = null
  emit('tune', place, key)
}

function waits(place: number): boolean {
  return props.waiting.includes(place)
}
</script>

<template>
  <div v-if="visible" class="flex w-full basis-full flex-col gap-1.5 border-t border-line pt-2" :data-test="`bonuses-${part.id}`">
    <div class="flex items-center justify-between gap-2">
      <p class="kick !mb-0 !text-[10px]">{{ t('part.bonus.title') }}</p>
      <button v-if="next && next.locked?.reason !== 'unavailable'" type="button" class="btn btn-secondary btn-sm" :aria-expanded="open" :data-test="`bonus-open-${part.id}`" @click="open = !open">
        {{ open ? t('workshop.bonus.close') : t('workshop.bonus.open') }}
      </button>
    </div>

    <ul class="flex flex-col gap-1.5">
      <li v-for="(bonus, index) in places" :key="index" class="flex flex-col gap-1" :data-test="`bonus-${part.id}-${index + 1}`">
        <p class="flex flex-wrap items-baseline gap-x-2 font-mono text-[11px]" :class="bonus ? 'text-cyan' : 'text-faint'">
          <span class="text-faint">{{ t('workshop.bonus.place', { place: index + 1 }) }}</span>
          <template v-if="bonus">
            <span :title="bonus.description" :data-test="`bonus-text-${part.id}-${index + 1}`">&#x25C6; {{ words(bonus) }}</span>
            <span class="text-faint">{{ t('part.bonus.tries', { count: bonus.tries }) }}</span>
          </template>
          <span v-else>{{ t('part.bonus.empty') }}</span>
        </p>

        <div v-if="open && canTune" class="flex flex-wrap items-center gap-1.5">
          <span v-if="waits(index + 1)" class="sub" :data-test="`bonus-waits-${part.id}-${index + 1}`">{{ t('workshop.bonus.waits') }}</span>
          <template v-else>
            <button
              type="button"
              class="btn btn-primary btn-sm"
              :disabled="disabled"
              :data-test="`bonus-${bonus ? 'again' : 'draw'}-${part.id}-${index + 1}`"
              @click="emit('tune', index + 1, null)"
            >
              {{ t(bonus ? 'workshop.bonus.again' : 'workshop.bonus.draw', { price: money(next!.cost_random_cents ?? 0, 2) }) }}
            </button>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="disabled"
              :aria-expanded="choosing === index + 1"
              :data-test="`bonus-choose-${part.id}-${index + 1}`"
              @click="choosing = choosing === index + 1 ? null : index + 1"
            >
              {{ t('workshop.bonus.choose') }}
            </button>
          </template>
        </div>

        <ul v-if="open && canTune && choosing === index + 1" class="flex flex-col gap-1" :data-test="`bonus-options-${part.id}-${index + 1}`">
          <li v-if="options.length === 0" class="sub">{{ t('workshop.bonus.none') }}</li>
          <li v-for="option in options" :key="option.key">
            <button
              type="button"
              class="btn btn-secondary btn-sm w-full !h-auto !justify-between gap-2 !py-1.5 text-left"
              :disabled="disabled"
              :data-test="`bonus-option-${part.id}-${index + 1}-${option.key}`"
              @click="pick(index + 1, option.key)"
            >
              <span class="min-w-0">
                <span class="block font-semibold">{{ option.name }}</span>
                <span class="sub block">{{ option.description }}</span>
                <span class="sub block">{{ t('workshop.bonus.option', { mean: percent(option.mean_percent), max: percent(option.max_percent) }) }}</span>
              </span>
              <span class="shrink-0">{{ t('workshop.bonus.pick', { price: money(next!.cost_pick_cents ?? 0, 2) }) }}</span>
            </button>
          </li>
        </ul>
      </li>
    </ul>

    <template v-if="open && next">
      <p v-if="next.locked?.reason === 'level'" class="sub" :data-test="`bonus-locked-${part.id}`">{{ t('workshop.bonus.level', { required: next.locked.required, current: next.locked.current }) }}</p>
      <template v-else-if="canTune">
        <p class="sub" :data-test="`bonus-cost-${part.id}`">{{ t('workshop.bonus.cost', { price: money(next.cost_random_cents ?? 0, 2), time: formatDuration(next.duration_ms ?? 0), pick: money(next.cost_pick_cents ?? 0, 2) }) }}</p>
        <p class="sub">{{ t('workshop.bonus.note') }}</p>
      </template>
    </template>
  </div>
</template>
