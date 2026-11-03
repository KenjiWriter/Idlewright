import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { i18n, setLocale } from '@/i18n'
import { aProfile } from '@/test/profileFixtures'

import TrackMap from './TrackMap.vue'

const show = (props: Record<string, unknown> = {}) =>
  mount(TrackMap, {
    props: { profile: aProfile(), player: () => 0.1, ...props },
    global: { plugins: [i18n] },
  })

describe('TrackMap', () => {
  beforeEach(async () => {
    await setLocale('en')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('draws the line of the track in pieces coloured by the speed of the car, and the car on it', () => {
    const wrapper = show()

    expect(wrapper.find('svg').attributes('aria-label')).toBe('The line of the track with the car on it')
    expect(wrapper.findAll('svg line')).toHaveLength(4)
    expect(wrapper.find('[data-test="track-car"]').exists()).toBe(true)
    // The slowest piece is warm and the fastest is green.
    const colours = wrapper.findAll('svg line').map((piece) => piece.attributes('stroke'))
    expect(new Set(colours).size).toBeGreaterThan(1)
  })

  it('says the lap time is set by hand when the track is driven by the formula, and nothing when it is driven on its thread', () => {
    const byHand = show({ profile: aProfile({ lap_from: 'stats' }) })
    const driven = show({ profile: aProfile({ lap_from: 'thread' }) })

    expect(byHand.get('[data-test="track-by-hand"]').text()).toBe('The lap time of this track is set by hand: the speeds shown are those of the model.')
    expect(driven.find('[data-test="track-by-hand"]').exists()).toBe(false)
  })

  it('says how fast the car goes and which section it is in', () => {
    const wrapper = show({ player: () => 0.1 })

    expect(wrapper.get('[data-test="track-speed"]').text()).toBe('90 km/h')
    expect(wrapper.get('[data-test="track-section"]').text()).toBe('Straight, 100 m')

    const inCorner = show({ player: () => 0.35 })

    expect(inCorner.get('[data-test="track-section"]').text()).toBe('Corner R32, 180°')
  })

  it('says it is on the way back for a run that has finished', () => {
    const wrapper = show({ profile: aProfile({ mode: 'run', drive_fraction: 0.8 }), player: () => 0.9 })

    expect(wrapper.get('[data-test="track-section"]').text()).toBe('Back to the start')
    expect(wrapper.get('[data-test="track-speed"]').text()).toBe('0 km/h')
  })

  it('puts the rivals on the line with their names, from their own clocks', () => {
    const wrapper = show({ rivals: [{ name: 'Bruk Racing', fraction: () => 0.5 }, { name: 'Kuźnia Speed', fraction: () => 0.9 }] })

    expect(wrapper.findAll('[data-test="track-rival"]')).toHaveLength(2)
    expect(wrapper.findAll('[data-test="track-rival"] title').map((title) => title.text())).toEqual(['Bruk Racing', 'Kuźnia Speed'])
  })

  it('moves the car when the clock moves: asked for the fraction again at every frame, with no polling', async () => {
    vi.useFakeTimers()
    let fraction = 0.1
    const wrapper = show({ player: () => fraction })
    const before = wrapper.get('[data-test="track-car"]').attributes('cy')

    fraction = 0.6
    await vi.advanceTimersByTimeAsync(100)

    expect(wrapper.get('[data-test="track-car"]').attributes('cy')).not.toBe(before)
    wrapper.unmount()
  })
})
