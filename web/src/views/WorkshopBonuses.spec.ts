import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { storeToken } from '@/auth/token'
import { setLocale } from '@/i18n'
import { respond, serveApi } from '@/test/fetchServer'
import { NOW, aBonus, aCar, aGarage, aGear, aJob, aMountedPart, aPart, aTuneNext } from '@/test/garageFixtures'
import { mountView } from '@/test/mountView'

import WorkshopView from './WorkshopView.vue'

const fetchMock = vi.fn()
const serve = (routes: Record<string, () => Response | Promise<Response>>) => serveApi(fetchMock, routes)
const noRun = () => respond(404, { message: 'No run', code: 'no_run' })

const tires = aPart({ id: 21, slug: 'street-tires-t2', name: 'Street tires T2', slot: 'tires', tier: 2, stats: { grip: 100, handling: 98, braking: 100 }, tuning_slots: 3, tuning: [null, null, null], tune_next: aTuneNext() })
const brakes = aMountedPart({ id: 12, slug: 'disc-brakes-t2', name: 'Disc brakes', slot: 'brakes', mounted: true, car_id: 5, tuning_slots: 3, tuning: [aBonus({ key: 'sprint-brake', name: 'Sprint braking', effect: 'stat', stat: 'braking', percent: 4.1, context: { kinds: ['sprint'], surfaces: null, weathers: null } }), null, null], tune_next: aTuneNext() })

describe('the bonuses of the parts in the workshop', () => {
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

  it('queues a try at a place of a part of the inventory, with no bonus named for a draw and the place and the bonus for a pick', async () => {
    serve({
      'GET /api/v1/garage': () => respond(200, aGarage({ inventory: [tires] })),
      'POST /api/v1/garage/jobs': () => respond(201, aGarage({ inventory: [tires], workshop: { city: { slug: 'zlomowo', name: 'Złomowo' }, queue_max: 4, mechanic: { level: 1, time_cut_ppm: 20_000 }, gear: aGear(), jobs: [aJob({ id: 9, kind: 'tune', part: tires, tune: { place: 2, key: null }, cost_cents: 1_710 })] } })),
      'GET /api/v1/runs/current': noRun,
    })
    const { wrapper } = await mountView(WorkshopView, '/workshop')
    await flushPromises()
    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')
    await wrapper.get('[data-test="bonus-draw-21-2"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-test="job-9"]').text()).toContain('Tuning: Street tires T2')

    // A draw names the place and no bonus.
    const bodies = fetchMock.mock.calls.filter(([, init]) => init?.method === 'POST').map(([, init]) => JSON.parse(String(init.body)))

    expect(bodies).toEqual([{ kind: 'tune', part: 21, place: 2 }])
  })

  it('shows the bonuses of a part on the car when its slot is tapped, and the places of the part', async () => {
    serve({
      'GET /api/v1/garage': () => respond(200, aGarage({ car: aCar({}, { brakes }), inventory: [] })),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(WorkshopView, '/workshop')
    await flushPromises()
    await wrapper.get('[data-test="spot-brakes"]').trigger('click')

    expect(wrapper.get('[data-test="bonus-text-12-1"]').text()).toBe('◆ Sprint braking +4.1%')
    expect(wrapper.get('[data-test="bonus-12-2"]').text()).toContain('Empty place')
  })

  it('shuts the buttons of the places where a try waits, and of a part that waits in another job', async () => {
    serve({
      'GET /api/v1/garage': () =>
        respond(
          200,
          aGarage({
            inventory: [tires],
            workshop: { city: { slug: 'zlomowo', name: 'Złomowo' }, queue_max: 4, mechanic: { level: 1, time_cut_ppm: 20_000 }, gear: aGear(), jobs: [aJob({ id: 8, kind: 'tune', part: tires, status: 'queued', tune: { place: 1, key: null } })] },
          }),
        ),
      'GET /api/v1/runs/current': noRun,
    })

    const { wrapper } = await mountView(WorkshopView, '/workshop')
    await flushPromises()
    await wrapper.get('[data-test="bonus-open-21"]').trigger('click')

    expect(wrapper.get('[data-test="bonus-waits-21-1"]').text()).toBe('A try is waiting')
    expect(wrapper.get('[data-test="bonus-draw-21-2"]').attributes('disabled')).toBeUndefined()
  })
})
