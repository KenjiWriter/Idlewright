import { describe, expect, it } from 'vitest'

import { aProfile } from '@/test/profileFixtures'

import { trackSpot } from './trackPosition'

describe('trackSpot', () => {
  it('puts the car at the start of the line at the start of the lap, and back there at the end of it', () => {
    const profile = aProfile()

    expect(trackSpot(profile, 0)).toMatchObject({ x: 0, y: 0, speedKmh: 60, distanceM: 0, section: 0, returning: false })
    expect(trackSpot(profile, 1)).toMatchObject({ x: 0, y: 0, distanceM: 400 })
  })

  it('moves the car along the line by the time of the lap and not by the distance: where it is slow it takes longer', () => {
    const profile = aProfile()
    // 10% of the lap time is half of the first 100 m (the car covers them in 20% of the time), 35% is half of the corner (it takes 30% of the time).
    const half = trackSpot(profile, 0.1)
    const corner = trackSpot(profile, 0.35)

    expect(half.x).toBeCloseTo(50)
    expect(half.speedKmh).toBeCloseTo(90)
    expect(half.distanceM).toBeCloseTo(50)
    expect(corner.distanceM).toBeCloseTo(150)
    expect(corner.y).toBeCloseTo(-32)
    expect(corner.section).toBe(1)
  })

  it('says which section the car is in by how far it has gone', () => {
    const profile = aProfile()

    expect(trackSpot(profile, 0.05).section).toBe(0)
    expect(trackSpot(profile, 0.4).section).toBe(1)
    expect(trackSpot(profile, 0.6).section).toBe(2)
    expect(trackSpot(profile, 0.9).section).toBe(3)
  })

  it('does not go past the ends for a fraction outside the lap', () => {
    const profile = aProfile()

    expect(trackSpot(profile, -0.5).distanceM).toBe(0)
    expect(trackSpot(profile, 3).distanceM).toBe(400)
  })

  it('is the same wherever the time is asked for twice, since it is a function of the profile and the fraction alone', () => {
    const profile = aProfile()

    expect(trackSpot(profile, 0.42)).toEqual(trackSpot(profile, 0.42))
  })

  it('keeps a run at the finish while it is on the way back to the start', () => {
    const profile = aProfile({ mode: 'run', drive_fraction: 0.8 })
    const driving = trackSpot(profile, 0.4)
    const back = trackSpot(profile, 0.9)

    expect(driving.returning).toBe(false)
    expect(driving.speedKmh).toBeGreaterThan(0)
    expect(back).toMatchObject({ returning: true, speedKmh: 0, distanceM: 400, section: 3 })
  })
})
