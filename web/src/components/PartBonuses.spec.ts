import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

import type { OwnedPart } from '@/api/types'
import { i18n, setLocale } from '@/i18n'
import { aBonus, aPart, aTuneNext } from '@/test/garageFixtures'

import PartBonuses from './PartBonuses.vue'

const tires = (overrides: Partial<OwnedPart> = {}) => aPart({ id: 21, slot: 'tires', tuning_slots: 3, tuning: [aBonus(), null, null], tune_next: aTuneNext(), ...overrides })

const show = (part: OwnedPart, waiting: number[] = [], disabled = false) => mount(PartBonuses, { props: { part, waiting, disabled }, global: { plugins: [i18n] } })

describe('PartBonuses', () => {
  beforeEach(async () => {
    await setLocale('en')
  })

  it('shows nothing for a part with no places, or an answer of a server that does not know them', () => {
    expect(show(aPart({ tuning_slots: 0, tuning: [] })).find('[data-test="bonuses-11"]').exists()).toBe(false)
    expect(show(aPart()).find('[data-test="bonuses-11"]').exists()).toBe(false)
  })

  it('says what each place holds in words, with the amount and the tries, and shows an empty place as empty', () => {
    const wrapper = show(tires())

    expect(wrapper.get('[data-test="bonus-text-21-1"]').text()).toBe('◆ Gravel grip +6.2% (Gravel, Mud)')
    expect(wrapper.get('[data-test="bonus-21-1"]').text()).toContain('tries: 7')
    expect(wrapper.get('[data-test="bonus-21-2"]').text()).toContain('Empty place')
    expect(wrapper.findAll('[data-test^="bonus-"][data-test$="-3"]').length).toBeGreaterThan(0)
  })

  it('keeps the editor closed until the driver opens it, and then offers a draw at an empty place and another try at a bonus that is held, for the price of the draw', async () => {
    const wrapper = show(tires())

    expect(wrapper.find('[data-test="bonus-draw-21-2"]').exists()).toBe(false)

    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')

    expect(wrapper.get('[data-test="bonus-again-21-1"]').text()).toBe('Try again ($17.10)')
    expect(wrapper.get('[data-test="bonus-draw-21-2"]').text()).toBe('Draw ($17.10)')
    expect(wrapper.get('[data-test="bonus-cost-21"]').text()).toBe('A try costs $17.10 and takes 1 min 57 s. Choosing the bonus costs $57.00.')
  })

  it('asks for a draw with no bonus named, and for a pick with the one that was chosen', async () => {
    const wrapper = show(tires())

    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')
    await wrapper.get('[data-test="bonus-draw-21-2"]').trigger('click')
    await wrapper.get('[data-test="bonus-choose-21-3"]').trigger('click')

    // The bonus that the part holds is not offered again: that is "try again".
    expect(wrapper.find('[data-test="bonus-option-21-3-gravel-grip"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="bonus-option-21-3-dry-grip"]').text()).toContain('5% on average, up to 12%')

    await wrapper.get('[data-test="bonus-option-21-3-dry-grip"]').trigger('click')

    expect(wrapper.emitted('tune')).toEqual([[2, null], [3, 'dry-grip']])
  })

  it('does not offer a second try at a place where one waits, and shuts the buttons while a request is on its way', async () => {
    const wrapper = show(tires(), [2])

    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')

    expect(wrapper.get('[data-test="bonus-waits-21-2"]').text()).toBe('A try is waiting')
    expect(wrapper.find('[data-test="bonus-draw-21-2"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="bonus-again-21-1"]').attributes('disabled')).toBeUndefined()

    await wrapper.setProps({ disabled: true })

    expect(wrapper.get('[data-test="bonus-again-21-1"]').attributes('disabled')).toBeDefined()
  })

  it('says when the driver is not of the level yet, and offers no try', async () => {
    const wrapper = show(tires({ tune_next: aTuneNext({ locked: { reason: 'level', required: 3, current: 1 }, cost_random_cents: null, cost_pick_cents: null, duration_ms: null, bonuses: [] }) }))

    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')

    expect(wrapper.get('[data-test="bonus-locked-21"]').text()).toBe('Bonuses open at level 3 (you are at level 1).')
    expect(wrapper.find('[data-test="bonus-draw-21-2"]').exists()).toBe(false)
  })

  it('shows the bonuses that are held, but offers no try, for a part that cannot be tuned', () => {
    const wrapper = show(tires({ tune_next: aTuneNext({ locked: { reason: 'unavailable' } }) }))

    expect(wrapper.find('[data-test="bonus-text-21-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="bonus-open-21"]').exists()).toBe(false)
  })
})
