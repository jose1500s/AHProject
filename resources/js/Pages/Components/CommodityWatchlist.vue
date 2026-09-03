<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Radar, Sparkles, ArrowUpCircle, ArrowDownCircle, Search, X, Plus, TrendingDown, TrendingUp, Minus, Package, Diamond, Link2, ChevronDown, Calculator } from '@lucide/vue'
import CoinAmount from './CoinAmount.vue'

const emit = defineEmits(['open-item'])

const watchlist = ref([])
const recommendations = ref([])
const rebounds = ref([])
const breakevenDrops = ref([])
const breakevenPercent = ref(null)
const loading = ref(true)

const searchQuery = ref('')
const searchResults = ref([])
const searchOpen = ref(false)
const searching = ref(false)

const watchlistFilter = ref('')
const recommendationsFilter = ref('')
const breakevenDropsFilter = ref('')
const reboundsFilter = ref('')

let searchTimeout = null

const TREND_CONFIG = {
    bajando: { label: 'Bajando', color: 'text-emerald-400', dot: 'bg-emerald-400', icon: TrendingDown },
    estable: { label: 'Estable', color: 'text-amber-400', dot: 'bg-amber-400', icon: Minus },
    subiendo: { label: 'Subiendo', color: 'text-red-400', dot: 'bg-red-400', icon: TrendingUp },
}

function normalizeText(str) {
    return String(str ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
}

async function fetchWatchlist() {
    loading.value = true
    try {
        const res = await fetch('/api/commodities/watchlist')
        const data = await res.json()
        watchlist.value = data.watchlist ?? []
        recommendations.value = data.recommendations ?? []
        rebounds.value = data.rebounds ?? []
        breakevenDrops.value = data.breakeven_drops ?? []
        breakevenPercent.value = data.breakeven_percent
    } finally {
        loading.value = false
    }
}

async function runSearch() {
    if (searchQuery.value.trim().length < 2) {
        searchResults.value = []
        return
    }

    searching.value = true
    try {
        const res = await fetch(`/api/commodities/watchlist/search?q=${encodeURIComponent(searchQuery.value)}`)
        const data = await res.json()
        searchResults.value = data.results ?? []
    } finally {
        searching.value = false
    }
}

watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchOpen.value = true
    searchTimeout = setTimeout(runSearch, 350)
})

async function addItem(itemId) {
    searchQuery.value = ''
    searchResults.value = []
    searchOpen.value = false

    await fetch('/api/commodities/watchlist', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId }),
    })

    fetchWatchlist()
}

async function removeItem(id) {
    watchlist.value = watchlist.value.filter(w => w.id !== id)

    try {
        await fetch(`/api/commodities/watchlist/${id}`, { method: 'DELETE' })
    } finally {
        fetchWatchlist()
    }
}

function closeSearchOnBlur() {
    setTimeout(() => { searchOpen.value = false }, 150)
}

function openItem(itemId) {
    emit('open-item', itemId)
}

function qualityIconClass(quality) {
    return quality === 'gold' ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-slate-300'
}

function copperToGsc(copper) {
    const negative = copper < 0
    const c = Math.round(Math.abs(copper))
    return {
        gold: Math.floor(c / 10000) * (negative ? -1 : 1),
        silver: Math.floor((c % 10000) / 100),
        copper: c % 100,
    }
}

function gscToCopper(gold, silver, copper) {
    return (gold || 0) * 10000 + (silver || 0) * 100 + (copper || 0)
}

const filteredWatchlist = computed(() => {
    const q = normalizeText(watchlistFilter.value.trim())
    if (!q) return watchlist.value
    return watchlist.value.filter(w => normalizeText(w.item_name).includes(q))
})

const filteredRecommendations = computed(() => {
    const q = normalizeText(recommendationsFilter.value.trim())
    if (!q) return recommendations.value
    return recommendations.value.filter(r => normalizeText(r.item_name).includes(q))
})

const filteredBreakevenDrops = computed(() => {
    const q = normalizeText(breakevenDropsFilter.value.trim())
    if (!q) return breakevenDrops.value
    return breakevenDrops.value.filter(r => normalizeText(r.item_name).includes(q))
})

const filteredRebounds = computed(() => {
    const q = normalizeText(reboundsFilter.value.trim())
    if (!q) return rebounds.value
    return rebounds.value.filter(r => normalizeText(r.item_name).includes(q))
})

// ===== Calculadora de reventa (con datos de mercado) =====
const calcMenuOpen = ref(false)
const calcMenuFilter = ref('')
const calcItemId = ref(null)
const calcQuantity = ref(1)
const calcTargetKey = ref('median')
const calcTargetMenuOpen = ref(false)
const calcEstimate = ref(null)
const calcLoading = ref(false)

const customGold = ref(0)
const customSilver = ref(0)
const customCopper = ref(0)

let calcTimeout = null

const followedWithData = computed(() => watchlist.value.filter(w => w.has_data))

const filteredCalcItems = computed(() => {
    const q = normalizeText(calcMenuFilter.value.trim())
    if (!q) return followedWithData.value
    return followedWithData.value.filter(w => normalizeText(w.item_name).includes(q))
})

const calcSelectedItem = computed(() =>
    watchlist.value.find(w => w.item_id === calcItemId.value) ?? null
)

const calcTargetOptions = computed(() => {
    const w = calcSelectedItem.value
    const options = []

    if (w && w.has_data) {
        options.push({ key: 'projection_min', label: 'Proyección mínima', copper: w.projection_min_copper })
        options.push({ key: 'median', label: 'Precio normal', copper: w.median_copper })
        options.push({ key: 'projection_max', label: 'Proyección máxima', copper: w.projection_max_copper })
    }

    if (calcEstimate.value?.post_purchase_floor_copper) {
        options.push({ key: 'post_purchase', label: 'Piso post-compra (mercado tras tu compra)', copper: calcEstimate.value.post_purchase_floor_copper })
    }

    options.push({ key: 'custom', label: 'Precio personalizado', copper: null })

    return options
})

const calcSelectedTarget = computed(() => {
    const opt = calcTargetOptions.value.find(o => o.key === calcTargetKey.value)
    if (!opt) return null

    if (opt.key === 'custom') {
        return { ...opt, copper: gscToCopper(customGold.value, customSilver.value, customCopper.value) }
    }

    return opt
})

async function runCalculation() {
    if (!calcItemId.value || !calcQuantity.value || calcQuantity.value < 1) {
        calcEstimate.value = null
        return
    }

    calcLoading.value = true
    try {
        const res = await fetch(`/api/commodities/${calcItemId.value}/buy-estimate?quantity=${calcQuantity.value}`)
        calcEstimate.value = await res.json()
    } finally {
        calcLoading.value = false
    }
}

watch([calcItemId, calcQuantity], () => {
    clearTimeout(calcTimeout)
    calcTimeout = setTimeout(runCalculation, 300)
})

function selectCalcItem(itemId) {
    calcItemId.value = itemId
    calcMenuOpen.value = false
    calcMenuFilter.value = ''
}

function selectCalcTarget(key) {
    calcTargetKey.value = key
    calcTargetMenuOpen.value = false
}

function closeCalcMenuOnBlur() {
    setTimeout(() => { calcMenuOpen.value = false }, 150)
}

const estimatedProfit = computed(() => {
    if (!calcEstimate.value || !calcSelectedTarget.value || calcSelectedTarget.value.copper === null) return null

    const totalBuy = calcEstimate.value.total_copper
    const totalSellGross = calcSelectedTarget.value.copper * calcQuantity.value
    const totalSellNet = totalSellGross * 0.95

    return Math.round(totalSellNet - totalBuy)
})

watch(followedWithData, (list) => {
    if (!calcItemId.value && list.length) {
        calcItemId.value = list[0].item_id
    }
}, { immediate: true })

// ===== Calculadora simple (manual, sin datos de mercado) =====
const simpleQuantity = ref(1)
const simpleTotalGold = ref(0)
const simpleTotalSilver = ref(0)
const simpleSellGold = ref(0)
const simpleSellSilver = ref(0)
const simpleSellCopper = ref(0)
const simpleApplyCommission = ref(true)

const simpleTotalInvestedCopper = computed(() =>
    gscToCopper(simpleTotalGold.value, simpleTotalSilver.value, 0)
)

const simpleSellUnitCopper = computed(() =>
    gscToCopper(simpleSellGold.value, simpleSellSilver.value, simpleSellCopper.value)
)

const simpleUnitCostCopper = computed(() => {
    if (!simpleQuantity.value || simpleQuantity.value < 1) return 0
    return simpleTotalInvestedCopper.value / simpleQuantity.value
})

const simpleGrossSaleCopper = computed(() =>
    simpleSellUnitCopper.value * simpleQuantity.value
)

const simpleNetSaleCopper = computed(() =>
    simpleApplyCommission.value ? simpleGrossSaleCopper.value * 0.95 : simpleGrossSaleCopper.value
)

const simpleProfitCopper = computed(() =>
    Math.round(simpleNetSaleCopper.value - simpleTotalInvestedCopper.value)
)

const simpleProfitPercent = computed(() => {
    if (simpleTotalInvestedCopper.value <= 0) return 0
    return Math.round((simpleProfitCopper.value / simpleTotalInvestedCopper.value) * 100)
})

onMounted(fetchWatchlist)
</script>

<template>
    <div class="rounded-xl border border-white/10 bg-[#141224]/70 backdrop-blur-xl p-5">
        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-indigo-400">
            <Radar class="size-3.5" />
            Seguimiento de mercado
        </div>

        <h2 class="text-lg font-bold text-slate-100">Watchlist</h2>
        <p class="mb-4 text-sm text-slate-500">Sigue precios y encuentra oportunidades para comprar y revender</p>

        <div class="relative mb-6">
            <Search class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 size-4 text-slate-500" />
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Buscar un objeto para seguir..."
                class="w-full rounded-lg border border-white/10 bg-white/5 py-2.5 pl-11 pr-4 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60"
                @focus="searchOpen = true"
                @blur="closeSearchOnBlur"
            />

            <div v-if="searchOpen && (searching || searchResults.length)"
                class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                <div v-if="searching" class="px-4 py-3 text-sm text-slate-500">Buscando...</div>
                <ul v-else class="app-scroll max-h-64 overflow-y-auto">
                    <li v-for="r in searchResults" :key="r.blizzard_id"
                        class="flex cursor-pointer items-center gap-2 px-4 py-2.5 text-sm text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                        @mousedown="addItem(r.blizzard_id)">
                        <div class="relative shrink-0">
                            <img v-if="r.icon_url" :src="r.icon_url" class="size-6 rounded" />
                            <Package v-else class="size-6 text-slate-600" />
                            <Diamond v-if="r.craft_quality" class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border border-[#12142b] bg-[#12142b] p-px"
                                :class="qualityIconClass(r.craft_quality)" />
                        </div>
                        <span class="flex-1 truncate">{{ r.name }}</span>
                        <CoinAmount v-if="r.current_price" v-bind="r.current_price" size="text-xs" />
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="loading" class="py-8 text-center text-sm text-slate-500">Cargando...</div>

        <template v-else>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-100">Mis items seguidos</h3>
                        <span class="text-xs text-slate-500">{{ watchlist.length }}</span>
                    </div>

                    <div class="relative mb-3">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="watchlistFilter" type="text" placeholder="Filtrar..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-9 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div v-if="!watchlist.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Aún no sigues ningún ítem
                    </div>
                    <div v-else-if="!filteredWatchlist.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Sin resultados
                    </div>

                    <div v-else class="app-scroll flex max-h-[32rem] flex-col gap-2 overflow-y-auto pr-1">
                        <div v-for="w in filteredWatchlist" :key="w.id" class="rounded-lg border border-white/10 bg-white/3 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <button type="button" class="flex min-w-0 items-center gap-2 text-left" @click="openItem(w.item_id)">
                                    <div class="relative shrink-0">
                                        <img v-if="w.icon_url" :src="w.icon_url" class="size-9 rounded" />
                                        <Package v-else class="size-9 text-slate-600" />
                                        <Diamond v-if="w.craft_quality" class="absolute -bottom-1 -right-1 size-3.5 rounded-full border-2 border-[#141224] bg-[#141224] p-0.5"
                                            :class="qualityIconClass(w.craft_quality)" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-100 hover:text-indigo-300">{{ w.item_name }}</p>
                                        <p v-if="w.has_data" class="flex items-center gap-1 text-xs" :class="TREND_CONFIG[w.trend].color">
                                            <span class="size-1.5 rounded-full" :class="TREND_CONFIG[w.trend].dot"></span>
                                            {{ TREND_CONFIG[w.trend].label }} · {{ w.percent_change_vs_yesterday > 0 ? '+' : '' }}{{ w.percent_change_vs_yesterday }}%
                                        </p>
                                        <p v-else class="text-xs text-slate-500">Sin datos aún</p>
                                    </div>
                                </button>

                                <button type="button" @click="removeItem(w.id)"
                                    class="shrink-0 rounded p-1 text-slate-600 transition hover:bg-red-500/10 hover:text-red-400">
                                    <X class="size-4" />
                                </button>
                            </div>

                            <div v-if="w.has_data" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Actual</div>
                                    <CoinAmount v-bind="w.current" size="text-sm" />
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Normalmente</div>
                                    <CoinAmount v-bind="w.median" size="text-sm" />
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Proyección máx.</div>
                                    <CoinAmount v-bind="w.projection_max" size="text-sm" class="text-emerald-400" />
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Proyección mín.</div>
                                    <CoinAmount v-bind="w.projection_min" size="text-sm" class="text-amber-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                            <Sparkles class="size-3.5 text-indigo-400" />
                            Recomendados
                        </h3>
                        <span class="text-xs text-slate-500">{{ recommendations.length }}</span>
                    </div>

                    <div class="relative mb-3">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="recommendationsFilter" type="text" placeholder="Filtrar..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-9 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div v-if="!recommendations.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Sin oportunidades aún.
                    </div>
                    <div v-else-if="!filteredRecommendations.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Sin resultados
                    </div>

                    <div v-else class="app-scroll flex max-h-[32rem] flex-col gap-2 overflow-y-auto pr-1">
                        <div v-for="r in filteredRecommendations" :key="r.item_id"
                            class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/3 p-3">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" @click="openItem(r.item_id)">
                                <div class="relative shrink-0">
                                    <img v-if="r.icon_url" :src="r.icon_url" class="size-9 rounded" />
                                    <Package v-else class="size-9 text-slate-600" />
                                    <Diamond v-if="r.craft_quality" class="absolute -bottom-1 -right-1 size-3.5 rounded-full border-2 border-[#141224] bg-[#141224] p-0.5"
                                        :class="qualityIconClass(r.craft_quality)" />
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-100 hover:text-indigo-300">{{ r.item_name }}</p>
                                    <p class="truncate text-xs text-indigo-300">{{ r.reason }}</p>
                                </div>
                            </button>

                            <div class="shrink-0 text-right">
                                <CoinAmount v-bind="r.current" size="text-xs" />
                                <div class="mt-1 text-xs font-bold text-emerald-400">-{{ r.discount_percent }}%</div>
                            </div>

                            <button type="button" @click="addItem(r.item_id)"
                                class="shrink-0 rounded-lg border border-indigo-400/30 p-1.5 text-indigo-300 transition hover:border-indigo-400/60 hover:bg-indigo-500/10">
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                            <ArrowDownCircle class="size-3.5 text-cyan-400" />
                            Cayó desde último sync
                        </h3>
                        <span class="text-xs text-slate-500">{{ breakevenDrops.length }}</span>
                    </div>
                    <p class="mb-3 text-xs text-slate-500">
                        Cayó al menos {{ breakevenPercent }}% desde la última hora — si vuelve a subir, ya cubres la comisión del AH.
                    </p>

                    <div class="relative mb-3">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="breakevenDropsFilter" type="text" placeholder="Filtrar..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-9 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div v-if="!breakevenDrops.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Nada detectado en el último sync.
                    </div>
                    <div v-else-if="!filteredBreakevenDrops.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Sin resultados
                    </div>

                    <div v-else class="app-scroll flex max-h-[32rem] flex-col gap-2 overflow-y-auto pr-1">
                        <div v-for="r in filteredBreakevenDrops" :key="r.item_id"
                            class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/3 p-3">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" @click="openItem(r.item_id)">
                                <div class="relative shrink-0">
                                    <img v-if="r.icon_url" :src="r.icon_url" class="size-9 rounded" />
                                    <Package v-else class="size-9 text-slate-600" />
                                    <Diamond v-if="r.craft_quality" class="absolute -bottom-1 -right-1 size-3.5 rounded-full border-2 border-[#141224] bg-[#141224] p-0.5"
                                        :class="qualityIconClass(r.craft_quality)" />
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-100 hover:text-indigo-300">{{ r.item_name }}</p>
                                    <p class="truncate text-xs text-cyan-300">Antes: <CoinAmount v-bind="r.previous" size="text-xs" class="inline-flex" /></p>
                                </div>
                            </button>

                            <div class="shrink-0 text-right">
                                <CoinAmount v-bind="r.current" size="text-xs" />
                                <div class="mt-1 text-xs font-bold text-cyan-400">-{{ r.drop_percent }}%</div>
                            </div>

                            <button type="button" @click="addItem(r.item_id)"
                                class="shrink-0 rounded-lg border border-indigo-400/30 p-1.5 text-indigo-300 transition hover:border-indigo-400/60 hover:bg-indigo-500/10">
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                            <ArrowUpCircle class="size-3.5 text-emerald-400" />
                            Recuperándose
                        </h3>
                        <span class="text-xs text-slate-500">{{ rebounds.length }}</span>
                    </div>

                    <div class="relative mb-3">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="reboundsFilter" type="text" placeholder="Filtrar..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-9 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div v-if="!rebounds.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Nada detectado aún.
                    </div>
                    <div v-else-if="!filteredRebounds.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                        Sin resultados
                    </div>

                    <div v-else class="app-scroll flex max-h-[32rem] flex-col gap-2 overflow-y-auto pr-1">
                        <div v-for="r in filteredRebounds" :key="r.item_id"
                            class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/3 p-3">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" @click="openItem(r.item_id)">
                                <div class="relative shrink-0">
                                    <img v-if="r.icon_url" :src="r.icon_url" class="size-9 rounded" />
                                    <Package v-else class="size-9 text-slate-600" />
                                    <Diamond v-if="r.craft_quality" class="absolute -bottom-1 -right-1 size-3.5 rounded-full border-2 border-[#141224] bg-[#141224] p-0.5"
                                        :class="qualityIconClass(r.craft_quality)" />
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-100 hover:text-indigo-300">{{ r.item_name }}</p>
                                    <p class="truncate text-xs text-emerald-300">{{ r.reason }}</p>
                                </div>
                            </button>

                            <div class="shrink-0 text-right">
                                <CoinAmount v-bind="r.current" size="text-xs" />
                                <div class="mt-1 text-xs font-bold text-emerald-400">+{{ r.rebound_percent }}%</div>
                            </div>

                            <button type="button" @click="addItem(r.item_id)"
                                class="shrink-0 rounded-lg border border-indigo-400/30 p-1.5 text-indigo-300 transition hover:border-indigo-400/60 hover:bg-indigo-500/10">
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t border-white/10 pt-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                        <Link2 class="size-3.5 text-indigo-400" />
                        Calculadora de reventa
                    </h3>
                    <span class="text-xs text-slate-500">Estimación por operación, usa datos de mercado</span>
                </div>

                <div v-if="!followedWithData.length" class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                    Sigue al menos un ítem con datos de precio para usar esta calculadora.
                </div>

                <div v-else>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Ítem</div>
                            <div class="relative">
                                <button type="button" @click="calcMenuOpen = !calcMenuOpen"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-indigo-400/30 bg-[#12142b] px-3 py-2 text-sm font-medium text-slate-100 transition-colors hover:border-indigo-400/60">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="relative shrink-0" v-if="calcSelectedItem">
                                            <img v-if="calcSelectedItem.icon_url" :src="calcSelectedItem.icon_url" class="size-5 rounded" />
                                            <Package v-else class="size-5 text-slate-600" />
                                            <Diamond v-if="calcSelectedItem.craft_quality" class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border border-[#12142b] bg-[#12142b] p-px"
                                                :class="qualityIconClass(calcSelectedItem.craft_quality)" />
                                        </span>
                                        <span class="truncate">{{ calcSelectedItem?.item_name ?? 'Selecciona un ítem' }}</span>
                                    </span>
                                    <ChevronDown class="size-4 shrink-0 text-indigo-300 transition-transform" :class="{ 'rotate-180': calcMenuOpen }" />
                                </button>

                                <div v-if="calcMenuOpen"
                                    class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                                    <div class="relative border-b border-white/5 p-2">
                                        <Search class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                        <input v-model="calcMenuFilter" type="text" placeholder="Filtrar..."
                                            class="w-full rounded-md bg-white/5 py-1.5 pl-7 pr-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:bg-white/10"
                                            @blur="closeCalcMenuOnBlur" />
                                    </div>
                                    <ul class="app-scroll max-h-56 overflow-y-auto">
                                        <li v-for="w in filteredCalcItems" :key="w.item_id" @mousedown="selectCalcItem(w.item_id)"
                                            class="flex cursor-pointer items-center gap-2 px-4 py-2.5 text-sm transition-colors"
                                            :class="w.item_id === calcItemId ? 'bg-indigo-500/15 text-indigo-300 font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white'">
                                            <span class="relative shrink-0">
                                                <img v-if="w.icon_url" :src="w.icon_url" class="size-6 rounded" />
                                                <Package v-else class="size-6 text-slate-600" />
                                                <Diamond v-if="w.craft_quality" class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border border-[#12142b] bg-[#12142b] p-px"
                                                    :class="qualityIconClass(w.craft_quality)" />
                                            </span>
                                            <span class="flex-1 truncate">{{ w.item_name }}</span>
                                            <CoinAmount v-bind="w.current" size="text-xs" />
                                        </li>
                                        <li v-if="!filteredCalcItems.length" class="px-4 py-3 text-sm text-slate-500">Sin resultados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Cantidad</div>
                            <input v-model.number="calcQuantity" type="number" min="1"
                                class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-400/60" />
                        </div>

                        <div>
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Venta objetivo</div>
                            <div class="relative">
                                <button type="button" @click="calcTargetMenuOpen = !calcTargetMenuOpen"
                                    class="flex w-full items-center justify-between rounded-lg border border-indigo-400/30 bg-[#12142b] px-3 py-2 text-sm font-medium text-slate-100 transition-colors hover:border-indigo-400/60">
                                    <span class="truncate">{{ calcTargetOptions.find(o => o.key === calcTargetKey)?.label ?? '—' }}</span>
                                    <ChevronDown class="size-4 shrink-0 text-indigo-300 transition-transform" :class="{ 'rotate-180': calcTargetMenuOpen }" />
                                </button>

                                <div v-if="calcTargetMenuOpen"
                                    class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                                    <ul>
                                        <li v-for="opt in calcTargetOptions" :key="opt.key" @click="selectCalcTarget(opt.key)"
                                            class="cursor-pointer px-4 py-2.5 text-sm transition-colors"
                                            :class="opt.key === calcTargetKey ? 'bg-indigo-500/15 text-indigo-300 font-medium' : 'text-slate-300 hover:bg-white/5 hover:text-white'">
                                            {{ opt.label }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="calcTargetKey === 'custom'" class="mt-3 flex items-end gap-2">
                        <div>
                            <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Oro</div>
                            <input v-model.number="customGold" type="number" min="0"
                                class="w-24 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-amber-400 outline-none focus:border-indigo-400/60" />
                        </div>
                        <div>
                            <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Plata</div>
                            <input v-model.number="customSilver" type="number" min="0" max="99"
                                class="w-20 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-slate-300 outline-none focus:border-indigo-400/60" />
                        </div>
                        <div>
                            <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Cobre</div>
                            <input v-model.number="customCopper" type="number" min="0" max="99"
                                class="w-20 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-orange-400 outline-none focus:border-indigo-400/60" />
                        </div>
                        <div class="pb-2 text-xs text-slate-500">por unidad</div>
                    </div>

                    <div v-if="calcLoading" class="mt-4 text-sm text-slate-500">Calculando...</div>

                    <div v-else-if="calcEstimate" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Compra real (escalonada)</div>
                            <CoinAmount v-bind="copperToGsc(calcEstimate.total_copper)" size="text-lg" />
                            <div class="mt-1 text-xs text-slate-500">
                                Promedio: <CoinAmount v-bind="copperToGsc(calcEstimate.avg_unit_copper)" size="text-xs" class="inline-flex" /> c/u
                            </div>
                            <div v-if="!calcEstimate.fully_covered" class="mt-1 text-xs text-red-400">
                                Solo hay {{ calcEstimate.fulfilled_quantity }} disponibles (pediste {{ calcEstimate.requested_quantity }})
                            </div>
                            <div v-if="calcEstimate.post_purchase_floor_copper" class="mt-1 text-xs text-slate-500">
                                Piso tras tu compra: <CoinAmount v-bind="copperToGsc(calcEstimate.post_purchase_floor_copper)" size="text-xs" class="inline-flex" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Venta estimada (neta, -5% comisión)</div>
                            <CoinAmount v-if="calcSelectedTarget?.copper !== null" v-bind="copperToGsc(calcSelectedTarget.copper * calcQuantity * 0.95)" size="text-lg" />
                            <span v-else class="text-sm text-slate-500">Define un precio</span>
                        </div>

                        <div class="rounded-lg border p-4"
                            :class="estimatedProfit >= 0 ? 'border-emerald-400/30 bg-emerald-500/10' : 'border-red-400/30 bg-red-500/10'">
                            <div class="text-[10px] uppercase tracking-wide" :class="estimatedProfit >= 0 ? 'text-emerald-400' : 'text-red-400'">
                                Ganancia estimada
                            </div>
                            <CoinAmount v-if="estimatedProfit !== null" v-bind="copperToGsc(estimatedProfit)" size="text-lg" />
                            <span v-else class="text-sm text-slate-500">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t border-white/10 pt-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                        <Calculator class="size-3.5 text-indigo-400" />
                        Calculadora rápida
                    </h3>
                    <span class="text-xs text-slate-500">Solo tus números, sin datos de mercado</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Cantidad comprada</div>
                        <input v-model.number="simpleQuantity" type="number" min="1"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-400/60" />
                    </div>

                    <div>
                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Total invertido</div>
                        <div class="flex gap-2">
                            <input v-model.number="simpleTotalGold" type="number" min="0" placeholder="Oro"
                                class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-amber-400 outline-none focus:border-indigo-400/60" />
                            <input v-model.number="simpleTotalSilver" type="number" min="0" max="99" placeholder="Plata"
                                class="w-24 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none focus:border-indigo-400/60" />
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Venta por unidad</div>
                        <div class="flex gap-2">
                            <input v-model.number="simpleSellGold" type="number" min="0" placeholder="Oro"
                                class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-amber-400 outline-none focus:border-indigo-400/60" />
                            <input v-model.number="simpleSellSilver" type="number" min="0" max="99" placeholder="Plata"
                                class="w-20 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300 outline-none focus:border-indigo-400/60" />
                            <input v-model.number="simpleSellCopper" type="number" min="0" max="99" placeholder="Cobre"
                                class="w-20 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-orange-400 outline-none focus:border-indigo-400/60" />
                        </div>
                    </div>
                </div>

                <label class="mt-3 flex w-fit cursor-pointer items-center gap-2 text-xs text-slate-400">
                    <input v-model="simpleApplyCommission" type="checkbox"
                        class="size-3.5 rounded border-white/20 bg-white/5 text-indigo-500 accent-indigo-500" />
                    Restar comisión del AH (5%)
                </label>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                        <div class="text-[10px] uppercase tracking-wide text-slate-500">Costo por unidad</div>
                        <CoinAmount v-bind="copperToGsc(simpleUnitCostCopper)" size="text-lg" />
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                        <div class="text-[10px] uppercase tracking-wide text-slate-500">
                            Venta total {{ simpleApplyCommission ? '(neta, -5%)' : '(bruta)' }}
                        </div>
                        <CoinAmount v-bind="copperToGsc(simpleNetSaleCopper)" size="text-lg" />
                    </div>

                    <div class="rounded-lg border p-4"
                        :class="simpleProfitCopper >= 0 ? 'border-emerald-400/30 bg-emerald-500/10' : 'border-red-400/30 bg-red-500/10'">
                        <div class="text-[10px] uppercase tracking-wide" :class="simpleProfitCopper >= 0 ? 'text-emerald-400' : 'text-red-400'">
                            Ganancia estimada
                        </div>
                        <CoinAmount v-bind="copperToGsc(simpleProfitCopper)" size="text-lg" />
                        <div class="mt-1 text-xs" :class="simpleProfitCopper >= 0 ? 'text-emerald-400' : 'text-red-400'">
                            {{ simpleProfitPercent > 0 ? '+' : '' }}{{ simpleProfitPercent }}% sobre lo invertido
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
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
</style>