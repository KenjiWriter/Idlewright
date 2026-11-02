<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import type { AccountProvider, SocialProvider } from '@/api/types'
import AccountDialog from '@/components/AccountDialog.vue'
import { clearUnlinkIntent, storeUnlinkIntent } from '@/features/account/unlinkIntent'
import { useAccountStore } from '@/stores/account'
import { useSessionStore } from '@/stores/session'

/**
 * Unlinking a sign-in from the account. It cannot be taken back, so it asks the driver to prove who they are first, on this device,
 * with a link sent to the e-mail of the account or by signing in again with a provider the account has; then the driver reads what
 * the unlinking does and confirms. The server decides all of it: the last sign-in is never unlinked, and a proof that ran out is a 403.
 */
const props = defineProps<{ provider: AccountProvider }>()

const emit = defineEmits<{
  close: []
  /** The sign-in was unlinked. */
  done: []
}>()

const { t } = useI18n()
const account = useAccountStore()
const session = useSessionStore()

/** How often to ask whether the link was opened. */
const POLL_MS = 3000

const step = ref<'loading' | 'prove' | 'waiting' | 'confirm' | 'done'>('loading')
const busy = ref(false)
const error = ref<string | null>(null)
const sent = ref<string | null>(null)
let timer: ReturnType<typeof setInterval> | null = null

const name = computed(() => t(`provider.${props.provider}`))
const isEmail = computed(() => props.provider === 'email')
const methods = computed(() => account.reauth?.methods ?? [])
const proveByEmail = computed(() => methods.value.includes('email'))
const proveByProvider = computed(() => methods.value.filter((method): method is SocialProvider => method !== 'email'))
const maskedEmail = computed(() => session.me?.identities.find((identity) => identity.provider === 'email')?.email ?? null)

function describe(failure: unknown): string {
  if (failure instanceof ApiError) {
    // 403 reauth_required, 404, 422 last_method and reauth_no_method: the server says so in the language of the driver.
    if (failure.status === 429) {
      return failure.message || t('common.tooManyRequests')
    }

    if ([403, 404, 409, 422].includes(failure.status)) {
      return failure.message
    }
  }

  return t('common.error')
}

function stopPolling(): void {
  if (timer !== null) {
    clearInterval(timer)
    timer = null
  }
}

/** Asks whether this device has the proof, and goes on to the confirmation when it has. */
async function check(): Promise<void> {
  try {
    if ((await account.loadReauth()).valid) {
      stopPolling()
      step.value = 'confirm'
    } else if (step.value === 'loading') {
      step.value = 'prove'
    }
  } catch (failure) {
    stopPolling()
    error.value = describe(failure)

    if (step.value === 'loading') {
      step.value = 'prove'
    }
  }
}

onMounted(check)
onBeforeUnmount(stopPolling)

async function sendLink(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    sent.value = await account.requestReauthLink()
    step.value = 'waiting'
    stopPolling()
    timer = setInterval(() => void check(), POLL_MS)
  } catch (failure) {
    error.value = describe(failure)
  } finally {
    busy.value = false
  }
}

async function signInAgain(provider: SocialProvider): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    // The browser leaves for the provider; the garage opens this step again when it comes back.
    storeUnlinkIntent(props.provider)
    await account.startReauthProvider(provider)
  } catch (failure) {
    clearUnlinkIntent()
    error.value = describe(failure)
    busy.value = false
  }
}

async function unlink(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    await account.unlink(props.provider)
    clearUnlinkIntent()
    step.value = 'done'
    emit('done')
  } catch (failure) {
    error.value = describe(failure)

    // The proof ran out between the question and the answer: back to proving.
    if (failure instanceof ApiError && failure.code === 'reauth_required') {
      step.value = 'prove'
    }
  } finally {
    busy.value = false
  }
}

function close(): void {
  clearUnlinkIntent()
  emit('close')
}
</script>

<template>
  <AccountDialog :title="step === 'done' ? t('unlink.doneTitle', { provider: name }) : t('unlink.title', { provider: name })" @close="close">
    <p v-if="step === 'loading'" class="text-muted" role="status" data-test="loading">{{ t('unlink.checking') }}</p>

    <template v-else-if="step === 'prove' || step === 'waiting'">
      <p class="text-muted" data-test="prove-intro">{{ t('unlink.proveIntro') }}</p>

      <div v-if="step === 'waiting'" class="flex flex-col gap-3 rounded-lg border border-cyan bg-surface-2 p-4" role="status" data-test="waiting">
        <p>{{ sent }}</p>
        <p class="text-sm text-muted">{{ t('unlink.waiting') }}</p>
        <div>
          <button type="button" class="btn btn-secondary" :disabled="busy" data-test="resend" @click="sendLink">{{ t('unlink.resend') }}</button>
        </div>
      </div>

      <div v-else class="flex flex-col gap-3" data-test="prove-options">
        <button v-if="proveByEmail" type="button" class="btn btn-secondary" :disabled="busy" data-test="prove-email" @click="sendLink">
          {{ maskedEmail ? t('unlink.proveEmailTo', { email: maskedEmail }) : t('unlink.proveEmail') }}
        </button>
        <button
          v-for="method in proveByProvider"
          :key="method"
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          :data-test="`prove-${method}`"
          @click="signInAgain(method)"
        >
          {{ t('unlink.proveProvider', { provider: t(`provider.${method}`) }) }}
        </button>
        <p v-if="methods.length === 0" class="text-sm text-danger" data-test="no-method">{{ t('unlink.noMethod') }}</p>
      </div>
    </template>

    <template v-else-if="step === 'confirm'">
      <p data-test="confirm-intro">{{ t('unlink.confirmIntro', { provider: name }) }}</p>
      <ul class="flex list-disc flex-col gap-2 pl-5 text-sm text-muted" data-test="consequences">
        <li>{{ t('unlink.consequenceDevices') }}</li>
        <li v-if="isEmail">{{ t('unlink.consequenceEmail') }}</li>
        <li v-else>{{ t('unlink.consequenceProvider', { provider: name }) }}</li>
        <li v-if="!isEmail && maskedEmail">{{ t('unlink.consequenceNotice') }}</li>
      </ul>
      <div class="flex flex-wrap gap-3">
        <button type="button" class="btn btn-danger" :disabled="busy" data-test="unlink" @click="unlink">
          {{ busy ? t('unlink.unlinking') : t('unlink.action', { provider: name }) }}
        </button>
        <button type="button" class="btn btn-secondary" :disabled="busy" data-test="cancel" @click="close">{{ t('unlink.cancel') }}</button>
      </div>
    </template>

    <template v-else>
      <p role="status" data-test="done">{{ t('unlink.doneBody', { provider: name }) }}</p>
      <div>
        <button type="button" class="btn btn-primary" data-test="close" @click="close">{{ t('unlink.close') }}</button>
      </div>
    </template>

    <p v-if="error" class="text-sm text-danger" role="alert" data-test="error">{{ error }}</p>

    <div v-if="step === 'prove' || step === 'waiting'" class="flex justify-end border-t border-line pt-4">
      <button type="button" class="btn btn-secondary" data-test="cancel" @click="close">{{ t('unlink.cancel') }}</button>
    </div>
  </AccountDialog>
</template>
