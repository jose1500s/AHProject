<script setup>
import { ref, computed, watch } from 'vue'
import { TrendingUp, TrendingDown, Minus } from '@lucide/vue'

const props = defineProps({
    itemId: { type: Number, required: true },
    ilvl: { type: Number, default: null },
    realms: { type: Array, required: true },
    days: { type: Number, default: 30 },
    endpointPrefix: { type: String, default: 'items' },
})

const RANGES = [
    { label: '1D', days: 1 },
    { label: '1W', days: 7 },
    { label: '1M', days: 30 },
    { label: '3M', days: 90 },
]

const selectedRangeDays = ref(props.days)

const results = ref([])
const loading = ref(false)
const priceChartRef = ref(null)
const volumeChartRef = ref(null)
const hiddenRealms = ref(new Set())

const PALETTE = ['#818cf8', '#22d3ee', '#fbbf24', '#34d399', '#f472b6', '#a78bfa', '#fb923c', '#60a5fa']

function buildUrl(realmSlug) {
    if (props.endpointPrefix === 'commodities') {
        const params = new URLSearchParams({ days: selectedRangeDays.value })
        return `/commodities/${props.itemId}/price-history?${params}`
    }

    const params = new URLSearchParams({ realm: realmSlug, days: selectedRangeDays.value })
    if (props.ilvl !== null) params.append('ilvl', props.ilvl)
    return `/items/${props.itemId}/price-history?${params}`
}

function sortAndDedupeHistory(history) {
    const byTimestamp = new Map()

    for (const row of history) {
        byTimestamp.set(row.snapshot_at, row)
    }

    return [...byTimestamp.values()].sort(
        (a, b) => new Date(a.snapshot_at).getTime() - new Date(b.snapshot_at).getTime()
    )
}

async function fetchHistory() {
    if (!props.realms.length) {
        results.value = []
        return
    }

    loading.value = true
    try {
        const fetched = await Promise.all(props.realms.map(async (realm) => {
            const res = await fetch(buildUrl(realm.slug))
            const data = await res.json()

            return { slug: realm.slug, name: realm.name, history: sortAndDedupeHistory(data.history) }
        }))

        results.value = fetched
        hiddenRealms.value = new Set()
    } finally {
        loading.value = false
    }
}

watch(() => [props.itemId, props.ilvl, props.realms, props.endpointPrefix], fetchHistory, { immediate: true, deep: true })
watch(selectedRangeDays, fetchHistory)

const hasEnoughData = computed(() => results.value.some(r => r.history.length >= 1))

const colors = computed(() => results.value.map((_, i) => PALETTE[i % PALETTE.length]))

const priceSeries = computed(() => results.value.map(r => ({
    name: r.name,
    data: r.history.map(row => [new Date(row.snapshot_at).getTime(), row.price_gold]),
})))

function formatCategoryLabel(snapshotAt) {
    const date = new Date(snapshotAt)
    return date.toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const allTimestamps = computed(() => {
    const set = new Set()
    results.value.forEach(r => r.history.forEach(h => set.add(h.snapshot_at)))
    return [...set].sort((a, b) => new Date(a).getTime() - new Date(b).getTime())
})

const volumeCategories = computed(() => allTimestamps.value.map(formatCategoryLabel))

const volumeSeries = computed(() => results.value.map(r => {
    const byTimestamp = new Map(r.history.map(h => [h.snapshot_at, h.listings]))
    return {
        name: r.name,
        data: allTimestamps.value.map(ts => byTimestamp.get(ts) ?? 0),
    }
}))

const trends = computed(() => results.value.map((r, i) => {
    if (r.history.length < 2) return { name: r.name, color: colors.value[i], trend: null }
    const first = r.history[0].price_gold
    const last = r.history[r.history.length - 1].price_gold
    if (first === 0) return { name: r.name, color: colors.value[i], trend: null }
    return { name: r.name, color: colors.value[i], trend: ((last - first) / first) * 100 }
}))

const allPrices = computed(() => results.value.flatMap(r => r.history.map(h => h.price_gold)))
const globalMin = computed(() => allPrices.value.length ? Math.min(...allPrices.value) : null)
const globalMax = computed(() => allPrices.value.length ? Math.max(...allPrices.value) : null)

const yAxisRange = computed(() => {
    if (globalMin.value === null || globalMax.value === null) return { min: undefined, max: undefined }
    if (globalMin.value === globalMax.value) {
        return { min: globalMin.value * 0.8, max: globalMax.value * 1.2 }
    }
    const padding = (globalMax.value - globalMin.value) * 0.15
    return { min: Math.max(0, globalMin.value - padding), max: globalMax.value + padding }
})

function toggleRealm(name) {
    const next = new Set(hiddenRealms.value)
    next.has(name) ? next.delete(name) : next.add(name)
    hiddenRealms.value = next

    priceChartRef.value?.toggleSeries(name)
    volumeChartRef.value?.toggleSeries(name)
}

const priceChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
        fontFamily: 'inherit',
    },
    theme: { mode: 'dark' },
    colors: colors.value,
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2.5 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.35,
            opacityTo: 0.02,
            stops: [0, 90, 100],
        },
    },
    markers: {
        size: 0,
        strokeColors: '#12142b',
        strokeWidth: 2,
        hover: { size: 5 },
    },
    grid: {
        borderColor: 'rgba(255,255,255,0.06)',
        strokeDashArray: 4,
        padding: { left: 8, right: 8 },
    },
    legend: { show: false },
    annotations: {
        yaxis: [
            ...(globalMin.value !== null ? [{
                y: globalMin.value,
                borderColor: '#475569',
                strokeDashArray: 3,
                label: {
                    text: `Mín ${globalMin.value.toFixed(1)}g`,
                    style: { color: '#94a3b8', background: '#1e293b', fontSize: '10px' },
                    position: 'left',
                },
            }] : []),
            ...(globalMax.value !== null && globalMax.value !== globalMin.value ? [{
                y: globalMax.value,
                borderColor: '#475569',
                strokeDashArray: 3,
                label: {
                    text: `Máx ${globalMax.value.toFixed(1)}g`,
                    style: { color: '#94a3b8', background: '#1e293b', fontSize: '10px' },
                    position: 'left',
                },
            }] : []),
        ],
    },
    xaxis: {
        type: 'datetime',
        labels: { style: { colors: '#64748b', fontSize: '11px' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        min: yAxisRange.value.min,
        max: yAxisRange.value.max,
        labels: {
            style: { colors: '#64748b', fontSize: '11px' },
            formatter: (val) => val === undefined || val === null ? '' : `${val.toFixed(1)}g`,
        },
    },
    tooltip: {
        theme: 'dark',
        shared: true,
        intersect: false,
        x: { format: 'dd MMM HH:mm' },
        y: { formatter: (val) => val === undefined || val === null ? '—' : `${val.toFixed(2)} oro` },
    },
}))

const volumeChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        stacked: false,
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
        fontFamily: 'inherit',
    },
    theme: { mode: 'dark' },
    colors: colors.value,
    plotOptions: {
        bar: {
            columnWidth: '60%',
            barHeight: '100%',
            borderRadius: 3,
        },
    },
    dataLabels: { enabled: false },
    legend: { show: false },
    grid: {
        borderColor: 'rgba(255,255,255,0.06)',
        strokeDashArray: 4,
        padding: { left: 8, right: 8 },
    },
    xaxis: {
        type: 'category',
        categories: volumeCategories.value,
        labels: {
            style: { colors: '#64748b', fontSize: '10px' },
            rotate: -45,
            trim: true,
            hideOverlappingLabels: true,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tickAmount: Math.min(8, volumeCategories.value.length || 1),
    },
    yaxis: {
        labels: {
            style: { colors: '#64748b', fontSize: '10px' },
            formatter: (val) => val === undefined || val === null ? '' : Math.round(val),
        },
    },
    tooltip: {
        theme: 'dark',
        shared: false,
        intersect: true,
        y: { formatter: (val) => val === undefined || val === null ? '—' : `${val} auctions` },
    },
}))
</script>

<template>
    <div class="border-b border-white/5 px-5 py-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Historial de precio ({{ selectedRangeDays }}d)</h3>

            <div class="flex items-center gap-1 rounded-lg border border-white/10 p-0.5">
                <button
                    v-for="r in RANGES"
                    :key="r.label"
                    type="button"
                    @click="selectedRangeDays = r.days"
                    class="rounded px-2 py-1 text-[11px] font-semibold transition-colors"
                    :class="selectedRangeDays === r.days ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-500 hover:text-white'"
                >
                    {{ r.label }}
                </button>
            </div>
        </div>

        <div v-if="hasEnoughData" class="mb-2 flex flex-wrap items-center gap-3">
            <span v-for="t in trends" :key="t.name" class="flex items-center gap-1 text-xs font-semibold">
                <span class="size-1.5 rounded-full" :style="{ backgroundColor: t.color }"></span>
                <span class="text-slate-500">{{ t.name }}</span>
                <template v-if="t.trend !== null">
                    <TrendingUp v-if="t.trend > 0" class="size-3 text-emerald-400" />
                    <TrendingDown v-else-if="t.trend < 0" class="size-3 text-red-400" />
                    <Minus v-else class="size-3 text-slate-500" />
                    <span :class="t.trend > 0 ? 'text-emerald-400' : t.trend < 0 ? 'text-red-400' : 'text-slate-500'">
                        {{ t.trend > 0 ? '+' : '' }}{{ t.trend.toFixed(1) }}%
                    </span>
                </template>
            </span>
        </div>

        <div v-if="loading" class="flex h-40 items-center justify-center text-sm text-slate-500">
            Cargando...
        </div>
        <div v-else-if="!hasEnoughData" class="flex h-40 items-center justify-center text-center text-sm text-slate-500">
            Aún no hay historial para graficar
        </div>
        <template v-else>
            <div v-if="results.length > 1" class="mb-3 flex flex-wrap items-center gap-3">
                <button
                    v-for="(r, i) in results"
                    :key="r.name"
                    type="button"
                    @click="toggleRealm(r.name)"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-colors"
                    :class="hiddenRealms.has(r.name)
                        ? 'border-white/5 text-slate-600'
                        : 'border-white/10 text-slate-300 hover:border-white/20'"
                >
                    <span
                        class="size-2.5 rounded-full transition-opacity"
                        :style="{ backgroundColor: colors[i] }"
                        :class="hiddenRealms.has(r.name) ? 'opacity-30' : 'opacity-100'"
                    ></span>
                    {{ r.name }}
                </button>
            </div>

            <apexchart ref="priceChartRef" type="area" height="220" :options="priceChartOptions" :series="priceSeries" />

            <p class="mb-1 mt-4 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Volumen (auctions)</p>
            <apexchart ref="volumeChartRef" type="bar" height="180" :options="volumeChartOptions" :series="volumeSeries" />
        </template>
    </div>
</template>