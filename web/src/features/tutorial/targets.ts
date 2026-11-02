import type { TutorialTarget } from '@/api/types'

/**
 * The screen of the game where the thing that a step points at is, by the name of its route: the goal in front of the driver leads there. The tab of the city
 * (`shop`) holds the crates, and the exchange is a screen of its own under it.
 */
export const TARGET_SCREEN: Record<TutorialTarget, string> = {
  start: 'race',
  laps: 'garage',
  cash: 'garage',
  refuel: 'garage',
  repair: 'garage',
  mount: 'workshop',
  crate: 'shop',
  travel: 'map',
  merge: 'workshop',
  exchange: 'exchange',
  service: 'workshop',
  away: 'garage',
  upgrade: 'workshop',
  equipment: 'workshop',
  profile: 'garage',
  garages: 'map',
  gigs: 'gigs',
  // The plate of the missions is in the bar at the top of every screen: the goal leads to the screen it opens.
  missions: 'missions',
}
