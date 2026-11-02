import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

import type { AwayAccount } from '@/api/types'
import { i18n, setLocale } from '@/i18n'

import AccountAway from './AccountAway.vue'

function aSummary(overrides: Partial<AwayAccount> = {}): AwayAccount {
  return {
    pending: true,
    from_ms: 0,
    to_ms: 0,
    away_minutes: 312,
    earned_cents: 6_000,
    cars: [
      { id: 1, name: 'Rdzak GT-4', played: true, earned_cents: 9_000, laps: 40, waiting: null },
      { id: 2, name: 'Bizon M-2', played: false, earned_cents: 4_500, laps: 12, waiting: null },
      { id: 3, name: 'Kuna 4x4', played: false, earned_cents: 1_500, laps: 4, waiting: 'fuel_empty' },
      { id: 4, name: 'Furgon Z-1', played: false, earned_cents: 0, laps: 0, waiting: 'breakdown' },
    ],
    jobs: [
      { kind: 'upgrade', part: 'Street tires', finished_ms: 0 },
      { kind: 'service', part: null, finished_ms: 0 },
    ],
    journeys: [
      { to: 'Kruszyn', arrived_ms: 0, boosted: true, police: { outcome: 'defaulted', paid_cents: 1_250 } },
      { to: 'Autodrome', arrived_ms: 0, boosted: false, police: null },
    ],
    ...overrides,
  }
}

function mountAway(summary: AwayAccount) {
  return mount(AccountAway, { props: { summary }, global: { plugins: [i18n] } })
}

describe('AccountAway', () => {
  beforeEach(async () => {
    await setLocale('en')
  })

  it('says how long the driver was away and what the cars that were not played earned, without the one that is played', () => {
    const wrapper = mountAway(aSummary())

    expect(wrapper.get('[data-test="away-account-duration"]').text()).toBe('5 h 12 min')
    expect(wrapper.get('[data-test="away-cars"]').text()).toContain('Other cars: +$60.00')
    expect(wrapper.find('[data-test="away-car-1"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="away-car-2"]').text()).toContain('Bizon M-2')
    expect(wrapper.get('[data-test="away-car-2"]').text()).toContain('+$45.00 · 12 laps')
  })

  it('says how many times the tank was filled for the driver in the cars that were not played, and the bonus of the VIP account', () => {
    const wrapper = mountAway(
      aSummary({
        cars: [
          { id: 1, name: 'Rdzak GT-4', played: true, earned_cents: 9_000, laps: 40, bonus_cents: 900, refills: 4, refill_charges: 4, waiting: null },
          { id: 2, name: 'Bizon M-2', played: false, earned_cents: 4_500, laps: 12, bonus_cents: 750, refills: 2, refill_charges: 1, waiting: null },
          { id: 3, name: 'Kuna 4x4', played: false, earned_cents: 1_500, laps: 4, bonus_cents: 250, refills: 1, refill_charges: 0, waiting: null },
        ],
      }),
    )

    expect(wrapper.get('[data-test="away-extras-refills"]').text()).toBe('The tank was filled for you 3 times (stock −1)')
    expect(wrapper.get('[data-test="away-extras-bonus"]').text()).toBe('VIP bonus: +$10.00')
  })

  it('says the refills were free when the pass paid for all of them, and shows nothing of the extras when there were none', () => {
    const free = mountAway(aSummary({ cars: [{ id: 2, name: 'Bizon M-2', played: false, earned_cents: 4_500, laps: 12, refills: 1, refill_charges: 0, waiting: null }] }))

    expect(free.get('[data-test="away-extras-refills"]').text()).toBe('The tank was filled for you 1 time, free with the pass')
    expect(free.find('[data-test="away-extras-bonus"]').exists()).toBe(false)
    expect(mountAway(aSummary()).find('[data-test="away-extras"]').exists()).toBe(false)
  })

  it('says which cars wait for the driver, and why', () => {
    const wrapper = mountAway(aSummary())

    expect(wrapper.get('[data-test="away-car-3-waiting"]').text()).toBe('out of fuel')
    expect(wrapper.get('[data-test="away-car-4-waiting"]').text()).toBe('broken down')
  })

  it('lists the jobs that were done, with the part, and the journeys with what the police made of them', () => {
    const wrapper = mountAway(aSummary())

    expect(wrapper.get('[data-test="away-jobs"]').text()).toContain('Upgraded: Street tires')
    expect(wrapper.get('[data-test="away-jobs"]').text()).toContain('Serviced')
    expect(wrapper.get('[data-test="away-journey-0"]').text()).toContain('Arrived in Kruszyn')
    expect(wrapper.get('[data-test="away-journey-0-police"]').text()).toBe('The police stopped you: the fine of $12.50 was paid for you.')
    expect(wrapper.get('[data-test="away-journey-1"]').text()).toBe('Arrived in Autodrome')
    expect(wrapper.find('[data-test="away-journey-1-police"]').exists()).toBe(false)
  })

  it('leaves out a section that has nothing in it', () => {
    const wrapper = mountAway(aSummary({ jobs: [], journeys: [] }))

    expect(wrapper.find('[data-test="away-jobs"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="away-journeys"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="away-cars"]').exists()).toBe(true)
  })

  it('tells the parent when the driver has read it', async () => {
    const wrapper = mountAway(aSummary())

    await wrapper.get('[data-test="away-account-dismiss"]').trigger('click')

    expect(wrapper.emitted('dismiss')).toHaveLength(1)
  })

  it('speaks the language of the driver', async () => {
    await setLocale('pl')
    const wrapper = mountAway(aSummary())

    expect(wrapper.get('h2').text()).toBe('Reszta konta')
    expect(wrapper.get('[data-test="away-car-3-waiting"]').text()).toBe('brak paliwa')
    expect(wrapper.get('[data-test="away-jobs"]').text()).toContain('Ulepszono: Street tires')
  })

  it('tells what the missions did in the break: how many were done, and what the server took for the driver when a period ended', () => {
    const wrapper = mountAway(aSummary({ missions: { done: 4, collected: 2, cash_cents: 3_000, xp: 12, coins: 2 } }))

    expect(wrapper.get('[data-test="away-missions-done"]').text()).toBe('Missions done: 4')
    expect(wrapper.get('[data-test="away-missions-taken"]').text()).toBe('Taken for you when the period ended: 2 ($30, 12 XP)')
    expect(wrapper.get('[data-test="away-missions-coins"]').text()).toBe('Bonus for the whole set: +2 RideCoin')
  })

  it('has no line of the missions when they did nothing', () => {
    expect(mountAway(aSummary({ missions: null })).find('[data-test="away-missions"]').exists()).toBe(false)
    expect(mountAway(aSummary({ missions: { done: 1, collected: 0, cash_cents: 0, xp: 0, coins: 0 } })).find('[data-test="away-missions-taken"]').exists()).toBe(false)
  })

  it('tells of the streak: kept, saved by a shield, lost, and what the server took', () => {
    const kept = mountAway(aSummary({ missions: { done: 0, collected: 0, cash_cents: 0, xp: 0, coins: 0, streak: { current: 5, counted: 2, shields_used: 1, lost: null, cash_cents: 1_600, xp: 6 } } }))

    expect(kept.get('[data-test="away-streak"]').text()).toBe('Streak: 5 days')
    expect(kept.get('[data-test="away-streak-shield"]').text()).toBe('Shields used to save the streak: 1')
    expect(kept.get('[data-test="away-streak-taken"]').text()).toBe('Taken for you: $16, 6 XP')

    const lost = mountAway(aSummary({ missions: { done: 0, collected: 0, cash_cents: 0, xp: 0, coins: 0, streak: { current: 0, counted: 0, shields_used: 0, lost: 6, cash_cents: 0, xp: 0 } } }))

    expect(lost.get('[data-test="away-streak-lost"]').text()).toBe('The streak is over (it was 6 days).')
    expect(lost.find('[data-test="away-streak"]').exists()).toBe(false)
  })

  it('tells the gigs that ended in the break, with what each paid and the sum', () => {
    const wrapper = mountAway(
      aSummary({
        gigs: [
          { kind: 'taxi', kind_name: 'Taxi', to: 'Kruszyn', finished_ms: 0, reward_cents: 41_800, bonus_cents: 0 },
          { kind: 'freight', kind_name: 'Freight', to: 'Złomowo', finished_ms: 0, reward_cents: 6_111, bonus_cents: 0 },
        ],
        gigs_cents: 47_911,
      }),
    )

    expect(wrapper.get('[data-test="away-gigs"] .kick').text()).toBe('Gigs: 2 · +$479')
    expect(wrapper.get('[data-test="away-gig-0"]').text()).toBe('Taxi · Kruszyn · +$418')
    expect(wrapper.get('[data-test="away-gig-1"]').text()).toBe('Freight · Złomowo · +$61')
  })

  it('shows no gigs when there were none', () => {
    expect(mountAway(aSummary()).find('[data-test="away-gigs"]').exists()).toBe(false)
  })
})
