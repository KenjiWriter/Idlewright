import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/api/client'
import * as subscription from '@/features/push/pushSubscription'
import * as support from '@/features/push/pushSupport'
import { shouldAsk } from '@/features/push/softAsk'
import { i18n, setLocale } from '@/i18n'

import PushAsk from './PushAsk.vue'

vi.mock('@/features/push/pushSupport', () => ({ pushSupport: vi.fn(), pushPermission: vi.fn() }))
vi.mock('@/features/push/pushSubscription', () => ({
  loadPushConfig: vi.fn(),
  enablePush: vi.fn(),
  disablePush: vi.fn(),
  hasSubscription: vi.fn(),
  syncPush: vi.fn(),
}))

/** The browser and the game as the test says. */
function world({ browser = 'ready', permission = 'default', enabled = true, held = false }: { browser?: string; permission?: string; enabled?: boolean; held?: boolean } = {}): void {
  vi.mocked(support.pushSupport).mockReturnValue(browser as never)
  vi.mocked(support.pushPermission).mockReturnValue(permission as never)
  vi.mocked(subscription.loadPushConfig).mockResolvedValue({ enabled, public_key: enabled ? 'KEY' : null, devices: 0 })
  vi.mocked(subscription.hasSubscription).mockResolvedValue(held)
}

async function show() {
  setActivePinia(createPinia())
  const wrapper = mount(PushAsk, { global: { plugins: [i18n] } })
  await flushPromises()

  return wrapper
}

describe('PushAsk', () => {
  beforeEach(async () => {
    localStorage.clear()
    vi.resetAllMocks()
    await setLocale('en')
  })

  it('asks a driver who has not been asked, with a card of the game before the question of the browser', async () => {
    world()
    const wrapper = await show()

    expect(wrapper.get('[data-test="push-ask"]').text()).toContain('Tell you when a car stops?')
    expect(wrapper.get('[data-test="push-ask-body"]').text()).toContain('runs out of fuel')
    expect(subscription.enablePush).not.toHaveBeenCalled()
  })

  it('turns notifications on when the driver agrees, and goes away', async () => {
    world()
    vi.mocked(subscription.enablePush).mockResolvedValue('subscribed')
    const wrapper = await show()

    await wrapper.get('[data-test="push-ask-enable"]').trigger('click')
    await flushPromises()

    expect(subscription.enablePush).toHaveBeenCalledExactlyOnceWith({ enabled: true, public_key: 'KEY', devices: 0 }, 'en')
    expect(wrapper.find('[data-test="push-ask"]').exists()).toBe(false)
  })

  it('goes away when the browser was told no, and says when it could not be done', async () => {
    world()
    vi.mocked(subscription.enablePush).mockResolvedValue('denied')
    const denied = await show()
    await denied.get('[data-test="push-ask-enable"]').trigger('click')
    await flushPromises()

    expect(denied.find('[data-test="push-ask"]').exists()).toBe(false)

    vi.mocked(subscription.enablePush).mockResolvedValue('unavailable')
    const unavailable = await show()
    await unavailable.get('[data-test="push-ask-enable"]').trigger('click')
    await flushPromises()

    expect(unavailable.get('[data-test="push-ask-error"]').text()).toBe('Notifications cannot be turned on in this browser now.')
    expect(unavailable.find('[data-test="push-ask"]').exists()).toBe(true)
  })

  it('says what the server refused with', async () => {
    world()
    vi.mocked(subscription.enablePush).mockRejectedValue(new ApiError(422, 'This game does not send notifications.', 'push_unavailable'))
    const wrapper = await show()

    await wrapper.get('[data-test="push-ask-enable"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="push-ask-error"]').text()).toBe('This game does not send notifications.')
  })

  it('puts the question off for a week when the driver says "not now"', async () => {
    world()
    const wrapper = await show()

    await wrapper.get('[data-test="push-ask-later"]').trigger('click')

    expect(wrapper.find('[data-test="push-ask"]').exists()).toBe(false)
    expect(shouldAsk()).toBe(false)

    const again = await show()
    expect(again.find('[data-test="push-ask"]').exists()).toBe(false)
    expect(subscription.loadPushConfig).toHaveBeenCalledTimes(1)
  })

  it('does not ask a driver whose browser has been told, whose browser cannot, or whose game sends nothing', async () => {
    for (const setting of [{ permission: 'granted' }, { permission: 'denied' }, { held: true, permission: 'granted' }, { browser: 'unsupported' }, { enabled: false }]) {
      world(setting)

      expect((await show()).find('[data-test="push-ask"]').exists()).toBe(false)
    }
  })

  it('asks nothing of the server from a browser that cannot take push messages', async () => {
    world({ browser: 'unsupported' })
    await show()

    expect(subscription.loadPushConfig).not.toHaveBeenCalled()
  })

  it('tells a driver on an iPhone to put the game on the home screen first, with no button to turn anything on', async () => {
    world({ browser: 'needs-install' })
    const wrapper = await show()

    expect(wrapper.get('[data-test="push-ask-body"]').text()).toContain('Add to Home Screen')
    expect(wrapper.find('[data-test="push-ask-enable"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="push-ask-later"]').text()).toBe('OK')
  })
})
