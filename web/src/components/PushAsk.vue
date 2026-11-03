<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import { usePushStore } from '@/stores/push'

/**
 * The card that asks a driver who has come back after a break whether they want to be told when a car stops or arrives. It comes before the question of the browser, which cannot be
 * taken back once it was answered with a "no": a driver who is not ready says "not now" here, and the server asks again after the time the rules give, as many times as they allow. A driver who has said yes, no or
 * nothing to the browser, or whose browser cannot take push messages, does not see it. On an iPhone or iPad it says that the game has to be on the home screen first.
 */
const { t } = useI18n()
const push = usePushStore()

const visible = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

const needsInstall = computed(() => push.support === 'needs-install')

onMounted(async () => {
  if (push.support === 'unsupported') {
    return
  }

  try {
    await push.load()
  } catch {
    return
  }

  // A permission that was given or refused is not asked for again.
  // The server says whether the card may come (how many times it was put off, and how long ago); the browser says whether anything is left to ask.
  visible.value = push.available && push.config?.ask === true && (needsInstall.value || (push.permission === 'default' && !push.subscribed))
})

async function enable(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    const outcome = await push.enable()

    // Subscribed, or the browser was told no: either way the card has said what it had to.
    if (outcome === 'subscribed' || outcome === 'denied') {
      visible.value = false
    } else {
      error.value = t('push.ask.unavailable')
    }
  } catch (failure) {
    error.value = failure instanceof ApiError && failure.status === 422 ? failure.message : t('common.error')
  } finally {
    busy.value = false
  }
}

function later(): void {
  visible.value = false
  push.putOff().catch(() => undefined)
}
</script>

<template>
  <section v-if="visible" class="card card-hi flex flex-col gap-2 !p-3" aria-labelledby="push-ask-title" data-test="push-ask">
    <h2 id="push-ask-title" class="kick !mb-0">{{ t('push.ask.title') }}</h2>
    <p class="text-sm" data-test="push-ask-body">{{ needsInstall ? t('push.ask.install') : t('push.ask.body') }}</p>
    <p v-if="error" class="text-sm text-danger" role="alert" data-test="push-ask-error">{{ error }}</p>
    <div class="flex flex-wrap gap-2">
      <button v-if="!needsInstall" type="button" class="btn btn-primary btn-sm" :disabled="busy" data-test="push-ask-enable" @click="enable">{{ t('push.ask.enable') }}</button>
      <button type="button" class="btn btn-secondary btn-sm" data-test="push-ask-later" @click="later">{{ needsInstall ? t('push.ask.ok') : t('push.ask.later') }}</button>
    </div>
  </section>
</template>
