import { afterEach, describe, expect, it, vi } from 'vitest'

import { pushPermission, pushSupport } from './pushSupport'

// What a browser has for push: a service worker, push and notifications; on an iPhone or iPad only an app that was added to the home screen has them.

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1'

/** Gives the browser what push needs, and the way it is used. */
function browser({ workers = true, push = true, notifications = true, userAgent = 'Mozilla/5.0 Chrome/126.0', installed = false, platform = 'Win32', touch = 0 } = {}): void {
  if (workers) {
    Object.defineProperty(navigator, 'serviceWorker', { value: {}, configurable: true })
  } else {
    // @ts-expect-error: a browser without workers has no such property
    delete navigator.serviceWorker
  }

  vi.stubGlobal('PushManager', push ? class {} : undefined)

  if (!push) {
    // @ts-expect-error: a browser without push has no such property
    delete window.PushManager
  }

  if (notifications) {
    vi.stubGlobal('Notification', { permission: 'default' })
  } else {
    // @ts-expect-error: a browser without notifications has no such property
    delete window.Notification
  }

  Object.defineProperty(navigator, 'userAgent', { value: userAgent, configurable: true })
  Object.defineProperty(navigator, 'platform', { value: platform, configurable: true })
  Object.defineProperty(navigator, 'maxTouchPoints', { value: touch, configurable: true })
  window.matchMedia = vi.fn().mockReturnValue({ matches: installed }) as unknown as typeof window.matchMedia
}

describe('pushSupport', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()

    for (const name of ['serviceWorker', 'userAgent', 'platform', 'maxTouchPoints']) {
      // @ts-expect-error: put the page back as the test environment has it: the own properties go and the ones of the prototype are seen again
      delete navigator[name]
    }
  })

  it('is ready in a browser that has workers, push and notifications', () => {
    browser()

    expect(pushSupport()).toBe('ready')
  })

  it('is unsupported where one of the three is missing', () => {
    for (const lacking of [{ workers: false }, { push: false }, { notifications: false }]) {
      browser(lacking)

      expect(pushSupport()).toBe('unsupported')
    }
  })

  it('asks an iPhone or an iPad to install the app first, because Safari gives a tab no push', () => {
    browser({ userAgent: IPHONE, push: false, notifications: false })
    expect(pushSupport()).toBe('needs-install')

    // An iPad says it is a Mac, but a Mac has no touch screen.
    browser({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', platform: 'MacIntel', touch: 5, push: false, notifications: false })
    expect(pushSupport()).toBe('needs-install')

    browser({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', platform: 'MacIntel', touch: 0 })
    expect(pushSupport()).toBe('ready')
  })

  it('is ready on an iPhone once the app was added to the home screen', () => {
    browser({ userAgent: IPHONE, installed: true })

    expect(pushSupport()).toBe('ready')
  })

  it('says what the driver told the browser, and that there is nothing to tell where there are no notifications', () => {
    browser()
    expect(pushPermission()).toBe('default')

    browser({ notifications: false })
    expect(pushPermission()).toBe('unsupported')
  })
})
