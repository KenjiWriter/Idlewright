import { api } from '@/api/client'

import { pushPermission, pushSupport } from './pushSupport'

/** GET /api/v1/push/config: whether the game sends push messages, the public key that a browser subscribes with, and how many devices of the driver are subscribed. */
export interface PushConfig {
  enabled: boolean
  public_key: string | null
  devices: number
}

/** What came of asking for notifications. */
export type PushOutcome = 'subscribed' | 'denied' | 'unsupported' | 'unavailable'

export async function loadPushConfig(): Promise<PushConfig> {
  return (await api<{ data: PushConfig }>('/v1/push/config')).data
}

/** The key of the game as the bytes that a browser subscribes with. */
export function keyToBytes(key: string): Uint8Array<ArrayBuffer> {
  const padded = (key + '='.repeat((4 - (key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(padded)

  return Uint8Array.from(raw, (char) => char.charCodeAt(0))
}

/** The key as base64url, as the server writes it (the bytes the browser holds for a subscription). */
function bytesToKey(bytes: ArrayBuffer | null): string {
  return bytes === null ? '' : btoa(String.fromCharCode(...new Uint8Array(bytes))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

/** Tells the server about the subscription of this device (the same call again changes nothing). */
async function save(subscription: PushSubscription, locale: string): Promise<void> {
  await api('/v1/push/subscription', {
    method: 'PUT',
    body: JSON.stringify({ ...subscription.toJSON(), timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, locale }),
  })
}

/**
 * Asks the browser for the permission (only call this from a tap: the browsers ask for one) and subscribes this device to the push messages of the game. A subscription that
 * was made with another key of the game is made again.
 */
export async function enablePush(config: PushConfig, locale: string): Promise<PushOutcome> {
  if (pushSupport() !== 'ready') {
    return 'unsupported'
  }

  if (!config.enabled || config.public_key === null) {
    return 'unavailable'
  }

  if ((await Notification.requestPermission()) !== 'granted') {
    return 'denied'
  }

  const registration = await navigator.serviceWorker.ready
  let subscription = await registration.pushManager.getSubscription()

  if (subscription !== null && bytesToKey(subscription.options.applicationServerKey) !== config.public_key) {
    await subscription.unsubscribe()
    subscription = null
  }

  subscription ??= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(config.public_key) })
  await save(subscription, locale)

  return 'subscribed'
}

/**
 * Keeps the server in step with the browser when the app starts: browsers renew subscriptions by themselves, so the one the browser holds is told to the server again. It
 * does nothing for a driver who has not agreed, and it never asks for the permission.
 */
export async function syncPush(config: PushConfig, locale: string): Promise<void> {
  if (pushSupport() !== 'ready' || pushPermission() !== 'granted') {
    return
  }

  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.getSubscription()

  if (subscription === null) {
    return
  }

  if (!config.enabled || config.public_key === null || bytesToKey(subscription.options.applicationServerKey) !== config.public_key) {
    // The game stopped sending, or got another key: this subscription is of no use, and the driver is asked again by the card, not behind their back.
    await disablePush()

    return
  }

  await save(subscription, locale)
}

/** Switches notifications off on this device: the browser forgets the subscription and the server is told. */
export async function disablePush(): Promise<void> {
  if (pushSupport() !== 'ready') {
    return
  }

  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.getSubscription()

  if (subscription === null) {
    return
  }

  const endpoint = subscription.endpoint

  await subscription.unsubscribe()
  await api('/v1/push/subscription', { method: 'DELETE', body: JSON.stringify({ endpoint }) })
}
