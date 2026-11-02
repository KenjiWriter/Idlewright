import type { RunProfile } from '@/api/types'

/**
 * The picture of a lap of a small loop: a straight of 100 m to the right, a corner down, and the same back, as the server makes it (a point every 50 m or so). The
 * car is slow in the corners and fast on the straights, and the lap is spread over the time from 0 to 1.
 */
export function aProfile(overrides: Partial<RunProfile> = {}): RunProfile {
  return {
    track: 'zlomowo-street',
    mode: 'circuit',
    length_m: 400,
    weather: 'dry',
    lap_s: 20,
    drive_fraction: 1,
    sections: [
      { type: 'straight', from_m: 0, to_m: 100 },
      { type: 'corner', from_m: 100, to_m: 200, radius_m: 32, angle_deg: 180, turn: 'right' },
      { type: 'straight', from_m: 200, to_m: 300 },
      { type: 'corner', from_m: 300, to_m: 400, radius_m: 32, angle_deg: 180, turn: 'right' },
    ],
    points: [
      [0, 0],
      [100, 0],
      [100, -64],
      [0, -64],
      [0, 0],
    ],
    distance_m: [0, 100, 200, 300, 400],
    speed_kmh: [60, 120, 80, 130, 60],
    time: [0, 0.2, 0.5, 0.7, 1],
    ...overrides,
  }
}
