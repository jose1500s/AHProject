<script setup>
import { ref, onMounted } from 'vue'
import { Gavel, Boxes } from '@lucide/vue'
import Layout from './Layout.vue'
import ItemCard from './Components/ItemCard.vue';
import CategoryFilter from './Components/CategoryFilter.vue';
import Pagination from './Components/Pagination.vue';
import AuctionBreakdownModal from './Components/AuctionBreakdownModal.vue';
import RealmComparisonSection from './Components/RealmComparisonSection.vue';
import CommoditiesGrid from './Components/CommoditiesGrid.vue';
import { useRealmSelection } from '../Composables/useRealmSelection.js'

const props = defineProps({
    realms: Array,
    auctions: Object,
    lastSyncedAt: String,
})

const { realmSlug } = useRealmSelection(props.realms)

const activeTab = ref('auctions')

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

const mounted = ref(false)
onMounted(() => { mounted.value = true })
</script>

<template>
    <Layout :realms="realms" :last-synced-at="lastSyncedAt">
        <main class="flex flex-col mt-5 items-center justify-center gap-5 w-[90vw] mx-auto">
            <div class="flex items-center gap-1 self-start rounded-xl border border-white/10 bg-[#12142b] p-1">
                <button
                    type="button"
                    @click="activeTab = 'auctions'"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'auctions' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'"
                >
                    <Gavel class="size-4" />
                    Subastas
                </button>
                <button
                    type="button"
                    @click="activeTab = 'commodities'"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'commodities' ? 'bg-indigo-500/20 text-indigo-300' : 'text-slate-400 hover:text-white'"
                >
                    <Boxes class="size-4" />
                    Commodities
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
                <CommoditiesGrid />
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