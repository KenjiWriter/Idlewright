<script setup lang="ts">
import type { TutorialMessenger } from '@/api/types'

/** The small picture of what the mentor writes on: the icon the game gave, or a drawing of its device (a phone, an envelope, a tablet, a bubble). */
defineProps<{ messenger: TutorialMessenger }>()
</script>

<template>
  <img v-if="messenger.icon_url" :src="messenger.icon_url" alt="" class="inline-block h-4 w-4 flex-none object-contain" aria-hidden="true" data-test="device-icon" />
  <svg
    v-else
    class="inline-block h-4 w-4 flex-none"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    :data-device="messenger.device"
    data-test="device-icon"
  >
    <template v-if="messenger.device === 'phone'">
      <rect x="7" y="2" width="10" height="20" rx="2" />
      <path d="M11 18h2" />
    </template>
    <template v-else-if="messenger.device === 'letter'">
      <rect x="3" y="5" width="18" height="14" rx="1" />
      <path d="M3 7l9 6 9-6" />
    </template>
    <template v-else-if="messenger.device === 'tablet'">
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <path d="M7 9h6M7 13h10" />
    </template>
    <template v-else-if="messenger.device === 'picture'">
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <path d="M7 8h10M7 12h7" />
    </template>
    <template v-else>
      <path d="M4 5h16v11H9l-5 4z" />
    </template>
  </svg>
</template>
