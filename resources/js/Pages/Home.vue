<script setup>
import { ref, onMounted, watch } from 'vue'
import { Gavel, Boxes, Wallet } from '@lucide/vue'
import Layout from './Layout.vue'
import ItemCard from './Components/ItemCard.vue';
import CategoryFilter from './Components/CategoryFilter.vue';
import Pagination from './Components/Pagination.vue';
import AuctionBreakdownModal from './Components/AuctionBreakdownModal.vue';
import RealmComparisonSection from './Components/RealmComparisonSection.vue';
import CommoditiesGrid from './Components/CommoditiesGrid.vue';
import MyGoldDashboard from './Components/MyGoldDashboard.vue';
import { useRealmSelection } from '../Composables/useRealmSelection.js'

const props = defineProps({
    realms: Array,
    auctions: Object,
    lastSyncedAt: String,
})

const { realmSlug } = useRealmSelection(props.realms)

const VALID_TABS = ['auctions', 'commodities', 'mygold']
const activeTab = ref('auctions')
const commoditiesSyncing = ref(false)

const selectedItemId = ref(null)
const selectedItemIlvl = ref(null)
const hasIlvlFilter = ref(false)
const compareRealms = ref([])

function openFromCard(id) {
    selectedItemId.value = id
    hasIlvlFilter.value = false
    selectedItemIlvl.value = null
    compareRealms.value = []
}

function openFromComparison({ itemId, ilvl, realms }) {
    selectedItemId.value = itemId
    selectedItemIlvl.value = ilvl
    hasIlvlFilter.value = true
    compareRealms.value = realms
}

function closeModal() {
    selectedItemId.value = null
    selectedItemIlvl.value = null
    hasIlvlFilter.value = false
    compareRealms.value = []
}

function switchTab(tab) {
    if (commoditiesSyncing.value) return
    activeTab.value = tab
}

const mounted = ref(false)
onMounted(() => {
    mounted.value = true

    try {
        const stored = localStorage.getItem('active_tab')
        if (stored && VALID_TABS.includes(stored)) {
            activeTab.value = stored
        }
    } catch {}
})

watch(activeTab, (value) => {
    try { localStorage.setItem('active_tab', value) } catch {}
})
</script>

<template>
    <Layout :realms="realms" :last-synced-at="lastSyncedAt">
        <main class="flex flex-col mt-5 items-center justify-center gap-5 w-[90vw] mx-auto">
            <div class="flex items-center gap-1 self-start rounded-xl border border-white/10 bg-[#12142b] p-1">
                <button
                    type="button"
                    @click="switchTab('auctions')"
                    :disabled="commoditiesSyncing"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                    :class="activeTab === 'auctions' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'"
                >
                    <Gavel class="size-4" />
                    Subastas
                </button>
                <button
                    type="button"
                    @click="switchTab('commodities')"
                    :disabled="commoditiesSyncing"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                    :class="activeTab === 'commodities' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'"
                >
                    <Boxes class="size-4" />
                    Commodities
                </button>
                <button
                    type="button"
                    @click="switchTab('mygold')"
                    :disabled="commoditiesSyncing"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                    :class="activeTab === 'mygold' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'"
                >
                    <Wallet class="size-4" />
                    Mi Oro
                </button>
            </div>

            <div v-show="activeTab === 'auctions'" class="flex flex-col items-center gap-5 w-full">
                <CategoryFilter />
                <div class="grid grid-cols-5 gap-2 w-full">
                    <ItemCard
                        v-for="listing in auctions.data"
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
                        @click="openFromCard(listing.id)"
                    />
                </div>

                <Pagination :links="auctions.links" />

                <RealmComparisonSection :realms="realms" @select-item="openFromComparison" />
            </div>

            <div v-show="activeTab === 'commodities'" class="w-full">
                <CommoditiesGrid @syncing="commoditiesSyncing = $event" />
            </div>

             <div v-show="activeTab === 'mygold'" class="w-full">
                <MyGoldDashboard />
            </div>
        </main>

        <AuctionBreakdownModal
            v-if="mounted"
            :item-id="selectedItemId"
            :realm-slug="realmSlug"
            :filter-ilvl="selectedItemIlvl"
            :has-ilvl-filter="hasIlvlFilter"
            :compare-realms="compareRealms"
            @close="closeModal"
        />
    </Layout>
</template>