<script setup>
import { ref, onMounted } from 'vue'
import Layout from './Layout.vue'
import ItemCard from './Components/ItemCard.vue';
import CategoryFilter from './Components/CategoryFilter.vue';
import Pagination from './Components/Pagination.vue';
import AuctionBreakdownModal from './Components/AuctionBreakdownModal.vue';
import RealmComparisonSection from './Components/RealmComparisonSection.vue';
import { useRealmSelection } from '../Composables/useRealmSelection.js'

const props = defineProps({
    realms: Array,
    auctions: Object,
    lastSyncedAt: String,
})

const { realmSlug } = useRealmSelection(props.realms)

const selectedItemId = ref(null)
const selectedItemIlvl = ref(null)
const hasIlvlFilter = ref(false)

function openFromCard(id) {
    selectedItemId.value = id
    hasIlvlFilter.value = false
    selectedItemIlvl.value = null
}

function openFromComparison({ itemId, ilvl }) {
    selectedItemId.value = itemId
    selectedItemIlvl.value = ilvl
    hasIlvlFilter.value = true
}

function closeModal() {
    selectedItemId.value = null
    selectedItemIlvl.value = null
    hasIlvlFilter.value = false
}

const mounted = ref(false)
onMounted(() => { mounted.value = true })
</script>

<template>
    <Layout :realms="realms" :last-synced-at="lastSyncedAt">
        <main class="flex flex-col mt-5 items-center justify-center gap-5 w-[90vw] mx-auto">
            <CategoryFilter />
            <div class="grid grid-cols-5 gap-2 w-full">
                <ItemCard v-for="listing in auctions.data" :key="listing.id" :name="listing.name"
                    :subtitle="listing.subtitle" :quality="listing.quality" :icon="listing.icon_url"
                    :gold="listing.gold" :silver="listing.silver" :copper="listing.copper" :listings="listing.listings"
                    :volume="listing.volume" @click="openFromCard(listing.id)" />
            </div>

            <Pagination :links="auctions.links" />

            <RealmComparisonSection :realms="realms" @select-item="openFromComparison" />
        </main>

        <AuctionBreakdownModal v-if="mounted" :item-id="selectedItemId" :realm-slug="realmSlug"
            :filter-ilvl="selectedItemIlvl" :has-ilvl-filter="hasIlvlFilter" @close="closeModal" />
    </Layout>
</template>