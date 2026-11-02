import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { readToken, storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { respond, serveApi } from '@/test/fetchServer'
import { mountView } from '@/test/mountView'

import AccountSecureView from './AccountSecureView.vue'

const fetchMock = vi.fn()

async function open(query = '?token=secret-from-the-mail') {
  const mounted = await mountView(AccountSecureView, `/account/secure${query}`)
  await flushPromises()

  return mounted
}

describe('AccountSecureView', () => {
  beforeEach(async () => {
    localStorage.clear()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    await setLocale('en')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('says what it will do and waits: nothing is sent by opening the page', async () => {
    const calls = serveApi(fetchMock, {})

    const { wrapper } = await open()

    expect(wrapper.get('[data-test="title"]').text()).toBe('Was this not you?')
    expect(wrapper.get('[data-test="body"]').text()).toContain('sign out every device')
    expect(calls).toHaveLength(0)
  })

  it('leaves no secret in the address', async () => {
    serveApi(fetchMock, {})

    const { router } = await open()

    expect(router.currentRoute.value.fullPath).toBe('/account/secure')
  })

  it('secures the garage with the link the page was opened with, when the driver presses the button', async () => {
    const calls = serveApi(fetchMock, { 'POST /api/v1/account/email/revoke': () => respond(200, { purpose: 'revoke', restored: true }) })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(calls[0]?.body).toEqual({ token: 'secret-from-the-mail' })
    expect(wrapper.get('[data-test="title"]').text()).toBe('Garage secured')
    expect(wrapper.get('[data-test="body"]').text()).toBe('We put the old e-mail address back and signed out every device.')
    expect(wrapper.get('[data-test="next"]').attributes('href')).toBe('/recover')
  })

  it('says it differently when there was no address to put back', async () => {
    serveApi(fetchMock, { 'POST /api/v1/account/email/revoke': () => respond(200, { purpose: 'revoke', restored: false }) })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="body"]').text()).toContain('no change of e-mail is waiting any more')
  })

  it('ends the session of this device if it was one of the garage, which the server signed out', async () => {
    storeToken('device-token')
    serveApi(fetchMock, {
      'POST /api/v1/account/email/revoke': () => respond(200, { purpose: 'revoke', restored: true }),
      'GET /api/v1/me': () => respond(401, { message: 'Unauthenticated.' }),
    })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(readToken()).toBeNull()
  })

  it('does not send the same request twice for a double click', async () => {
    const calls = serveApi(fetchMock, { 'POST /api/v1/account/email/revoke': () => respond(200, { purpose: 'revoke', restored: false }) })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(calls.filter((call) => call.url === '/api/v1/account/email/revoke')).toHaveLength(1)
  })

  it('says that the link does not work when it was used, expired or never existed', async () => {
    serveApi(fetchMock, { 'POST /api/v1/account/email/revoke': () => respond(422, { message: 'This link is not valid or has expired.', errors: { token: ['x'] } }) })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="title"]').text()).toBe('This link does not work')
    expect(wrapper.get('[data-test="body"]').text()).toBe('It was already used, it has expired or it is not complete.')
  })

  it('says it when there were too many tries', async () => {
    serveApi(fetchMock, { 'POST /api/v1/account/email/revoke': () => respond(429, { message: '' }) })
    const { wrapper } = await open()

    await wrapper.get('[data-test="secure"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="body"]').text()).toBe('Too many attempts. Try again later.')
  })

  it('has nothing to send without a link', async () => {
    const calls = serveApi(fetchMock, {})

    const { wrapper } = await open('')

    expect(wrapper.get('[data-test="title"]').text()).toBe('This link does not work')
    expect(calls).toHaveLength(0)
  })
})
