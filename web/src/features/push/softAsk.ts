/**
 * How often the app asks a driver whether they want notifications. The browser asks for the permission once and a "no" there cannot be taken back from a page, so the app asks first
 * with a card of its own, and a "not now" is remembered: it comes back after a week, three times at most, and then not any more (the driver can still switch them on in the account).
 */
const KEY = 'ride.push-ask'
const WEEK_MS = 7 * 24 * 60 * 60 * 1000

/** How many times a driver may put it off. */
export const MAX_DECLINES = 3

interface Memory {
  declined: number
  at: number
}

function read(): Memory {
  try {
    const value = JSON.parse(localStorage.getItem(KEY) ?? 'null') as Partial<Memory> | null

    return { declined: Number(value?.declined) || 0, at: Number(value?.at) || 0 }
  } catch {
    return { declined: 0, at: 0 }
  }
}

/** Whether the card is to be shown now: not asked yet, or put off less than three times and a week ago. */
export function shouldAsk(now: number = Date.now()): boolean {
  const { declined, at } = read()

  return declined < MAX_DECLINES && (declined === 0 || now - at >= WEEK_MS)
}

/** The driver said "not now". */
export function putOff(now: number = Date.now()): void {
  try {
    localStorage.setItem(KEY, JSON.stringify({ declined: read().declined + 1, at: now }))
  } catch {
    // Without the memory the card comes back at the next return, which is no harm.
  }
}
