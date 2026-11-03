import { describe, expect, it } from 'vitest'

import type { MapTrackThread } from '@/api/types'

import { characterShare, fitOf } from './trackCharacter'

function aThread(overrides: Partial<MapTrackThread> = {}): MapTrackThread {
  return {
    lap_from: 'thread',
    mode: 'circuit',
    points: [
      [0, 0],
      [100, 0],
      [100, 50],
    ],
    character: 'balanced',
    shares: { accelerating: 59, holding: 0, braking: 10, cornering: 30 },
    car: { accelerating: -6.9, holding: 0, braking: 4.4, cornering: 0.6 },
    ...overrides,
  }
}

describe('fitOf', () => {
  it('names where the car gains the most and where it loses the most, and keeps the parts that count', () => {
    const fit = fitOf(aThread(), 75_000)!

    // 2% of a lap of 75 s is 1.5 s: the 0.6 s in the corners is rounding, the rest counts.
    expect(fit.rows).toEqual([
      { group: 'accelerating', seconds: -6.9 },
      { group: 'braking', seconds: 4.4 },
    ])
    expect(fit.gain).toBe('accelerating')
    expect(fit.loss).toBe('braking')
  })

  it('counts a smaller difference on a short lap: the floor is a share of the lap', () => {
    const fit = fitOf(aThread({ car: { accelerating: -0.8, holding: 0, braking: 0.6, cornering: 0.1 } }), 13_000)!

    expect(fit.rows.map((row) => row.group)).toEqual(['accelerating', 'braking'])
  })

  it('says nothing is gained or lost when every difference is below the floor', () => {
    const fit = fitOf(aThread({ car: { accelerating: 0.2, holding: 0, braking: -0.3, cornering: 0.1 } }), 75_000)!

    expect(fit).toEqual({ rows: [], gain: null, loss: null })
  })

  it('has no fit when the thread says nothing about the car', () => {
    expect(fitOf(aThread({ car: null, shares: null, character: null }), 75_000)).toBeNull()
  })

  it('picks the biggest gain and the biggest loss when there are several', () => {
    const fit = fitOf(aThread({ car: { accelerating: -3, holding: 0, braking: 5, cornering: 8 } }), 75_000)!

    expect(fit.gain).toBe('accelerating')
    expect(fit.loss).toBe('cornering')
  })
})

describe('characterShare', () => {
  it('is the share of the lap that gives the track its character', () => {
    expect(characterShare(aThread({ character: 'twisty' }))).toBe(30)
    expect(characterShare(aThread({ character: 'power' }))).toBe(59)
    expect(characterShare(aThread({ character: 'limit', shares: { accelerating: 0, holding: 85, braking: 0, cornering: 15 } }))).toBe(85)
  })

  it('is null for a balanced track and for a thread that says nothing', () => {
    expect(characterShare(aThread())).toBeNull()
    expect(characterShare(aThread({ character: null, shares: null }))).toBeNull()
  })
})
