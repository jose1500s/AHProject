<script setup>
import { ref, watch, onMounted } from 'vue'
import { GitCompare, Sparkles, X } from '@lucide/vue'
import ItemPicker from './ItemPicker.vue'
import RealmMultiSelect from './RealmMultiSelect.vue'
import RefreshButton from './RefreshButton.vue'
import { useRealmSelection } from '../../Composables/useRealmSelection.js'

const props = defineProps({
    realms: { type: Array, required: true },
})

const STORAGE_KEY = 'compare_items'

const selectedItems = ref([])
const selectedRealms = ref([])
const rows = ref([])
const lastSynced = ref({})
const loading = ref(false)
const openCells = ref(new Set())
const userTouchedRealms = ref(false)

const { realm } = useRealmSelection(props.realms)

watch(realm, (newRealmName) => {
    if (!newRealmName || userTouchedRealms.value) return
    const slug = props.realms.find(r => r.name === newRealmName)?.slug
    if (slug) selectedRealms.value = [slug]
}, { immediate: true })

function onRealmsChange(newVal) {
    userTouchedRealms.value = true
    selectedRealms.value = newVal
}

onMounted(() => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY)
        if (stored) selectedItems.value = JSON.parse(stored)
    } catch { }
})

watch(selectedItems, (val) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(val)) } catch { }
}, { deep: true })

let debounceTimeout = null
watch([selectedItems, selectedRealms], () => {
    clearTimeout(debounceTimeout)

    if (!selectedItems.value.length || !selectedRealms.value.length) {
        rows.value = []
        return
    }

    debounceTimeout = setTimeout(() => fetchComparison(), 300)
}, { deep: true })

async function fetchComparison(force = false) {
    loading.value = true
    try {
        const params = new URLSearchParams()
        params.append('items', JSON.stringify(selectedItems.value.map(i => ({ item_id: i.id, ilvl: i.ilvl }))))
        selectedRealms.value.forEach(slug => params.append('realm_slugs[]', slug))
        if (force) params.append('force', '1')

        const res = await fetch(`/api/realm-comparison?${params}`)
        const data = await res.json()
        rows.value = data.items
        lastSynced.value = data.last_synced
    } finally {
        loading.value = false
    }
}

function removeItem(id, ilvl) {
    selectedItems.value = selectedItems.value.filter(i => !(i.id === id && i.ilvl === ilvl))
}

function cellKey(row, slug) {
    return `${row.item_id}-${row.ilvl}-${slug}`
}

function toggleCell(key) {
    const next = new Set(openCells.value)
    next.has(key) ? next.delete(key) : next.add(key)
    openCells.value = next
}

function timeAgo(dateStr) {
    if (!dateStr) return '—'
    const diffMinutes = Math.floor((Date.now() - new Date(dateStr)) / 60000)
    if (diffMinutes < 1) return 'ahora'
    if (diffMinutes < 60) return `hace ${diffMinutes}m`
    return `hace ${Math.floor(diffMinutes / 60)}h`
}

const QUALITY_COLORS = {
    poor: 'text-slate-400', common: 'text-slate-100', uncommon: 'text-emerald-400',
    rare: 'text-sky-400', epic: 'text-purple-400', legendary: 'text-orange-400',
}
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-[#12142b] p-5 w-full mb-20">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                <GitCompare class="size-4 text-indigo-400" />
                Realm Price Comparison
            </h2>
            <RefreshButton v-if="rows.length" :loading="loading" @click="fetchComparison(true)" />
        </div>

        <div class="grid grid-cols-2 gap-6">
            <ItemPicker v-model="selectedItems" />
            <RealmMultiSelect :model-value="selectedRealms" @update:model-value="onRealmsChange" :realms="realms" />
        </div>

        <div v-if="selectedItems.length && selectedRealms.length"
            class="mt-6 overflow-x-auto rounded-xl border border-white/5">
            <table class="w-full text-sm">
                <thead class="bg-white/[0.02] text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Item</th>
                        <th v-for="slug in selectedRealms" :key="slug" class="px-4 py-2.5 text-left">
                            <div class="flex items-center gap-1.5">
                                <span>{{realms.find(r => r.slug === slug)?.name ?? slug}}</span>
                                <span class="text-slate-600">—</span>
                                <span class="font-normal normal-case text-slate-500">{{ timeAgo(lastSynced[slug])
                                }}</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="selectedRealms.length + 1" class="px-4 py-6 text-center text-slate-500">
                            Cargando...</td>
                    </tr>
                    <tr v-for="row in rows" :key="`${row.item_id}-${row.ilvl}`"
                        class="border-t border-white/5 align-top">
                        <td class="px-4 py-2.5">
                            <span class="flex items-center gap-2"
                                :class="QUALITY_COLORS[row.quality] ?? 'text-slate-100'">
                                <img v-if="row.icon_url" :src="row.icon_url" class="size-5 rounded" />
                                <Sparkles v-else class="size-5" />
                                {{ row.name }}
                                <span class="rounded bg-white/5 px-1.5 py-0.5 text-[12px] text-slate-100 font-semibold">
                                    {{ row.ilvl !== null ? `ilvl ${row.ilvl}` : 'Sin ilvl' }}
                                </span>
                                <button type="button" @click="removeItem(row.item_id, row.ilvl)"
                                    class="text-slate-500 hover:text-red-400">
                                    <X class="size-3.5" />
                                </button>
                            </span>
                        </td>
                        <td v-for="slug in selectedRealms" :key="slug" class="px-4 py-2.5">
                            <template v-if="row.prices[slug]?.length">
                                <button type="button" @click="toggleCell(cellKey(row, slug))"
                                    class="inline-flex items-center gap-1 rounded hover:bg-white/5 px-1 py-0.5">
                                    <span class="inline-flex items-center gap-0.5 text-amber-400 font-semibold">
                                        <span class="size-2 rounded-full bg-amber-400"></span>{{
                                            row.prices[slug][0].gold }}
                                    </span>
                                    <span class="inline-flex items-center gap-0.5 text-slate-300 font-semibold">
                                        <span class="size-2 rounded-full bg-slate-300"></span>{{
                                            row.prices[slug][0].silver }}
                                    </span>
                                    <span v-if="row.prices[slug].length > 1" class="ml-1 text-[10px] text-slate-500">
                                        +{{ row.prices[slug].length - 1 }}
                                    </span>
                                </button>

                                <div v-if="openCells.has(cellKey(row, slug))"
                                    class="mt-1 flex flex-col gap-1 border-l border-white/10 pl-2">
                                    <span v-for="(price, i) in row.prices[slug].slice(1)" :key="i"
                                        class="inline-flex items-center gap-1 text-xs">
                                        <span class="inline-flex items-center gap-0.5 text-amber-400/80">
                                            <span class="size-1.5 rounded-full bg-amber-400"></span>{{ price.gold }}
                                        </span>
                                        <span class="inline-flex items-center gap-0.5 text-slate-400">
                                            <span class="size-1.5 rounded-full bg-slate-300"></span>{{ price.silver }}
                                        </span>
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-slate-600">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>