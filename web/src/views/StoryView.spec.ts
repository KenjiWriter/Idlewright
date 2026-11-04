import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { respond, serveApi } from '@/test/fetchServer'
import { mountView } from '@/test/mountView'

import StoryView from './StoryView.vue'

const fetchMock = vi.fn()
const profile = { id: 1, name: 'Maciek_Turbo', locale: 'en', is_guest: true, has_recovery_code: false, identities: [] }

async function show() {
  const mounted = await mountView(StoryView, '/story')
  await flushPromises()

  return mounted
}

/** The driver asks the system for less motion. */
function reduceMotion(): void {
  vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }))
  window.matchMedia = vi.fn().mockReturnValue({ matches: true })
}

describe('StoryView', () => {
  beforeEach(async () => {
    localStorage.clear()
    storeToken('device-token')
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    serveApi(fetchMock, { 'GET /api/v1/me': () => respond(200, { data: profile }) })
    vi.useFakeTimers()
    await setLocale('en')
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    Reflect.deleteProperty(window, 'matchMedia')
  })

  it('starts with the words of the first scene, and says it is the first of four', async () => {
    const { wrapper } = await show()

    expect(wrapper.get('[data-test="story-text"]').text()).toBe('“Listen, I have to disappear for a while.”')
    expect(wrapper.text()).toContain('Scene 1 of 4')
    expect(wrapper.find('[data-test="story-sign"]').exists()).toBe(false)
  })

  it('moves on by itself when the time of the scene is up', async () => {
    const { wrapper } = await show()

    await vi.advanceTimersByTimeAsync(6_000)
    expect(wrapper.get('[data-test="story-text"]').text()).toContain('I’m leaving you the workshop')

    await vi.advanceTimersByTimeAsync(6_000)
    expect(wrapper.get('[data-test="story-text"]').text()).toContain('Złomowo lives on racing')
  })

  it('moves on at a tap, without waiting', async () => {
    const { wrapper } = await show()

    await wrapper.get('[data-test="story-stage"]').trigger('click')

    expect(wrapper.get('[data-test="story-text"]').text()).toContain('I’m leaving you the workshop')
  })

  it('lets the driver skip after three seconds, and not before', async () => {
    const { wrapper, router } = await show()

    expect(wrapper.find('[data-test="story-skip"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(3_000)
    expect(wrapper.find('[data-test="story-skip"]').exists()).toBe(true)

    await wrapper.get('[data-test="story-skip"]').trigger('click')
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('choose'))
  })

  it('puts the name of the driver on the sign in the last scene, with the first message of the mentor', async () => {
    const { wrapper } = await show()
    for (let scene = 0; scene < 3; scene += 1) {
      await wrapper.get('[data-test="story-stage"]').trigger('click')
    }

    expect(wrapper.get('[data-test="story-name"]').text()).toBe('Maciek_Turbo')
    expect(wrapper.get('[data-test="story-text"]').text()).toBe('“Manager of the workshop: Maciek_Turbo.”')
    expect(wrapper.get('[data-test="story-sms"]').text()).toBe('+48 555 010 112: It’s me. Don’t ask. Pick a car, I’ll text you the rest. — K.')
  })

  it('does not leave the last scene by itself, and offers the choice of a wreck after two seconds', async () => {
    const { wrapper, router } = await show()
    for (let scene = 0; scene < 3; scene += 1) {
      await wrapper.get('[data-test="story-stage"]').trigger('click')
    }

    expect(wrapper.find('[data-test="story-choose"]').exists()).toBe(false)

    await vi.advanceTimersByTimeAsync(60_000)
    expect(router.currentRoute.value.name).toBe('story')
    expect(wrapper.get('[data-test="story-choose"]').text()).toBe('Choose a wreck')

    await wrapper.get('[data-test="story-choose"]').trigger('click')
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('choose'))
  })

  it('keeps the camera and the clock still for a driver who asks for less motion, and offers everything at once', async () => {
    reduceMotion()
    const { wrapper } = await show()

    // Nothing moves on by itself, however long the driver reads.
    await vi.advanceTimersByTimeAsync(60_000)
    expect(wrapper.get('[data-test="story-text"]').text()).toBe('“Listen, I have to disappear for a while.”')
    expect(wrapper.find('[data-test="story-skip"]').exists()).toBe(true)

    for (let scene = 0; scene < 3; scene += 1) {
      await wrapper.get('[data-test="story-stage"]').trigger('click')
    }
    await vi.advanceTimersByTimeAsync(0)
    expect(wrapper.find('[data-test="story-choose"]').exists()).toBe(true)
  })

  it('speaks the language of the driver, with the French spaces in the right places', async () => {
    await setLocale('fr')
    const { wrapper } = await show()

    expect(wrapper.get('[data-test="story-text"]').text()).toBe('« Écoute, je dois disparaître un moment. »')

    await setLocale('en')
  })

  describe('the story of the game', () => {
    const letter = { device: 'letter' as const, parts: { crack: true, status_bar: true, input: true, who: true }, icon_url: null, frame_url: null, screen: null }

    function tells(data: Record<string, unknown>): void {
      serveApi(fetchMock, {
        'GET /api/v1/me': () => respond(200, { data: profile }),
        'GET /api/v1/tutorial/story': () =>
          respond(200, { data: { enabled: true, builtin: false, picture_url: '/api/v1/media/harbour.jpg', scenes: [], sender: null, sms: null, messenger: letter, ...data } }),
      })
    }

    it('tells its own scenes on its own picture, with the name of the driver and the first message on its device', async () => {
      tells({
        scenes: [
          { text: 'The harbour is yours now.', ms: 4_000, camera: 'pan-left', focus: { x: 30, y: 40 } },
          { text: 'Captain {name}, the boats wait.', ms: 5_000, camera: 'still', focus: { x: 50, y: 50 } },
        ],
        sender: 'Harbour master',
        sms: 'Pick a boat. I will write.',
      })
      const { wrapper } = await show()

      expect(wrapper.get('[data-test="story-text"]').text()).toBe('The harbour is yours now.')
      expect(wrapper.text()).toContain('Scene 1 of 2')
      expect(wrapper.get('[data-test="story-picture"]').attributes('src')).toBe('/api/v1/media/harbour.jpg')
      expect(wrapper.get('[data-test="story-picture"]').attributes('style')).toContain('object-position: 30% 40%')

      await vi.advanceTimersByTimeAsync(4_000)
      expect(wrapper.get('[data-test="story-text"]').text()).toBe('Captain Maciek_Turbo, the boats wait.')
      expect(wrapper.get('[data-test="story-sms"]').text()).toBe('Harbour master: Pick a boat. I will write.')
      expect(wrapper.get('[data-test="story-sms"]').classes()).toContain('story-note-letter')
    })

    it('writes the first message of the story of the app from the sender of the device that the game gave', async () => {
      tells({ builtin: true, picture_url: null, sender: 'K', messenger: { ...letter, device: 'phone' } })
      const { wrapper } = await show()
      for (let scene = 0; scene < 3; scene += 1) {
        await wrapper.get('[data-test="story-stage"]').trigger('click')
      }

      expect(wrapper.get('[data-test="story-sms"]').text()).toBe('K: It’s me. Don’t ask. Pick a car, I’ll text you the rest. — K.')
    })

    it('goes straight to the choice of a wreck when the game tells no story', async () => {
      tells({ enabled: false })
      const { router } = await show()

      await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('choose'))
    })
  })
})
