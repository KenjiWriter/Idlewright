<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { ApiError } from '@/api/client'
import AccountData from '@/components/AccountData.vue'
import LegalLinks from '@/components/LegalLinks.vue'
import { currentLocale } from '@/i18n'
import { useFormat } from '@/i18n/useFormat'
import { changesSince } from '@/legal/changes'
import { useAccountStore } from '@/stores/account'
import { useSessionStore } from '@/stores/session'

/**
 * The screen that asks a driver to accept the terms and the privacy policy again, after they changed. The game waits behind it: until the driver
 * accepts, the server answers every call with 403 `consent_required`. A driver who does not want to has the way out: sign out, take a copy of their
 * data, delete the account. The texts are not on this screen, only what changed and the links to them; the checkboxes are the ones of the window
 * that secures the account.
 */
const { t } = useI18n()
const { day } = useFormat()
const router = useRouter()
const session = useSessionStore()
const account = useAccountStore()

const terms = ref(false)
const age = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)

// What was said about a box that was not ticked is old as soon as the driver ticks one.
watch([terms, age], ([termsTicked, ageTicked]) => {
  if (termsTicked || ageTicked) {
    error.value = null
  }
})

const consent = computed(() => session.me?.consent ?? null)
/** What changed since the version the driver accepted, in their language: none when the one who changed the texts wrote nothing about it. */
const changes = computed(() => (consent.value ? changesSince(consent.value.accepted_version, consent.value.current_version, currentLocale()) : []))

onMounted(async () => {
  // The profile says whether the texts changed; on a reload it is not there yet.
  if (session.me === null) {
    await session.fetchMe().catch(() => undefined)
  }

  void account.loadLegal().catch(() => undefined)

  if (session.me !== null && !session.needsConsent) {
    await router.replace({ name: 'garage' })
  }
})

function describe(failure: unknown): string {
  if (failure instanceof ApiError) {
    // 409 consent_version_changed: the texts changed while the driver read. 422: a checkbox is missing. The server explains.
    if (failure.status === 409 || failure.status === 422) {
      return failure.message
    }

    if (failure.status === 429) {
      return failure.message || t('common.tooManyRequests')
    }
  }

  return t('common.error')
}

async function accept(): Promise<void> {
  error.value = null

  if (busy.value) {
    return
  }

  if (!terms.value || !age.value) {
    error.value = t('account.consentRequired')

    return
  }

  const version = consent.value?.current_version

  if (!version) {
    error.value = t('common.error')

    return
  }

  busy.value = true

  try {
    await account.acceptConsent(version)
    await router.replace({ name: 'garage' })
  } catch (failure) {
    error.value = describe(failure)

    // The texts changed while the driver read: the profile has the new version, and the screen shows it.
    if (failure instanceof ApiError && failure.code === 'consent_version_changed') {
      terms.value = false
      age.value = false
      await session.fetchMe().catch(() => undefined)
    }
  } finally {
    busy.value = false
  }
}

async function leave(): Promise<void> {
  session.signOut()
  await router.replace({ name: 'landing' })
}
</script>

<template>
  <main class="mx-auto flex max-w-md flex-col gap-6 px-4 py-12" data-test="consent">
    <h1 class="text-3xl font-bold tracking-tight" data-test="title">{{ t('consent.title') }}</h1>
    <p class="text-muted" data-test="intro">{{ t('consent.intro') }}</p>

    <section v-if="consent" class="card flex flex-col gap-3" aria-labelledby="consent-changes" data-test="changes">
      <h2 id="consent-changes" class="text-xl font-bold">{{ t('consent.changesTitle') }}</h2>
      <ul v-if="changes.length > 0" class="flex list-disc flex-col gap-2 pl-5" data-test="change-list">
        <li v-for="line in changes" :key="line">{{ line }}</li>
      </ul>
      <p v-else data-test="no-list">{{ t('consent.noList') }}</p>
      <p class="text-sm text-muted" data-test="versions">
        {{ consent.accepted_version ? t('consent.accepted', { date: day(consent.accepted_version) }) : t('consent.acceptedBefore') }}
        ·
        {{ t('consent.current', { date: day(consent.current_version) }) }}
      </p>
      <p class="text-sm"><LegalLinks keypath="consent.read" /></p>
    </section>

    <form class="flex flex-col gap-4" novalidate @submit.prevent="accept">
      <fieldset class="flex flex-col gap-3">
        <legend class="sr-only">{{ t('account.consentLegend') }}</legend>
        <label class="flex items-start gap-3">
          <input v-model="terms" type="checkbox" class="mt-1 size-5 shrink-0 accent-amber" data-test="terms" />
          <LegalLinks keypath="account.terms" class="text-sm" />
        </label>
        <label class="flex items-start gap-3">
          <input v-model="age" type="checkbox" class="mt-1 size-5 shrink-0 accent-amber" data-test="age" />
          <span class="text-sm" data-test="age-text">
            {{ account.legal ? t('account.age', { age: account.legal.min_age }) : t('account.ageUnknown') }}
          </span>
        </label>
      </fieldset>

      <p v-if="error" class="text-sm text-danger" role="alert" data-test="error">{{ error }}</p>

      <button type="submit" class="btn btn-primary" :disabled="busy" data-test="accept">{{ busy ? t('consent.accepting') : t('consent.accept') }}</button>
    </form>

    <section class="flex flex-col gap-3 border-t border-line pt-6" aria-labelledby="consent-decline" data-test="decline">
      <h2 id="consent-decline" class="text-xl font-bold">{{ t('consent.declineTitle') }}</h2>
      <p class="text-muted">{{ t('consent.declineBody') }}</p>
      <div>
        <button type="button" class="btn btn-secondary" data-test="leave" @click="leave">{{ t('consent.leave') }}</button>
      </div>
      <AccountData />
    </section>
  </main>
</template>
