<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { ChevronDown, Search } from '@lucide/vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // array de slugs
  realms: { type: Array, required: true },
  region: { type: String, default: 'US' },
})
const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const query = ref('')
const rootRef = ref(null)

const filteredRealms = computed(() => {
  if (!query.value.trim()) return props.realms
  const q = query.value.toLowerCase()
  return props.realms.filter(r => String(r?.name ?? '').toLowerCase().includes(q))
})

const summaryLabel = computed(() => {
  if (!props.modelValue.length) return 'Selecciona reinos...'
  return props.realms.filter(r => props.modelValue.includes(r.slug)).map(r => r.name).join(', ')
})

function toggle(slug) {
  const set = new Set(props.modelValue)
  set.has(slug) ? set.delete(slug) : set.add(slug)
  emit('update:modelValue', Array.from(set))
}

function onClickOutside(e) {
  if (rootRef.value && !rootRef.value.contains(e.target)) isOpen.value = false
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="rootRef" class="relative">
    <label class="mb-1 block text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Realms</label>

    <button type="button" @click="isOpen = !isOpen"
      class="flex w-full items-center justify-between gap-3 rounded-lg border border-indigo-400/30 bg-[#12142b] px-3 py-2 text-sm text-slate-100 hover:border-indigo-400/60">
      <span class="truncate">{{ summaryLabel }}</span>
      <span class="flex items-center gap-2 shrink-0">
        <span v-if="modelValue.length" class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-xs text-indigo-300">
          {{ modelValue.length }}
        </span>
        <ChevronDown class="size-4 text-indigo-300 transition-transform" :class="{ 'rotate-180': isOpen }" />
      </span>
    </button>

    <div v-if="isOpen"
      class="absolute z-10 mt-2 w-80 overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
      <div class="relative border-b border-white/5 p-2">
        <Search class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
        <input v-model="query" type="text" placeholder="Buscar reinos..."
          class="w-full rounded-md bg-white/5 py-1.5 pl-7 pr-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:bg-white/10" />
      </div>

      <ul class="max-h-64 overflow-y-auto">
        <li v-for="realm in filteredRealms" :key="realm.slug" @click="toggle(realm.slug)"
          class="flex cursor-pointer items-center justify-between px-4 py-2 text-sm hover:bg-white/5"
          :class="modelValue.includes(realm.slug) ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-300'">
          <span class="flex items-center gap-2">
            <span class="flex size-4 shrink-0 items-center justify-center rounded border"
              :class="modelValue.includes(realm.slug) ? 'border-indigo-400 bg-indigo-500' : 'border-white/20'">
              <svg v-if="modelValue.includes(realm.slug)" viewBox="0 0 12 12" class="size-2.5 text-white"
                fill="currentColor">
                <path d="M4.5 8.5L2 6l-.7.7L4.5 10 10 4.5l-.7-.7z" />
              </svg>
            </span>
            {{ realm.name }}
          </span>
          <span class="text-xs text-slate-500">{{ region }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>