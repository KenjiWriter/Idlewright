<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import { useAccountStore } from '@/stores/account'
import { useSessionStore } from '@/stores/session'

/**
 * The page behind "This was not me" in the notice sent to the old address when the e-mail of a garage is changed. It does not do anything
 * by itself: it says what it will do and waits for the button, because the address of a mail may be opened by a program that only wants to
 * look at it, and this signs every device out. Open to everybody: the link alone is the proof.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const account = useAccountStore()
const session = useSessionStore()

const step = ref<'ask' | 'working' | 'done' | 'failed'>('ask')
const restored = ref(false)
const failure = ref<string | null>(null)
let secret = ''

onMounted(async () => {
  secret = typeof route.query.token === 'string' ? route.query.token : ''

  // The link works once. Its secret should not stay in the address bar, the history or what the page sends on.
  await router.replace({ name: 'account-secure' })

  if (secret === '') {
    step.value = 'failed'
  }
})

async function secureGarage(): Promise<void> {
  if (step.value === 'working') {
    return
  }

  step.value = 'working'

  try {
    restored.value = await account.revokeEmailChange(secret)
    step.value = 'done'

    // Every device was signed out, this one too if it was the garage's: asking for the profile finds that out and ends the session.
    if (session.isAuthenticated) {
      await session.fetchMe().catch(() => undefined)
    }
  } catch (error) {
    // 422: the link is unknown, used or expired. 429: too many tries.
    failure.value = error instanceof ApiError && error.status === 429 ? t('common.tooManyRequests') : error instanceof ApiError && error.status === 422 ? null : t('common.error')
    step.value = 'failed'
  }
}
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-6 px-4 py-12">
    <template v-if="step === 'ask' || step === 'working'">
      <h1 class="text-3xl font-bold tracking-tight" data-test="title">{{ t('secure.title') }}</h1>
      <p class="text-muted" data-test="body">{{ t('secure.body') }}</p>
      <div>
        <button type="button" class="btn btn-danger" :disabled="step === 'working'" data-test="secure" @click="secureGarage">
          {{ step === 'working' ? t('secure.working') : t('secure.action') }}
        </button>
      </div>
    </template>

    <template v-else-if="step === 'done'">
      <h1 class="text-3xl font-bold tracking-tight" data-test="title">{{ t('secure.doneTitle') }}</h1>
      <p class="text-muted" data-test="body">{{ restored ? t('secure.doneRestored') : t('secure.doneKept') }}</p>
      <RouterLink to="/recover" class="btn btn-primary" data-test="next">{{ t('secure.signIn') }}</RouterLink>
    </template>

    <template v-else>
      <h1 class="text-3xl font-bold tracking-tight" data-test="title">{{ t('secure.failedTitle') }}</h1>
      <p class="text-danger" role="alert" data-test="body">{{ failure ?? t('secure.invalid') }}</p>
      <RouterLink to="/recover" class="btn btn-primary" data-test="next">{{ t('secure.signIn') }}</RouterLink>
    </template>
  </main>
</template>
