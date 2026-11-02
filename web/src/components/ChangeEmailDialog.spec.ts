import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { respond, serveApi, type Call } from '@/test/fetchServer'
import { mountView } from '@/test/mountView'

import ChangeEmailDialog from './ChangeEmailDialog.vue'

const fetchMock = vi.fn()

async function open(routes: Record<string, () => Response> = {}) {
  const calls = serveApi(fetchMock, routes)
  const mounted = await mountView(ChangeEmailDialog, '/', { attachTo: document.body })

  return { ...mounted, calls }
}

async function send(wrapper: Awaited<ReturnType<typeof open>>['wrapper'], address: string) {
  await wrapper.get('[data-test="email"]').setValue(address)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

function made(calls: Call[], url: string): Call[] {
  return calls.filter((call) => call.method === 'POST' && call.url === url)
}

describe('ChangeEmailDialog', () => {
  beforeEach(async () => {
    localStorage.clear()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    await setLocale('en')
    storeToken('device-token')
  })

  afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('asks for the new address, and says what the change does before it is asked for', async () => {
    const { wrapper } = await open()

    expect(wrapper.get('[data-test="dialog-title"]').text()).toBe('Change e-mail')
    expect(wrapper.get('[data-test="intro"]').text()).toContain('Nothing changes until it is used')
    expect(wrapper.get('[data-test="email"]').attributes('autocomplete')).toBe('email')
  })

  it('sends the address and shows what the server says', async () => {
    const { wrapper, calls } = await open({
      'POST /api/v1/account/email/change': () => respond(202, { message: 'We sent a link to new@example.com.', expires_in: 1800 }),
    })

    await send(wrapper, '  new@example.com ')

    expect(made(calls, '/api/v1/account/email/change')[0]?.body).toEqual({ email: 'new@example.com' })
    expect(wrapper.get('[data-test="sent"]').text()).toContain('We sent a link to new@example.com.')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('asks before it sends: an empty field and something that is not an address are not sent', async () => {
    const { wrapper, calls } = await open()

    await send(wrapper, '')
    expect(wrapper.get('[data-test="error"]').text()).toBe('Enter your e-mail.')

    await send(wrapper, 'turbo')
    expect(wrapper.get('[data-test="error"]').text()).toBe('This does not look like an e-mail address.')
    expect(made(calls, '/api/v1/account/email/change')).toHaveLength(0)
  })

  it('shows the refusal of the pause between two changes', async () => {
    const { wrapper } = await open({
      'POST /api/v1/account/email/change': () =>
        respond(422, { message: 'The e-mail was changed recently. The next change is possible from 2026-11-09.', code: 'email_change_cooldown', available_at: '2026-11-09T12:00:00+00:00' }),
    })

    await send(wrapper, 'new@example.com')

    expect(wrapper.get('[data-test="error"]').text()).toBe('The e-mail was changed recently. The next change is possible from 2026-11-09.')
    expect(wrapper.find('[data-test="sent"]').exists()).toBe(false)
  })

  it('shows the refusal for an account that has no e-mail to change', async () => {
    const { wrapper } = await open({
      'POST /api/v1/account/email/change': () => respond(409, { message: 'This garage does not sign in with an e-mail. Add an e-mail instead of changing it.', code: 'no_email' }),
    })

    await send(wrapper, 'new@example.com')

    expect(wrapper.get('[data-test="error"]').text()).toContain('Add an e-mail instead of changing it')
  })

  it('says it when there were too many tries', async () => {
    const { wrapper } = await open({ 'POST /api/v1/account/email/change': () => respond(429, { message: '' }) })

    await send(wrapper, 'new@example.com')

    expect(wrapper.get('[data-test="error"]').text()).toBe('Too many attempts. Try again later.')
  })

  it('goes back to the form for another address, with the old one selected', async () => {
    const { wrapper } = await open({
      'POST /api/v1/account/email/change': () => respond(202, { message: 'Sent.', expires_in: 1800 }),
    })
    await send(wrapper, 'new@example.com')

    // jsdom keeps no selection in an e-mail field, so what is checked is that the text is selected.
    const select = vi.spyOn(HTMLInputElement.prototype, 'select')
    await wrapper.get('[data-test="another"]').trigger('click')
    await flushPromises()

    const field = wrapper.get('[data-test="email"]').element as HTMLInputElement
    expect(field.value).toBe('new@example.com')
    expect(document.activeElement).toBe(field)
    expect(select).toHaveBeenCalled()
    select.mockRestore()
  })

  it('closes on Escape and with the button', async () => {
    const { wrapper } = await open()

    await wrapper.get('[data-test="dialog"]').trigger('keydown', { key: 'Escape' })
    await wrapper.get('[data-test="close"]').trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(2)
  })
})
