<script setup>
import { Sparkles, ChevronRight, ListOrdered } from '@lucide/vue'

defineProps({
  name: String,
  subtitle: String,
  icon: { type: Object, default: () => Sparkles },
  gold: Number,
  silver: Number,
  copper: Number,
  listings: Number,
  volume: Number,
})
</script>

<template>
  <div class="w-72 rounded-2xl border border-white/10 bg-[#141224] p-4
              shadow-[0_0_20px_-8px_rgba(168,85,247,0.25)] hover:cursor-pointer">

    <div class="flex items-start gap-3">
      <div class="flex size-11 shrink-0 items-center justify-center rounded-xl
                  border border-purple-400/30 bg-purple-500/10">
        <component :is="icon" class="size-5 text-purple-400" />
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="truncate text-sm font-bold text-purple-300">{{ name }}</h3>
        <p class="text-xs text-slate-500">{{ subtitle }}</p>
      </div>

      <ChevronRight class="mt-1 size-4 shrink-0 text-slate-600" />
    </div>

    <div class="my-3 border-t border-white/5"></div>

    <div>
      <p class="text-[10px] font-semibold tracking-widest text-slate-500 uppercase">Market Price</p>
      <div class="mt-1.5 flex items-center gap-4">
        <div class="flex items-center gap-1.5">
          <span class="size-2.5 rounded-full bg-amber-400"></span>
          <span class="text-lg font-bold text-slate-100">{{ gold }}</span>
        </div>
        <div v-if="silver !== undefined" class="flex items-center gap-1.5">
          <span class="size-2.5 rounded-full bg-slate-300"></span>
          <span class="text-lg font-bold text-slate-100">{{ silver }}</span>
        </div>
        <div v-if="copper !== undefined" class="flex items-center gap-1.5">
          <span class="size-2.5 rounded-full bg-orange-700"></span>
          <span class="text-lg font-bold text-slate-100">{{ copper }}</span>
        </div>
      </div>
    </div>

    <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
      <div class="flex items-center gap-1">
        <ListOrdered class="size-3.5" />
        <span>{{ listings }} listings</span>
      </div>
      <span>Vol {{ volume?.toLocaleString() }}</span>
    </div>
  </div>
</template>