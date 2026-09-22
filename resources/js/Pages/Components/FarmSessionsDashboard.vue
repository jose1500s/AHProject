<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Play, Square, Package, TrendingUp, TrendingDown, Minus, BarChart3, Pickaxe, Clock, Diamond, ChevronDown, RefreshCw, LineChart, Coins, Check, Trash2, PieChart, Info, Search, ArrowUpDown } from '@lucide/vue'
import CoinAmount from './CoinAmount.vue'
import VueApexCharts from 'vue3-apexcharts'
defineOptions({ components: { apexchart: VueApexCharts } })

const viewMode = ref('loading')
const liveTab = ref('session')
const setupTab = ref('sessions')

const selectedMode = ref('freeform')
const plannedMinutes = ref(30)

const characters = ref([])
const detectedCharacterKey = ref(null)

const activeSession = ref(null)
const now = ref(Date.now())
let timerInterval = null
let pollInterval = null

const liveValue = ref(null)
const liveRefreshing = ref(false)
const liveStats = ref(null)
const finalValue = ref(null)
const sessionStats = ref(null)

const summaryTotals = ref(null)
const pastSessions = ref([])
const loadingSetup = ref(true)

const expandedSessionId = ref(null)
const expandedSessionDetail = ref(null)
const expandedSessionStats = ref(null)
const loadingExpanded = ref(false)

const analyticsData = ref(null)
const loadingAnalytics = ref(false)

const goldItemSearch = ref('')
const goldItemSortDesc = ref(true)
const silverItemSearch = ref('')
const silverItemSortDesc = ref(true)
const otherItemSearch = ref('')
const otherItemSortDesc = ref(true)

const expandedGoldSearch = ref('')
const expandedGoldSortDesc = ref(true)
const expandedSilverSearch = ref('')
const expandedSilverSortDesc = ref(true)
const expandedOtherSearch = ref('')
const expandedOtherSortDesc = ref(true)

const analyticsGoldSearch = ref('')
const analyticsGoldSortDesc = ref(true)
const analyticsSilverSearch = ref('')
const analyticsSilverSortDesc = ref(true)
const analyticsOtherSearch = ref('')
const analyticsOtherSortDesc = ref(true)

const CLASS_COLORS = {
    WARRIOR: 'text-[#C79C6E]', PALADIN: 'text-[#F58CBA]', HUNTER: 'text-[#ABD473]',
    ROGUE: 'text-[#FFF569]', PRIEST: 'text-white', DEATHKNIGHT: 'text-[#C41F3B]',
    SHAMAN: 'text-[#0070DE]', MAGE: 'text-[#69CCF0]', WARLOCK: 'text-[#9482C9]',
    MONK: 'text-[#00FF96]', DRUID: 'text-[#FF7D0A]', DEMONHUNTER: 'text-[#A330C9]',
    EVOKER: 'text-[#33937F]',
}

const PROFESSION_ICON_URLS = {
    mining: 'https://wow.zamimg.com/images/wow/icons/medium/trade_mining.jpg',
    herbalism: 'https://wow.zamimg.com/images/wow/icons/medium/trade_herbalism.jpg',
}

const PALETTE = ['#818cf8', '#22d3ee', '#fbbf24', '#34d399', '#f472b6', '#a78bfa', '#fb923c', '#60a5fa']

function normalizeClassKey(cls) {
    return String(cls ?? '').toUpperCase().replace(/[\s-]/g, '')
}

function normalizeText(str) {
    return String(str ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
}

function copperToGsc(copper) {
    const c = Math.round(Math.abs(copper || 0))
    return {
        gold: Math.floor(c / 10000),
        silver: Math.floor((c % 10000) / 100),
        copper: c % 100,
    }
}

function formatDuration(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds))
    const hours = Math.floor(s / 3600)
    const minutes = Math.floor((s % 3600) / 60)
    const seconds = s % 60
    if (hours > 0) return `${hours}h ${minutes}m ${seconds}s`
    if (minutes > 0) return `${minutes}m ${seconds}s`
    return `${seconds}s`
}

function formatDurationShort(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds))
    const hours = Math.floor(s / 3600)
    const minutes = Math.floor((s % 3600) / 60)
    const seconds = s % 60
    const pad = n => String(n).padStart(2, '0')
    if (hours > 0) return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
    return `${pad(minutes)}:${pad(seconds)}`
}

function sessionDuration(s) {
    if (!s.stopped_at) return 'en curso'
    const seconds = (new Date(s.stopped_at).getTime() - new Date(s.started_at).getTime()) / 1000
    return formatDuration(seconds)
}

function formatClockTime(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    let hours = d.getHours()
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const ampm = hours >= 12 ? 'pm' : 'am'
    hours = hours % 12
    if (hours === 0) hours = 12
    return `${hours}:${minutes}${ampm}`
}

function formatDateDMY(dateStr) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
}

function formatStaleness(seconds) {
    if (seconds === null || seconds === undefined) return { label: 'sin datos', color: 'text-slate-500' }
    const minutes = Math.floor(seconds / 60)
    if (minutes < 15) return { label: `hace ${minutes}m`, color: 'text-emerald-400' }
    if (minutes < 60) return { label: `hace ${minutes}m`, color: 'text-amber-400' }
    return { label: `hace ${Math.floor(minutes / 60)}h ${minutes % 60}m`, color: 'text-red-400' }
}

function qualityIconClass(quality) {
    return quality === 'gold' ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-slate-300'
}

function characterClass(characterKey) {
    return characters.value.find(c => c.key === characterKey)?.class ?? null
}

function characterColorClass(characterKey) {
    const cls = characterClass(characterKey)
    return CLASS_COLORS[normalizeClassKey(cls)] ?? 'text-slate-100'
}

const RECOMMENDATION_CONFIG = {
    vender_ahora: { label: 'Vender ahora', color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-400/30', icon: TrendingDown },
    esperar: { label: 'Esperar', color: 'text-amber-400', bg: 'bg-amber-500/10 border-amber-400/30', icon: TrendingUp },
    estable: { label: 'Estable', color: 'text-slate-300', bg: 'bg-white/5 border-white/10', icon: Minus },
}

const elapsedSeconds = computed(() => {
    if (!activeSession.value) return 0
    return (now.value - new Date(activeSession.value.started_at).getTime()) / 1000
})

const detectedCharacterLabel = computed(() => {
    if (!detectedCharacterKey.value) return 'Detectando...'
    const c = characters.value.find(c => c.key === detectedCharacterKey.value)
    return c ? `${c.name} · ${c.realm}` : detectedCharacterKey.value
})

const sessionSummaryTitle = computed(() => {
    if (!finalValue.value) return 'Resumen de sesión'
    const start = formatClockTime(finalValue.value.started_at)
    const end = formatClockTime(finalValue.value.ended_at)
    const date = formatDateDMY(finalValue.value.started_at)
    return `Resumen de sesión ${start} - ${end} ${date}`
})

const sessionSummaryDuration = computed(() => {
    if (!finalValue.value) return ''
    const seconds = (new Date(finalValue.value.ended_at).getTime() - new Date(finalValue.value.started_at).getTime()) / 1000
    return formatDuration(seconds)
})

function buildValueByItemChart(items, valueKey = 'current_total_copper') {
    if (!items || !items.length) return { options: {}, series: [] }

    const withValue = items.filter(i => (i[valueKey] || 0) > 0)
    const sorted = [...withValue].sort((a, b) => (b[valueKey] || 0) - (a[valueKey] || 0))
    const top = sorted.slice(0, 7)
    const rest = sorted.slice(7)
    const restTotal = rest.reduce((sum, i) => sum + (i[valueKey] || 0), 0)

    const labels = top.map(i => i.item_name)
    const series = top.map(i => Math.round((i[valueKey] || 0) / 10000 * 100) / 100)

    if (restTotal > 0) {
        labels.push(`Otros (${rest.length})`)
        series.push(Math.round(restTotal / 10000 * 100) / 100)
    }

    return {
        series,
        options: {
            chart: { type: 'donut', background: 'transparent', fontFamily: 'inherit' },
            theme: { mode: 'dark' },
            colors: PALETTE,
            labels,
            legend: {
                position: 'bottom',
                fontSize: '10px',
                labels: { colors: '#94a3b8' },
                itemMargin: { horizontal: 5, vertical: 2 },
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => `${val.toFixed(1)}%`,
                style: { fontSize: '10px' },
            },
            stroke: { colors: ['#12142b'] },
            tooltip: {
                theme: 'dark',
                y: { formatter: (val) => `${val}g` },
            },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                color: '#e2e8f0',
                                formatter: (w) => `${Math.round(w.globals.seriesTotals.reduce((a, b) => a + b, 0))}g`,
                            },
                        },
                    },
                },
            },
        },
    }
}

const valueByItemChart = computed(() => buildValueByItemChart(finalValue.value?.items ?? [], 'current_total_copper'))
const expandedValueByItemChart = computed(() => buildValueByItemChart(expandedSessionDetail.value?.items ?? [], 'current_total_copper'))
const analyticsValueByItemChart = computed(() => buildValueByItemChart(analyticsData.value?.item_mix ?? [], 'value_copper'))

function makePaceChartOptions(pace) {
    return {
        chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent', fontFamily: 'inherit' },
        theme: { mode: 'dark' },
        colors: [PALETTE[0]],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
        xaxis: {
            categories: pace.map(b => b.label),
            labels: { style: { colors: '#64748b', fontSize: '9px' }, rotate: -45 },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: { style: { colors: '#64748b', fontSize: '10px' } },
        },
        tooltip: {
            theme: 'dark',
            y: { formatter: (val) => `${val} nodos` },
        },
    }
}

function makePaceChartSeries(pace) {
    return [{ name: 'Nodos', data: pace.map(b => b.nodes) }]
}

const paceChartOptions = computed(() => makePaceChartOptions(sessionStats.value?.pace_chart ?? []))
const paceChartSeries = computed(() => makePaceChartSeries(sessionStats.value?.pace_chart ?? []))

const expandedPaceChartOptions = computed(() => makePaceChartOptions(expandedSessionStats.value?.pace_chart ?? []))
const expandedPaceChartSeries = computed(() => makePaceChartSeries(expandedSessionStats.value?.pace_chart ?? []))

function makeGoldPerHourChartOptions(rows) {
    return {
        chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent', fontFamily: 'inherit' },
        theme: { mode: 'dark' },
        colors: [PALETTE[2]],
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 3, strokeColors: '#12142b', strokeWidth: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
        xaxis: {
            categories: rows.map(r => r.label),
            labels: { style: { colors: '#64748b', fontSize: '9px' }, rotate: -45 },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { colors: '#64748b', fontSize: '10px' },
                formatter: (val) => `${val}g`,
            },
        },
        tooltip: {
            theme: 'dark',
            y: { formatter: (val) => `${val}g/h` },
        },
    }
}

function makeGoldPerHourChartSeries(rows) {
    return [{ name: 'Oro/hora', data: rows.map(r => r.gold_per_hour) }]
}

const goldPerHourChartOptions = computed(() => makeGoldPerHourChartOptions(sessionStats.value?.gold_per_hour_chart ?? []))
const goldPerHourChartSeries = computed(() => makeGoldPerHourChartSeries(sessionStats.value?.gold_per_hour_chart ?? []))

const expandedGoldPerHourChartOptions = computed(() => makeGoldPerHourChartOptions(expandedSessionStats.value?.gold_per_hour_chart ?? []))
const expandedGoldPerHourChartSeries = computed(() => makeGoldPerHourChartSeries(expandedSessionStats.value?.gold_per_hour_chart ?? []))

function filterAndSortItems(items, searchQuery, sortDesc, valueKey = 'current_total_copper') {
    const q = normalizeText(searchQuery.trim())
    let list = items

    if (q) {
        list = list.filter(i => normalizeText(i.item_name).includes(q))
    }

    return [...list].sort((a, b) => {
        const diff = (b[valueKey] || 0) - (a[valueKey] || 0)
        return sortDesc ? diff : -diff
    })
}

const goldBreakdownItems = computed(() => (finalValue.value?.items ?? []).filter(i => i.craft_quality === 'gold'))
const silverBreakdownItems = computed(() => (finalValue.value?.items ?? []).filter(i => i.craft_quality === 'silver'))
const otherBreakdownItems = computed(() => (finalValue.value?.items ?? []).filter(i => !i.craft_quality))

const filteredGoldItems = computed(() => filterAndSortItems(goldBreakdownItems.value, goldItemSearch.value, goldItemSortDesc.value))
const filteredSilverItems = computed(() => filterAndSortItems(silverBreakdownItems.value, silverItemSearch.value, silverItemSortDesc.value))
const filteredOtherItems = computed(() => filterAndSortItems(otherBreakdownItems.value, otherItemSearch.value, otherItemSortDesc.value))

const expandedGoldBreakdown = computed(() => (expandedSessionDetail.value?.items ?? []).filter(i => i.craft_quality === 'gold'))
const expandedSilverBreakdown = computed(() => (expandedSessionDetail.value?.items ?? []).filter(i => i.craft_quality === 'silver'))
const expandedOtherBreakdown = computed(() => (expandedSessionDetail.value?.items ?? []).filter(i => !i.craft_quality))

const filteredExpandedGoldItems = computed(() => filterAndSortItems(expandedGoldBreakdown.value, expandedGoldSearch.value, expandedGoldSortDesc.value))
const filteredExpandedSilverItems = computed(() => filterAndSortItems(expandedSilverBreakdown.value, expandedSilverSearch.value, expandedSilverSortDesc.value))
const filteredExpandedOtherItems = computed(() => filterAndSortItems(expandedOtherBreakdown.value, expandedOtherSearch.value, expandedOtherSortDesc.value))

const analyticsGoldBreakdown = computed(() => (analyticsData.value?.item_mix ?? []).filter(i => i.craft_quality === 'gold'))
const analyticsSilverBreakdown = computed(() => (analyticsData.value?.item_mix ?? []).filter(i => i.craft_quality === 'silver'))
const analyticsOtherBreakdown = computed(() => (analyticsData.value?.item_mix ?? []).filter(i => !i.craft_quality))

const filteredAnalyticsGoldItems = computed(() => filterAndSortItems(analyticsGoldBreakdown.value, analyticsGoldSearch.value, analyticsGoldSortDesc.value, 'value_copper'))
const filteredAnalyticsSilverItems = computed(() => filterAndSortItems(analyticsSilverBreakdown.value, analyticsSilverSearch.value, analyticsSilverSortDesc.value, 'value_copper'))
const filteredAnalyticsOtherItems = computed(() => filterAndSortItems(analyticsOtherBreakdown.value, analyticsOtherSearch.value, analyticsOtherSortDesc.value, 'value_copper'))

let originalDocumentTitle = null

watch([elapsedSeconds, viewMode], () => {
    if (viewMode.value === 'active') {
        if (originalDocumentTitle === null) {
            originalDocumentTitle = document.title
        }
        document.title = `${formatDurationShort(elapsedSeconds.value)} · Farmeando`
    } else if (originalDocumentTitle !== null) {
        document.title = originalDocumentTitle
        originalDocumentTitle = null
    }
})

onUnmounted(() => {
    if (originalDocumentTitle !== null) {
        document.title = originalDocumentTitle
    }
})

async function fetchCharacters() {
    const res = await fetch('/api/wow/characters')
    const data = await res.json()
    characters.value = data.characters ?? data
}

async function fetchDetectedCharacter() {
    const res = await fetch('/api/farm-sessions/detected-character')
    const data = await res.json()
    detectedCharacterKey.value = data.character_key
}

async function fetchSetupData() {
    loadingSetup.value = true
    try {
        const [summaryRes, listRes] = await Promise.all([
            fetch('/api/farm-sessions/summary'),
            fetch('/api/farm-sessions'),
        ])
        summaryTotals.value = await summaryRes.json()
        const listData = await listRes.json()
        pastSessions.value = listData.sessions ?? []

        const existingActive = pastSessions.value.find(s => s.status === 'active')
        if (existingActive) {
            activeSession.value = existingActive
            viewMode.value = 'active'
            liveTab.value = 'session'
            fetchLiveValue()
            fetchLiveStats()
            return
        }

        viewMode.value = 'setup'
    } finally {
        loadingSetup.value = false
    }
}

async function startSession() {
    const body = { mode: selectedMode.value }
    if (selectedMode.value === 'timed') {
        body.planned_duration_minutes = plannedMinutes.value
    }

    const res = await fetch('/api/farm-sessions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    })
    const data = await res.json()
    activeSession.value = data.session
    viewMode.value = 'active'
    liveTab.value = 'session'
    fetchLiveValue()
    fetchLiveStats()
}

async function fetchLiveValue() {
    if (!activeSession.value) return
    liveRefreshing.value = true
    try {
        const res = await fetch(`/api/farm-sessions/${activeSession.value.id}`)
        const data = await res.json()
        liveValue.value = data.value
        if (data.session) {
            activeSession.value = data.session
        }
    } finally {
        liveRefreshing.value = false
    }
}

async function fetchLiveStats() {
    if (!activeSession.value) return
    const res = await fetch(`/api/farm-sessions/${activeSession.value.id}/stats`)
    liveStats.value = await res.json()
}

function switchLiveTab(tab) {
    liveTab.value = tab
    if (tab === 'analytics' && !analyticsData.value) fetchAnalytics()
}

async function stopSession() {
    if (!activeSession.value) return

    await fetch(`/api/farm-sessions/${activeSession.value.id}/stop`, { method: 'PATCH' })

    const [showRes, statsRes] = await Promise.all([
        fetch(`/api/farm-sessions/${activeSession.value.id}`),
        fetch(`/api/farm-sessions/${activeSession.value.id}/stats`),
    ])
    finalValue.value = (await showRes.json()).value
    sessionStats.value = await statsRes.json()

    viewMode.value = 'summary'
    liveTab.value = 'session'
}

function acceptSummary() {
    activeSession.value = null
    liveValue.value = null
    liveStats.value = null
    finalValue.value = null
    sessionStats.value = null
    selectedMode.value = 'freeform'
    plannedMinutes.value = 30
    expandedSessionId.value = null
    expandedSessionDetail.value = null
    expandedSessionStats.value = null
    goldItemSearch.value = ''
    silverItemSearch.value = ''
    otherItemSearch.value = ''
    fetchSetupData()
    fetchDetectedCharacter()
}

async function toggleSessionExpand(sessionId) {
    if (expandedSessionId.value === sessionId) {
        expandedSessionId.value = null
        expandedSessionDetail.value = null
        expandedSessionStats.value = null
        return
    }

    expandedSessionId.value = sessionId
    loadingExpanded.value = true
    try {
        const [showRes, statsRes] = await Promise.all([
            fetch(`/api/farm-sessions/${sessionId}`),
            fetch(`/api/farm-sessions/${sessionId}/stats`),
        ])
        expandedSessionDetail.value = (await showRes.json()).value
        expandedSessionStats.value = await statsRes.json()
    } finally {
        loadingExpanded.value = false
    }
}

function deleteSession(sessionId, event) {
    event.stopPropagation()

    push.warning({
        title: 'Eliminar sesión',
        message: '¿Estás seguro de que quieres eliminar esta sesión? Esta acción no se puede deshacer.',
        duration: Infinity,
        props: {
            confirmDelete: true,
            onConfirm: (item) => confirmDeleteSession(sessionId, item),
        },
    })
}

async function confirmDeleteSession(sessionId, item) {
    item.clear()

    if (expandedSessionId.value === sessionId) {
        expandedSessionId.value = null
        expandedSessionDetail.value = null
        expandedSessionStats.value = null
    }

    pastSessions.value = pastSessions.value.filter(s => s.id !== sessionId)

    try {
        await fetch(`/api/farm-sessions/${sessionId}`, {
            method: 'DELETE',
        })

        fetchSetupData()
    } catch {
        fetchSetupData()
    }
}

async function fetchAnalytics() {
    loadingAnalytics.value = true
    try {
        const res = await fetch('/api/farm-sessions/analytics')
        analyticsData.value = await res.json()
    } finally {
        loadingAnalytics.value = false
    }
}

function switchSetupTab(tab) {
    setupTab.value = tab
    if (tab === 'analytics' && !analyticsData.value) {
        fetchAnalytics()
    }
}

onMounted(() => {
    fetchCharacters()
    fetchDetectedCharacter()
    fetchSetupData()

    timerInterval = setInterval(() => {
        now.value = Date.now()
    }, 1000)

    pollInterval = setInterval(() => {
        if (viewMode.value === 'active' && liveTab.value === 'session') {
            fetchLiveValue()
            fetchLiveStats()
        }
    }, 30000)
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
    if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
    <div class="flex flex-col gap-5 w-full">
        <div class="flex items-center gap-2">
            <Pickaxe class="size-6 text-indigo-400" />
            <h1 class="text-2xl font-bold text-slate-100">Farm Sessions</h1>
        </div>

        <div v-if="viewMode === 'loading' || loadingSetup" class="py-10 text-center text-sm text-slate-500">
            Cargando...
        </div>

        <template v-else-if="viewMode === 'setup'">
            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-100">Iniciar sesión de recolección</h2>
                    <div class="group relative">
                        <Info class="size-3.5 text-slate-500" />
                        <div class="pointer-events-none absolute left-0 top-6 z-20 w-64 rounded-lg border border-white/10 bg-[#181b3a] p-2.5 text-xs text-slate-300 opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                            El personaje se detecta automáticamente — al hacer /reload en el juego, la app reconoce con quién estás jugando en ese momento.
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex gap-2">
                    <button type="button" @click="selectedMode = 'freeform'"
                        class="flex-1 rounded-lg border px-4 py-3 text-sm font-medium transition-colors"
                        :class="selectedMode === 'freeform' ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300' : 'border-white/10 text-slate-400 hover:border-white/20'">
                        Freeform (sin límite)
                    </button>
                    <button type="button" @click="selectedMode = 'timed'"
                        class="flex-1 rounded-lg border px-4 py-3 text-sm font-medium transition-colors"
                        :class="selectedMode === 'timed' ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300' : 'border-white/10 text-slate-400 hover:border-white/20'">
                        Timed (con cronómetro)
                    </button>
                </div>

                <div v-if="selectedMode === 'timed'" class="mb-4">
                    <label class="mb-1 block text-[10px] font-semibold uppercase tracking-widest text-slate-500">Minutos</label>
                    <input v-model.number="plannedMinutes" type="number" min="1"
                        class="w-32 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-400/60" />
                </div>

                <button type="button" @click="startSession"
                    class="flex items-center gap-2 rounded-lg border border-emerald-400/40 bg-emerald-500/10 px-5 py-2.5 text-sm font-semibold text-emerald-300 transition-colors hover:border-emerald-400/70 hover:bg-emerald-500/20">
                    <Play class="size-4" />
                    Iniciar sesión
                </button>
            </div>

            <div v-if="summaryTotals" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                    <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-emerald-400">
                        <Coins class="size-3.5" /> Total farmeado
                    </div>
                    <CoinAmount v-bind="copperToGsc(summaryTotals.total_gold_copper)" />
                    <p class="mt-1 text-xs text-slate-500">{{ summaryTotals.total_sessions }} sesión(es)</p>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                    <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-indigo-300">
                        <Clock class="size-3.5" /> Tiempo invertido
                    </div>
                    <div class="text-lg font-bold text-slate-100">{{ formatDuration(summaryTotals.total_seconds) }}</div>
                    <p class="mt-1 text-xs text-slate-500">acumulado</p>
                </div>

                <div class="rounded-xl border border-white/10 bg-[#12142b] p-4">
                    <div class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-widest text-cyan-300">
                        <Package class="size-3.5" /> Nodos totales
                    </div>
                    <div class="text-lg font-bold text-slate-100">{{ summaryTotals.total_nodes }}</div>
                    <p class="mt-1 text-xs text-slate-500">recolectados</p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <div class="mb-3 flex gap-1 rounded-lg border border-white/10 bg-[#181b3a] p-1 w-fit">
                    <button type="button" @click="switchSetupTab('sessions')"
                        class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="setupTab === 'sessions' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'">
                        Sesiones anteriores
                    </button>
                    <button type="button" @click="switchSetupTab('analytics')"
                        class="flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="setupTab === 'analytics' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'">
                        <LineChart class="size-3.5" />
                        Analíticas
                    </button>
                </div>

                <div v-if="setupTab === 'sessions'">
                    <div v-if="!pastSessions.length" class="py-6 text-center text-sm text-slate-500">
                        Aún no has hecho ninguna sesión.
                    </div>

                    <div v-else class="flex flex-col gap-2">
                        <div v-for="s in pastSessions" :key="s.id" class="rounded-lg border border-white/10 bg-white/3">
                            <button type="button" @click="toggleSessionExpand(s.id)"
                                class="flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-300">
                                        <Pickaxe class="size-4" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold" :class="characterColorClass(s.character_key)">{{ s.character_key }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ new Date(s.started_at).toLocaleString('es-MX') }} · {{ s.mode === 'timed' ? 'Timed' : 'Freeform' }}
                                            · duró {{ sessionDuration(s) }}
                                            <span v-if="s.total_nodes !== undefined"> · {{ s.total_nodes }} nodos</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <div v-if="s.recommendation" class="flex items-center gap-2">
                                        <CoinAmount v-if="s.current_value_copper !== undefined" v-bind="copperToGsc(s.current_value_copper)" size="text-sm" />
                                        <span class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                            :class="[RECOMMENDATION_CONFIG[s.recommendation]?.bg, RECOMMENDATION_CONFIG[s.recommendation]?.color]">
                                            {{ RECOMMENDATION_CONFIG[s.recommendation]?.label }}
                                        </span>
                                    </div>
                                    <button type="button" @click="deleteSession(s.id, $event)"
                                        class="rounded p-1 text-slate-600 transition hover:bg-red-500/10 hover:text-red-400">
                                        <Trash2 class="size-4" />
                                    </button>
                                    <ChevronDown class="size-4 text-slate-500 transition-transform" :class="{ 'rotate-180': expandedSessionId === s.id }" />
                                </div>
                            </button>

                            <div v-if="expandedSessionId === s.id" class="border-t border-white/5 p-3">
                                <div v-if="loadingExpanded" class="py-4 text-center text-sm text-slate-500">Cargando...</div>

                                <div v-else-if="expandedSessionDetail" class="flex flex-col gap-4">
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Valor al recoger</div>
                                            <CoinAmount v-bind="copperToGsc(expandedSessionDetail.baseline_value_copper)" size="text-sm" />
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Valor actual</div>
                                            <CoinAmount v-bind="copperToGsc(expandedSessionDetail.current_value_copper)" size="text-sm" />
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Delta</div>
                                            <div class="text-sm font-bold" :class="RECOMMENDATION_CONFIG[expandedSessionDetail.recommendation]?.color">
                                                {{ expandedSessionDetail.delta_percent > 0 ? '+' : '' }}{{ expandedSessionDetail.delta_percent }}%
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="expandedSessionStats" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Nodos/hora</div>
                                            <div class="text-sm font-bold text-slate-100">{{ expandedSessionStats.nodes_per_hour }}</div>
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Oro/nodo</div>
                                            <div class="text-sm font-bold text-amber-400">{{ expandedSessionStats.avg_gold_per_node }}g</div>
                                        </div>
                                        <div class="rounded-lg border border-white/10 bg-white/3 p-3">
                                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Seg/nodo</div>
                                            <div class="text-sm font-bold text-slate-100">{{ expandedSessionStats.avg_seconds_per_node }}s</div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                        <div>
                                            <h4 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                                <PieChart class="size-3.5" /> Valor por tipo de material
                                            </h4>
                                            <apexchart v-if="expandedValueByItemChart.series.length" type="donut" height="220" :options="expandedValueByItemChart.options" :series="expandedValueByItemChart.series" />
                                            <p v-else class="py-6 text-center text-xs text-slate-500">Sin datos de valor.</p>
                                        </div>

                                        <div>
                                            <h4 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                                <BarChart3 class="size-3.5" /> Ritmo de nodos
                                            </h4>
                                            <apexchart v-if="expandedSessionStats?.pace_chart?.length" type="bar" height="220" :options="expandedPaceChartOptions" :series="expandedPaceChartSeries" />
                                            <p v-else class="py-6 text-center text-xs text-slate-500">Sin datos suficientes.</p>
                                        </div>

                                        <div>
                                            <h4 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                                <TrendingUp class="size-3.5" /> Oro por hora
                                            </h4>
                                            <apexchart v-if="expandedSessionStats?.gold_per_hour_chart?.length" type="line" height="220" :options="expandedGoldPerHourChartOptions" :series="expandedGoldPerHourChartSeries" />
                                            <p v-else class="py-6 text-center text-xs text-slate-500">Sin datos suficientes.</p>
                                        </div>
                                    </div>

                                    <div v-if="expandedSessionDetail.items?.length" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-amber-400">
                                                    <Diamond class="size-3.5 fill-amber-400" /> Oro
                                                </h4>
                                                <button type="button" @click="expandedGoldSortDesc = !expandedGoldSortDesc"
                                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                                    <ArrowUpDown class="size-3.5" />
                                                </button>
                                            </div>
                                            <div class="relative mb-2">
                                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                                <input v-model="expandedGoldSearch" type="text" placeholder="Buscar..."
                                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                            </div>
                                            <div v-if="!filteredExpandedGoldItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                            <div v-else class="app-scroll flex max-h-64 flex-col gap-1.5 overflow-y-auto pr-1">
                                                <div v-for="item in filteredExpandedGoldItems" :key="item.item_id"
                                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                                    </div>
                                                    <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                                    <span v-else class="text-xs text-slate-500">sin precio</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-300">
                                                    <Diamond class="size-3.5 fill-slate-300" /> Plata
                                                </h4>
                                                <button type="button" @click="expandedSilverSortDesc = !expandedSilverSortDesc"
                                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                                    <ArrowUpDown class="size-3.5" />
                                                </button>
                                            </div>
                                            <div class="relative mb-2">
                                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                                <input v-model="expandedSilverSearch" type="text" placeholder="Buscar..."
                                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                            </div>
                                            <div v-if="!filteredExpandedSilverItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                            <div v-else class="app-scroll flex max-h-64 flex-col gap-1.5 overflow-y-auto pr-1">
                                                <div v-for="item in filteredExpandedSilverItems" :key="item.item_id"
                                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                                    </div>
                                                    <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                                    <span v-else class="text-xs text-slate-500">sin precio</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                                    <Package class="size-3.5" /> Otros materiales
                                                </h4>
                                                <button type="button" @click="expandedOtherSortDesc = !expandedOtherSortDesc"
                                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                                    <ArrowUpDown class="size-3.5" />
                                                </button>
                                            </div>
                                            <div class="relative mb-2">
                                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                                <input v-model="expandedOtherSearch" type="text" placeholder="Buscar..."
                                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                            </div>
                                            <div v-if="!filteredExpandedOtherItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                            <div v-else class="app-scroll flex max-h-64 flex-col gap-1.5 overflow-y-auto pr-1">
                                                <div v-for="item in filteredExpandedOtherItems" :key="item.item_id"
                                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                                    </div>
                                                    <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                                    <span v-else class="text-xs text-slate-500">sin precio</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="py-2 text-center text-xs text-slate-500">
                                        Sin ítems registrados en esta sesión.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="setupTab === 'analytics'">
                    <div v-if="loadingAnalytics" class="py-6 text-center text-sm text-slate-500">Cargando...</div>

                    <div v-else-if="analyticsData">
                        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Nodos/hora promedio</div>
                                <div class="text-lg font-bold text-slate-100">{{ analyticsData.avg_nodes_per_hour }}</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Oro promedio/nodo</div>
                                <div class="text-lg font-bold text-amber-400">{{ analyticsData.avg_gold_per_node }}g</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Sesiones analizadas</div>
                                <div class="text-lg font-bold text-slate-100">{{ analyticsData.total_sessions }}</div>
                            </div>
                        </div>

                        <div v-if="analyticsData.item_mix?.length" class="mb-5">
                            <h3 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                <PieChart class="size-3.5" /> Valor por tipo de material (todo el historial)
                            </h3>
                            <apexchart v-if="analyticsValueByItemChart.series.length" type="donut" height="280" :options="analyticsValueByItemChart.options" :series="analyticsValueByItemChart.series" />
                            <p v-else class="py-6 text-center text-xs text-slate-500">Sin datos de valor.</p>
                        </div>

                        <div v-if="analyticsData.item_mix?.length" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-amber-400">
                                        <Diamond class="size-3.5 fill-amber-400" /> Oro
                                    </h3>
                                    <button type="button" @click="analyticsGoldSortDesc = !analyticsGoldSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="analyticsGoldSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredAnalyticsGoldItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredAnalyticsGoldItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-300">
                                        <Diamond class="size-3.5 fill-slate-300" /> Plata
                                    </h3>
                                    <button type="button" @click="analyticsSilverSortDesc = !analyticsSilverSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="analyticsSilverSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredAnalyticsSilverItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredAnalyticsSilverItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                        <Package class="size-3.5" /> Otros materiales
                                    </h3>
                                    <button type="button" @click="analyticsOtherSortDesc = !analyticsOtherSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="analyticsOtherSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredAnalyticsOtherItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredAnalyticsOtherItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="viewMode === 'active' || viewMode === 'summary'">
            <div v-if="viewMode === 'active'" class="flex gap-1 rounded-lg border border-white/10 bg-[#12142b] p-1 w-fit">
                <button type="button" @click="switchLiveTab('session')"
                    class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="liveTab === 'session' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'">
                    Sesión
                </button>
                <button type="button" @click="switchLiveTab('analytics')"
                    class="flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="liveTab === 'analytics' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'">
                    <LineChart class="size-3.5" />
                    Analíticas
                </button>
            </div>

            <template v-if="liveTab === 'session' || viewMode === 'summary'">
                <template v-if="viewMode === 'active'">
                    <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/5 p-6 text-center">
                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-widest text-emerald-400">Sesión activa</div>
                        <p v-if="liveValue?.total_nodes > 0" class="mb-1 text-sm font-semibold" :class="characterColorClass(activeSession?.character_key)">
                            {{ activeSession?.character_key }}
                        </p>
                        <p v-else class="mb-1 text-xs text-slate-500">Esperando /reload en el juego para detectar lo recolectado.</p>
                        <div class="mb-1 font-mono text-5xl font-bold text-slate-100">{{ formatDuration(elapsedSeconds) }}</div>
                        <p v-if="activeSession?.planned_duration_minutes" class="text-sm text-slate-500">
                            Objetivo: {{ activeSession.planned_duration_minutes }} min
                        </p>

                        <div v-if="liveValue" class="mt-4 flex items-center justify-center gap-2">
                            <span class="text-xs text-slate-500">Oro acumulado (estimado):</span>
                            <CoinAmount v-bind="copperToGsc(liveValue.current_value_copper)" size="text-lg" />
                        </div>

                        <div class="mt-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-500">
                            No es un feed en vivo — los precios se actualizan según el último sync de commodities disponible.
                            <span v-if="liveValue" :class="formatStaleness(liveValue.staleness_seconds).color">
                                Última actualización: {{ formatStaleness(liveValue.staleness_seconds).label }}
                            </span>
                        </div>

                        <div v-if="liveStats" class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Nodos/hora</div>
                                <div class="text-sm font-bold text-slate-100">{{ liveStats.nodes_per_hour }}</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Oro/nodo</div>
                                <div class="text-sm font-bold text-amber-400">{{ liveStats.avg_gold_per_node }}g</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Seg/nodo</div>
                                <div class="text-sm font-bold text-slate-100">{{ liveStats.avg_seconds_per_node }}s</div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-center gap-2">
                            <button type="button" @click="fetchLiveValue(); fetchLiveStats()" :disabled="liveRefreshing"
                                class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:border-indigo-400/60 hover:text-white disabled:cursor-not-allowed disabled:opacity-50">
                                <RefreshCw class="size-4" :class="{ 'animate-spin': liveRefreshing }" />
                                Actualizar
                            </button>

                            <button type="button" @click="stopSession"
                                class="flex items-center gap-2 rounded-lg border border-red-400/40 bg-red-500/10 px-5 py-2.5 text-sm font-semibold text-red-300 transition-colors hover:border-red-400/70 hover:bg-red-500/20">
                                <Square class="size-4" />
                                Detener sesión
                            </button>
                        </div>
                    </div>

                    <div v-if="liveValue?.items?.length" class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                        <h3 class="mb-3 text-sm font-bold text-slate-100">Ítems recolectados</h3>
                        <div class="app-scroll flex max-h-80 flex-col gap-2 overflow-y-auto pr-1">
                            <div v-for="item in liveValue.items" :key="item.item_id"
                                class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                <div class="relative shrink-0">
                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-8 rounded" />
                                    <Package v-else class="size-8 text-slate-600" />
                                    <img v-if="PROFESSION_ICON_URLS[item.profession]" :src="PROFESSION_ICON_URLS[item.profession]"
                                        class="absolute -top-1 -left-1 size-3.5 rounded-full border-2 border-[#12142b]" />
                                    <Diamond v-if="item.craft_quality" class="absolute -bottom-1 -right-1 size-3.5 rounded-full border-2 border-[#12142b] bg-[#12142b] p-0.5"
                                        :class="qualityIconClass(item.craft_quality)" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                </div>
                                <CoinAmount v-if="item.current_total_copper !== null" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                <span v-else class="text-xs text-slate-500">sin precio</span>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="finalValue">
                    <div class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                        <h2 class="text-sm font-bold text-slate-100">{{ sessionSummaryTitle }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Duración: {{ sessionSummaryDuration }}</p>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Valor al recoger</div>
                                <CoinAmount v-bind="copperToGsc(finalValue.baseline_value_copper)" size="text-lg" />
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Valor actual</div>
                                <CoinAmount v-bind="copperToGsc(finalValue.current_value_copper)" size="text-lg" />
                            </div>
                            <div class="rounded-lg border p-4" :class="RECOMMENDATION_CONFIG[finalValue.recommendation]?.bg">
                                <div class="text-[10px] uppercase tracking-wide" :class="RECOMMENDATION_CONFIG[finalValue.recommendation]?.color">Recomendación</div>
                                <div class="flex items-center gap-2 text-lg font-bold" :class="RECOMMENDATION_CONFIG[finalValue.recommendation]?.color">
                                    <component :is="RECOMMENDATION_CONFIG[finalValue.recommendation]?.icon" class="size-5" />
                                    {{ RECOMMENDATION_CONFIG[finalValue.recommendation]?.label }}
                                </div>
                                <p class="mt-1 text-xs" :class="RECOMMENDATION_CONFIG[finalValue.recommendation]?.color">
                                    {{ finalValue.delta_percent > 0 ? '+' : '' }}{{ finalValue.delta_percent }}%
                                </p>
                                <p class="mt-1 text-[10px]" :class="formatStaleness(finalValue.staleness_seconds).color">
                                    Datos de mercado: {{ formatStaleness(finalValue.staleness_seconds).label }}
                                </p>
                            </div>
                        </div>

                        <div v-if="sessionStats" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Nodos por hora</div>
                                <div class="text-lg font-bold text-slate-100">{{ sessionStats.nodes_per_hour }}</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Oro promedio/nodo</div>
                                <div class="text-lg font-bold text-amber-400">{{ sessionStats.avg_gold_per_node }}g</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Segundos promedio/nodo</div>
                                <div class="text-lg font-bold text-slate-100">{{ sessionStats.avg_seconds_per_node }}s</div>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div>
                                <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    <PieChart class="size-3.5" /> Valor por tipo de material
                                </h3>
                                <apexchart v-if="valueByItemChart.series.length" type="donut" height="260" :options="valueByItemChart.options" :series="valueByItemChart.series" />
                                <p v-else class="py-8 text-center text-xs text-slate-500">Sin datos de valor.</p>
                            </div>

                            <div>
                                <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    <BarChart3 class="size-3.5" /> Ritmo de nodos
                                </h3>
                                <apexchart v-if="sessionStats?.pace_chart?.length" type="bar" height="260" :options="paceChartOptions" :series="paceChartSeries" />
                                <p v-else class="py-8 text-center text-xs text-slate-500">Sin datos suficientes.</p>
                            </div>

                            <div>
                                <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    <TrendingUp class="size-3.5" /> Oro por hora
                                </h3>
                                <apexchart v-if="sessionStats?.gold_per_hour_chart?.length" type="line" height="260" :options="goldPerHourChartOptions" :series="goldPerHourChartSeries" />
                                <p v-else class="py-8 text-center text-xs text-slate-500">Sin datos suficientes.</p>
                            </div>
                        </div>

                        <div v-if="finalValue.items?.length" class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-amber-400">
                                        <Diamond class="size-3.5 fill-amber-400" /> Oro
                                    </h3>
                                    <button type="button" @click="goldItemSortDesc = !goldItemSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="goldItemSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredGoldItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredGoldItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                        <span v-else class="text-xs text-slate-500">sin precio</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-300">
                                        <Diamond class="size-3.5 fill-slate-300" /> Plata
                                    </h3>
                                    <button type="button" @click="silverItemSortDesc = !silverItemSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="silverItemSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredSilverItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredSilverItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                        <span v-else class="text-xs text-slate-500">sin precio</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                        <Package class="size-3.5" /> Otros materiales
                                    </h3>
                                    <button type="button" @click="otherItemSortDesc = !otherItemSortDesc"
                                        class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                        <ArrowUpDown class="size-3.5" />
                                    </button>
                                </div>
                                <div class="relative mb-2">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                    <input v-model="otherItemSearch" type="text" placeholder="Buscar..."
                                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                                </div>
                                <div v-if="!filteredOtherItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                                <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                    <div v-for="item in filteredOtherItems" :key="item.item_id"
                                        class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                        <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                        <Package v-else class="size-7 shrink-0 text-slate-600" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                        </div>
                                        <CoinAmount v-if="item.has_price_data" v-bind="copperToGsc(item.current_total_copper)" size="text-xs" />
                                        <span v-else class="text-xs text-slate-500">sin precio</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="acceptSummary"
                        class="flex items-center gap-2 self-start rounded-lg border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition-colors hover:border-emerald-400/70 hover:bg-emerald-500/20">
                        <Check class="size-4" />
                        Aceptar
                    </button>
                </template>
            </template>

            <div v-else-if="liveTab === 'analytics' && viewMode === 'active'" class="rounded-2xl border border-white/10 bg-[#12142b] p-5">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-slate-100">
                    <LineChart class="size-4 text-indigo-400" />
                    Analíticas de todas las sesiones
                </h2>

                <div v-if="loadingAnalytics" class="py-6 text-center text-sm text-slate-500">Cargando...</div>

                <div v-else-if="analyticsData">
                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Nodos/hora promedio</div>
                            <div class="text-lg font-bold text-slate-100">{{ analyticsData.avg_nodes_per_hour }}</div>
                        </div>
                        <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Oro promedio/nodo</div>
                            <div class="text-lg font-bold text-amber-400">{{ analyticsData.avg_gold_per_node }}g</div>
                        </div>
                        <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500">Sesiones analizadas</div>
                            <div class="text-lg font-bold text-slate-100">{{ analyticsData.total_sessions }}</div>
                        </div>
                    </div>

                    <div v-if="analyticsData.item_mix?.length" class="mb-5">
                        <h3 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                            <PieChart class="size-3.5" /> Valor por tipo de material (todo el historial)
                        </h3>
                        <apexchart v-if="analyticsValueByItemChart.series.length" type="donut" height="280" :options="analyticsValueByItemChart.options" :series="analyticsValueByItemChart.series" />
                        <p v-else class="py-6 text-center text-xs text-slate-500">Sin datos de valor.</p>
                    </div>

                    <div v-if="analyticsData.item_mix?.length" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-amber-400">
                                    <Diamond class="size-3.5 fill-amber-400" /> Oro
                                </h3>
                                <button type="button" @click="analyticsGoldSortDesc = !analyticsGoldSortDesc"
                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                    <ArrowUpDown class="size-3.5" />
                                </button>
                            </div>
                            <div class="relative mb-2">
                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                <input v-model="analyticsGoldSearch" type="text" placeholder="Buscar..."
                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div v-if="!filteredAnalyticsGoldItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                            <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                <div v-for="item in filteredAnalyticsGoldItems" :key="item.item_id"
                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-300">
                                    <Diamond class="size-3.5 fill-slate-300" /> Plata
                                </h3>
                                <button type="button" @click="analyticsSilverSortDesc = !analyticsSilverSortDesc"
                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                    <ArrowUpDown class="size-3.5" />
                                </button>
                            </div>
                            <div class="relative mb-2">
                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                <input v-model="analyticsSilverSearch" type="text" placeholder="Buscar..."
                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div v-if="!filteredAnalyticsSilverItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                            <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                <div v-for="item in filteredAnalyticsSilverItems" :key="item.item_id"
                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    <Package class="size-3.5" /> Otros materiales
                                </h3>
                                <button type="button" @click="analyticsOtherSortDesc = !analyticsOtherSortDesc"
                                    class="rounded p-1 text-slate-500 transition hover:bg-white/5 hover:text-slate-300">
                                    <ArrowUpDown class="size-3.5" />
                                </button>
                            </div>
                            <div class="relative mb-2">
                                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />
                                <input v-model="analyticsOtherSearch" type="text" placeholder="Buscar..."
                                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-xs text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div v-if="!filteredAnalyticsOtherItems.length" class="py-4 text-center text-xs text-slate-500">Sin ítems.</div>
                            <div v-else class="app-scroll flex max-h-72 flex-col gap-1.5 overflow-y-auto pr-1">
                                <div v-for="item in filteredAnalyticsOtherItems" :key="item.item_id"
                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-3 py-2">
                                    <img v-if="item.icon_url" :src="item.icon_url" class="size-7 shrink-0 rounded" />
                                    <Package v-else class="size-7 shrink-0 text-slate-600" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-200">{{ item.item_name }} <span class="text-slate-500">x{{ item.quantity }}</span></p>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ item.percent_of_total }}%</span>
                                </div>
                            </div>
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
    height: 6px;
}

.app-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.app-scroll::-webkit-scrollbar-thumb {
    background-color: #312e5c;
    border-radius: 9999px;
}
</style>