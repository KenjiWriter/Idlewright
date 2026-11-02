import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { storeToken } from '@/auth/token'
import { useSessionStore } from '@/stores/session'

import { installGuards } from './guards'
import { routes } from './routes'

function freshRouter() {
  setActivePinia(createPinia())

  return installGuards(createRouter({ history: createMemoryHistory(), routes }))
}

describe('route guards', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('sends a visitor away from the garage', async () => {
    const router = freshRouter()

    await router.push('/garage')

    expect(router.currentRoute.value.name).toBe('landing')
  })

  it('lets a visitor open the sign-up screen', async () => {
    const router = freshRouter()

    await router.push('/create')

    expect(router.currentRoute.value.name).toBe('create')
  })

  it('sends a signed-in driver from the sign-up screen to the garage', async () => {
    storeToken('device-token')
    const router = freshRouter()

    await router.push('/create')

    expect(router.currentRoute.value.name).toBe('garage')
  })

  it('lets a signed-in driver open the garage and the landing page', async () => {
    storeToken('device-token')
    const router = freshRouter()

    await router.push('/garage')
    expect(router.currentRoute.value.name).toBe('garage')

    await router.push('/')
    expect(router.currentRoute.value.name).toBe('landing')
  })

  describe('a driver who has to accept the terms again', () => {
    function waiting() {
      storeToken('device-token')
      const router = freshRouter()
      useSessionStore().me = {
        id: 1,
        name: 'Maciek_Turbo',
        locale: 'en',
        is_guest: false,
        has_recovery_code: false,
        identities: [],
        consent: { required: true, accepted_version: '2026-10-23', current_version: '2026-11-01' },
      }

      return router
    }

    it('is kept at the screen that asks, whatever screen of the game they open', async () => {
      const router = waiting()

      for (const path of ['/garage', '/workshop', '/race', '/missions', '/']) {
        await router.push(path)
        expect(router.currentRoute.value.name).toBe('consent')
      }
    })

    it('can still read the terms and the privacy policy, and open the pages a link leads to', async () => {
      const router = waiting()

      for (const [path, name] of [['/terms', 'terms'], ['/privacy', 'privacy'], ['/account/confirm', 'account-confirm'], ['/account/secure', 'account-secure'], ['/auth/callback', 'auth-callback']]) {
        await router.push(path as string)
        expect(router.currentRoute.value.name).toBe(name)
      }
    })

    it('is let into the game as soon as the profile says they have accepted', async () => {
      const router = waiting()
      await router.push('/garage')
      expect(router.currentRoute.value.name).toBe('consent')

      useSessionStore().me = { ...useSessionStore().me!, consent: { required: false, accepted_version: '2026-11-01', current_version: '2026-11-01' } }
      await router.push('/garage')

      expect(router.currentRoute.value.name).toBe('garage')
    })

    it('is sent from the screen that asks to the garage when there is nothing to accept', async () => {
      storeToken('device-token')
      const router = freshRouter()
      useSessionStore().me = { id: 1, name: 'M', locale: 'en', is_guest: false, has_recovery_code: false, identities: [], consent: { required: false, accepted_version: '2026-11-01', current_version: '2026-11-01' } }

      await router.push('/consent')

      expect(router.currentRoute.value.name).toBe('garage')
    })

    it('may open the screen that asks while the profile is not there yet (a reload), which finds out by itself', async () => {
      storeToken('device-token')
      const router = freshRouter()

      await router.push('/consent')

      expect(router.currentRoute.value.name).toBe('consent')
    })

    it('is a matter for signed-in drivers only', async () => {
      const router = freshRouter()

      await router.push('/consent')

      expect(router.currentRoute.value.name).toBe('landing')
    })
  })

})
