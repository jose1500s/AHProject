<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import ChevronDown from './ChevronDown.vue'

const props = defineProps({
  modelValue: String,
  options: { type: Array, required: true }, // array de strings
  label: String,
})
const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const rootRef = ref(null)

function toggle() {
  isOpen.value = !isOpen.value
}

function select(option) {
  emit('update:modelValue', option)
  isOpen.value = false
}

function onClickOutside(e) {
  if (rootRef.value && !rootRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="rootRef" class="relative w-40">
    <label class="mb-1 block text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
      {{ label }}
    </label>

    <button
      type="button"
      @click="toggle"
      class="flex w-full items-center justify-between rounded-lg border border-indigo-400/30
             bg-[#12142b] px-3 py-2 text-sm font-medium text-slate-100
             transition-colors hover:border-indigo-400/60 focus:outline-none focus:border-indigo-400"
    >
      <span>{{ modelValue }}</span>
      <ChevronDown class="size-4 text-indigo-300 transition-transform duration-200"/>
    </button>

    <ul
      v-if="isOpen"
      class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20
             bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]"
    >
      <li
        v-for="option in options"
        :key="option"
        @click="select(option)"
        class="flex cursor-pointer items-center justify-between px-4 py-2.5 text-sm transition-colors"
        :class="option === modelValue
          ? 'bg-indigo-500/15 text-indigo-300 font-medium'
          : 'text-slate-300 hover:bg-white/5 hover:text-white'"
      >
        {{ option }}
        <span v-if="option === modelValue" class="size-1.5 rounded-full bg-indigo-400"></span>
      </li>
    </ul>
  </div>
</template>