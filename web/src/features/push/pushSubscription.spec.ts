import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { respond, serveApi } from '@/test/fetchServer'

import { disablePush, enablePush, keyToBytes, loadPushConfig, syncPush, type PushConfig } from './pushSubscription'

// Subscribing this device to the push messages of the game: asking for the permission, the key of the game, and telling the server. The browser is held by these tests.

const KEY = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'
const OTHER_KEY = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'
const ENABLED: PushConfig = { enabled: true, public_key: KEY, devices: 0 }

const fetchMock = vi.fn()

/** The bytes of a key, as the browser holds them in the options of a subscription. */
const bytesOf = (key: string): ArrayBuffer => keyToBytes(key).buffer as ArrayBuffer

/** A subscription of the browser, made with a key. */
function aSubscription(key: string = KEY) {
  return {
    endpoint: 'https://fcm.googleapis.com/fcm/send/device-1',
    options: { applicationServerKey: bytesOf(key) },
    toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/device-1', keys: { p256dh: 'p', auth: 'a' } }),
    unsubscribe: vi.fn(async () => true),
  }
}

/** The browser: a registration that holds a subscription (or none) and makes one when asked, and the permission it grants. */
function browser(held: ReturnType<typeof aSubscription> | null, permission: NotificationPermission = 'granted') {
  const made = aSubscription()
  const pushManager = { getSubscription: vi.fn(async () => held), subscribe: vi.fn(async () => made) }

  Object.defineProperty(navigator, 'serviceWorker', { value: { ready: Promise.resolve({ pushManager }) }, configurable: true })
  vi.stubGlobal('PushManager', class {})
  vi.stubGlobal('Notification', { permission, requestPermission: vi.fn(async () => permission) })
  Object.defineProperty(navigator, 'userAgent', { value: 'Mozilla/5.0 Chrome/126.0', configurable: true })

  return { pushManager, made }
}

describe('push subscription', () => {
  beforeEach(() => {
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    storeToken('device-token')
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()

    for (const name of ['serviceWorker', 'userAgent']) {
      // @ts-expect-error: put the page back as the test environment has it: the own properties go and the ones of the prototype are seen again
      delete navigator[name]
    }
  })

  it('turns the key of the game into the bytes a browser subscribes with', () => {
    const bytes = keyToBytes(KEY)

    expect(bytes).toHaveLength(65)
    expect(bytes[0]).toBe(4)
  })

  it('reads what the game says about push', async () => {
    serveApi(fetchMock, { 'GET /api/v1/push/config': () => respond(200, { data: { enabled: true, public_key: KEY, devices: 2 } }) })

    expect(await loadPushConfig()).toEqual({ enabled: true, public_key: KEY, devices: 2 })
  })

  it('asks for the permission, subscribes with the key of the game and tells the server, with the time zone and the language', async () => {
    const { pushManager } = browser(null, 'granted')
    const calls = serveApi(fetchMock, { 'PUT /api/v1/push/subscription': () => respond(200, { data: { subscribed: true, devices: 1 } }) })

    expect(await enablePush(ENABLED, 'fr')).toBe('subscribed')

    expect(Notification.requestPermission).toHaveBeenCalledOnce()
    expect(pushManager.subscribe).toHaveBeenCalledExactlyOnceWith({ userVisibleOnly: true, applicationServerKey: expect.any(Uint8Array) })
    expect(calls).toEqual([
      {
        url: '/api/v1/push/subscription',
        method: 'PUT',
        body: { endpoint: 'https://fcm.googleapis.com/fcm/send/device-1', keys: { p256dh: 'p', auth: 'a' }, timezone: expect.any(String), locale: 'fr' },
      },
    ])
  })

  it('does not subscribe a driver who said no, and does not ask a browser that cannot', async () => {
    const { pushManager } = browser(null, 'denied')
    const calls = serveApi(fetchMock, {})

    expect(await enablePush(ENABLED, 'pl')).toBe('denied')
    expect(pushManager.subscribe).not.toHaveBeenCalled()
    expect(calls).toEqual([])

    // @ts-expect-error: a browser without workers has no such property
    delete navigator.serviceWorker

    expect(await enablePush(ENABLED, 'pl')).toBe('unsupported')
  })

  it('does not ask for the permission when the game does not send', async () => {
    browser(null)

    expect(await enablePush({ enabled: false, public_key: null, devices: 0 }, 'pl')).toBe('unavailable')
    expect(Notification.requestPermission).not.toHaveBeenCalled()
  })

  it('makes the subscription again when the game has another key now', async () => {
    const old = aSubscription(OTHER_KEY)
    const { pushManager } = browser(old)
    serveApi(fetchMock, { 'PUT /api/v1/push/subscription': () => respond(200, { data: { subscribed: true, devices: 1 } }) })

    expect(await enablePush(ENABLED, 'pl')).toBe('subscribed')
    expect(old.unsubscribe).toHaveBeenCalledOnce()
    expect(pushManager.subscribe).toHaveBeenCalledOnce()
  })

  it('keeps the subscription it has when the key is the same, and tells the server about it', async () => {
    const held = aSubscription()
    const { pushManager } = browser(held)
    serveApi(fetchMock, { 'PUT /api/v1/push/subscription': () => respond(200, { data: { subscribed: true, devices: 1 } }) })

    await enablePush(ENABLED, 'pl')

    expect(held.unsubscribe).not.toHaveBeenCalled()
    expect(pushManager.subscribe).not.toHaveBeenCalled()
  })

  describe('at the start of the app', () => {
    it('tells the server again about the subscription the browser holds, and never asks for the permission', async () => {
      browser(aSubscription(), 'granted')
      const calls = serveApi(fetchMock, { 'PUT /api/v1/push/subscription': () => respond(200, { data: { subscribed: true, devices: 1 } }) })

      await syncPush(ENABLED, 'de')

      expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual(['PUT /api/v1/push/subscription'])
      expect(Notification.requestPermission).not.toHaveBeenCalled()
    })

    it('does nothing for a driver who has not agreed, or whose browser holds no subscription', async () => {
      browser(aSubscription(), 'default')
      const calls = serveApi(fetchMock, {})

      await syncPush(ENABLED, 'de')
      browser(null, 'granted')
      await syncPush(ENABLED, 'de')

      expect(calls).toEqual([])
    })

    it('drops a subscription that the game cannot serve any more (it sends no more, or has another key), and tells the server', async () => {
      const held = aSubscription(OTHER_KEY)
      browser(held)
      const calls = serveApi(fetchMock, { 'DELETE /api/v1/push/subscription': () => respond(200, { data: { removed: true, devices: 0 } }) })

      await syncPush(ENABLED, 'pl')

      expect(held.unsubscribe).toHaveBeenCalledOnce()
      expect(calls).toEqual([{ url: '/api/v1/push/subscription', method: 'DELETE', body: { endpoint: 'https://fcm.googleapis.com/fcm/send/device-1' } }])
    })
  })

  it('switches notifications off on this device: the browser forgets it and the server is told', async () => {
    const held = aSubscription()
    browser(held)
    const calls = serveApi(fetchMock, { 'DELETE /api/v1/push/subscription': () => respond(200, { data: { removed: true, devices: 0 } }) })

    await disablePush()

    expect(held.unsubscribe).toHaveBeenCalledOnce()
    expect(calls).toHaveLength(1)

    browser(null)
    await disablePush()

    expect(calls).toHaveLength(1)
  })
})
