import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api } from '@/api/client'
import type { TutorialMessenger, TutorialResponse } from '@/api/types'
import { useRunStore } from '@/stores/run'

/** The old cracked phone of RideIdle, with all its parts: what a server that says nothing of the messenger means. */
export const PHONE: TutorialMessenger = {
  device: 'phone',
  parts: { crack: true, status_bar: true, input: true, who: true },
  icon_url: null,
  frame_url: null,
  screen: null,
}

/**
 * Where the driver is in the tutorial. The server decides: it brings the progress up to date whenever it is asked,
 * from the state of the game, so the store only has to ask, and to ask again when the run moves.
 */
export const useTutorialStore = defineStore('tutorial', () => {
  const tutorial = ref<TutorialResponse['data'] | null>(null)
  /** What the last answer paid, until the screen has said it: cents of cash, experience of the car and of the driver, the parts given and the garages given (by slug). */
  const reward = ref<{ cashCents: number; carXp: number; xp: number; parts: string[]; garages: string[] } | null>(null)
  /** The server was asked at least once, whatever it answered: a screen that waits for the phone waits for this. */
  const asked = ref(false)

  /** What to do next, while the tutorial is on, whether the step has started or still waits for its trigger. */
  const goal = computed(() => (tutorial.value?.status === 'in_progress' ? (tutorial.value.goal ?? null) : null))
  /** What the mentor writes on: the device of the game, or the old phone when the server says nothing of it. */
  const messenger = computed<TutorialMessenger>(() => tutorial.value?.messenger ?? PHONE)
  /** The step that waits, while the tutorial is on. */
  const current = computed(() => (tutorial.value?.status === 'in_progress' ? tutorial.value.current : null))
  /** The phone is out: the driver has not answered the messages yet. */
  const showing = computed(() => current.value !== null && !current.value.acknowledged)
  /** The messages are answered, and the step waits for the driver to do the thing it asked. */
  const waiting = computed(() => current.value !== null && current.value.acknowledged)

  function apply(response: TutorialResponse): void {
    tutorial.value = response.data

    if (response.data.rewarded.length > 0) {
      reward.value = {
        cashCents: response.data.rewarded.reduce((sum, paid) => sum + paid.cash_cents, 0),
        carXp: response.data.rewarded.reduce((sum, paid) => sum + paid.car_xp, 0),
        xp: response.data.rewarded.reduce((sum, paid) => sum + (paid.xp ?? 0), 0),
        parts: response.data.rewarded.flatMap((paid) => (paid.part ? [paid.part] : [])),
        garages: response.data.rewarded.flatMap((paid) => (paid.garage ? [paid.garage] : [])),
      }

      // The cash and the experience of the driver are in the HUD: ask for them again.
      useRunStore()
        .refresh()
        .catch(() => undefined)
    }
  }

  async function load(): Promise<void> {
    try {
      apply(await api<TutorialResponse>('/v1/tutorial'))
    } finally {
      asked.value = true
    }
  }

  async function reply(): Promise<void> {
    apply(await api<TutorialResponse>('/v1/tutorial/reply', { method: 'POST' }))
  }

  /** Leaves the optional step that waits for later: no reward, and the next one goes on. */
  async function decline(): Promise<void> {
    apply(await api<TutorialResponse>('/v1/tutorial/decline', { method: 'POST' }))
  }

  async function skip(): Promise<void> {
    apply(await api<TutorialResponse>('/v1/tutorial/skip', { method: 'POST' }))
  }

  function acknowledgeReward(): void {
    reward.value = null
  }

  /** Forgets everything, for example when the driver signs out. */
  function reset(): void {
    tutorial.value = null
    reward.value = null
    asked.value = false
  }

  return { tutorial, reward, asked, goal, current, messenger, showing, waiting, load, reply, decline, skip, acknowledgeReward, reset }
})
