<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { Sparkles, X, Plus, Search, ArrowLeft } from '@lucide/vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const mode = ref('search')
const query = ref('')
const results = ref([])
const activeItem = ref(null)
const variants = ref([])
const loadingVariants = ref(false)
const rootRef = ref(null)
let searchTimeout = null

watch(query, (q) => {
    clearTimeout(searchTimeout)
    if (q.trim().length < 2) { results.value = []; return }
    searchTimeout = setTimeout(async () => {
        const res = await fetch(`/api/items/search?q=${encodeURIComponent(q)}`)
        results.value = await res.json()
    }, 300)
})

async function selectItem(item) {
    activeItem.value = item
    mode.value = 'variants'
    loadingVariants.value = true
    try {
        const res = await fetch(`/api/items/${item.blizzard_id}/variants`)
        const data = await res.json()
        variants.value = data.variants
    } finally {
        loadingVariants.value = false
    }
}

function addVariant(variant) {
    const id = activeItem.value.blizzard_id
    if (props.modelValue.some(i => i.id === id && i.ilvl === variant.ilvl)) return

    emit('update:modelValue', [...props.modelValue, {
        id,
        ilvl: variant.ilvl,
        name: activeItem.value.name,
        icon_url: activeItem.value.icon_url,
        quality: activeItem.value.quality,
    }])

    closeAndReset()
}

function backToSearch() {
    mode.value = 'search'
    activeItem.value = null
    variants.value = []
}

function closeAndReset() {
    isOpen.value = false
    mode.value = 'search'
    query.value = ''
    results.value = []
    activeItem.value = null
    variants.value = []
}

function onClickOutside(e) {
    if (rootRef.value && !rootRef.value.contains(e.target)) closeAndReset()
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
    <div ref="rootRef" class="relative">
        <label class="mb-1 block text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Items</label>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="isOpen = !isOpen"
                class="flex items-center gap-1 rounded-lg border border-white/10 px-3 py-1.5 text-sm text-slate-400 hover:border-white/20 hover:text-white">
                <Plus class="size-3.5" /> Add
            </button>
        </div>

        <div v-if="isOpen"
            class="absolute z-10 mt-2 w-80 overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
            <!-- paso 1: buscar por nombre -->
            <template v-if="mode === 'search'">
                <div class="relative border-b border-white/5 p-2">
                    <Search
                        class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                    <input v-model="query" type="text" placeholder="Buscar objeto..."
                        class="w-full rounded-md bg-white/5 py-1.5 pl-7 pr-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:bg-white/10" />
                </div>

                <ul class="max-h-56 overflow-y-auto">
                    <li v-for="result in results" :key="result.blizzard_id" @click="selectItem(result)"
                        class="flex cursor-pointer items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">
                        <img v-if="result.icon_url" :src="result.icon_url" class="size-5 rounded" />
                        {{ result.name }}
                    </li>
                    <li v-if="query.length >= 2 && !results.length" class="px-4 py-3 text-sm text-slate-500">
                        Sin resultados
                    </li>
                </ul>
            </template>

            <!-- paso 2: elegir variante por ilvl -->
            <template v-else>
                <div class="flex items-center gap-2 border-b border-white/5 px-3 py-2">
                    <button type="button" @click="backToSearch" class="text-slate-500 hover:text-white">
                        <ArrowLeft class="size-4" />
                    </button>
                    <img v-if="activeItem?.icon_url" :src="activeItem.icon_url" class="size-5 rounded" />
                    <span class="truncate text-sm font-medium text-slate-200">{{ activeItem?.name }}</span>
                </div>

                <div v-if="loadingVariants" class="px-4 py-4 text-center text-sm text-slate-500">Cargando variantes...
                </div>

                <template v-else>
                    <ul v-if="variants.length" class="max-h-56 overflow-y-auto">
                        <li v-for="variant in variants" :key="variant.ilvl ?? 'none'" @click="addVariant(variant)"
                            class="flex cursor-pointer items-center justify-between px-4 py-2 text-sm hover:bg-white/5">
                            <span class="text-slate-200">{{ variant.ilvl !== null ? `ilvl ${variant.ilvl}` : 'Sin ilvl'
                            }}</span>
                            <span class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-0.5 text-amber-400 font-semibold">
                                    <span class="size-1.5 rounded-full bg-amber-400"></span>{{ variant.cheapest.gold }}
                                </span>
                                <span class="text-[10px] text-slate-500">{{ variant.auction_count }}x</span>
                            </span>
                        </li>
                    </ul>

                    <!-- sin auctions activas en ningún realm sincronizado: permite agregarlo igual, para buscarlo en otros reinos -->
                    <div v-else class="px-4 py-4 text-center">
                        <p class="mb-2 text-sm text-slate-500">Sin subastas activas para este objeto</p>
                        <button type="button" @click="addVariant({ ilvl: null })"
                            class="rounded-lg border border-indigo-400/30 bg-indigo-500/10 px-3 py-1.5 text-xs text-indigo-300 hover:border-indigo-400/60">
                            Agregar de todas formas
                        </button>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>