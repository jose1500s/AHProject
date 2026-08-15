<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  links: { type: Array, required: true },
})

function go(url) {
  if (!url) return

  router.get(url, {}, {
    only: ['auctions'], // solo repide auctions, no realms ni nada más
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