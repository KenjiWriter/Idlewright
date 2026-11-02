<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import type { AccountProvider } from '@/api/types'
import AccountMethods from '@/components/AccountMethods.vue'
import ChangeEmailDialog from '@/components/ChangeEmailDialog.vue'
import UnlinkDialog from '@/components/UnlinkDialog.vue'
import { clearUnlinkIntent, readUnlinkIntent } from '@/features/account/unlinkIntent'
import { useAccountStore } from '@/stores/account'
import { useSessionStore } from '@/stores/session'

/**
 * The ways the account can be signed in to, with a way to add another one, so that losing one does not lose the garage, and to unlink one
 * or change the e-mail. The address is masked by the server: enough to tell which one it is. The last sign-in cannot be unlinked, so the
 * button is not there for it.
 */
const { t } = useI18n()
const session = useSessionStore()
const account = useAccountStore()

const adding = ref(false)
const unlinking = ref<AccountProvider | null>(null)
const changingEmail = ref(false)
const notice = ref<string | null>(null)

const identities = computed(() => session.me?.identities ?? [])
const linked = computed(() => new Set(identities.value.map((identity) => identity.provider)))
const canUnlink = computed(() => identities.value.length > 1)

/** Something is left to add: an e-mail, or a provider the server has on that the account does not have. */
const canAdd = computed(() => !linked.value.has('email') || account.providers.some((provider) => !linked.value.has(provider)))

// Only a driver with an account has anything to add, so only then is the server asked which providers are on.
watch(
  () => identities.value.length > 0,
  (hasAccount) => {
    if (hasAccount && !account.providersLoaded) {
      void account.loadProviders()
    }
  },
  { immediate: true },
)

// Back from the provider the driver signed in with again to prove who they are: the step they were at opens again. The profile may
// arrive after the card does (a reload), so this waits for the sign-ins and looks once.
let intentLooked = false

watch(
  () => identities.value.length > 0,
  (hasAccount) => {
    if (!hasAccount || intentLooked) {
      return
    }

    intentLooked = true

    const intent = readUnlinkIntent()

    if (intent !== null && linked.value.has(intent)) {
      unlinking.value = intent
    } else {
      clearUnlinkIntent()
    }
  },
  { immediate: true },
)

function unlinked(): void {
  notice.value = t('linked.unlinked', { provider: t(`provider.${unlinking.value ?? 'email'}`) })
}
</script>

<template>
  <section v-if="identities.length > 0" class="card flex flex-col gap-3" aria-labelledby="linked-title" data-test="linked">
    <h2 id="linked-title" class="text-xl font-bold">{{ t('linked.title') }}</h2>

    <ul class="flex flex-col gap-2">
      <li v-for="identity in identities" :key="identity.provider" class="flex flex-wrap items-baseline gap-x-3 gap-y-1" :data-test="`identity-${identity.provider}`">
        <span class="font-semibold">{{ t(`provider.${identity.provider}`) }}</span>
        <span v-if="identity.email" class="text-sm text-muted">{{ identity.email }}</span>
        <div class="ml-auto flex gap-3 text-sm">
          <button v-if="identity.provider === 'email'" type="button" class="text-amber underline" data-test="change-email" @click="changingEmail = true">
            {{ t('linked.changeEmail') }}
          </button>
          <button v-if="canUnlink" type="button" class="text-muted underline" :data-test="`unlink-${identity.provider}`" @click="unlinking = identity.provider">
            {{ t('linked.unlink') }}
          </button>
        </div>
      </li>
    </ul>

    <p v-if="!canUnlink" class="text-sm text-muted" data-test="last-hint">{{ t('linked.lastHint') }}</p>
    <p v-if="notice" class="text-sm text-muted" role="status" data-test="notice">{{ notice }}</p>

    <template v-if="canAdd">
      <div>
        <button
          type="button"
          class="btn btn-secondary"
          :aria-expanded="adding"
          aria-controls="add-sign-in"
          data-test="add-toggle"
          @click="adding = !adding"
        >
          {{ adding ? t('linked.close') : t('linked.add') }}
        </button>
      </div>

      <div v-if="adding" id="add-sign-in" class="flex flex-col gap-4 border-t border-line pt-4" data-test="add">
        <p class="text-sm text-muted">{{ t('linked.addDescription') }}</p>
        <AccountMethods mode="add" />
      </div>
    </template>

    <UnlinkDialog v-if="unlinking !== null" :provider="unlinking" @done="unlinked" @close="unlinking = null" />
    <ChangeEmailDialog v-if="changingEmail" @close="changingEmail = false" />
  </section>
</template>
