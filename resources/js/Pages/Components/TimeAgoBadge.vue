<script setup>
defineProps({
  label: { type: String, default: '0m ago' },
})
</script>

<template>
  <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-[#12142b] px-3 py-1.5">
    <span class="relative flex size-1.5">
      <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
      <span class="relative inline-flex size-1.5 rounded-full bg-emerald-500"></span>
    </span>
    <span class="text-xs font-semibold tracking-wide text-slate-300 uppercase">{{ label }}</span>
  </div>
</template>