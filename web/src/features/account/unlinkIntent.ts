import type { AccountProvider } from '@/api/types'

const STORAGE_KEY = 'ride.unlink'

/** How long a driver has to come back from the provider they sign in with again to prove who they are. */
const MAX_AGE_MS = 15 * 60 * 1000

const PROVIDERS: AccountProvider[] = ['email', 'google', 'facebook', 'apple']

/**
 * Remembers which sign-in the driver was about to unlink while the browser is away at a provider, to prove who they are. When it
 * comes back the garage opens the same step again. Kept for this tab only; if storage is blocked the driver just starts again.
 */
export function storeUnlinkIntent(provider: AccountProvider): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ provider, at: Date.now() }))
  } catch {
    // Nothing is lost: the driver presses "Unlink" again.
  }
}

export function readUnlinkIntent(): AccountProvider | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    const intent = raw ? (JSON.parse(raw) as { provider?: unknown; at?: unknown }) : null

    if (intent && typeof intent.at === 'number' && Date.now() - intent.at <= MAX_AGE_MS) {
      return PROVIDERS.find((provider) => provider === intent.provider) ?? null
    }

    return null
  } catch {
    return null
  }
}

export function clearUnlinkIntent(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // Nothing to clear.
  }
}
