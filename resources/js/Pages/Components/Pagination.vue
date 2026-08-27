<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  links: { type: Array, required: true },
  mode: { type: String, default: 'inertia' },
  only: { type: Array, default: () => ['auctions'] },
})
const emit = defineEmits(['navigate'])

function go(url) {
  if (!url) return

  if (props.mode === 'callback') {
    emit('navigate', url)
    return
  }

  router.get(url, {}, {
    only: props.only,
    preserveState: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <nav class="flex flex-wrap items-center justify-center gap-1 mt-6">
    <template v-for="(link, i) in links" :key="i">
      <span
        v-if="!link.url"
        class="rounded-lg px-3 py-1.5 text-sm text-slate-600"
        v-html="link.label"
      />
      <button
        v-else
        type="button"
        @click="go(link.url)"
        class="rounded-lg px-3 py-1.5 text-sm transition-colors"
        :class="link.active
          ? 'bg-indigo-500/20 text-indigo-300 font-semibold'
          : 'text-slate-400 hover:bg-white/5 hover:text-white'"
        v-html="link.label"
      />
    </template>
  </nav>
</template>