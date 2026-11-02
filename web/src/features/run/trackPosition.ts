import type { RunProfile } from '@/api/types'

/** Where the car is on the line of the track, how fast it goes there, and which section of the thread it is on. */
export interface TrackSpot {
  x: number
  y: number
  speedKmh: number
  /** How far along the lap, in metres. */
  distanceM: number
  /** The index of the section in `profile.sections`, or -1 when the profile has none. */
  section: number
  /** The car is on the way back to the start (a run, after the finish): it waits at the end of the line. */
  returning: boolean
}

/** The first index whose time is not before `fraction`, in a list that only grows. */
function firstAtOrAfter(times: number[], fraction: number): number {
  let low = 0
  let high = times.length - 1

  while (low < high) {
    const middle = (low + high) >> 1

    if (times[middle]! < fraction) {
      low = middle + 1
    } else {
      high = middle
    }
  }

  return low
}

/**
 * Where the car is when `fraction` of the lap time has gone, a pure function of the profile: no clock, no server. `fraction` is how far through the lap in progress
 * the car is, 0 up to but not including 1 (`lapProgress`); the profile spreads its own times over the lap that was frozen, so the car is where the settlement says
 * it is to the millisecond. A run does not drive the whole lap: after `drive_fraction` the car waits at the finish for the way back.
 */
export function trackSpot(profile: RunProfile, fraction: number): TrackSpot {
  const last = profile.points.length - 1
  const lap = Math.min(1, Math.max(0, fraction))
  const returning = lap > profile.drive_fraction
  const driven = profile.drive_fraction >= 1 ? lap : Math.min(1, lap / profile.drive_fraction)
  const upper = Math.max(1, firstAtOrAfter(profile.time, driven))
  const lower = upper - 1
  const span = profile.time[upper]! - profile.time[lower]!
  const share = span > 0 ? Math.min(1, Math.max(0, (driven - profile.time[lower]!) / span)) : 1
  const mix = (a: number, b: number): number => a + (b - a) * share

  const distanceM = mix(profile.distance_m[lower]!, profile.distance_m[upper]!)
  const section = profile.sections.findIndex((entry) => distanceM >= entry.from_m && distanceM <= entry.to_m)

  return {
    x: mix(profile.points[lower]![0], profile.points[upper]![0]),
    y: mix(profile.points[lower]![1], profile.points[upper]![1]),
    speedKmh: returning ? 0 : mix(profile.speed_kmh[lower]!, profile.speed_kmh[upper]!),
    distanceM: returning ? profile.distance_m[last]! : distanceM,
    section: returning ? profile.sections.length - 1 : section,
    returning,
  }
}
