<script setup>
import { ref, watch, onMounted } from 'vue'
import { RefreshCw, Loader2 } from '@lucide/vue'
import ItemCard from './ItemCard.vue'
import Pagination from './Pagination.vue'
import CommodityDetailModal from './CommodityDetailModal.vue'

const emit = defineEmits(['syncing'])

const search = ref('')
const commodities = ref({ data: [], links: [] })
const lastSyncedAt = ref(null)
const loading = ref(false)
const syncing = ref(false)
const selectedItemId = ref(null)
const mounted = ref(false)

async function fetchCommodities(url = null) {
    loading.value = true
    try {
        const target = url ?? `/api/commodities${search.value ? `?search=${encodeURIComponent(search.value)}` : ''}`
        const res = await fetch(target)
        const data = await res.json()
        commodities.value = data.commodities
        lastSyncedAt.value = data.lastSyncedAt
    } finally {
        loading.value = false
    }
}

async function syncNow() {
    if (syncing.value) return

    syncing.value = true
    emit('syncing', true)

    try {
        await fetch('/api/commodities/sync', { method: 'POST' })
        await fetchCommodities()
    } finally {
        syncing.value = false
        emit('syncing', false)
    }
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchCommodities(), 400)
})

onMounted(() => {
    mounted.value = true
    fetchCommodities()
})

function formatLastSync(dateStr) {
    if (!dateStr) return 'nunca'

    const diffMinutes = Math.floor((Date.now() - new Date(dateStr)) / 60000)
    if (diffMinutes < 1) return 'ahora'
    if (diffMinutes < 60) return `hace ${diffMinutes}m`

    const diffHours = Math.floor(diffMinutes / 60)
    if (diffHours < 24) return `hace ${diffHours}h`

    const diffDays = Math.floor(diffHours / 24)
    return `hace ${diffDays} día${diffDays === 1 ? '' : 's'}`
}

function openDetail(id) {
    selectedItemId.value = id
}

function closeDetail() {
    selectedItemId.value = null
}
</script>

<template>
    <div class="flex flex-col items-center gap-5 w-full">
        <div class="flex w-full items-center justify-between gap-4">
            <input
                v-model="search"
                type="text"
                placeholder="Buscar hierbas, minerales, tela..."
                class="w-full max-w-md rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60"
            />

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-xs text-slate-500">Últ. actualización: {{ formatLastSync(lastSyncedAt) }}</span>
                <button
                    type="button"
                    @click="syncNow"
                    :disabled="syncing"
                    class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-300 transition-colors hover:border-indigo-400/60 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <RefreshCw class="size-4" :class="{ 'animate-spin': syncing }" />
                    Sincronizar
                </button>
            </div>
        </div>

        <div v-if="syncing" class="flex flex-col items-center gap-3 py-16">
            <Loader2 class="size-8 animate-spin text-indigo-400" />
            <p class="text-sm text-slate-400">Sincronizando commodities con Blizzard...</p>
        </div>

        <template v-else>
            <div v-if="loading" class="py-10 text-center text-sm text-slate-500">Cargando...</div>

            <div v-else class="grid grid-cols-5 gap-2 w-full">
                <ItemCard
                    v-for="listing in commodities.data"
                    :key="listing.id"
                    :name="listing.name"
                    :subtitle="listing.subtitle"
                    :quality="listing.quality"
                    :icon="listing.icon_url"
                    :gold="listing.gold"
                    :silver="listing.silver"
                    :copper="listing.copper"
                    :listings="listing.listings"
                    :volume="listing.volume"
                    @click="openDetail(listing.id)"
                />
            </div>

            <Pagination
                v-if="commodities.links?.length"
                :links="commodities.links"
                mode="callback"
                @navigate="fetchCommodities"
            />
        </template>

        <CommodityDetailModal v-if="mounted" :item-id="selectedItemId" @close="closeDetail" />
    </div>
</template>