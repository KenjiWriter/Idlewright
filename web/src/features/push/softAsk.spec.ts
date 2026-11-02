import { beforeEach, describe, expect, it } from 'vitest'

import { MAX_DECLINES, putOff, shouldAsk } from './softAsk'

const DAY = 24 * 60 * 60 * 1000

describe('softAsk', () => {
  beforeEach(() => localStorage.clear())

  it('asks a driver who was never asked', () => {
    expect(shouldAsk(1_000)).toBe(true)
  })

  it('does not ask again for a week after a "not now", then asks again, three times at most', () => {
    putOff(10 * DAY)

    expect(shouldAsk(10 * DAY + 6 * DAY)).toBe(false)
    expect(shouldAsk(10 * DAY + 7 * DAY)).toBe(true)

    putOff(20 * DAY)
    putOff(40 * DAY)

    expect(shouldAsk(40 * DAY + 100 * DAY)).toBe(false)
    expect(MAX_DECLINES).toBe(3)
  })

  it('asks when the memory cannot be read', () => {
    localStorage.setItem('ride.push-ask', '{broken')

    expect(shouldAsk(1_000)).toBe(true)
  })
})
