import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { NOW } from '@/test/garageFixtures'
import { respond, serveApi } from '@/test/fetchServer'
import { aBoard, anActiveGig, anOpportunity } from '@/test/gigsFixtures'
import { mountView } from '@/test/mountView'

import NightRunView from './NightRunView.vue'

const fetchMock = vi.fn()
const serve = (routes: Record<string, () => Response | Promise<Response>>) => serveApi(fetchMock, routes)
const noRun = () => respond(404, { message: 'No run', code: 'no_run' })
const atNight = (extra: Parameters<typeof aBoard>[0] = {}) => aBoard({ phase: 'night', opportunity: anOpportunity(), ...extra })

describe('NightRunView', () => {
  beforeEach(async () => {
    localStorage.clear()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval', 'setTimeout', 'clearTimeout'] })
    vi.setSystemTime(new Date(NOW))
    await setLocale('en')
    storeToken('device-token')
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('shows the night run as the board of the design does: the pay beside the plain pay, the chances of a stop, and what each end costs', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, atNight()), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()

    expect(wrapper.get('[data-test="chip"]').text()).toBe('OPPORTUNITY · NIGHT WINDOW')
    expect(wrapper.get('[data-test="dawn"]').text()).toBe('until dawn 43 min 00 s')
    expect(wrapper.get('[data-test="title"]').text()).toBe('Night run')
    expect(wrapper.get('[data-test="route"]').text()).toBe('Cargo without an invoice · Złomowo → Kruszyn and back, 76 km')
    expect(wrapper.get('[data-test="reward"]').text()).toBe('$1,902')
    expect(wrapper.get('[data-test="pay"]').text()).toContain('×2.5 the plain pay')
    expect(wrapper.get('[data-test="pay"]').text()).toContain('the same trip as a plain gig: $761')
    expect(wrapper.get('[data-test="pay"]').text()).toContain('load 40 kg · drive 57 min 00 s')

    expect(wrapper.get('[data-test="patrols"]').text()).toBe('36%')
    expect(wrapper.get('[data-test="class-row"]').text()).toBe('Quiet car−14 pp')
    expect(wrapper.find('[data-test="heat-row"]').exists()).toBe(false)
    expect(wrapper.get('[data-test="control"]').text()).toBe('22%')
    expect(wrapper.get('[data-test="escape-share"]').text()).toBe('58%')

    expect(wrapper.get('[data-test="end-clean"]').text()).toBe('Clean run78%full pay')
    expect(wrapper.get('[data-test="end-escaped"]').text()).toBe('Got away12.8%pay −50%')
    expect(wrapper.get('[data-test="end-arrested"]').text()).toBe('Arrest9.2%the load is taken, fine $571, arrest 25 min')
    expect(wrapper.get('[data-test="vehicle"]').text()).toBe('Your car: room for 120 kg is enough · noise low')
    expect(wrapper.get('[data-test="take"]').attributes('disabled')).toBeUndefined()
  })

  it('says when the patrols look harder for the driver, and that the car is loud', async () => {
    const hot = anOpportunity()
    hot.night = { ...hot.night, heated: true, odds: { ...hot.night.odds!, class_ppm: 100_000, heat_ppm: 100_000, control_ppm: 560_000, noise: 'high' } }
    serve({ 'GET /api/v1/gigs': () => respond(200, atNight({ opportunity: hot })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()

    expect(wrapper.get('[data-test="class-row"]').text()).toBe('Loud car+10 pp')
    expect(wrapper.get('[data-test="heat-row"]').text()).toBe('Wanted in this city+10 pp')
    expect(wrapper.get('[data-test="vehicle"]').text()).toContain('noise high')
  })

  it('takes the risk: the night run is taken by its key, and the driver is back at the board with the car on the road', async () => {
    const afterTaking = aBoard({ phase: 'night', opportunity: null, active: anActiveGig({ title: 'Cargo without an invoice' }) })
    const calls = serve({
      'GET /api/v1/gigs': () => respond(200, atNight()),
      'POST /api/v1/gigs': () => respond(201, afterTaking),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper, router } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()
    await wrapper.get('[data-test="take"]').trigger('click')
    await flushPromises()

    const post = calls.find((call) => call.method === 'POST' && call.url.endsWith('/api/v1/gigs'))

    expect(post?.body).toEqual({ offer: 'zlomowo.1327900.99.cccccccc' })
    // The board of the gigs is a screen that is loaded when it is asked for, which takes a moment.
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('gigs'))
  })

  it('does not let the driver take it when something stands in the way, and says what', async () => {
    const blocked = anOpportunity({ eligible: false, blocked: { code: 'arrested', details: { until_ms: NOW + 60_000 } } })
    serve({ 'GET /api/v1/gigs': () => respond(200, atNight({ opportunity: blocked })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()

    expect(wrapper.get('[data-test="blocked"]').text()).toBe('You are under arrest')
    expect(wrapper.get('[data-test="take"]').attributes('disabled')).toBeDefined()
  })

  it('shows what the server refuses in its own words', async () => {
    serve({
      'GET /api/v1/gigs': () => respond(200, atNight()),
      'POST /api/v1/gigs': () => respond(422, { message: 'That gig is no longer on the board.', code: 'gig_gone' }),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()
    await wrapper.get('[data-test="take"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="error"]').text()).toBe('That gig is no longer on the board.')
  })

  it('goes back to the gigs on "Not now", and says so when there is no night run', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, atNight()), 'GET /api/v1/runs/current': noRun })

    const { wrapper, router } = await mountView(NightRunView, '/gigs/night')
    await flushPromises()
    await wrapper.get('[data-test="skip"]').trigger('click')

    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('gigs'))

    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard()), 'GET /api/v1/runs/current': noRun })

    const empty = await mountView(NightRunView, '/gigs/night')
    await flushPromises()

    expect(empty.wrapper.get('[data-test="none"]').text()).toBe('There is no night run for you now.')
    expect(empty.wrapper.find('[data-test="take"]').exists()).toBe(false)
  })
})
