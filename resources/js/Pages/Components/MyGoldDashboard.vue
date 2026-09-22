<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { TrendingUp, TrendingDown, Wallet, Award, Gavel, ChevronDown, ArrowUp, ArrowDown, Package, Search, RefreshCw, Calendar, ShoppingBag, X, Vault, LineChart, Target, Pencil, Check } from '@lucide/vue'
import VueApexCharts from 'vue3-apexcharts'
import CoinAmount from './CoinAmount.vue'

defineOptions({ components: { apexchart: VueApexCharts } })

const CHARACTER_STORAGE_KEY = 'mygold_selected_character'
const HIDDEN_CHARACTERS_KEY = 'mygold_hidden_characters'
const PALETTE = ['#818cf8', '#22d3ee', '#fbbf24', '#34d399', '#f472b6', '#a78bfa', '#fb923c', '#60a5fa']

const characters = ref([])
const warband = ref(null)
const selectedCharacter = ref(null)
const hiddenCharacterKeys = ref(new Set())

if (typeof window !== 'undefined') {
    try {
        selectedCharacter.value = localStorage.getItem(CHARACTER_STORAGE_KEY) || null
    } catch {}

    try {
        const storedHidden = localStorage.getItem(HIDDEN_CHARACTERS_KEY)
        if (storedHidden) hiddenCharacterKeys.value = new Set(JSON.parse(storedHidden))
    } catch {}
}

const overview = ref(null)
const activeAuctions = ref([])
const transactions = ref({ data: [], links: [] })
const salesByItem = ref([])
const txFilter = ref('all')
const loadingTx = ref(false)
const characterMenuOpen = ref(false)
const auctionSearch = ref('')
const txSearch = ref('')
const salesByItemSearch = ref('')
const openSalesItems = ref(new Set())
const syncing = ref(false)
const syncCooldown = ref(0)

const goldHistory = ref(null)
const goldHistoryRange = ref('7d')
const loadingGoldHistory = ref(false)

const goldGoal = ref(null)
const goalEditing = ref(false)
const goalSaving = ref(false)
const goalForm = ref({ title: '', target_gold: null, deadline_date: '' })

async function fetchCharacters() {
    const res = await fetch('/api/wow/characters')
    const data = await res.json()
    characters.value = data.characters
    warband.value = data.warband
}

async function fetchOverview() {
    const params = new URLSearchParams()
    if (selectedCharacter.value) params.append('character', selectedCharacter.value)

    const res = await fetch(`/api/wow/overview?${params}`)
    overview.value = await res.json()
}

async function fetchGoldHistory() {
    loadingGoldHistory.value = true
    try {
        const params = new URLSearchParams({ range: goldHistoryRange.value })
        if (selectedCharacter.value) params.append('character', selectedCharacter.value)

        const res = await fetch(`/api/wow/gold-history?${params}`)
        goldHistory.value = await res.json()
    } finally {
        loadingGoldHistory.value = false
    }
}

async function fetchGoldGoal() {
    const res = await fetch('/api/wow/gold-goal')
    const data = await res.json()
    goldGoal.value = data.goal
}

function startEditGoal() {
    goalForm.value = {
        title: goldGoal.value?.title ?? 'Mi meta de oro',
        target_gold: goldGoal.value?.target_gold_number ?? null,
        deadline_date: goldGoal.value?.deadline_date ?? '',
    }
    goalEditing.value = true
}

async function saveGoal() {
    if (!goalForm.value.title || !goalForm.value.target_gold) return

    goalSaving.value = true
    try {
        await fetch('/api/wow/gold-goal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(goalForm.value),
        })
        await fetchGoldGoal()
        goalEditing.value = false
    } finally {
        goalSaving.value = false
    }
}

async function fetchActiveAuctions() {
    const params = new URLSearchParams()
    if (selectedCharacter.value) params.append('character', selectedCharacter.value)

    const res = await fetch(`/api/wow/active-auctions?${params}`)
    const data = await res.json()
    activeAuctions.value = data.auctions
}

async function fetchTransactions(url = null) {
    loadingTx.value = true
    try {
        let target = url
        if (!target) {
            const params = new URLSearchParams()
            if (selectedCharacter.value) params.append('character', selectedCharacter.value)
            if (txFilter.value !== 'all') params.append('type', txFilter.value)
            target = `/api/wow/transactions?${params}`
        }

        const res = await fetch(target)
        transactions.value = await res.json()
    } finally {
        loadingTx.value = false
    }
}

async function fetchSalesByItem() {
    const params = new URLSearchParams()
    if (selectedCharacter.value) params.append('character', selectedCharacter.value)

    const res = await fetch(`/api/wow/sales-by-item?${params}`)
    const data = await res.json()
    salesByItem.value = data.items
}

async function refreshAll() {
    const calls = [
        fetchCharacters(),
        fetchOverview(),
        fetchActiveAuctions(),
        fetchTransactions(),
        fetchSalesByItem(),
        fetchGoldHistory(),
    ]

    if (!selectedCharacter.value) {
        calls.push(fetchGoldGoal())
    }

    await Promise.all(calls)
}

let cooldownTimer = null

async function syncNow() {
    if (syncing.value || syncCooldown.value > 0) return

    syncing.value = true
    try {
        await refreshAll()
    } finally {
        syncing.value = false
    }

    syncCooldown.value = 10
    cooldownTimer = setInterval(() => {
        syncCooldown.value -= 1
        if (syncCooldown.value <= 0) {
            clearInterval(cooldownTimer)
            cooldownTimer = null
        }
    }, 1000)
}

watch(selectedCharacter, (value) => {
    try {
        if (value) {
            localStorage.setItem(CHARACTER_STORAGE_KEY, value)
        } else {
            localStorage.removeItem(CHARACTER_STORAGE_KEY)
        }
    } catch {}

    refreshAll()
})

watch(txFilter, () => fetchTransactions())
watch(goldHistoryRange, () => fetchGoldHistory())

onMounted(async () => {
    await fetchCharacters()
    refreshAll()
})

function selectCharacter(key) {
    selectedCharacter.value = key
    characterMenuOpen.value = false
}

function hideCharacter(key, event) {
    event.stopPropagation()

    const next = new Set(hiddenCharacterKeys.value)
    next.add(key)
    hiddenCharacterKeys.value = next

    try {
        localStorage.setItem(HIDDEN_CHARACTERS_KEY, JSON.stringify([...next]))
    } catch {}

    if (selectedCharacter.value === key) {
        selectedCharacter.value = null
    }
}

const currentCharacter = computed(() =>
    characters.value.find(c => c.key === selectedCharacter.value) ?? null
)

const visibleCharacters = computed(() =>
    characters.value.filter(c => !hiddenCharacterKeys.value.has(c.key))
)

function normalizeText(str) {
    return String(str ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
}

const filteredActiveAuctions = computed(() => {
    const q = normalizeText(auctionSearch.value.trim())
    if (!q) return activeAuctions.value
    return activeAuctions.value.filter(a => normalizeText(a.item_name).includes(q))
})

const filteredTransactions = computed(() => {
    const q = normalizeText(txSearch.value.trim())
    if (!q) return transactions.value.data ?? []
    return (transactions.value.data ?? []).filter(tx => normalizeText(tx.item_name).includes(q))
})

const filteredSalesByItem = computed(() => {
    const q = normalizeText(salesByItemSearch.value.trim())
    if (!q) return salesByItem.value
    return salesByItem.value.filter(item => normalizeText(item.item_name).includes(q))
})

const maxSalesByItemTotal = computed(() => {
    if (!salesByItem.value.length) return 1
    return Math.max(...salesByItem.value.map(i => i.total_copper), 1)
})

function salesBarWidth(totalCopper) {
    return `${Math.max(4, (totalCopper / maxSalesByItemTotal.value) * 100)}%`
}

function toggleSalesItem(itemName) {
    const next = new Set(openSalesItems.value)
    next.has(itemName) ? next.delete(itemName) : next.add(itemName)
    openSalesItems.value = next
}

function formatCompactGold(gold) {
    const n = gold ?? 0
    return n.toLocaleString('es-MX')
}

function formatTimeLeft(seconds) {
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`
    if (seconds < 86400) return `${Math.round(seconds / 3600)}h`
    return `${Math.round(seconds / 86400)}d`
}

function timeAgo(dateStr) {
    const diffMinutes = Math.floor((Date.now() - new Date(dateStr)) / 60000)
    if (diffMinutes < 1) return 'ahora'
    if (diffMinutes < 60) return `hace ${diffMinutes}m`
    if (diffMinutes < 1440) return `hace ${Math.floor(diffMinutes / 60)}h`
    return `hace ${Math.floor(diffMinutes / 1440)}d`
}

function formatShortDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })
}

function formatFullDate(dateStr) {
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function formatDeadline(dateStr) {
    if (!dateStr) return 'Sin fecha límite'
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-MX', {
        day: '2-digit', month: 'long', year: 'numeric',
    })
}

const todayFormatted = computed(() => {
    const d = new Date()
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
})

const CLASS_COLORS = {
    Warrior: 'text-[#C79C6E]', Paladin: 'text-[#F58CBA]', Hunter: 'text-[#ABD473]',
    Rogue: 'text-[#FFF569]', Priest: 'text-white', 'Death Knight': 'text-[#C41F3B]',
    Shaman: 'text-[#0070DE]', Mage: 'text-[#69CCF0]', Warlock: 'text-[#9482C9]',
    Monk: 'text-[#00FF96]', Druid: 'text-[#FF7D0A]', 'Demon Hunter': 'text-[#A330C9]',
    Evoker: 'text-[#33937F]',
}

const QUALITY_COLORS = {
    poor: 'text-slate-400', common: 'text-slate-100', uncommon: 'text-emerald-400',
    rare: 'text-sky-400', epic: 'text-purple-400', legendary: 'text-orange-400',
}

const QUALITY_BAR_COLORS = {
    poor: 'bg-slate-400', common: 'bg-slate-300', uncommon: 'bg-emerald-500',
    rare: 'bg-sky-500', epic: 'bg-purple-500', legendary: 'bg-orange-500',
}

function copperToGoldNumber(copper) {
    return Math.round((copper || 0) / 10000)
}

const goldEvolutionChartOptions = computed(() => {
    const series = goldHistory.value?.series ?? []
    const showMarkers = series.length <= 5

    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            fontFamily: 'inherit',
        },
        theme: { mode: 'dark' },
        colors: [PALETTE[3]],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 90, 100],
            },
        },
        markers: {
            size: showMarkers ? 5 : 0,
            colors: [PALETTE[3]],
            strokeColors: '#12142b',
            strokeWidth: 2,
            hover: { size: 7 },
        },
        grid: {
            borderColor: 'rgba(255,255,255,0.08)',
            strokeDashArray: 4,
            padding: { left: 8, right: 8 },
        },
        xaxis: {
            type: 'datetime',
            categories: series.map(s => s.snapshot_at),
            labels: { style: { colors: '#64748b', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
            crosshairs: {
                show: true,
                position: 'back',
                stroke: { color: '#64748b', width: 1, dashArray: 4 },
            },
        },
        yaxis: {
            labels: {
                style: { colors: '#64748b', fontSize: '11px' },
                formatter: (val) => val === undefined || val === null ? '' : `${formatCompactGold(Math.round(val))}g`,
            },
        },
        tooltip: {
            theme: 'dark',
            x: { format: 'dd MMM HH:mm' },
            y: { formatter: (val) => `${val?.toLocaleString('es-MX') ?? 0}g` },
            marker: { show: true },
        },
    }
})

const goldEvolutionChartSeries = computed(() => [{
    name: 'Oro total',
    data: (goldHistory.value?.series ?? []).map(s => copperToGoldNumber(s.total_gold_copper)),
}])
</script>

<template>
    <div class="flex flex-col gap-5 w-full pb-16">
        <!-- fecha actual -->
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <Calendar class="size-4" />
            {{ todayFormatted }}
        </div>

        <!-- selector de personaje + boton de sync -->
        <div class="flex items-center gap-3">
            <div class="relative w-72">
                <button type="button" @click="characterMenuOpen = !characterMenuOpen"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-white/10 bg-[#12142b] px-4 py-3">
                    <div class="flex items-center gap-3 text-left">
                        <img v-if="currentCharacter?.class_icon" :src="currentCharacter.class_icon"
                            class="size-9 rounded-lg border border-white/10" />
                        <div v-else class="flex size-9 items-center justify-center rounded-lg bg-white/5 font-bold text-slate-300">
                            {{ currentCharacter ? currentCharacter.name[0] : 'T' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-100">
                                {{ currentCharacter ? `${currentCharacter.name} - ${currentCharacter.realm}` : 'Todos los personajes' }}
                            </p>
                            <p class="text-xs text-slate-500" v-if="currentCharacter">{{ currentCharacter.class }}</p>
                            <p class="text-xs text-slate-500" v-else>Vista consolidada</p>
                        </div>
                    </div>
                    <ChevronDown class="size-4 text-slate-500 transition-transform" :class="{ 'rotate-180': characterMenuOpen }" />
                </button>

                <div v-if="characterMenuOpen"
                    class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]">
                    <button type="button" @click="selectCharacter(null)"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-sm hover:bg-white/5"
                        :class="!selectedCharacter ? 'text-indigo-300' : 'text-slate-300'">
                        Todos los personajes
                    </button>
                    <div v-for="c in visibleCharacters" :key="c.key"
                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm hover:bg-white/5"
                        :class="selectedCharacter === c.key ? 'text-indigo-300' : 'text-slate-300'">
                        <button type="button" @click="selectCharacter(c.key)" class="flex flex-1 items-center gap-2 text-left">
                            <img v-if="c.class_icon" :src="c.class_icon" class="size-5 rounded" />
                            <span class="flex-1 text-left">
                                <span :class="CLASS_COLORS[c.class] ?? 'text-slate-300'" class="font-medium">{{ c.name }}</span>
                                <span class="text-slate-500"> · {{ c.realm }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-400">
                                {{ formatCompactGold(c.gold.gold) }}<span class="size-2 rounded-full bg-amber-400"></span>
                            </span>
                        </button>
                        <button type="button" @click="hideCharacter(c.key, $event)"
                            class="shrink-0 rounded p-1 text-slate-600 hover:bg-white/10 hover:text-red-400">
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="syncNow" :disabled="syncing || syncCooldown > 0"
                class="flex items-center gap-2 rounded-xl border border-white/10 bg-[#12142b] px-4 py-3 text-sm font-medium text-slate-300 transition-colors hover:border-indigo-400/60 hover:text-white disabled:cursor-not-allowed disabled:opacity-50">
                <RefreshCw class="size-4" :class="{ 'animate-spin': syncing }" />
                <span v-if="syncCooldown > 0">Sincronizar ({{ syncCooldown }}s)</span>
                <span v-else>Sincronizar</span>
            </button>
        </div>

        <!-- header con oro actual (personaje especifico) -->
        <div v-if="currentCharacter" class="flex items-center justify-between rounded-2xl border border-white/10 bg-[#12142b] p-5">
            <div class="flex items-center gap-4">
                <img v-if="currentCharacter.class_icon" :src="currentCharacter.class_icon"
                    class="size-14 rounded-xl border border-white/10" />
                <div>
                    <h2 class="text-lg font-bold text-slate-100">{{ currentCharacter.name }} - {{ currentCharacter.realm }}</h2>
                    <p class="text-xs text-slate-500">
                        <span class="rounded bg-white/5 px-1.5 py-0.5 font-semibold">ilvl {{ currentCharacter.ilvl }}</span>
                        <span :class="CLASS_COLORS[currentCharacter.class] ?? 'text-slate-400'" class="ml-2 font-medium">{{ currentCharacter.class }}</span>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Oro actual</p>
                <CoinAmount
                    :gold="currentCharacter.gold.gold"
                    :silver="currentCharacter.gold.silver"
                    :copper="currentCharacter.gold.copper"
                    size="text-xl"
                />
            </div>
        </div>

        <!-- vista consolidada: 3 cards (oro combinado, resumen de actividad, objetivo de oro) -->
        <div v-else-if="overview" class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Oro total combinado</p>
                <p class="text-3xl font-bold text-amber-400">
                    {{ formatCompactGold(goldHistory ? copperToGoldNumber(goldHistory.current_total_copper) : 0) }}g
                </p>
                <p v-if="goldHistory?.week_change_percent !== null && goldHistory?.week_change_percent !== undefined"
                    class="mt-1 text-xs font-semibold"
                    :class="goldHistory.week_change_percent >= 0 ? 'text-emerald-400' : 'text-red-400'">
                    {{ goldHistory.week_change_percent >= 0 ? '+' : '' }}{{ goldHistory.week_change_percent }}% frente a la semana pasada
                </p>
                <p v-else class="mt-1 text-xs text-slate-500">Aún no hay suficiente historial</p>

                <div class="mt-3 flex items-center gap-4 border-t border-white/5 pt-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Warband</p>
                        <p class="text-lg font-bold text-amber-400">{{ warband ? formatCompactGold(warband.gold) : '—' }}g</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Personajes</p>
                        <p class="text-lg font-bold text-amber-400">{{ formatCompactGold(overview.current_gold.gold) }}g</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Resumen de actividad</p>
                    <span class="text-[10px] font-medium text-indigo-300">Vista actual</span>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <p class="text-lg font-bold text-slate-100">{{ overview.today_sales_count }}</p>
                        <p class="text-[10px] text-slate-500">Ventas hoy</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-slate-100">{{ overview.total_earned_count }}</p>
                        <p class="text-[10px] text-slate-500">Ventas totales</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-slate-100">{{ overview.total_spent_count }}</p>
                        <p class="text-[10px] text-slate-500">Compras totales</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-6 border-t border-white/5 pt-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Ganancia neta generada</p>
                        <p class="text-lg font-bold text-emerald-400">
                            +{{ formatCompactGold(overview.net_profit.gold) }}g
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Ganancia hoy</p>
                        <p class="text-lg font-bold" :class="overview.today_net_profit.gold >= 0 ? 'text-emerald-400' : 'text-red-400'">
                            {{ overview.today_net_profit.gold >= 0 ? '+' : '' }}{{ formatCompactGold(overview.today_net_profit.gold) }}g
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-400/20 bg-gradient-to-br from-amber-500/5 to-transparent p-5">
                <template v-if="!goalEditing">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-amber-300">
                            <Target class="size-3.5" /> {{ goldGoal ? goldGoal.title : 'Objetivo de oro' }}
                        </p>
                        <button type="button" @click="startEditGoal" class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-amber-300">
                            <Pencil class="size-3.5" />
                        </button>
                    </div>

                    <template v-if="goldGoal">
                        <p class="text-3xl font-bold text-amber-300">{{ goldGoal.target_gold.gold.toLocaleString('es-MX') }}g</p>
                        <p class="text-sm text-slate-400">
                            <span class="text-slate-500">Fecha límite:</span> {{ formatDeadline(goldGoal.deadline_date) }}
                            <span v-if="goldGoal.days_remaining !== null" class="ml-1 font-semibold"
                                :class="goldGoal.days_remaining >= 0 ? 'text-amber-300' : 'text-red-400'">
                                ({{ goldGoal.days_remaining >= 0 ? `faltan ${goldGoal.days_remaining} día${goldGoal.days_remaining === 1 ? '' : 's'}` : `vencida hace ${Math.abs(goldGoal.days_remaining)} día${Math.abs(goldGoal.days_remaining) === 1 ? '' : 's'}` }})
                            </span>
                        </p>

                        <div class="mt-3 h-2 w-full rounded-full bg-white/5">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-300 transition-all"
                                :style="{ width: goldGoal.percent_achieved + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                            <span>{{ goldGoal.percent_achieved }}% cumplido</span>
                            <span>{{ formatCompactGold(goldGoal.remaining.gold) }}g restante</span>
                        </div>
                    </template>
                    <p v-else class="py-2 text-sm text-slate-500">Aún no has definido una meta. Da clic en el lápiz para crear una.</p>
                </template>

                <template v-else>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-300">Configurar meta</p>
                        <button type="button" @click="goalEditing = false" class="text-slate-500 hover:text-white">
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <div class="flex flex-col gap-2">
                        <input v-model="goalForm.title" type="text" placeholder="Título de la meta"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-amber-400/60" />
                        <input v-model.number="goalForm.target_gold" type="number" placeholder="Meta en oro (ej. 2500000)"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-amber-400/60" />
                        <input v-model="goalForm.deadline_date" type="date"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-100 outline-none focus:border-amber-400/60" />

                        <button type="button" @click="saveGoal" :disabled="goalSaving"
                            class="mt-1 flex items-center justify-center gap-2 rounded-lg border border-amber-400/40 bg-amber-500/10 px-3 py-1.5 text-sm font-semibold text-amber-300 transition-colors hover:border-amber-400/70 hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:opacity-50">
                            <Check class="size-4" />
                            {{ goalSaving ? 'Guardando...' : 'Guardar meta' }}
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- grafica de evolucion del oro -- disponible tanto en vista consolidada como por personaje -->
        <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                    <LineChart class="size-4 text-amber-400" />
                    Evolución del oro
                    <span v-if="currentCharacter" class="font-normal text-slate-500">— {{ currentCharacter.name }}</span>
                </h3>
                <div class="flex items-center gap-1 rounded-lg border border-white/10 p-0.5">
                    <button v-for="r in [{ label: '1D', value: '1d' }, { label: '7D', value: '7d' }, { label: '30D', value: '30d' }, { label: '90D', value: '90d' }]"
                        :key="r.value" type="button" @click="goldHistoryRange = r.value"
                        class="rounded px-2.5 py-1 text-xs font-semibold transition-colors"
                        :class="goldHistoryRange === r.value ? 'bg-amber-500/20 text-amber-300' : 'text-slate-500 hover:text-white'">
                        {{ r.label }}
                    </button>
                </div>
            </div>

            <div v-if="loadingGoldHistory" class="flex h-56 items-center justify-center text-sm text-slate-500">
                Cargando...
            </div>
            <div v-else-if="!goldHistory?.series?.length" class="flex h-56 items-center justify-center text-center text-sm text-slate-500">
                Aún no hay suficiente historial para graficar — se irá llenando con cada sync.
            </div>
            <apexchart v-else type="area" height="260" :options="goldEvolutionChartOptions" :series="goldEvolutionChartSeries" />
        </div>

        <!-- 4 cards de estadisticas -->
        <div v-if="overview" class="grid grid-cols-4 gap-3">
            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-emerald-400">
                    <TrendingUp class="size-3.5" /> Ganado en total
                </div>
                <CoinAmount :gold="overview.total_earned.gold" :silver="overview.total_earned.silver" :copper="overview.total_earned.copper" />
                <p class="mt-1 text-xs text-slate-500">{{ overview.total_earned_count }} ventas</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-red-400">
                    <TrendingDown class="size-3.5" /> Gastado en total
                </div>
                <CoinAmount :gold="overview.total_spent.gold" :silver="overview.total_spent.silver" :copper="overview.total_spent.copper" />
                <p class="mt-1 text-xs text-slate-500">{{ overview.total_spent_count }} compras</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-indigo-300">
                    <Wallet class="size-3.5" /> Ganancia neta
                </div>
                <CoinAmount :gold="overview.net_profit.gold" :silver="overview.net_profit.silver" :copper="overview.net_profit.copper" />
                <p class="mt-1 text-xs text-slate-500">Histórico</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-cyan-300">
                    <Gavel class="size-3.5" /> Invertido
                </div>
                <CoinAmount :gold="overview.invested.gold" :silver="overview.invested.silver" :copper="overview.invested.copper" />
                <p class="mt-1 text-xs text-slate-500">{{ overview.invested_count }} subastas activas</p>
            </div>
        </div>

        <div v-if="overview" class="grid grid-cols-4 gap-3">
            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-amber-400">
                    <Award class="size-3.5" /> Mejor venta
                </div>
                <div v-if="overview.best_flip" class="flex items-center gap-2">
                    <img v-if="overview.best_flip.icon_url" :src="overview.best_flip.icon_url" class="size-6 rounded" />
                    <Package v-else class="size-6 text-slate-600" />
                    <div>
                        <p class="text-sm font-bold text-slate-100">{{ overview.best_flip.item_name }}</p>
                        <CoinAmount :gold="overview.best_flip.amount.gold" :silver="overview.best_flip.amount.silver" :copper="overview.best_flip.amount.copper" size="text-xs" />
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">Sin datos aún</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-emerald-400">
                    <ArrowDown class="size-3.5" /> Ganado hoy
                </div>
                <CoinAmount :gold="overview.today_earned.gold" :silver="overview.today_earned.silver" :copper="overview.today_earned.copper" />
                <div class="mt-2 h-1.5 w-full rounded-full bg-white/5">
                    <div class="h-full rounded-full bg-emerald-500" style="width: 100%"></div>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-red-400">
                    <ArrowUp class="size-3.5" /> Gastado hoy
                </div>
                <CoinAmount :gold="overview.today_spent.gold" :silver="overview.today_spent.silver" :copper="overview.today_spent.copper" />
                <div class="mt-2 h-1.5 w-full rounded-full bg-white/5">
                    <div class="h-full rounded-full bg-red-500" style="width: 100%"></div>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-amber-300">
                    <TrendingUp class="size-3.5" /> Promedio diario
                </div>
                <CoinAmount :gold="overview.avg_daily_earned.gold" :silver="overview.avg_daily_earned.silver" :copper="overview.avg_daily_earned.copper" />
                <p class="mt-1 text-xs text-slate-500">{{ overview.avg_daily_days }} día(s)</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <!-- subastas activas -->
            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="shrink-0 text-sm font-bold text-slate-100">Subastas activas</h3>
                    <div class="relative w-40">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                        <input v-model="auctionSearch" type="text" placeholder="Buscar..."
                            class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60 focus:bg-white/10" />
                    </div>
                </div>
                <div v-if="!filteredActiveAuctions.length" class="py-6 text-center text-sm text-slate-500">
                    {{ activeAuctions.length ? 'Sin resultados' : 'Sin subastas activas' }}
                </div>
                <div v-else class="app-scroll flex max-h-96 flex-col gap-2 overflow-y-auto pr-1">
                    <div v-for="(a, i) in filteredActiveAuctions" :key="i"
                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2.5">
                        <img v-if="a.icon_url" :src="a.icon_url" class="size-8 shrink-0 rounded" />
                        <Package v-else class="size-8 shrink-0 text-slate-600" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-indigo-300">{{ a.item_name }}</p>
                            <p class="text-xs text-slate-500">
                                x{{ a.quantity }} · {{ formatTimeLeft(a.time_left_seconds) }}
                                <span v-if="a.num_bids"> · {{ a.num_bids }} pujas</span>
                                <span v-if="a.quantity > 1"> · {{ a.buyout.gold }}g c/u</span>
                            </p>
                        </div>
                        <CoinAmount :gold="a.total.gold" :silver="a.total.silver" :copper="a.total.copper" size="text-xs" />
                    </div>
                </div>
            </div>

            <!-- historial de transacciones -->
            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-100">Historial de transacciones</h3>
                    <div class="flex gap-1 rounded-lg border border-white/10 p-0.5">
                        <button type="button" @click="txFilter = 'all'"
                            class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="txFilter === 'all' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-500 hover:text-white'">
                            Todas
                        </button>
                        <button type="button" @click="txFilter = 'sales'"
                            class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="txFilter === 'sales' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-500 hover:text-white'">
                            Ventas
                        </button>
                        <button type="button" @click="txFilter = 'purchases'"
                            class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="txFilter === 'purchases' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-500 hover:text-white'">
                            Compras
                        </button>
                    </div>
                </div>

                <div class="relative mb-3 w-full">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                    <input v-model="txSearch" type="text" placeholder="Buscar en el historial..."
                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60 focus:bg-white/10" />
                </div>

                <div v-if="loadingTx" class="py-6 text-center text-sm text-slate-500">Cargando...</div>
                <div v-else-if="!filteredTransactions.length" class="py-6 text-center text-sm text-slate-500">
                    {{ transactions.data?.length ? 'Sin resultados' : 'Sin transacciones' }}
                </div>
                <div v-else class="app-scroll flex max-h-96 flex-col gap-1 overflow-y-auto pr-1">
                    <div v-for="tx in filteredTransactions" :key="tx.id"
                        class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-white/5">
                        <img v-if="tx.icon_url" :src="tx.icon_url" class="size-7 shrink-0 rounded" />
                        <div v-else class="flex size-7 shrink-0 items-center justify-center rounded"
                            :class="tx.type === 'sale' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'">
                            <ArrowUp v-if="tx.type === 'sale'" class="size-3.5" />
                            <ArrowDown v-else class="size-3.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-200">
                                {{ tx.item_name }}
                                <span v-if="tx.quantity > 1" class="ml-1 text-xs font-semibold text-slate-500">x{{ tx.quantity }}</span>
                            </p>
                            <p class="text-xs text-slate-500" :title="formatFullDate(tx.occurred_at)">
                                {{ timeAgo(tx.occurred_at) }} · {{ formatShortDate(tx.occurred_at) }}
                            </p>
                        </div>
                        <span class="shrink-0 flex items-center gap-1">
                            <span class="text-xs font-bold" :class="tx.type === 'sale' ? 'text-emerald-400' : 'text-red-400'">
                                {{ tx.type === 'sale' ? '+' : '-' }}
                            </span>
                            <CoinAmount :gold="tx.amount.gold" :silver="tx.amount.silver" :copper="tx.amount.copper" size="text-xs" />
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ventas por item -->
        <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="flex shrink-0 items-center gap-2 text-sm font-bold text-slate-100">
                    <ShoppingBag class="size-4 text-indigo-400" />
                    Ventas por ítem
                </h3>
                <div class="relative w-56">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                    <input v-model="salesByItemSearch" type="text" placeholder="Buscar ítem..."
                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-2 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60 focus:bg-white/10" />
                </div>
            </div>

            <div v-if="!filteredSalesByItem.length" class="py-6 text-center text-sm text-slate-500">
                {{ salesByItem.length ? 'Sin resultados' : 'Aún no hay ventas registradas' }}
            </div>

            <div v-else class="app-scroll flex max-h-128 flex-col overflow-y-auto pr-1">
                <div v-for="item in filteredSalesByItem" :key="item.item_name" class="border-b border-white/5 last:border-b-0">
                    <button type="button" @click="toggleSalesItem(item.item_name)"
                        class="flex w-full items-center gap-3 py-3 text-left transition-colors hover:bg-white/5">
                        <img v-if="item.icon_url" :src="item.icon_url" class="size-9 shrink-0 rounded" />
                        <Package v-else class="size-9 shrink-0 text-slate-600" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" :class="QUALITY_COLORS[item.quality] ?? 'text-slate-100'">
                                {{ item.item_name }}
                            </p>
                            <p class="text-xs text-slate-500">{{ item.sales_count }} venta{{ item.sales_count === 1 ? '' : 's' }}</p>
                            <div class="mt-1.5 h-1 w-full max-w-xs rounded-full bg-white/5">
                                <div class="h-full rounded-full transition-all"
                                    :class="QUALITY_BAR_COLORS[item.quality] ?? 'bg-emerald-500'"
                                    :style="{ width: salesBarWidth(item.total_copper) }"></div>
                            </div>
                        </div>

                        <CoinAmount :gold="item.total.gold" :silver="item.total.silver" :copper="item.total.copper" size="text-sm" />
                        <ChevronDown class="size-4 shrink-0 text-slate-500 transition-transform"
                            :class="{ 'rotate-180': openSalesItems.has(item.item_name) }" />
                    </button>

                    <div v-if="openSalesItems.has(item.item_name)" class="flex flex-col gap-1 pb-3 pl-12">
                        <div v-for="sale in item.sales" :key="sale.id"
                            class="flex items-center justify-between rounded-lg px-2 py-1.5 text-xs hover:bg-white/5">
                            <span class="text-slate-400">
                                <span v-if="sale.counterparty">Vendido a {{ sale.counterparty }}</span>
                                <span v-else>Venta</span>
                                · <span :title="formatFullDate(sale.occurred_at)">{{ timeAgo(sale.occurred_at) }}</span>
                            </span>
                            <CoinAmount :gold="sale.amount.gold" :silver="sale.amount.silver" :copper="sale.amount.copper" size="text-xs" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

.app-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #4338ca;
}
</style>