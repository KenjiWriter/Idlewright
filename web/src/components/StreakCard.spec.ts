import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

import type { MissionStreak } from '@/api/types'
import { i18n, setLocale } from '@/i18n'
import { useMissionsStore } from '@/stores/missions'
import { aStreak, missionsView } from '@/test/missionsFixtures'

import StreakCard from './StreakCard.vue'

function show(streak: MissionStreak | null, props: { busy?: boolean } = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useMissionsStore().apply(missionsView({ streak }))

  return mount(StreakCard, { props, global: { plugins: [pinia, i18n] } })
}

describe('StreakCard', () => {
  beforeEach(async () => {
    await setLocale('en')
  })

  it('shows nothing when the game has no streak', () => {
    expect(show(null).find('[data-test="streak"]').exists()).toBe(false)
  })

  it('tells the days of the streak, the shields, the best streak and how far the goal of the day is', () => {
    const wrapper = show(aStreak())

    expect(wrapper.get('[data-test="streak-days"]').text()).toBe('3 days')
    expect(wrapper.get('[data-test="streak-shields"]').text()).toBe('Shields: 1 of 2')
    expect(wrapper.get('[data-test="streak-best"]').text()).toBe('Best: 5')
    expect(wrapper.get('[data-test="streak-goal"]').text()).toBe('Goal of the day: 2 of 3')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('67')
    expect(wrapper.get('[data-test="streak-next"]').text()).toContain('$8')
    expect(wrapper.get('[data-test="streak-milestone"]').text()).toBe('Next milestone: day 14')
  })

  it('marks the days of the cycle that are done, the day the driver is working for, and the ones to come', () => {
    const wrapper = show(aStreak())

    expect([1, 2, 3, 4, 5, 6, 7].map((day) => wrapper.get(`[data-test="streak-cell-${day}"]`).attributes('data-state'))).toEqual(['done', 'done', 'done', 'today', 'later', 'later', 'later'])
    // The day that pays a crate says so.
    expect(wrapper.get('[data-test="streak-cell-7"]').text()).toContain('▣')
  })

  it('has a day that counted as done, and asks to be taken, when the goal of today is done', async () => {
    const reward = { cash_cents: 800, xp: 0, items: [] }
    const wrapper = show(aStreak({ current: 4, cycle_day: 4, goal: { needed: 3, done: 3, reached: true }, today: { state: 'ready', streak: 4, cycle_day: 4, reward }, next: { cycle_day: 5, streak: 5, cash_cents: 1_100, xp: 12, items: [] } }))

    expect(wrapper.get('[data-test="streak-goal"]').text()).toBe('Goal of the day done')
    expect(wrapper.get('[data-test="streak-cell-4"]').attributes('data-state')).toBe('done')
    expect(wrapper.get('[data-test="streak-ready"]').text()).toContain('$8')

    await wrapper.get('[data-test="streak-claim"]').trigger('click')

    expect(wrapper.emitted('claim')).toHaveLength(1)
  })

  it('does not let the button be pressed while something else is on its way', () => {
    const reward = { cash_cents: 800, xp: 0, items: [] }
    const wrapper = show(aStreak({ current: 4, goal: { needed: 3, done: 3, reached: true }, today: { state: 'ready', streak: 4, cycle_day: 4, reward } }), { busy: true })

    expect(wrapper.get('[data-test="streak-claim"]').attributes('disabled')).toBeDefined()
  })

  it('says that the reward of today was taken, and what tomorrow pays', () => {
    const wrapper = show(aStreak({ goal: { needed: 3, done: 3, reached: true }, today: { state: 'claimed', streak: 3, cycle_day: 3, reward: { cash_cents: 800, xp: 0, items: [] } }, next: { cycle_day: 4, streak: 4, cash_cents: 800, xp: 0, items: [] } }))

    expect(wrapper.get('[data-test="streak-claimed"]').text()).toContain('Taken')
    expect(wrapper.get('[data-test="streak-claimed"]').text()).toContain('Tomorrow: $8')
    expect(wrapper.find('[data-test="streak-claim"]').exists()).toBe(false)
  })

  it('says that the streak is over, and starts the cycle again', () => {
    const wrapper = show(aStreak({ current: 0, cycle_day: 0, shields: 0, lost: { streak: 6, day: '2026-10-29' }, next: { cycle_day: 1, streak: 1, cash_cents: 800, xp: 0, items: [] } }))

    expect(wrapper.get('[data-test="streak-days"]').text()).toBe('0 days')
    expect(wrapper.get('[data-test="streak-lost"]').text()).toContain('it was 6 days')
    expect(wrapper.get('[data-test="streak-cell-1"]').attributes('data-state')).toBe('today')
    expect(wrapper.get('[data-test="streak-cell-2"]').attributes('data-state')).toBe('later')
  })
})
