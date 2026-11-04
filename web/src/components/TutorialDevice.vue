<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import type { TutorialMessenger } from '@/api/types'

/**
 * What the mentor writes on, as the game draws it: the old cracked phone of RideIdle, a letter on paper, the panel of a science fiction console, a plain window in
 * the colours of the game, or a frame of the game's own with the messages in its screen. Every device shows the same thing: who writes, the messages, the button
 * that answers them, the one that leaves an optional step for later and the one that asks who is writing; the parts a game leaves out are not drawn.
 */
const props = defineProps<{
  messenger: TutorialMessenger
  sender: string
  /** Step 4/20. */
  step: string
  clock: string
  messages: string[]
  /** The driver asked who is writing, and the mentor answered. */
  asked: boolean
  error: boolean
  busy: boolean
  reply: string
  /** The words of the button that leaves the step for later, or null for a step that may not be left. */
  decline: string | null
}>()

const emit = defineEmits<{ reply: []; decline: []; who: [] }>()

const { t } = useI18n()

const replyButton = ref<HTMLButtonElement | null>(null)

const device = computed(() => props.messenger.device)
const parts = computed(() => props.messenger.parts)
/** A device of the game's own with no picture to draw falls back to the plain window. */
const kind = computed(() => (device.value === 'picture' && (!props.messenger.frame_url || !props.messenger.screen) ? 'plain' : device.value))
const showWho = computed(() => parts.value.who && !props.asked)

const screenStyle = computed(() => {
  const screen = props.messenger.screen

  return screen ? { top: `${screen.top}%`, right: `${screen.right}%`, bottom: `${screen.bottom}%`, left: `${screen.left}%` } : {}
})

/** Puts the answer in reach of the keyboard. */
function focusReply(): void {
  replyButton.value?.focus()
}

defineExpose({ focusReply })
</script>

<template>
  <!-- The old phone: a speaker, a cracked screen with the bar of the network, the bubbles of text messages, and the home button. -->
  <section
    v-if="kind === 'phone'"
    class="phone phone-in pointer-events-auto w-[264px] max-w-full"
    style="height: min(500px, 64svh); --tilt: -2deg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('tutorial.from', { sender })"
    data-test="tutorial-phone"
    data-device="phone"
  >
    <div class="spk" />
    <div class="scr2">
      <div v-if="parts.status_bar" class="ps" data-test="tutorial-status">
        <span>{{ t('tutorial.network') }}</span>
        <b>{{ clock }}</b>
        <span class="flex items-center gap-1">
          78%
          <i class="relative inline-block h-[9px] w-[18px] rounded-[2px] border border-[#e8eaf0]"><i class="absolute inset-y-px left-px right-1 bg-[#7be05a]" /></i>
        </span>
      </div>
      <div class="ph-hd">
        <span class="ph-btn !px-2">
          <span aria-hidden="true">‹</span>
          <span class="sr-only">{{ t('tutorial.messages') }}</span>
        </span>
        <span class="min-w-0 flex-1 truncate text-center text-[11px]" data-test="tutorial-sender">{{ sender }}</span>
        <span class="ph-btn !px-1.5 !text-[11px]" data-test="tutorial-step">{{ step }}</span>
      </div>

      <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-2 py-2.5" aria-live="polite" data-test="tutorial-thread">
        <div class="sms-t">{{ t('tutorial.today', { time: clock }) }}</div>
        <div v-for="line in messages" :key="line" class="sms-in" data-test="tutorial-sms">{{ line }}</div>
        <template v-if="asked">
          <div class="sms-out">{{ t('tutorial.who') }}</div>
          <div class="sms-in" data-test="tutorial-deflect">{{ t('tutorial.deflect') }}</div>
        </template>
        <p v-if="error" class="sms-t !text-[#b3261e]" role="alert" data-test="tutorial-error">{{ t('common.error') }}</p>
      </div>

      <div class="flex flex-none flex-wrap gap-1.5 px-2 pb-2">
        <button ref="replyButton" type="button" class="ph-send !min-h-11 !border" :disabled="busy" data-test="tutorial-reply" @click="emit('reply')">{{ reply }}</button>
        <button v-if="decline" type="button" class="ph-btn !min-h-11" :disabled="busy" data-test="tutorial-decline" @click="emit('decline')">{{ decline }}</button>
        <button v-if="showWho" type="button" class="ph-btn !min-h-11" data-test="tutorial-who" @click="emit('who')">{{ t('tutorial.who') }}</button>
      </div>
      <div v-if="parts.input" class="ph-input" aria-hidden="true" data-test="tutorial-input">
        <div class="fx">{{ t('tutorial.input') }}</div>
        <span class="ph-send">{{ t('tutorial.send') }}</span>
      </div>

      <!-- The screen is cracked: the phone is old, and it has been dropped. Only for the eye, it never gets in the way of a tap. -->
      <svg v-if="parts.crack" class="crack" viewBox="0 0 100 200" preserveAspectRatio="none" aria-hidden="true" data-test="tutorial-crack">
        <g class="crack-shade">
          <path d="M80 34 L66 30 L52 36 L41 31 L22 44 L9 41" />
          <path d="M80 34 L72 47 L74 63 L63 78 L66 96 L52 120 L55 148" />
          <path d="M80 34 L88 46 L94 58 L91 76 L100 88" />
          <path d="M80 34 L84 20 L79 8 L86 0" />
          <path d="M80 34 L92 28 L100 22" />
          <path d="M80 34 L61 52 L40 56 L26 74 L0 80" />
          <path d="M52 36 L47 46 L38 49" />
          <path d="M63 78 L74 84 L83 99" />
          <path d="M74 36 L79 43 L87 41 L88 31 L80 26 L73 30 Z" />
          <path d="M64 36 L72 51 L88 54 L96 40" />
        </g>
        <g class="crack-line">
          <path d="M80 34 L66 30 L52 36 L41 31 L22 44 L9 41" />
          <path d="M80 34 L72 47 L74 63 L63 78 L66 96 L52 120 L55 148" />
          <path d="M80 34 L88 46 L94 58 L91 76 L100 88" />
          <path d="M80 34 L84 20 L79 8 L86 0" />
          <path d="M80 34 L92 28 L100 22" />
          <path d="M80 34 L61 52 L40 56 L26 74 L0 80" />
          <path d="M52 36 L47 46 L38 49" />
          <path d="M63 78 L74 84 L83 99" />
          <path d="M74 36 L79 43 L87 41 L88 31 L80 26 L73 30 Z" />
          <path d="M64 36 L72 51 L88 54 L96 40" />
        </g>
        <path class="crack-shard" d="M80 34 L66 30 L61 52 Z M80 34 L88 46 L72 47 Z" />
      </svg>
    </div>
    <div class="home" />
  </section>

  <!-- A letter: a sheet of paper with who it is from, the messages as paragraphs written by hand, a line to answer on and a stamped button. -->
  <section
    v-else-if="kind === 'letter'"
    class="letter phone-in pointer-events-auto w-[300px] max-w-full"
    style="max-height: 64svh; --tilt: -1.5deg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('tutorial.from', { sender })"
    data-test="tutorial-phone"
    data-device="letter"
  >
    <div class="ltr-hd">
      <span class="ltr-from" data-test="tutorial-sender">{{ sender }}</span>
      <span class="ltr-step" data-test="tutorial-step">{{ step }}</span>
    </div>
    <div class="ltr-date">{{ t('tutorial.today', { time: clock }) }}</div>

    <div class="ltr-body" aria-live="polite" data-test="tutorial-thread">
      <p v-for="line in messages" :key="line" class="ltr-p" data-test="tutorial-sms">{{ line }}</p>
      <template v-if="asked">
        <p class="ltr-q">{{ t('tutorial.who') }}</p>
        <p class="ltr-p" data-test="tutorial-deflect">{{ t('tutorial.deflect') }}</p>
      </template>
      <p v-if="error" class="ltr-err" role="alert" data-test="tutorial-error">{{ t('common.error') }}</p>
    </div>

    <div v-if="parts.input" class="ltr-input" aria-hidden="true" data-test="tutorial-input">{{ t('tutorial.input') }}</div>

    <div class="ltr-actions">
      <button ref="replyButton" type="button" class="ltr-stamp" :disabled="busy" data-test="tutorial-reply" @click="emit('reply')">{{ reply }}</button>
      <button v-if="decline" type="button" class="ltr-btn" :disabled="busy" data-test="tutorial-decline" @click="emit('decline')">{{ decline }}</button>
      <button v-if="showWho" type="button" class="ltr-btn" data-test="tutorial-who" @click="emit('who')">{{ t('tutorial.who') }}</button>
    </div>
  </section>

  <!-- The panel of a console: a bar of the link, the channel that calls, the messages as the lines of a log, and the keys to answer. -->
  <section
    v-else-if="kind === 'tablet'"
    class="tablet phone-in pointer-events-auto w-[320px] max-w-full"
    style="height: min(460px, 62svh); --tilt: 0deg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('tutorial.from', { sender })"
    data-test="tutorial-phone"
    data-device="tablet"
  >
    <div class="tab-scr">
      <div v-if="parts.status_bar" class="tab-ps" data-test="tutorial-status">
        <span>{{ t('tutorial.network') }}</span>
        <b>{{ clock }}</b>
        <span class="tab-bat" aria-hidden="true"><i /></span>
      </div>
      <div class="tab-hd">
        <span class="tab-ch min-w-0 flex-1 truncate" data-test="tutorial-sender">{{ sender }}</span>
        <span class="tab-step" data-test="tutorial-step">{{ step }}</span>
      </div>

      <div class="tab-log" aria-live="polite" data-test="tutorial-thread">
        <div class="tab-t">{{ t('tutorial.today', { time: clock }) }}</div>
        <p v-for="line in messages" :key="line" class="tab-line" data-test="tutorial-sms">{{ line }}</p>
        <template v-if="asked">
          <p class="tab-line tab-out">{{ t('tutorial.who') }}</p>
          <p class="tab-line" data-test="tutorial-deflect">{{ t('tutorial.deflect') }}</p>
        </template>
        <p v-if="error" class="tab-line tab-err" role="alert" data-test="tutorial-error">{{ t('common.error') }}</p>
      </div>

      <div class="flex flex-none flex-wrap gap-1.5 px-2.5 pb-2.5">
        <button ref="replyButton" type="button" class="tab-btn tab-go" :disabled="busy" data-test="tutorial-reply" @click="emit('reply')">{{ reply }}</button>
        <button v-if="decline" type="button" class="tab-btn" :disabled="busy" data-test="tutorial-decline" @click="emit('decline')">{{ decline }}</button>
        <button v-if="showWho" type="button" class="tab-btn" data-test="tutorial-who" @click="emit('who')">{{ t('tutorial.who') }}</button>
      </div>
      <div v-if="parts.input" class="tab-input" aria-hidden="true" data-test="tutorial-input">
        <span class="min-w-0 flex-1 truncate">{{ t('tutorial.input') }}</span>
        <span class="tab-send">{{ t('tutorial.send') }}</span>
      </div>
    </div>
  </section>

  <!-- A frame of the game's own (a bottle, a radio, a scroll): the picture, with the messages in the place of its screen. -->
  <section
    v-else-if="kind === 'picture'"
    class="msgr-picture phone-in pointer-events-auto relative w-[300px] max-w-full"
    style="--tilt: 0deg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('tutorial.from', { sender })"
    data-test="tutorial-phone"
    data-device="picture"
  >
    <img :src="messenger.frame_url ?? ''" alt="" class="pointer-events-none block h-auto max-h-[64svh] w-full object-contain" data-test="tutorial-frame" />
    <div class="msgr-screen absolute flex flex-col overflow-hidden" :style="screenStyle" data-test="tutorial-screen">
      <div class="msgr-hd">
        <span class="min-w-0 flex-1 truncate" data-test="tutorial-sender">{{ sender }}</span>
        <span class="msgr-step" data-test="tutorial-step">{{ step }}</span>
      </div>
      <div class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto px-2 py-1.5" aria-live="polite" data-test="tutorial-thread">
        <p v-for="line in messages" :key="line" class="msgr-msg" data-test="tutorial-sms">{{ line }}</p>
        <template v-if="asked">
          <p class="msgr-msg msgr-out">{{ t('tutorial.who') }}</p>
          <p class="msgr-msg" data-test="tutorial-deflect">{{ t('tutorial.deflect') }}</p>
        </template>
        <p v-if="error" class="msgr-msg !text-danger" role="alert" data-test="tutorial-error">{{ t('common.error') }}</p>
      </div>
      <div class="flex flex-none flex-wrap gap-1 px-2 pb-2">
        <button ref="replyButton" type="button" class="btn btn-primary btn-sm" :disabled="busy" data-test="tutorial-reply" @click="emit('reply')">{{ reply }}</button>
        <button v-if="decline" type="button" class="btn btn-secondary btn-sm" :disabled="busy" data-test="tutorial-decline" @click="emit('decline')">{{ decline }}</button>
        <button v-if="showWho" type="button" class="btn btn-secondary btn-sm" data-test="tutorial-who" @click="emit('who')">{{ t('tutorial.who') }}</button>
      </div>
    </div>
  </section>

  <!-- A plain window in the colours of the game: no device at all. -->
  <section
    v-else
    class="card card-hi msgr-plain phone-in pointer-events-auto flex w-[320px] max-w-full flex-col gap-3"
    style="max-height: 64svh; --tilt: 0deg"
    role="dialog"
    aria-modal="true"
    :aria-label="t('tutorial.from', { sender })"
    data-test="tutorial-phone"
    data-device="plain"
  >
    <div class="flex items-center justify-between gap-2">
      <span class="min-w-0 flex-1 truncate font-display text-xl font-bold uppercase leading-none tracking-wide text-amber" data-test="tutorial-sender">{{ sender }}</span>
      <span class="tag tag-am flex-none" data-test="tutorial-step">{{ step }}</span>
    </div>
    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto" aria-live="polite" data-test="tutorial-thread">
      <p v-for="line in messages" :key="line" class="msgr-msg" data-test="tutorial-sms">{{ line }}</p>
      <template v-if="asked">
        <p class="msgr-msg msgr-out">{{ t('tutorial.who') }}</p>
        <p class="msgr-msg" data-test="tutorial-deflect">{{ t('tutorial.deflect') }}</p>
      </template>
      <p v-if="error" class="text-sm text-danger" role="alert" data-test="tutorial-error">{{ t('common.error') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button ref="replyButton" type="button" class="btn btn-primary btn-sm" :disabled="busy" data-test="tutorial-reply" @click="emit('reply')">{{ reply }}</button>
      <button v-if="decline" type="button" class="btn btn-secondary btn-sm" :disabled="busy" data-test="tutorial-decline" @click="emit('decline')">{{ decline }}</button>
      <button v-if="showWho" type="button" class="btn btn-secondary btn-sm" data-test="tutorial-who" @click="emit('who')">{{ t('tutorial.who') }}</button>
    </div>
  </section>
</template>
