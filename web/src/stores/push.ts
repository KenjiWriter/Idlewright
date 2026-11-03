import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api } from '@/api/client'
import { currentLocale } from '@/i18n'
import { disablePush, enablePush, hasSubscription, loadPushConfig, syncPush, type PushConfig, type PushOutcome } from '@/features/push/pushSubscription'
import { pushPermission, pushSupport } from '@/features/push/pushSupport'

/** The kinds of message a driver can switch on and off. */
export const PUSH_KINDS = ['car_stopped', 'car_arrived', 'job_done'] as const

export type PushKind = (typeof PUSH_KINDS)[number]

/** The notifications of the game on this device: what the game can send, what this browser allows, whether it is subscribed, and what the driver wants to be told of. */
export const usePushStore = defineStore('push', () => {
  const config = ref<PushConfig | null>(null)
  const kinds = ref<Record<PushKind, boolean> | null>(null)
  const permission = ref<NotificationPermission | 'unsupported'>(pushPermission())
  const subscribed = ref(false)
  const loaded = ref(false)
  /** Whether this start of the app has told the server about the subscription of the browser: once is enough. */
  const synced = ref(false)

  const support = computed(() => pushSupport())
  /** Whether there is anything to offer: the game sends push messages and this browser can take them (or can after the app is installed). */
  const available = computed(() => config.value?.enabled === true && support.value !== 'unsupported')

  async function refreshDevice(): Promise<void> {
    permission.value = pushPermission()
    subscribed.value = support.value === 'ready' && permission.value === 'granted' ? await hasSubscription() : false
  }

  /** Asks the game what it can do, and the browser what it holds. Nothing is asked of the server by a browser that cannot take push messages. */
  async function load(): Promise<void> {
    if (support.value === 'unsupported') {
      loaded.value = true

      return
    }

    config.value = await loadPushConfig()
    await refreshDevice()
    loaded.value = true
  }

  async function loadKinds(): Promise<void> {
    kinds.value = (await api<{ data: { kinds: Record<PushKind, boolean> } }>('/v1/push/preferences')).data.kinds
  }

  async function setKind(kind: PushKind, on: boolean): Promise<void> {
    kinds.value = (await api<{ data: { kinds: Record<PushKind, boolean> } }>('/v1/push/preferences', { method: 'PATCH', body: JSON.stringify({ kinds: { [kind]: on } }) })).data.kinds
  }

  /** The driver agreed: the browser asks for the permission (from their tap) and subscribes. */
  async function enable(): Promise<PushOutcome> {
    if (config.value === null) {
      await load()
    }

    const outcome = config.value === null ? 'unavailable' : await enablePush(config.value, currentLocale())
    await refreshDevice()

    return outcome
  }

  async function disable(): Promise<void> {
    await disablePush()
    await refreshDevice()
  }

  /** The driver said "not now" to the card that asks: the server counts it, and the card does not come back before the time the rules give. */
  async function putOff(): Promise<void> {
    await api('/v1/push/prompt', { method: 'POST' })

    if (config.value !== null) {
      config.value = { ...config.value, ask: false }
    }
  }

  /** A message to the driver's own devices, to see that it arrives; how many devices it went to. */
  async function test(): Promise<number> {
    return (await api<{ data: { queued: number } }>('/v1/push/test', { method: 'POST' })).data.queued
  }

  /** At the start of the app: a browser that holds a subscription tells the server about it again. For a driver who has not agreed nothing is asked of anyone. */
  async function sync(): Promise<void> {
    if (synced.value || support.value !== 'ready' || pushPermission() !== 'granted') {
      return
    }

    synced.value = true

    await load()

    if (config.value !== null) {
      await syncPush(config.value, currentLocale())
      await refreshDevice()
    }
  }

  return { config, kinds, permission, subscribed, loaded, support, available, load, loadKinds, setKind, enable, disable, putOff, test, sync }
})
