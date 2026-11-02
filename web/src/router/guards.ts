import type { Router } from 'vue-router'

import { useSessionStore } from '@/stores/session'

/** The screens a driver who has to accept the terms again can still open: the one that asks, the texts, and the pages a link or a provider leads to. */
export const OPEN_WITHOUT_CONSENT = new Set<string>(['consent', 'terms', 'privacy', 'account-confirm', 'account-secure', 'auth-callback'])

/**
 * Keeps visitors out of the game and drivers out of the sign-up screens.
 * Pinia must be installed before the first navigation.
 */
export function installGuards(router: Router): Router {
  router.beforeEach((to) => {
    const session = useSessionStore()

    if (to.meta.requiresAuth && !session.isAuthenticated) {
      return { name: 'landing' }
    }

    // The terms changed since the driver accepted them: the game waits behind the screen that asks.
    if (session.needsConsent && !OPEN_WITHOUT_CONSENT.has(String(to.name))) {
      return { name: 'consent' }
    }

    // The screen that asks is for a driver who has to accept; the profile may not be there yet (a reload), and then the screen finds out.
    if (to.name === 'consent' && session.me !== null && !session.needsConsent) {
      return { name: 'garage' }
    }

    if (to.meta.guestOnly && session.isAuthenticated) {
      return { name: 'garage' }
    }

    return true
  })

  return router
}
