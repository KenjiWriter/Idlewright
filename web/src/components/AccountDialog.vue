<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * The window of a step in the account: a dialog, so the focus moves into it and stays there, Esc and a click outside close it, and the
 * focus goes back to where it was. It has a title and whatever the step puts in it. (The window "Save your garage" has its own.)
 */
defineProps<{ title: string }>()

const emit = defineEmits<{ close: [] }>()

const dialog = ref<HTMLElement | null>(null)
let returnFocusTo: Element | null = null

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'

onMounted(() => {
  returnFocusTo = document.activeElement
  dialog.value?.focus()
})

onBeforeUnmount(() => {
  if (returnFocusTo instanceof HTMLElement) {
    returnFocusTo.focus()
  }
})

/** Keeps Tab inside the dialog. */
function keep(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')

    return
  }

  if (event.key !== 'Tab' || !dialog.value) {
    return
  }

  const items = [...dialog.value.querySelectorAll<HTMLElement>(FOCUSABLE)]
  const first = items[0]
  const last = items[items.length - 1]

  if (!first || !last) {
    return
  }

  if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4" data-test="backdrop" @click.self="emit('close')">
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="account-dialog-title"
      tabindex="-1"
      class="card flex max-h-full w-full max-w-md flex-col gap-4 overflow-y-auto rounded-b-none outline-none sm:rounded-b-xl"
      data-test="dialog"
      @keydown="keep"
    >
      <h2 id="account-dialog-title" class="text-2xl font-bold" data-test="dialog-title">{{ title }}</h2>
      <slot />
    </div>
  </div>
</template>
