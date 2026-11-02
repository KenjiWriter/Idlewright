import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { Me } from '@/api/types'
import { readToken, storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { useSessionStore } from '@/stores/session'
import { respond, serveApi, type Call } from '@/test/fetchServer'
import { mountView } from '@/test/mountView'

import ConsentView from './ConsentView.vue'

vi.mock('@/legal/changes', () => ({
  changesSince: vi.fn((accepted: string | null) => (accepted === '2026-10-23' ? ['The privacy policy names the mail provider.', 'The terms say how long a garage is kept.'] : [])),
}))

const fetchMock = vi.fn()

function profile(accepted: string | null = '2026-10-23', required = true): Me {
  return {
    id: 1,
    name: 'Maciek_Turbo',
    locale: 'en',
    is_guest: false,
    has_recovery_code: false,
    identities: [{ provider: 'email', email: 't***@example.com' }],
    consent: { required, accepted_version: accepted, current_version: '2026-11-01' },
  }
}

const legal = { data: { min_age: 16, version: '2026-11-01', guest_expiry_days: 90, operator: { name: null, address: null, email: null } } }

async function open(me: Me | null = profile(), extra: Record<string, () => Response> = {}) {
  const calls = serveApi(fetchMock, {
    'GET /api/v1/meta/legal': () => respond(200, legal),
    'GET /api/v1/me': () => respond(200, { data: me }),
    ...extra,
  })
  const mounted = await mountView(ConsentView, '/consent', {
    prepare: () => {
      useSessionStore().me = me
    },
  })
  await flushPromises()

  return { ...mounted, calls }
}

function made(calls: Call[], method: string, url: string): Call[] {
  return calls.filter((call) => call.method === method && call.url === url)
}

describe('ConsentView', () => {
  beforeEach(async () => {
    localStorage.clear()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    await setLocale('en')
    storeToken('device-token')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('tells the driver that the terms changed, and which version they accepted and which is in force', async () => {
    const { wrapper } = await open()

    expect(wrapper.get('[data-test="title"]').text()).toBe('Our terms have changed')
    expect(wrapper.get('[data-test="versions"]').text()).toContain('Version you accepted before')
    expect(wrapper.get('[data-test="versions"]').text()).toContain('Version in force')
  })

  it('lists what changed when the one who changed the texts wrote it down', async () => {
    const { wrapper } = await open()

    expect(wrapper.findAll('[data-test="change-list"] li').map((item) => item.text())).toEqual([
      'The privacy policy names the mail provider.',
      'The terms say how long a garage is kept.',
    ])
    expect(wrapper.find('[data-test="no-list"]').exists()).toBe(false)
  })

  it('says it in general when nothing was written down, and still links the texts', async () => {
    const { wrapper } = await open(profile('2026-10-01'))

    expect(wrapper.find('[data-test="change-list"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="no-list"]').text()).toContain('Read them in full before you accept')
    expect(wrapper.get('[data-test="changes"] [data-test="terms-link"]').attributes('target')).toBe('_blank')
    expect(wrapper.get('[data-test="changes"] [data-test="privacy-link"]').attributes('href')).toBe('/privacy')
  })

  it('says so when the driver accepted before the texts had a version', async () => {
    const { wrapper } = await open(profile(null))

    expect(wrapper.get('[data-test="versions"]').text()).toContain('from before versions were numbered')
  })

  it('asks again for the age the server says', async () => {
    const { wrapper } = await open()

    expect(wrapper.get('[data-test="age-text"]').text()).toContain('16')
  })

  it('does not accept without both boxes, and asks the server nothing', async () => {
    const { wrapper, calls } = await open()

    await wrapper.get('[data-test="terms"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-test="error"]').text()).toBe('Accept the terms and confirm your age to continue.')
    expect(made(calls, 'POST', '/api/v1/account/consent')).toHaveLength(0)
  })

  it('forgets the complaint about a missing box once the driver ticks one', async () => {
    const { wrapper } = await open()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.find('[data-test="error"]').exists()).toBe(true)

    await wrapper.get('[data-test="terms"]').setValue(true)

    expect(wrapper.find('[data-test="error"]').exists()).toBe(false)
  })

  it('accepts the version that was shown and goes to the garage', async () => {
    const { wrapper, calls, router } = await open(profile(), {
      'POST /api/v1/account/consent': () => respond(200, { data: profile('2026-11-01', false) }),
    })

    await wrapper.get('[data-test="terms"]').setValue(true)
    await wrapper.get('[data-test="age"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(made(calls, 'POST', '/api/v1/account/consent')[0]?.body).toEqual({ accept_terms: true, confirm_age: true, version: '2026-11-01' })
    expect(useSessionStore().needsConsent).toBe(false)
    // The screens are loaded when they are opened, which takes a moment.
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('garage'))
  })

  it('shows the new version when the texts changed while the driver read', async () => {
    const { wrapper } = await open(profile(), {
      'POST /api/v1/account/consent': () =>
        respond(409, { message: 'The texts changed while you were reading. Read the new version.', code: 'consent_version_changed', current_version: '2026-11-15' }),
      'GET /api/v1/me': () => respond(200, { data: { ...profile(), consent: { required: true, accepted_version: '2026-10-23', current_version: '2026-11-15' } } }),
    })

    await wrapper.get('[data-test="terms"]').setValue(true)
    await wrapper.get('[data-test="age"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-test="error"]').text()).toBe('The texts changed while you were reading. Read the new version.')
    expect(useSessionStore().me?.consent?.current_version).toBe('2026-11-15')
    expect((wrapper.get('[data-test="terms"]').element as HTMLInputElement).checked).toBe(false)
  })

  it('does not double the request for a double click', async () => {
    let release: (response: Response) => void = () => undefined
    const { wrapper, calls } = await open(profile(), {
      'POST /api/v1/account/consent': () => new Promise<Response>((resolve) => (release = resolve)) as unknown as Response,
    })
    await wrapper.get('[data-test="terms"]').setValue(true)
    await wrapper.get('[data-test="age"]').setValue(true)

    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    release(respond(200, { data: profile('2026-11-01', false) }))
    await flushPromises()

    expect(made(calls, 'POST', '/api/v1/account/consent')).toHaveLength(1)
  })

  it('offers the way out: sign out, take the data, delete the account', async () => {
    const { wrapper } = await open()

    expect(wrapper.get('[data-test="decline"]').text()).toContain('Without agreeing you cannot play')
    expect(wrapper.find('[data-test="download"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="delete"]').exists()).toBe(true)
  })

  it('signs out and goes to the start for a driver who does not agree', async () => {
    const { wrapper, router } = await open()

    await wrapper.get('[data-test="leave"]').trigger('click')
    await flushPromises()

    expect(readToken()).toBeNull()
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('landing'))
  })

  it('goes to the garage when there is nothing to accept', async () => {
    const { router } = await open(profile('2026-11-01', false))

    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('garage'))
  })

  it('asks for the profile when the page was reloaded, and shows what it says', async () => {
    const asked = profile()
    const { wrapper, calls } = await open(null, { 'GET /api/v1/me': () => respond(200, { data: asked }) })

    expect(made(calls, 'GET', '/api/v1/me')).toHaveLength(1)
    expect(wrapper.get('[data-test="versions"]').text()).toContain('2026')
  })
})
