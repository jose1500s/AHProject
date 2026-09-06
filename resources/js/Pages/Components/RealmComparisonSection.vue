<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { GitCompare, Sparkles, X, Star, Search, ArrowLeftRight, RefreshCw, Filter } from '@lucide/vue'
import ItemPicker from './ItemPicker.vue'
import RealmMultiSelect from './RealmMultiSelect.vue'
import RefreshButton from './RefreshButton.vue'
import { useRealmSelection } from '../../Composables/useRealmSelection.js'

const props = defineProps({
    realms: { type: Array, required: true },
})
const emit = defineEmits(['select-item'])

const STORAGE_KEY = 'compare_items'
const FAVORITES_KEY = 'compare_favorites'

const ARBITRAGE_REALM_NAMES = ['Moon Guard', 'Illidan', 'Area 52', 'Sargeras']

const selectedItems = ref([])
const selectedRealms = ref([])
const rows = ref([])
const lastSynced = ref({})
const loading = ref(false)
const openCells = ref(new Set())
const userTouchedRealms = ref(false)
const favorites = ref(new Set())
const tableSearch = ref('')

const arbitrageRows = ref([])
const arbitrageLastSynced = ref({})
const arbitrageLoading = ref(false)
const arbitrageStarted = ref(false)
const arbitrageSearch = ref('')

const arbitrageBuyRealmFilter = ref('')
const arbitrageSellRealmFilter = ref('')
const arbitrageQualityFilter = ref('')
const arbitrageBuyMenuOpen = ref(false)
const arbitrageSellMenuOpen = ref(false)
const arbitrageQualityMenuOpen = ref(false)

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

    try {
        const storedFavorites = localStorage.getItem(FAVORITES_KEY)
        if (storedFavorites) favorites.value = new Set(JSON.parse(storedFavorites))
    } catch { }
})

watch(selectedItems, (val) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(val)) } catch { }
}, { deep: true })

watch(favorites, (val) => {
    try { localStorage.setItem(FAVORITES_KEY, JSON.stringify([...val])) } catch { }
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

const arbitrageRealmSlugs = computed(() =>
    ARBITRAGE_REALM_NAMES
        .map(name => props.realms.find(r => r.name === name)?.slug)
        .filter(Boolean)
)

async function fetchArbitrage(force = false) {
    if (!selectedItems.value.length) return

    arbitrageStarted.value = true
    arbitrageLoading.value = true
    try {
        const params = new URLSearchParams()
        params.append('items', JSON.stringify(selectedItems.value.map(i => ({ item_id: i.id, ilvl: i.ilvl }))))
        arbitrageRealmSlugs.value.forEach(slug => params.append('realm_slugs[]', slug))
        if (force) params.append('force', '1')

        const res = await fetch(`/api/realm-comparison?${params}`)
        const data = await res.json()
        arbitrageRows.value = data.items
        arbitrageLastSynced.value = data.last_synced
    } finally {
        arbitrageLoading.value = false
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

function favoriteKey(row) {
    return `${row.item_id}-${row.ilvl}`
}

function toggleFavorite(row) {
    const key = favoriteKey(row)
    const next = new Set(favorites.value)
    next.has(key) ? next.delete(key) : next.add(key)
    favorites.value = next
}

function openItemDetail(row) {
    emit('select-item', {
        itemId: row.item_id,
        ilvl: row.ilvl,
        realms: selectedRealms.value.map(slug => ({
            slug,
            name: props.realms.find(r => r.slug === slug)?.name ?? slug,
        })),
    })
}

function realmName(slug) {
    return props.realms.find(r => r.slug === slug)?.name ?? slug
}

function priceToCopper(price) {
    return (price.gold || 0) * 10000 + (price.silver || 0) * 100 + (price.copper || 0)
}

function copperToGsc(copper) {
    const c = Math.round(Math.abs(copper))
    return {
        gold: Math.floor(c / 10000),
        silver: Math.floor((c % 10000) / 100),
        copper: c % 100,
    }
}

const sortedRows = computed(() => {
    return [...rows.value].sort((a, b) => {
        const aFav = favorites.value.has(favoriteKey(a))
        const bFav = favorites.value.has(favoriteKey(b))
        if (aFav === bFav) return 0
        return aFav ? -1 : 1
    })
})

function normalizeText(str) {
    return String(str ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // quita los acentos/diacríticos
}

const filteredRows = computed(() => {
    const q = normalizeText(tableSearch.value.trim())
    if (!q) return sortedRows.value
    return sortedRows.value.filter(row => normalizeText(row?.name).includes(q))
})

const arbitrageOpportunities = computed(() => {
    const opportunities = []

    for (const row of arbitrageRows.value) {
        if (row.ilvl === null || row.ilvl === undefined) continue

        const pricesBySlug = arbitrageRealmSlugs.value
            .map(slug => {
                const price = row.prices?.[slug]?.[0]
                if (!price) return null
                return { slug, copper: priceToCopper(price) }
            })
            .filter(Boolean)

        if (pricesBySlug.length < 2) continue

        const buy = pricesBySlug.reduce((a, b) => (b.copper < a.copper ? b : a))
        const sell = pricesBySlug.reduce((a, b) => (b.copper > a.copper ? b : a))

        if (buy.slug === sell.slug || buy.copper <= 0) continue

        const netSell = Math.round(sell.copper * 0.95)
        const netProfit = netSell - buy.copper

        if (netProfit <= 0) continue

        opportunities.push({
            row,
            buySlug: buy.slug,
            buyCopper: buy.copper,
            sellSlug: sell.slug,
            sellCopper: sell.copper,
            netProfit,
        })
    }

    return opportunities.sort((a, b) => b.netProfit - a.netProfit)
})

const arbitrageBuyRealms = computed(() =>
    [...new Set(arbitrageOpportunities.value.map(o => o.buySlug))].sort()
)

const arbitrageSellRealms = computed(() =>
    [...new Set(arbitrageOpportunities.value.map(o => o.sellSlug))].sort()
)

const arbitrageQualities = computed(() =>
    [...new Set(arbitrageOpportunities.value.map(o => o.row.quality).filter(Boolean))]
)

function selectBuyRealmFilter(slug) {
    arbitrageBuyRealmFilter.value = slug
    arbitrageBuyMenuOpen.value = false
}

function selectSellRealmFilter(slug) {
    arbitrageSellRealmFilter.value = slug
    arbitrageSellMenuOpen.value = false
}

function selectQualityFilter(quality) {
    arbitrageQualityFilter.value = quality
    arbitrageQualityMenuOpen.value = false
}

function closeBuyMenuOnBlur() {
    setTimeout(() => { arbitrageBuyMenuOpen.value = false }, 150)
}

function closeSellMenuOnBlur() {
    setTimeout(() => { arbitrageSellMenuOpen.value = false }, 150)
}

function closeQualityMenuOnBlur() {
    setTimeout(() => { arbitrageQualityMenuOpen.value = false }, 150)
}

const filteredArbitrage = computed(() => {
    let list = arbitrageOpportunities.value

    if (arbitrageBuyRealmFilter.value) {
        list = list.filter(o => o.buySlug === arbitrageBuyRealmFilter.value)
    }

    if (arbitrageSellRealmFilter.value) {
        list = list.filter(o => o.sellSlug === arbitrageSellRealmFilter.value)
    }

    if (arbitrageQualityFilter.value) {
        list = list.filter(o => o.row.quality === arbitrageQualityFilter.value)
    }

    const q = normalizeText(arbitrageSearch.value.trim())
    if (q) {
        list = list.filter(o => normalizeText(o.row.name).includes(q))
    }

    return list
})

const totalArbitrageProfit = computed(() =>
    arbitrageOpportunities.value.reduce((sum, o) => sum + o.netProfit, 0)
)

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
    <section class="rounded-2xl border border-white/10 bg-[#12142b]/55 backdrop-blur-xl p-5 w-full">
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

        <div v-if="selectedItems.length && selectedRealms.length" class="mt-4 flex justify-center">
            <div class="relative w-1/3">
                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                <input v-model="tableSearch" type="text" placeholder="Buscar en la tabla..."
                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-md text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60 focus:bg-white/10 text-center" />
            </div>
        </div>

        <div v-if="selectedItems.length && selectedRealms.length"
            class="mt-3 overflow-x-auto rounded-xl border border-white/5 bg-white/[0.02] backdrop-blur-md">
            <div class="app-scroll max-h-[28rem] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-[#181b3a] text-[11px] uppercase tracking-wider text-slate-500">
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
                        <tr v-else-if="!filteredRows.length">
                            <td :colspan="selectedRealms.length + 1" class="px-4 py-6 text-center text-slate-500">
                                Sin resultados para "{{ tableSearch }}"</td>
                        </tr>
                        <tr v-for="row in filteredRows" :key="`${row.item_id}-${row.ilvl}`"
                            class="border-t border-white/5 align-top"
                            :class="favorites.has(favoriteKey(row)) ? 'bg-amber-400/4' : ''">
                            <td class="px-4 py-2.5 cursor-pointer hover:bg-white/3" @click="openItemDetail(row)">
                                <span class="flex items-center gap-2"
                                    :class="QUALITY_COLORS[row.quality] ?? 'text-slate-100'">
                                    <button type="button" @click.stop="toggleFavorite(row)" class="shrink-0">
                                        <Star class="size-4 transition-colors" :class="favorites.has(favoriteKey(row))
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-slate-600 hover:text-slate-400'" />
                                    </button>
                                    <img v-if="row.icon_url" :src="row.icon_url" class="size-5 rounded shrink-0" />
                                    <Sparkles v-else class="size-5 shrink-0" />
                                    <span class="truncate">{{ row.name }}</span>
                                    <span
                                        class="shrink-0 rounded bg-white/5 px-1.5 py-0.5 text-[12px] font-semibold text-slate-100">
                                        {{ row.ilvl !== null ? `ilvl ${row.ilvl}` : 'Sin ilvl' }}
                                    </span>
                                    <button type="button" @click.stop="removeItem(row.item_id, row.ilvl)"
                                        class="shrink-0 text-slate-500 hover:text-red-400">
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
        </div>

        <div v-if="selectedItems.length" class="mt-5 border-t border-white/10 pt-5">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                        <ArrowLeftRight class="size-4 text-indigo-400" />
                        Oportunidades de arbitraje
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Compara los {{ selectedItems.length }} ítems de tu lista contra {{ ARBITRAGE_REALM_NAMES.length }} reinos fijos ({{ ARBITRAGE_REALM_NAMES.join(', ') }}). Esta comparación no toma en cuenta objetos sin ilvl (housing, patrones, etc., ya que aún están en desarrollo).
                    </p>
                </div>

                <button v-if="arbitrageStarted" type="button" @click="fetchArbitrage(true)" :disabled="arbitrageLoading"
                    class="flex shrink-0 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:border-indigo-400/60 hover:text-white disabled:cursor-not-allowed disabled:opacity-50">
                    <RefreshCw class="size-3.5" :class="{ 'animate-spin': arbitrageLoading }" />
                    Actualizar
                </button>
            </div>

            <button v-if="!arbitrageStarted" type="button" @click="fetchArbitrage(false)"
                class="flex items-center gap-2 rounded-lg border border-indigo-400/40 bg-indigo-500/10 px-4 py-2 text-sm font-semibold text-indigo-300 transition-colors hover:border-indigo-400/70 hover:bg-indigo-500/20">
                <RefreshCw class="size-4" />
                Comparar oportunidades
            </button>

            <div v-else-if="arbitrageLoading" class="py-8 text-center text-sm text-slate-500">
                Comparando {{ selectedItems.length }} ítems entre {{ ARBITRAGE_REALM_NAMES.length }} reinos...
            </div>

            <template v-else>
                <div v-if="!arbitrageOpportunities.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                    No se encontraron oportunidades de arbitraje rentables con los datos actuales.
                </div>

                <div v-else class="rounded-xl border border-indigo-400/20 bg-indigo-500/5 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-bold text-slate-100">Compra barato. Vende mejor.</h4>
                        <div class="text-right">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Beneficio total estimado</div>
                            <div class="flex items-center justify-end gap-1 text-sm font-bold text-emerald-400">
                                <span class="inline-flex items-center gap-0.5"><span class="size-1.5 rounded-full bg-amber-400"></span>{{ copperToGsc(totalArbitrageProfit).gold }}</span>
                                <span class="inline-flex items-center gap-0.5"><span class="size-1.5 rounded-full bg-slate-300"></span>{{ copperToGsc(totalArbitrageProfit).silver }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative mb-3 w-full max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="arbitrageSearch" type="text" placeholder="Filtrar oportunidades..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div class="app-scroll max-h-96 overflow-y-auto rounded-lg border border-white/10">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-[#181b3a] text-[11px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">
                                        <div class="flex items-center gap-1.5">
                                            Item
                                            <div class="relative">
                                                <button type="button" @click="arbitrageQualityMenuOpen = !arbitrageQualityMenuOpen" @blur="closeQualityMenuOnBlur"
                                                    class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-indigo-300"
                                                    :class="{ 'text-indigo-400': arbitrageQualityFilter }">
                                                    <Filter class="size-3" />
                                                </button>
                                                <div v-if="arbitrageQualityMenuOpen"
                                                    class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-indigo-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                                                    <div @mousedown="selectQualityFilter('')"
                                                        class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                        :class="!arbitrageQualityFilter ? 'bg-indigo-500/15 text-indigo-300' : 'text-slate-300 hover:bg-white/5'">
                                                        Todas las calidades
                                                    </div>
                                                    <div v-for="q in arbitrageQualities" :key="q" @mousedown="selectQualityFilter(q)"
                                                        class="cursor-pointer px-3 py-2 text-xs capitalize transition-colors"
                                                        :class="[QUALITY_COLORS[q] ?? 'text-slate-300', arbitrageQualityFilter === q ? 'bg-indigo-500/15' : 'hover:bg-white/5']">
                                                        {{ q }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="px-3 py-2 text-left">
                                        <div class="flex items-center gap-1.5">
                                            Comprar en
                                            <div class="relative">
                                                <button type="button" @click="arbitrageBuyMenuOpen = !arbitrageBuyMenuOpen" @blur="closeBuyMenuOnBlur"
                                                    class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-indigo-300"
                                                    :class="{ 'text-indigo-400': arbitrageBuyRealmFilter }">
                                                    <Filter class="size-3" />
                                                </button>
                                                <div v-if="arbitrageBuyMenuOpen"
                                                    class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-indigo-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                                                    <div @mousedown="selectBuyRealmFilter('')"
                                                        class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                        :class="!arbitrageBuyRealmFilter ? 'bg-indigo-500/15 text-indigo-300' : 'text-slate-300 hover:bg-white/5'">
                                                        Todos los reinos
                                                    </div>
                                                    <div v-for="slug in arbitrageBuyRealms" :key="slug" @mousedown="selectBuyRealmFilter(slug)"
                                                        class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                        :class="arbitrageBuyRealmFilter === slug ? 'bg-indigo-500/15 text-indigo-300' : 'text-slate-300 hover:bg-white/5'">
                                                        {{ realmName(slug) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="px-3 py-2 text-left">Precio</th>
                                    <th class="px-3 py-2 text-left">
                                        <div class="flex items-center gap-1.5">
                                            Vender en
                                            <div class="relative">
                                                <button type="button" @click="arbitrageSellMenuOpen = !arbitrageSellMenuOpen" @blur="closeSellMenuOnBlur"
                                                    class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-indigo-300"
                                                    :class="{ 'text-indigo-400': arbitrageSellRealmFilter }">
                                                    <Filter class="size-3" />
                                                </button>
                                                <div v-if="arbitrageSellMenuOpen"
                                                    class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-indigo-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                                                    <div @mousedown="selectSellRealmFilter('')"
                                                        class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                        :class="!arbitrageSellRealmFilter ? 'bg-indigo-500/15 text-indigo-300' : 'text-slate-300 hover:bg-white/5'">
                                                        Todos los reinos
                                                    </div>
                                                    <div v-for="slug in arbitrageSellRealms" :key="slug" @mousedown="selectSellRealmFilter(slug)"
                                                        class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                        :class="arbitrageSellRealmFilter === slug ? 'bg-indigo-500/15 text-indigo-300' : 'text-slate-300 hover:bg-white/5'">
                                                        {{ realmName(slug) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="px-3 py-2 text-left">Precio</th>
                                    <th class="px-3 py-2 text-right">Beneficio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in filteredArbitrage" :key="`${o.row.item_id}-${o.row.ilvl}`" class="border-t border-white/5">
                                    <td class="px-3 py-2">
                                        <span class="flex items-center gap-2" :class="QUALITY_COLORS[o.row.quality] ?? 'text-slate-100'">
                                            <img v-if="o.row.icon_url" :src="o.row.icon_url" class="size-6 shrink-0 rounded" />
                                            <Sparkles v-else class="size-6 shrink-0" />
                                            <span class="truncate">{{ o.row.name }}</span>
                                            <span class="shrink-0 rounded bg-white/5 px-1.5 py-0.5 text-[11px] font-semibold text-slate-100">
                                                ilvl {{ o.row.ilvl }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-amber-300">{{ realmName(o.buySlug) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="inline-flex items-center gap-0.5 text-amber-400"><span class="size-1.5 rounded-full bg-amber-400"></span>{{ copperToGsc(o.buyCopper).gold }}</span>
                                            <span class="inline-flex items-center gap-0.5 text-slate-300"><span class="size-1.5 rounded-full bg-slate-300"></span>{{ copperToGsc(o.buyCopper).silver }}</span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-emerald-300">{{ realmName(o.sellSlug) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="inline-flex items-center gap-0.5 text-amber-400"><span class="size-1.5 rounded-full bg-amber-400"></span>{{ copperToGsc(o.sellCopper).gold }}</span>
                                            <span class="inline-flex items-center gap-0.5 text-slate-300"><span class="size-1.5 rounded-full bg-slate-300"></span>{{ copperToGsc(o.sellCopper).silver }}</span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-400">
                                            +<span class="inline-flex items-center gap-0.5"><span class="size-1.5 rounded-full bg-amber-400"></span>{{ copperToGsc(o.netProfit).gold }}</span>
                                            <span class="inline-flex items-center gap-0.5"><span class="size-1.5 rounded-full bg-slate-300"></span>{{ copperToGsc(o.netProfit).silver }}</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!filteredArbitrage.length">
                                    <td colspan="6" class="px-3 py-4 text-center text-slate-500">Sin resultados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Beneficios ya con comisión del AH (5%) restada. Comparativa actualizada {{ timeAgo(Object.values(arbitrageLastSynced)[0]) }}.
                    </p>
                </div>
            </template>
        </div>
    </section>
</template>

<style scoped>
.app-scroll {
    scrollbar-width: thin;
    scrollbar-color: #312e5c #12142b;
}

.app-scroll::-webkit-scrollbar {
    width: 6px;
}

.app-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.app-scroll::-webkit-scrollbar-thumb {
    background-color: #312e5c;
    border-radius: 9999px;
}

.app-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #4338ca;
}
</style>