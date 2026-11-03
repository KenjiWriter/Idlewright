import type { MapTrackThread, ThreadGroup, TrackCharacter } from '@/api/types'

/** The parts of a lap, in the order they are told. */
export const THREAD_GROUPS: ThreadGroup[] = ['accelerating', 'holding', 'braking', 'cornering']

/** A part of a lap counts for the car from this share of the base lap of the track: below it the difference is rounding. */
const SIGNIFICANT = 0.02

export interface FitRow {
  group: ThreadGroup
  /** Seconds of the lap of the track against the car the track is made for: below zero is time gained. */
  seconds: number
}

export interface Fit {
  /** The parts where the difference counts, in the order they are told. */
  rows: FitRow[]
  /** Where the car gains the most, or null. */
  gain: ThreadGroup | null
  /** Where it loses the most, or null. */
  loss: ThreadGroup | null
}

/** What the car does well and badly on a track, from the seconds that the server gave (null when the thread says nothing about the car). */
export function fitOf(thread: MapTrackThread, baseLapMs: number): Fit | null {
  if (thread.car === null) {
    return null
  }

  const floor = (baseLapMs / 1000) * SIGNIFICANT
  const rows = THREAD_GROUPS.map((group): FitRow => ({ group, seconds: thread.car![group] })).filter((row) => Math.abs(row.seconds) >= floor)
  const gains = rows.filter((row) => row.seconds < 0).sort((a, b) => a.seconds - b.seconds)
  const losses = rows.filter((row) => row.seconds > 0).sort((a, b) => b.seconds - a.seconds)

  return { rows, gain: gains[0]?.group ?? null, loss: losses[0]?.group ?? null }
}

/** The share of the time of a lap that gives the track its character, in percent (what the sentence says). */
export function characterShare(thread: MapTrackThread): number | null {
  const character: TrackCharacter | null = thread.character

  if (character === null || thread.shares === null) {
    return null
  }

  switch (character) {
    case 'twisty':
      return thread.shares.cornering
    case 'power':
      return thread.shares.accelerating
    case 'limit':
      return thread.shares.holding
    default:
      return null
  }
}
