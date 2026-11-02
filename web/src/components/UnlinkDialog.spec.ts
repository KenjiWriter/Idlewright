import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { AccountIdentity, AccountProvider, Me } from '@/api/types'
import { storeToken } from '@/auth/token'
import { goTo } from '@/features/account/redirect'
import { readUnlinkIntent } from '@/features/account/unlinkIntent'
import { setLocale } from '@/i18n'
import { useSessionStore } from '@/stores/session'
import { respond, serveApi, type Call } from '@/test/fetchServer'
import { mountView } from '@/test/mountView'

import UnlinkDialog from './UnlinkDialog.vue'

vi.mock('@/features/account/redirect', () => ({ goTo: vi.fn() }))

const fetchMock = vi.fn()

const email: AccountIdentity = { provider: 'email', email: 't***@example.com' }
const google: AccountIdentity = { provider: 'google', email: 'm***@gmail.com' }

function profile(identities: AccountIdentity[]): Me {
  return { id: 1, name: 'Maciek_Turbo', locale: 'en', is_guest: false, has_recovery_code: false, identities }
}

function reauth(valid: boolean, methods: AccountProvider[] = ['email', 'google']) {
  return respond(200, { data: { valid, expires_in: valid ? 600 : 0, methods } })
}

/** Opens the dialog for a provider on a server that answers by the given routes. */
async function open(provider: AccountProvider, routes: Record<string, () => Response>, identities = [email, google]) {
  const calls = serveApi(fetchMock, routes)
  const mounted = await mountView(UnlinkDialog, '/', {
    props: { provider },
    prepare: () => {
      useSessionStore().me = profile(identities)
    },
  })
  await flushPromises()

  return { ...mounted, calls }
}

function made(calls: Call[], method: string, url: string): Call[] {
  return calls.filter((call) => call.method === method && call.url === url)
}

describe('UnlinkDialog', () => {
  beforeEach(async () => {
    localStorage.clear()
    sessionStorage.clear()
    fetchMock.mockReset()
    vi.mocked(goTo).mockReset()
    vi.stubGlobal('fetch', fetchMock)
    await setLocale('en')
    storeToken('device-token')
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  describe('proving who the driver is', () => {
    it('asks whether this device has the proof, and offers the ways to get it when it has not', async () => {
      const { wrapper, calls } = await open('google', { 'GET /api/v1/account/reauth': () => reauth(false) })

      expect(made(calls, 'GET', '/api/v1/account/reauth')).toHaveLength(1)
      expect(wrapper.get('[data-test="dialog-title"]').text()).toBe('Unlink Google?')
      expect(wrapper.get('[data-test="prove-email"]').text()).toBe('Send a link to t***@example.com')
      expect(wrapper.get('[data-test="prove-google"]').text()).toBe('Sign in again with Google')
      expect(wrapper.find('[data-test="unlink"]').exists()).toBe(false)
    })

    it('offers only what the account has: no e-mail for an account without one', async () => {
      const { wrapper } = await open('facebook', { 'GET /api/v1/account/reauth': () => reauth(false, ['google']) }, [google, { provider: 'facebook', email: null }])

      expect(wrapper.find('[data-test="prove-email"]').exists()).toBe(false)
      expect(wrapper.find('[data-test="prove-google"]').exists()).toBe(true)
    })

    it('says so when nothing can prove it', async () => {
      const { wrapper } = await open('google', { 'GET /api/v1/account/reauth': () => reauth(false, []) })

      expect(wrapper.get('[data-test="no-method"]').text()).toContain('Nothing can confirm it right now')
    })

    it('sends a link to the e-mail of the account and waits for it to be opened', async () => {
      const { wrapper, calls } = await open('google', {
        'GET /api/v1/account/reauth': () => reauth(false),
        'POST /api/v1/account/reauth/email': () => respond(202, { message: 'We sent a link to the e-mail of the account.', expires_in: 1800 }),
      })

      await wrapper.get('[data-test="prove-email"]').trigger('click')
      await flushPromises()

      expect(made(calls, 'POST', '/api/v1/account/reauth/email')).toHaveLength(1)
      expect(wrapper.get('[data-test="waiting"]').text()).toContain('We sent a link to the e-mail of the account.')
      expect(wrapper.find('[data-test="prove-options"]').exists()).toBe(false)
    })

    it('goes on to the confirmation by itself when the link was opened somewhere', async () => {
      vi.useFakeTimers()
      let proved = false
      const { wrapper } = await open('google', {
        'GET /api/v1/account/reauth': () => reauth(proved),
        'POST /api/v1/account/reauth/email': () => respond(202, { message: 'Sent.', expires_in: 1800 }),
      })

      await wrapper.get('[data-test="prove-email"]').trigger('click')
      await flushPromises()
      expect(wrapper.find('[data-test="waiting"]').exists()).toBe(true)

      proved = true
      await vi.advanceTimersByTimeAsync(3100)
      await flushPromises()

      expect(wrapper.find('[data-test="waiting"]').exists()).toBe(false)
      expect(wrapper.find('[data-test="unlink"]').exists()).toBe(true)
    })

    it('stops asking when the dialog is closed', async () => {
      vi.useFakeTimers()
      const { wrapper, calls } = await open('google', {
        'GET /api/v1/account/reauth': () => reauth(false),
        'POST /api/v1/account/reauth/email': () => respond(202, { message: 'Sent.', expires_in: 1800 }),
      })
      await wrapper.get('[data-test="prove-email"]').trigger('click')
      await flushPromises()

      wrapper.unmount()
      const before = made(calls, 'GET', '/api/v1/account/reauth').length
      await vi.advanceTimersByTimeAsync(10_000)

      expect(made(calls, 'GET', '/api/v1/account/reauth')).toHaveLength(before)
    })

    it('sends the browser to the provider, and remembers what it was doing there', async () => {
      const { wrapper, calls } = await open('email', {
        'GET /api/v1/account/reauth': () => reauth(false),
        'POST /api/v1/account/reauth/google/start': () => respond(200, { url: 'https://accounts.example/auth?state=abc', verifier: 'verifier-1' }),
      })

      await wrapper.get('[data-test="prove-google"]').trigger('click')
      await flushPromises()

      expect(made(calls, 'POST', '/api/v1/account/reauth/google/start')).toHaveLength(1)
      expect(goTo).toHaveBeenCalledWith('https://accounts.example/auth?state=abc')
      expect(JSON.parse(localStorage.getItem('ride.oauth') ?? '{}')).toMatchObject({ provider: 'google', verifier: 'verifier-1' })
      expect(readUnlinkIntent()).toBe('email')
    })

    it('forgets what it was doing when the provider could not be started', async () => {
      const { wrapper } = await open('email', {
        'GET /api/v1/account/reauth': () => reauth(false),
        'POST /api/v1/account/reauth/google/start': () => respond(404, { message: 'This sign-in is not available.', code: 'provider_unavailable' }),
      })

      await wrapper.get('[data-test="prove-google"]').trigger('click')
      await flushPromises()

      expect(goTo).not.toHaveBeenCalled()
      expect(readUnlinkIntent()).toBeNull()
      expect(wrapper.get('[data-test="error"]').text()).toBe('This sign-in is not available.')
    })
  })

  describe('confirming', () => {
    const proved = { 'GET /api/v1/account/reauth': () => reauth(true) }

    it('goes straight to what unlinking does when the proof is there', async () => {
      const { wrapper } = await open('google', proved)

      expect(wrapper.find('[data-test="prove-options"]').exists()).toBe(false)
      expect(wrapper.get('[data-test="confirm-intro"]').text()).toBe('Once unlinked, Google will no longer let anyone into this garage.')
      expect(wrapper.get('[data-test="consequences"]').text()).toContain('Devices that signed in this way will be signed out')
    })

    it('says that the data of a provider stay with the provider, and that a notice is sent', async () => {
      const { wrapper } = await open('google', proved)

      const text = wrapper.get('[data-test="consequences"]').text()
      expect(text).toContain('The Google account and its data stay with Google: we do not delete them.')
      expect(text).toContain('short notice')
    })

    it('says that the address goes and its links stop, for the e-mail', async () => {
      const { wrapper } = await open('email', proved)

      const text = wrapper.get('[data-test="consequences"]').text()
      expect(text).toContain('The e-mail address leaves the garage')
      expect(text).not.toContain('stay with')
    })

    it('unlinks, updates the profile and says it is done', async () => {
      const { wrapper, calls } = await open('google', {
        ...proved,
        'DELETE /api/v1/account/identities/google': () => respond(200, { data: profile([email]) }),
      })

      await wrapper.get('[data-test="unlink"]').trigger('click')
      await flushPromises()

      expect(made(calls, 'DELETE', '/api/v1/account/identities/google')).toHaveLength(1)
      expect(useSessionStore().me?.identities).toEqual([email])
      expect(wrapper.get('[data-test="dialog-title"]').text()).toBe('Unlinked: Google')
      expect(wrapper.get('[data-test="done"]').text()).toBe('Google no longer signs in to this garage.')
      expect(wrapper.emitted('done')).toHaveLength(1)
    })

    it('goes back to proving who the driver is when the proof ran out meanwhile', async () => {
      const { wrapper } = await open('google', {
        ...proved,
        'DELETE /api/v1/account/identities/google': () => respond(403, { message: 'Confirm that it is you before you unlink a sign-in.', code: 'reauth_required' }),
      })

      await wrapper.get('[data-test="unlink"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-test="prove-options"]').exists()).toBe(true)
      expect(wrapper.get('[data-test="error"]').text()).toBe('Confirm that it is you before you unlink a sign-in.')
      expect(wrapper.emitted('done')).toBeUndefined()
    })

    it('shows what the server says about the last sign-in', async () => {
      const { wrapper } = await open('google', {
        ...proved,
        'DELETE /api/v1/account/identities/google': () => respond(422, { message: 'This is the last sign-in of this garage, so it cannot be unlinked. Add another one first.', code: 'last_method' }),
      })

      await wrapper.get('[data-test="unlink"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[data-test="error"]').text()).toContain('the last sign-in')
      expect(useSessionStore().me?.identities).toHaveLength(2)
    })

    it('does not send the same request twice for a double click', async () => {
      let release: (response: Response) => void = () => undefined
      const { wrapper, calls } = await open('google', {
        ...proved,
        'DELETE /api/v1/account/identities/google': () => new Promise<Response>((resolve) => (release = resolve)) as unknown as Response,
      })

      await wrapper.get('[data-test="unlink"]').trigger('click')
      await wrapper.get('[data-test="unlink"]').trigger('click')
      release(respond(200, { data: profile([email]) }))
      await flushPromises()

      expect(made(calls, 'DELETE', '/api/v1/account/identities/google')).toHaveLength(1)
    })
  })

  it('closes on Escape and forgets what it was doing', async () => {
    const { wrapper } = await open('google', { 'GET /api/v1/account/reauth': () => reauth(false) })
    sessionStorage.setItem('ride.unlink', JSON.stringify({ provider: 'google', at: Date.now() }))

    await wrapper.get('[data-test="dialog"]').trigger('keydown', { key: 'Escape' })

    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(readUnlinkIntent()).toBeNull()
  })

  it('shows what went wrong when the proof cannot be asked about', async () => {
    const { wrapper } = await open('google', { 'GET /api/v1/account/reauth': () => respond(500, { message: 'Server error' }) })

    expect(wrapper.get('[data-test="error"]').text()).toBe('Something went wrong. Try again.')
    expect(wrapper.find('[data-test="prove-options"]').exists()).toBe(true)
  })
})
