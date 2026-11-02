<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/api/client'
import AccountDialog from '@/components/AccountDialog.vue'
import { useAccountStore } from '@/stores/account'

/**
 * Changing the e-mail of the account. The driver types the new address and a link goes to it; nothing changes until the link is opened,
 * and the old address is told, with a way to say it was not them. The answer is the same whether the new address has a garage or not.
 */
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const account = useAccountStore()

const email = ref('')
const field = ref<HTMLInputElement | null>(null)
const busy = ref(false)
const sent = ref<string | null>(null)
const error = ref<string | null>(null)

function describe(failure: unknown): string {
  if (failure instanceof ApiError) {
    // 422: not an address, the same address, or the pause after the last change; 409: the account has no e-mail to change. The server explains.
    if (failure.status === 422) {
      return failure.firstError('email') ?? failure.message
    }

    if (failure.status === 429) {
      return failure.message || t('common.tooManyRequests')
    }

    if (failure.status === 409) {
      return failure.message
    }
  }

  return t('common.error')
}

onMounted(() => field.value?.focus())

async function send(): Promise<void> {
  error.value = null

  const address = email.value.trim()

  if (busy.value) {
    return
  }

  if (address === '') {
    error.value = t('account.errors.emailRequired')

    return
  }

  if (!address.includes('@')) {
    error.value = t('account.errors.emailInvalid')

    return
  }

  busy.value = true

  try {
    sent.value = await account.requestEmailChange(address)
  } catch (failure) {
    error.value = describe(failure)
  } finally {
    busy.value = false
  }
}

/** Back to the form for another address; the old text is selected, so typing replaces it. */
async function another(): Promise<void> {
  sent.value = null
  error.value = null

  await nextTick()
  field.value?.focus()
  field.value?.select()
}
</script>

<template>
  <AccountDialog :title="t('changeEmail.title')" @close="emit('close')">
    <div v-if="sent" class="flex flex-col gap-3 rounded-lg border border-cyan bg-surface-2 p-4" role="status" data-test="sent">
      <p>{{ sent }}</p>
      <p class="text-sm text-muted">{{ t('changeEmail.afterSent') }}</p>
      <div>
        <button type="button" class="btn btn-secondary" data-test="another" @click="another">{{ t('changeEmail.another') }}</button>
      </div>
    </div>

    <form v-else class="flex flex-col gap-3" novalidate @submit.prevent="send">
      <p class="text-muted" data-test="intro">{{ t('changeEmail.intro') }}</p>
      <label for="change-email" class="font-semibold">{{ t('changeEmail.label') }}</label>
      <input
        id="change-email"
        ref="field"
        v-model="email"
        class="field"
        type="email"
        name="email"
        inputmode="email"
        autocomplete="email"
        autocapitalize="none"
        spellcheck="false"
        maxlength="191"
        placeholder="name@example.com"
        data-test="email"
      />
      <button type="submit" class="btn btn-primary" :disabled="busy" data-test="send">{{ busy ? t('changeEmail.sending') : t('changeEmail.send') }}</button>
    </form>

    <p v-if="error" class="text-sm text-danger" role="alert" data-test="error">{{ error }}</p>

    <div class="flex justify-end border-t border-line pt-4">
      <button type="button" class="btn btn-secondary" data-test="close" @click="emit('close')">{{ t('changeEmail.close') }}</button>
    </div>
  </AccountDialog>
</template>
