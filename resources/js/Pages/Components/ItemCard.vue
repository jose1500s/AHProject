<script setup>
import { Sparkles, ListOrdered } from '@lucide/vue'

const QUALITY_STYLES = {
  poor: { text: 'text-slate-400', border: 'border-slate-400/30', bg: 'bg-slate-400/10' },
  common: { text: 'text-slate-100', border: 'border-white/10', bg: 'bg-white/5' },
  uncommon: { text: 'text-emerald-400', border: 'border-emerald-400/30', bg: 'bg-emerald-500/10' },
  rare: { text: 'text-sky-400', border: 'border-sky-400/30', bg: 'bg-sky-500/10' },
  epic: { text: 'text-purple-400', border: 'border-purple-400/30', bg: 'bg-purple-500/10' },
  legendary: { text: 'text-orange-400', border: 'border-orange-400/30', bg: 'bg-orange-500/10' },
}

defineProps({
  name: String,
  icon: { type: [Object, Function], default: () => Sparkles },
  quality: { type: String, default: 'common' },
  gold: Number,
  silver: Number,
  copper: Number,
  listings: Number,
  volume: Number,
})
</script>

<template>
  <div class="flex w-72 items-center gap-2.5 rounded-lg border border-white/10 bg-[#141224] px-2.5 py-2
           transition-colors hover:cursor-pointer hover:border-white/20">

    <div class="flex size-9 shrink-0 items-center justify-center rounded-md border"
      :class="[QUALITY_STYLES[quality].border, QUALITY_STYLES[quality].bg]">
      <component :is="icon" class="size-4" :class="QUALITY_STYLES[quality].text" />
    </div>

    <div class="min-w-0 flex-1">
      <h3 class="truncate text-sm font-semibold" :class="QUALITY_STYLES[quality].text">
        {{ name }}
      </h3>
      <div class="flex items-center gap-1 text-[11px] text-slate-500">
        <ListOrdered class="size-3" />
        <span>{{ listings }} · Vol {{ volume?.toLocaleString('en-US') }}</span>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-1">
      <div v-if="gold !== undefined" class="flex items-center gap-1">
        <span class="size-2 rounded-full bg-amber-400"></span>
        <span class="text-sm font-bold text-slate-100">{{ gold }}</span>
      </div>
      <div v-if="silver !== undefined" class="flex items-center gap-1">
        <span class="size-2 rounded-full bg-slate-300"></span>
        <span class="text-sm font-bold text-slate-100">{{ silver }}</span>
      </div>
      <div v-if="copper !== undefined" class="flex items-center gap-1">
        <span class="size-2 rounded-full bg-orange-700"></span>
        <span class="text-sm font-bold text-slate-100">{{ copper }}</span>
      </div>
    </div>
  </div>
</template>