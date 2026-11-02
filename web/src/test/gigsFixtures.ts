import type { GigActive, GigOffer, GigsResponse } from '@/api/types'
import { NOW } from '@/test/garageFixtures'

/** One offer of the board: a taxi ride across the city for two people that the Rdzak can take. */
export function anOffer(extra: Partial<GigOffer> = {}): GigOffer {
  return {
    key: 'zlomowo.1327920.0.aaaaaaaa',
    template: 'taxi-city',
    title: 'Ride across town',
    kind: 'taxi',
    kind_name: 'Taxi',
    region: 'zlomowo',
    to: null,
    to_name: null,
    km: 12,
    capacity: 'seats',
    amount: 2,
    class_in: null,
    rarity: 'ordinary',
    phase: 'day',
    until_ms: NOW + 25 * 60_000,
    duration_ms: 9 * 60_000,
    fuel_ml: 720,
    reward_cents: 6_111,
    bonus_cents: 0,
    xp: 38,
    eligible: true,
    blocked: null,
    ...extra,
  }
}

/** A gig the car is out on: it left at NOW and is back in nine minutes. */
export function anActiveGig(extra: Partial<GigActive> = {}): GigActive {
  return {
    id: 5,
    offer: 'zlomowo.1327920.0.aaaaaaaa',
    template: 'taxi-city',
    title: 'Ride across town',
    kind: 'taxi',
    kind_name: 'Taxi',
    region: 'zlomowo',
    region_name: 'Złomowo',
    to: null,
    to_name: null,
    km: 12,
    capacity: 'seats',
    amount: 2,
    rarity: 'ordinary',
    phase: 'day',
    start_ms: NOW,
    finish_ms: NOW + 9 * 60_000,
    cancel_until_ms: NOW + 60_000,
    reward_cents: 6_111,
    bonus_cents: 0,
    xp: 38,
    status: 'active',
    ...extra,
  }
}

/** The board of Złomowo in the day: two offers, one of them a ride to Kruszyn that the car has too few seats for. */
export function aBoard(extra: Partial<GigsResponse['data']> = {}): GigsResponse {
  return {
    data: {
      enabled: true,
      locked: null,
      city: 'zlomowo',
      city_name: 'Złomowo',
      now_ms: NOW,
      phase: 'day',
      next_phase_ms: NOW + 40 * 60_000,
      wave: { number: 1327920, ends_ms: NOW + 15 * 60_000 },
      cancel_window_ms: 60_000,
      car: { id: 1, seats: 4, cargo_kg: 120, class: 'hatchback', speed_kmh: 80 },
      offers: [
        anOffer(),
        anOffer({
          key: 'zlomowo.1327920.1.bbbbbbbb',
          title: 'Ride to another city',
          to: 'kruszyn',
          to_name: 'Kruszyn',
          km: 76,
          amount: 5,
          eligible: false,
          blocked: { code: 'gig_requirements', details: { what: 'seats', need: 5, have: 4 } },
          reward_cents: 41_800,
          duration_ms: 57 * 60_000,
          fuel_ml: 4_560,
        }),
      ],
      active: null,
      limits: { daily_used: 1, daily_limit: 6, resets_ms: NOW + 10 * 3_600_000 },
      ...extra,
    },
    now_ms: NOW,
    wallet: { cash_cents: 600_000, xp: 1_200 },
  }
}
