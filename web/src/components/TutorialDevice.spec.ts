import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { TutorialMessenger } from '@/api/types'
import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { mountView } from '@/test/mountView'
import { aGoal, aStep, aTutorial } from '@/test/tutorialFixtures'
import { PHONE, useTutorialStore } from '@/stores/tutorial'

import TutorialGoal from './TutorialGoal.vue'
import TutorialOverlay from './TutorialOverlay.vue'

const mounted: { unmount: () => void }[] = []

function messenger(overrides: Partial<TutorialMessenger> = {}): TutorialMessenger {
  return { ...PHONE, ...overrides, parts: { ...PHONE.parts, ...overrides.parts } }
}

/** The overlay of a step that waits, with the messenger the game gives. */
async function showOn(given: TutorialMessenger, step = aStep()) {
  const view = await mountView(TutorialOverlay, '/garage', { prepare: () => (useTutorialStore().tutorial = aTutorial(step, { messenger: given }).data) })
  mounted.push(view.wrapper)
  await flushPromises()

  return view.wrapper
}

describe('the device the mentor writes on', () => {
  beforeEach(async () => {
    localStorage.clear()
    storeToken('device-token')
    vi.stubGlobal('fetch', vi.fn())
    await setLocale('en')
  })

  afterEach(() => {
    mounted.splice(0).forEach((view) => view.unmount())
    vi.unstubAllGlobals()
  })

  it('is the old cracked phone when the server says nothing of the messenger', async () => {
    const view = await mountView(TutorialOverlay, '/garage', { prepare: () => (useTutorialStore().tutorial = aTutorial().data) })
    mounted.push(view.wrapper)
    await flushPromises()

    expect(view.wrapper.get('[data-test="tutorial-phone"]').attributes('data-device')).toBe('phone')
    expect(view.wrapper.find('[data-test="tutorial-crack"]').exists()).toBe(true)
    expect(view.wrapper.find('[data-test="tutorial-status"]').exists()).toBe(true)
  })

  it.each(['letter', 'tablet', 'plain'] as const)('shows who writes, the messages and the buttons on a %s', async (device) => {
    const wrapper = await showOn(messenger({ device }), aStep({ sender: 'Harbour master', optional: true, decline: 'Later' }))

    expect(wrapper.get('[data-test="tutorial-phone"]').attributes('data-device')).toBe(device)
    expect(wrapper.get('[data-test="tutorial-sender"]').text()).toBe('Harbour master')
    expect(wrapper.findAll('[data-test="tutorial-sms"]')).toHaveLength(2)
    expect(wrapper.get('[data-test="tutorial-reply"]').text()).toBe('OK, filling up')
    expect(wrapper.get('[data-test="tutorial-decline"]').text()).toBe('Later')
    expect(wrapper.find('[data-test="tutorial-crack"]').exists()).toBe(false)

    await wrapper.get('[data-test="tutorial-who"]').trigger('click')
    expect(wrapper.find('[data-test="tutorial-deflect"]').exists()).toBe(true)
  })

  it('leaves out the parts the game turns off', async () => {
    const wrapper = await showOn(messenger({ parts: { crack: false, status_bar: false, input: false, who: false } }))

    expect(wrapper.find('[data-test="tutorial-crack"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="tutorial-status"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="tutorial-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="tutorial-who"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="tutorial-reply"]').exists()).toBe(true)
  })

  it('puts the messages in the screen of a frame of the game, and falls back to the plain window without one', async () => {
    const framed = await showOn(messenger({ device: 'picture', frame_url: '/api/v1/media/frame.png', screen: { top: 12, right: 8, bottom: 20, left: 9 } }))

    expect(framed.get('[data-test="tutorial-frame"]').attributes('src')).toBe('/api/v1/media/frame.png')
    expect(framed.get('[data-test="tutorial-screen"]').attributes('style')).toContain('top: 12%')
    expect(framed.get('[data-test="tutorial-screen"]').attributes('style')).toContain('left: 9%')

    const bare = await showOn(messenger({ device: 'picture' }))

    expect(bare.get('[data-test="tutorial-phone"]').attributes('data-device')).toBe('plain')
  })

  it('answers the messages from any device', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(aTutorial(null)), { status: 200, headers: { 'Content-Type': 'application/json' } }))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = await showOn(messenger({ device: 'letter' }))

    await wrapper.get('[data-test="tutorial-reply"]').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls.some(([url, init]) => String(url).endsWith('/v1/tutorial/reply') && init?.method === 'POST')).toBe(true)
  })

  it('gives the goal bar the icon of the device, or the one the game gave', async () => {
    const drawn = await mountView(TutorialGoal, '/garage', { prepare: () => (useTutorialStore().tutorial = aTutorial(aStep({ acknowledged: true }), { goal: aGoal(), messenger: messenger({ device: 'letter' }) }).data) })
    mounted.push(drawn.wrapper)

    expect(drawn.wrapper.get('[data-test="device-icon"]').attributes('data-device')).toBe('letter')

    const given = await mountView(TutorialGoal, '/garage', { prepare: () => (useTutorialStore().tutorial = aTutorial(aStep({ acknowledged: true }), { goal: aGoal(), messenger: messenger({ icon_url: '/api/v1/media/icon.png' }) }).data) })
    mounted.push(given.wrapper)

    expect(given.wrapper.get('[data-test="device-icon"]').attributes('src')).toBe('/api/v1/media/icon.png')
  })
})
