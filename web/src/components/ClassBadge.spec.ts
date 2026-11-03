import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

import { i18n, setLocale } from '@/i18n'

import ClassBadge from './ClassBadge.vue'

describe('ClassBadge', () => {
  beforeEach(async () => {
    await setLocale('en')
  })

  it('shows the class and the PI of a car as one: B 612, with the words in its title', () => {
    const wrapper = mount(ClassBadge, { props: { performance: { pi: 612, class: 'B' } }, global: { plugins: [i18n] } })

    expect(wrapper.get('[data-test="car-class"]').text()).toBe('B 612')
    expect(wrapper.get('[data-test="car-class"]').attributes('title')).toBe('Class B, 612 PI')
    expect(wrapper.get('[data-test="car-class"]').attributes('data-class')).toBe('B')
  })

  it('shows the lowest and the highest score of the scale', () => {
    expect(mount(ClassBadge, { props: { performance: { pi: 0, class: 'F' } }, global: { plugins: [i18n] } }).text()).toBe('F 0')
    expect(mount(ClassBadge, { props: { performance: { pi: 999, class: 'SS' } }, global: { plugins: [i18n] } }).text()).toBe('SS 999')
  })
})
