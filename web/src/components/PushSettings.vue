<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import { endSessionIfUnauthorized } from '@/features/session/unauthorized'
import { PUSH_KINDS, usePushStore, type PushKind } from '@/stores/push'

/**
 * The notifications of the account: whether this device gets them, what the driver wants to be told of (a car stopped, a car arrived, a job is done) and a test message. It is
 * not there when the game does not send them or the browser cannot take them. When the browser has been told no, the page cannot ask again, so it says where to change that.
 */
const { t } = useI18n()
const push = usePushStore()

const busy = ref(false)
const error = ref<string | null>(null)
const note = ref<string | null>(null)

const state = computed<'install' | 'denied' | 'off' | 'on'>(() => {
  if (push.support === 'needs-install') {
    return 'install'
  }

  if (push.permission === 'denied') {
    return 'denied'
  }

  return push.subscribed ? 'on' : 'off'
})

onMounted(async () => {
  if (push.support === 'unsupported') {
    return
  }

  try {
    await push.load()

    if (push.available) {
      await push.loadKinds()
    }
  } catch (failure) {
    endSessionIfUnauthorized(failure)
  }
})

async function run(work: () => Promise<void>): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null
  note.value = null

  try {
    await work()
  } catch (failure) {
    if (endSessionIfUnauthorized(failure)) {
      return
    }

    error.value = failure instanceof ApiError && failure.status === 422 ? failure.message : t('common.error')
  } finally {
    busy.value = false
  }
}

const enable = (): Promise<void> => run(async () => void (await push.enable()))

const disable = (): Promise<void> => run(() => push.disable())

const change = (kind: PushKind, event: Event): Promise<void> => run(() => push.setKind(kind, (event.target as HTMLInputElement).checked))

const test = (): Promise<void> =>
  run(async () => {
    await push.test()
    note.value = t('push.settings.testSent')
  })
</script>

<template>
  <section v-if="push.available" class="card flex flex-col gap-2 !p-3" aria-labelledby="push-settings-title" data-test="push-settings">
    <h2 id="push-settings-title" class="kick !mb-0">{{ t('push.settings.title') }}</h2>
    <p class="text-sm text-muted" data-test="push-settings-state">{{ t(`push.settings.${state}`) }}</p>

    <ul v-if="state === 'on' && push.kinds" class="flex flex-col gap-1.5" data-test="push-settings-kinds">
      <li v-for="kind in PUSH_KINDS" :key="kind">
        <label class="flex items-center gap-3 text-ink">
          <input type="checkbox" role="switch" class="size-5 shrink-0 accent-amber" :checked="push.kinds[kind]" :disabled="busy" :data-test="`push-kind-${kind}`" @change="change(kind, $event)" />
          <span>{{ t(`push.kinds.${kind}`) }}</span>
        </label>
      </li>
    </ul>

    <div class="flex flex-wrap gap-2">
      <button v-if="state === 'off'" type="button" class="btn btn-primary btn-sm" :disabled="busy" data-test="push-enable" @click="enable">{{ t('push.settings.enable') }}</button>
      <template v-if="state === 'on'">
        <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" data-test="push-test" @click="test">{{ t('push.settings.test') }}</button>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" data-test="push-disable" @click="disable">{{ t('push.settings.disable') }}</button>
      </template>
    </div>

    <p v-if="note" class="text-sm" role="status" data-test="push-settings-note">{{ note }}</p>
    <p v-if="error" class="text-sm text-danger" role="alert" data-test="push-settings-error">{{ error }}</p>
  </section>
</template>
