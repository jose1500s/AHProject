<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import ChevronDown from './ChevronDown.vue'
import { Search } from '@lucide/vue'

const props = defineProps({
  modelValue: String,
  options: { type: Array, required: true },
  label: String,
})
const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const rootRef = ref(null)
const searchInputRef = ref(null)
const query = ref('')

const filteredOptions = computed(() => {
  if (!query.value.trim()) return props.options
  return props.options.filter(option =>
    option.toLowerCase().includes(query.value.toLowerCase())
  )
})

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

watch(isOpen, async (open) => {
  if (open) {
    query.value = ''
    await nextTick()
    searchInputRef.value?.focus()
  }
})

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="rootRef" class="relative w-40">
    <label class="mb-1 block text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
      {{ label }}
    </label>

    <button type="button" @click="toggle" class="flex w-full items-center justify-between rounded-lg border border-indigo-400/30
             bg-[#12142b] px-3 py-2 text-sm font-medium text-slate-100
             transition-colors hover:border-indigo-400/60 focus:outline-none focus:border-indigo-400">
      <span>{{ modelValue }}</span>
      <ChevronDown class="size-4 text-indigo-300 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" />
    </button>

    <div v-if="isOpen" class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20
             bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
      <div class="relative border-b border-white/5 p-2">
        <Search class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
        <input ref="searchInputRef" v-model="query" type="text" placeholder="Buscar..." class="w-full rounded-md bg-white/5 py-1.5 pl-7 pr-2 text-sm text-slate-100
                 placeholder:text-slate-500 outline-none focus:bg-white/10" />
      </div>

      <ul class="app-scroll max-h-56 overflow-y-auto overscroll-contain">
        <li v-for="option in filteredOptions" :key="option" @click="select(option)"
          class="flex cursor-pointer items-center justify-between px-4 py-2.5 text-sm transition-colors" :class="option === modelValue
            ? 'bg-indigo-500/15 text-indigo-300 font-medium'
            : 'text-slate-300 hover:bg-white/5 hover:text-white'">
          {{ option }}
          <span v-if="option === modelValue" class="size-1.5 rounded-full bg-indigo-400"></span>
        </li>

        <li v-if="!filteredOptions.length" class="px-4 py-3 text-sm text-slate-500">
          Sin resultados
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.app-scroll {
  scrollbar-width: thin;
  scrollbar-color: #312e5c #12142b;
}

.app-scroll::-webkit-scrollbar {
  width: 8px;
}

.app-scroll::-webkit-scrollbar-track {
  background: #12142b;
}

.app-scroll::-webkit-scrollbar-thumb {
  background-color: #312e5c;
  border-radius: 9999px;
  border: 2px solid #12142b;
}

.app-scroll::-webkit-scrollbar-thumb:hover {
  background-color: #4338ca;
}
</style>