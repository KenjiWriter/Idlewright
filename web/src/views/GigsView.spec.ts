import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { NOW } from '@/test/garageFixtures'
import { respond, serveApi } from '@/test/fetchServer'
import { aBoard, anActiveGig, anOffer } from '@/test/gigsFixtures'
import { mountView } from '@/test/mountView'

import GigsView from './GigsView.vue'

const fetchMock = vi.fn()
const serve = (routes: Record<string, () => Response | Promise<Response>>) => serveApi(fetchMock, routes)
const noRun = () => respond(404, { message: 'No run', code: 'no_run' })

describe('GigsView', () => {
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

  it('lists the offers of the board as the board does: kind, title, route, pay, what it carries, how long it takes and burns', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard()), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Gigs')
    expect(wrapper.get('[data-test="sub"]').text()).toBe('Złomowo · day · the board changes in 15 min 00 s')
    expect(wrapper.findAll('[data-offer]')).toHaveLength(2)

    const first = wrapper.get('[data-test="offer-0"]')
    expect(first.get('[data-test="kind"]').text()).toBe('Taxi')
    expect(first.get('[data-test="title"]').text()).toBe('Ride across town')
    expect(first.get('[data-test="route"]').text()).toBe('In the city, 12 km')
    expect(first.get('[data-test="pay"]').text()).toBe('$61')
    expect(first.get('[data-test="amount"]').text()).toBe('2 people')
    expect(first.get('[data-test="duration"]').text()).toBe('drive 9 min 00 s')
    expect(first.get('[data-test="fuel"]').text()).toBe('fuel 0.7 l')
    expect(first.get('[data-test="take"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.get('[data-test="limit"]').text()).toBe('Today: 1 of 6 gigs')
  })

  it('says why the car cannot take an offer, and does not let it', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard()), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    const second = wrapper.get('[data-test="offer-1"]')
    expect(second.get('[data-test="route"]').text()).toBe('Złomowo → Kruszyn and back, 76 km')
    expect(second.get('[data-test="blocked"]').text()).toBe('Needs 5 seats, the car has 4')
    expect(second.get('[data-test="take"]').attributes('disabled')).toBeDefined()
  })

  it('takes an offer, says the car is out, and shows the gig the car is on with the way to take it back', async () => {
    const afterTaking = aBoard({ active: anActiveGig(), offers: [] })
    afterTaking.gig = anActiveGig()

    const calls = serve({
      'GET /api/v1/gigs': () => respond(200, aBoard()),
      'POST /api/v1/gigs': () => respond(201, afterTaking),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()
    await wrapper.get('[data-test="offer-0"] [data-test="take"]').trigger('click')
    await flushPromises()

    const post = calls.find((call) => call.method === 'POST' && call.url.endsWith('/api/v1/gigs'))

    expect(post?.body).toEqual({ offer: 'zlomowo.1327920.0.aaaaaaaa' })
    expect(wrapper.get('[data-test="done"]').text()).toBe('The car is out: Ride across town.')
    expect(wrapper.get('[data-test="active"]').text()).toContain('On a gig: In the city, 12 km. Back in 9 min 00 s, pays $61.')
    expect(wrapper.find('[data-test="cancel"]').exists()).toBe(true)
  })

  it('takes a gig back in the first seconds', async () => {
    const calls = serve({
      'GET /api/v1/gigs': () => respond(200, aBoard({ active: anActiveGig(), offers: [] })),
      'DELETE /api/v1/gigs/5': () => respond(200, aBoard()),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()
    await wrapper.get('[data-test="cancel"]').trigger('click')
    await flushPromises()

    expect(calls.some((call) => call.method === 'DELETE' && call.url.endsWith('/api/v1/gigs/5'))).toBe(true)
    expect(wrapper.get('[data-test="done"]').text()).toBe('The gig was taken back, the fuel is in the tank.')
    expect(wrapper.find('[data-test="active"]').exists()).toBe(false)
  })

  it('does not offer to take a gig back that is more than a minute old', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard({ active: anActiveGig(), offers: [] })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    expect(wrapper.find('[data-test="cancel"]').exists()).toBe(true)

    await vi.advanceTimersByTimeAsync(61_000)

    expect(wrapper.find('[data-test="active"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="cancel"]').exists()).toBe(false)
  })

  it('shows what the server refuses in its own words, and asks for the board again', async () => {
    const calls = serve({
      'GET /api/v1/gigs': () => respond(200, aBoard()),
      'POST /api/v1/gigs': () => respond(422, { message: 'That gig is no longer on the board.', code: 'gig_gone' }),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()
    await wrapper.get('[data-test="offer-0"] [data-test="take"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="error"]').text()).toBe('That gig is no longer on the board.')
    expect(calls.filter((call) => call.method === 'GET' && call.url.endsWith('/api/v1/gigs'))).toHaveLength(2)
  })

  it('tells a driver who has not got far enough in the tutorial that the board is closed, and shows no offers', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard({ locked: 'tutorial', offers: [], car: null, limits: null })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    expect(wrapper.get('[data-test="locked"]').text()).toBe('The board opens when you get further in the tutorial.')
    expect(wrapper.find('[data-offer]').exists()).toBe(false)
  })

  it('says so when the board has no offer for the car', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard({ offers: [] })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    expect(wrapper.get('[data-test="empty"]').text()).toBe('No offers for this car right now. The board changes in 15 min 00 s.')
  })

  it('shows the good and the great offers with a mark', async () => {
    serve({ 'GET /api/v1/gigs': () => respond(200, aBoard({ offers: [anOffer({ rarity: 'great' })] })), 'GET /api/v1/runs/current': noRun })

    const { wrapper } = await mountView(GigsView, '/gigs')
    await flushPromises()

    expect(wrapper.get('[data-test="rarity"]').text()).toBe('Great')
  })
})
