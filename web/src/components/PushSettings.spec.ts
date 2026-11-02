import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import * as subscription from '@/features/push/pushSubscription'
import * as support from '@/features/push/pushSupport'
import { i18n, setLocale } from '@/i18n'
import { respond, serveApi } from '@/test/fetchServer'

import PushSettings from './PushSettings.vue'

vi.mock('@/features/push/pushSupport', () => ({ pushSupport: vi.fn(), pushPermission: vi.fn() }))
vi.mock('@/features/push/pushSubscription', () => ({
  loadPushConfig: vi.fn(),
  enablePush: vi.fn(),
  disablePush: vi.fn(),
  hasSubscription: vi.fn(),
  syncPush: vi.fn(),
}))

const fetchMock = vi.fn()
const KINDS = { car_stopped: true, car_arrived: true, job_done: false }

function world({ browser = 'ready', permission = 'granted', enabled = true, held = true }: { browser?: string; permission?: string; enabled?: boolean; held?: boolean } = {}): void {
  vi.mocked(support.pushSupport).mockReturnValue(browser as never)
  vi.mocked(support.pushPermission).mockReturnValue(permission as never)
  vi.mocked(subscription.loadPushConfig).mockResolvedValue({ enabled, public_key: enabled ? 'KEY' : null, devices: held ? 1 : 0 })
  vi.mocked(subscription.hasSubscription).mockResolvedValue(held)
}

async function show() {
  setActivePinia(createPinia())
  const wrapper = mount(PushSettings, { global: { plugins: [i18n] } })
  await flushPromises()

  return wrapper
}

describe('PushSettings', () => {
  beforeEach(async () => {
    localStorage.clear()
    vi.resetAllMocks()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    storeToken('device-token')
    await setLocale('en')
  })

  afterEach(() => vi.unstubAllGlobals())

  it('is not there when the game sends nothing or the browser cannot take it', async () => {
    serveApi(fetchMock, {})

    world({ enabled: false })
    expect((await show()).find('[data-test="push-settings"]').exists()).toBe(false)

    world({ browser: 'unsupported' })
    expect((await show()).find('[data-test="push-settings"]').exists()).toBe(false)
    expect(subscription.loadPushConfig).toHaveBeenCalledTimes(1)
  })

  it('shows what the driver is told of when the notifications are on, and changes one kind at a time', async () => {
    world()
    const calls = serveApi(fetchMock, {
      'GET /api/v1/push/preferences': () => respond(200, { data: { kinds: KINDS } }),
      'PATCH /api/v1/push/preferences': () => respond(200, { data: { kinds: { ...KINDS, job_done: true } } }),
    })
    const wrapper = await show()

    expect(wrapper.get('[data-test="push-settings-state"]').text()).toBe('Notifications are on on this device.')
    expect(wrapper.findAll('[data-test="push-settings-kinds"] input').map((box) => (box.element as HTMLInputElement).checked)).toEqual([true, true, false])

    const box = wrapper.get('[data-test="push-kind-job_done"]')
    ;(box.element as HTMLInputElement).checked = true
    await box.trigger('change')
    await flushPromises()

    expect(calls.find((call) => call.method === 'PATCH')?.body).toEqual({ kinds: { job_done: true } })
    expect((wrapper.get('[data-test="push-kind-job_done"]').element as HTMLInputElement).checked).toBe(true)
  })

  it('turns notifications on for this device from a tap', async () => {
    world({ held: false, permission: 'default' })
    vi.mocked(subscription.enablePush).mockImplementation(async () => {
      vi.mocked(support.pushPermission).mockReturnValue('granted')
      vi.mocked(subscription.hasSubscription).mockResolvedValue(true)

      return 'subscribed'
    })
    serveApi(fetchMock, { 'GET /api/v1/push/preferences': () => respond(200, { data: { kinds: KINDS } }) })
    const wrapper = await show()

    expect(wrapper.get('[data-test="push-settings-state"]').text()).toBe('Notifications are off on this device.')
    expect(wrapper.find('[data-test="push-settings-kinds"]').exists()).toBe(false)

    await wrapper.get('[data-test="push-enable"]').trigger('click')
    await flushPromises()

    expect(subscription.enablePush).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-test="push-settings-state"]').text()).toBe('Notifications are on on this device.')
  })

  it('turns them off for this device', async () => {
    world()
    vi.mocked(subscription.disablePush).mockImplementation(async () => {
      vi.mocked(subscription.hasSubscription).mockResolvedValue(false)
    })
    serveApi(fetchMock, { 'GET /api/v1/push/preferences': () => respond(200, { data: { kinds: KINDS } }) })
    const wrapper = await show()

    await wrapper.get('[data-test="push-disable"]').trigger('click')
    await flushPromises()

    expect(subscription.disablePush).toHaveBeenCalledOnce()
    expect(wrapper.get('[data-test="push-settings-state"]').text()).toBe('Notifications are off on this device.')
  })

  it('sends a test message and says so, and says what the server refused with', async () => {
    world()
    let answer = respond(200, { data: { queued: 1 } })
    serveApi(fetchMock, {
      'GET /api/v1/push/preferences': () => respond(200, { data: { kinds: KINDS } }),
      'POST /api/v1/push/test': () => answer,
    })
    const wrapper = await show()

    await wrapper.get('[data-test="push-test"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="push-settings-note"]').text()).toBe('A test notification is on its way.')

    answer = respond(429, { message: 'Too many attempts.' })
    await wrapper.get('[data-test="push-test"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-test="push-settings-note"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="push-settings-error"]').exists()).toBe(true)
  })

  it('says where to allow the notifications when the browser has been told no, and how to install the game on an iPhone', async () => {
    serveApi(fetchMock, { 'GET /api/v1/push/preferences': () => respond(200, { data: { kinds: KINDS } }) })

    world({ permission: 'denied', held: false })
    const denied = await show()
    expect(denied.get('[data-test="push-settings-state"]').text()).toContain('Allow them in the settings of the site')
    expect(denied.find('[data-test="push-enable"]').exists()).toBe(false)

    world({ browser: 'needs-install', permission: 'default', held: false })
    const install = await show()
    expect(install.get('[data-test="push-settings-state"]').text()).toContain('Add to Home Screen')
    expect(install.find('[data-test="push-enable"]').exists()).toBe(false)
  })
})
