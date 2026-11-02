import { defineStore } from 'pinia'
import { ref } from 'vue'

import { api } from '@/api/client'
import type { GigActive, GigsResponse } from '@/api/types'
import { useFleetStore } from '@/stores/fleet'
import { useRunStore } from '@/stores/run'

/**
 * The board of gigs of the city where the car the driver plays stands: the offers with what each would pay, take and burn for that car, the gig the car is out on, and
 * how many the driver may still take today. The server makes the board again, the same, for as long as a wave lasts, so the store only has to ask, and to count the time
 * to the end of the wave and of the gig with the clock of the server (the offset of the last answer), not with its own.
 */
export const useGigsStore = defineStore('gigs', () => {
  const board = ref<GigsResponse['data'] | null>(null)
  const cash = ref<GigsResponse['wallet'] | null>(null)
  const loaded = ref(false)
  /** The clock of the server minus the clock of this device, as it was at the last answer. */
  const clockOffsetMs = ref(0)

  /** What time it is for the server now. */
  function serverNow(): number {
    return Date.now() + clockOffsetMs.value
  }

  function apply(response: GigsResponse): void {
    board.value = response.data
    cash.value = response.wallet
    clockOffsetMs.value = response.now_ms - Date.now()
    loaded.value = true
  }

  /** The car left or came back, the fuel and the cash changed: the run and the fleet are asked for again. A failed ask is not worth a message, the next one tries again. */
  function syncRun(): void {
    useRunStore()
      .refresh()
      .catch(() => undefined)
    useFleetStore()
      .load()
      .catch(() => undefined)
  }

  async function load(): Promise<void> {
    apply(await api<GigsResponse>('/v1/gigs'))
  }

  /** Takes an offer with the car the driver plays. Taking it twice takes it once: the answer says `repeated`. Returns the gig, null when the server did not send it. */
  async function take(offer: string): Promise<GigActive | null> {
    const response = await api<GigsResponse>('/v1/gigs', { method: 'POST', body: JSON.stringify({ offer }) })

    apply(response)
    syncRun()

    return response.gig ?? null
  }

  /** Takes a gig back in the first seconds after it was taken. */
  async function cancel(id: number): Promise<void> {
    apply(await api<GigsResponse>(`/v1/gigs/${id}`, { method: 'DELETE' }))
    syncRun()
  }

  return { board, cash, loaded, serverNow, load, take, cancel }
})
