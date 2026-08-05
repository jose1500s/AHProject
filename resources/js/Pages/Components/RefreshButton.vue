<!-- Components/ui/RefreshButton.vue -->
<script setup>
import { ref } from 'vue'
import { RefreshCw } from '@lucide/vue';

const emit = defineEmits(['click'])
const isSpinning = ref(false)

function handleClick() {
  isSpinning.value = true
  emit('click')
  setTimeout(() => (isSpinning.value = false), 600)
}
</script>

<template>
  <button
    type="button"
    @click="handleClick"
    class="flex size-9 items-center justify-center rounded-lg border border-indigo-400/40
           bg-[#12142b] text-indigo-300 transition-all duration-200
           shadow-[0_0_6px_0_rgba(129,140,248,0.15)]
           hover:border-indigo-400/70 hover:text-indigo-200
           hover:shadow-[0_0_8px_1px_rgba(129,140,248,0.3)]
           focus:outline-none"
  >
    <RefreshCw class="size-4" :class="{ 'animate-spin': isSpinning }" />
  </button>
</template>