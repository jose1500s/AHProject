<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRealmSelection } from '../../Composables/useRealmSelection.js'
import RecipeCard from './RecipeCard.vue'
import AuctionBreakdownModal from './AuctionBreakdownModal.vue'

const props = defineProps({
    realms: { type: Array, default: () => [] },
})

const { realmSlug, hydrateFromStorage } = useRealmSelection(props.realms)

const professions = ref([])
const selectedProfessionId = ref(null)
const recipes = ref([])
const loading = ref(false)
const search = ref('')
const quantity = ref(1)
const customQuantity = ref('')
const showCustomInput = ref(false)
const ignoredItemIds = ref(new Set())
const mounted = ref(false)
const selectedItemId = ref(null)

const quantityOptions = [1, 5, 10, 50, 100]

async function fetchProfessions() {
    const res = await fetch('/api/professions')
    const data = await res.json()
    professions.value = data.professions
    if (!selectedProfessionId.value && data.professions.length) {
        selectedProfessionId.value = data.professions[0].blizzard_id
    }
}

async function fetchRecipes() {
    if (!selectedProfessionId.value || !realmSlug.value) return
    loading.value = true
    try {
        const params = new URLSearchParams({
            profession_id: selectedProfessionId.value,
            realm_slug: realmSlug.value,
            quantity: quantity.value,
        })
        const res = await fetch(`/api/crafts?${params}`)
        const data = await res.json()
        recipes.value = data.recipes
    } finally {
        loading.value = false
    }
}

function selectQuantity(q) {
    quantity.value = q
    showCustomInput.value = false
}

function applyCustomQuantity() {
    const n = parseInt(customQuantity.value, 10)
    if (n > 0) {
        quantity.value = n
    }
}

function toggleIgnore(itemId) {
    const next = new Set(ignoredItemIds.value)
    if (next.has(itemId)) {
        next.delete(itemId)
    } else {
        next.add(itemId)
    }
    ignoredItemIds.value = next
    if (typeof window !== 'undefined') {
        try {
            localStorage.setItem('craft_ignored_items', JSON.stringify([...next]))
        } catch { }
    }
}

function openItem(itemId) {
    if (!itemId) return
    selectedItemId.value = itemId
}

function closeModal() {
    selectedItemId.value = null
}

function reagentsCost(recipe) {
    return recipe.reagents.reduce((sum, r) => sum + r.unit_price_low_copper * r.quantity_needed, 0)
}

const filteredRecipes = computed(() => {
    let list = recipes.value

    if (search.value) {
        const term = search.value.toLowerCase()
        list = list.filter(r => r.name.toLowerCase().includes(term))
    }

    return [...list].sort((a, b) => {
        const profitA = a.sell_unit_low_copper * a.produces_quantity - reagentsCost(a)
        const profitB = b.sell_unit_low_copper * b.produces_quantity - reagentsCost(b)
        return profitB - profitA
    })
})

watch([selectedProfessionId, quantity, realmSlug], fetchRecipes)

onMounted(() => {
    mounted.value = true
    hydrateFromStorage()
    if (typeof window !== 'undefined') {
        try {
            const stored = JSON.parse(localStorage.getItem('craft_ignored_items') || '[]')
            ignoredItemIds.value = new Set(stored)
        } catch { }
    }
    fetchProfessions().then(fetchRecipes)
})
</script>

<template>
    <div class="flex flex-col gap-5 w-full">
        <div class="flex items-center gap-2 text-xs text-slate-500">
            <span class="uppercase tracking-wide">Fuente de mats</span>
        </div>

        <div class="flex flex-wrap gap-2">
            <button class="rounded-lg bg-indigo-500/20 px-3 py-1.5 text-sm text-indigo-300">Solo AH</button>
            <button class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-slate-500"
                disabled>Personaje</button>
            <button class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-slate-500"
                disabled>Warband</button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button v-for="p in professions" :key="p.blizzard_id" type="button"
                class="rounded-lg border px-3 py-1.5 text-sm transition" :class="selectedProfessionId === p.blizzard_id
                    ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                    : 'border-white/10 text-slate-400 hover:border-white/20'"
                @click="selectedProfessionId = p.blizzard_id">
                {{ p.name }}
            </button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-xs uppercase tracking-wide text-slate-500">Craftear</span>
                <button v-for="q in quantityOptions" :key="q" type="button"
                    class="rounded-lg border px-3 py-1 text-sm transition" :class="quantity === q && !showCustomInput
                        ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                        : 'border-white/10 text-slate-400 hover:border-white/20'" @click="selectQuantity(q)">
                    {{ q }}
                </button>
                <input v-if="showCustomInput" v-model="customQuantity" type="number" min="1" placeholder="Cantidad"
                    autofocus
                    class="w-28 rounded-lg border border-indigo-400/60 bg-white/5 px-3 py-1.5 text-sm text-slate-100 outline-none focus:border-indigo-400"
                    @keyup.enter="applyCustomQuantity" @blur="applyCustomQuantity" />
                <button v-else type="button"
                    class="rounded-lg border border-white/10 px-3 py-1 text-sm text-slate-400 hover:border-white/20"
                    @click="showCustomInput = true">
                    ...
                </button>
                <span class="text-xs text-slate-500">UNIDADES</span>
            </div>

            <input v-model="search" type="text" placeholder="Buscar receta..."
                class="w-56 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60" />
        </div>

        <div v-if="loading" class="py-10 text-center text-sm text-slate-500">Cargando...</div>

        <div v-else-if="filteredRecipes.length === 0" class="py-10 text-center text-sm text-slate-500">
            Receta no encontrada.
        </div>

        <div v-else class="flex flex-col gap-3">
            <RecipeCard
                v-for="recipe in filteredRecipes"
                :key="recipe.recipe_id"
                :recipe="recipe"
                :ignored-item-ids="ignoredItemIds"
                @toggle-ignore="toggleIgnore"
                @open-item="openItem"
            />
        </div>

        <AuctionBreakdownModal
            v-if="mounted"
            :item-id="selectedItemId"
            :realm-slug="realmSlug"
            :filter-ilvl="null"
            :has-ilvl-filter="false"
            :compare-realms="[]"
            @close="closeModal"
        />
    </div>
</template>