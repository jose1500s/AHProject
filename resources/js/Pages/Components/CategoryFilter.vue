<script setup>
import { ref, computed, watch } from 'vue'
import { ChevronDown } from '@lucide/vue'

const props = defineProps({
    category: {
        type: String,
        default: 'all',
    },
    subcategory: {
        type: String,
        default: 'all',
    },
})

const emit = defineEmits(['change'])

const categories = {
    all: {
        label: 'All Items',
        subcategories: [],
    },

    armor: {
        label: 'Armor',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'head', label: 'Head' },
            { value: 'shoulders', label: 'Shoulders' },
            { value: 'chest', label: 'Chest' },
            { value: 'hands', label: 'Hands' },
            { value: 'legs', label: 'Legs' },
            { value: 'feet', label: 'Feet' },
        ],
    },

    weapons: {
        label: 'Weapons',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'one-hand', label: 'One-Hand' },
            { value: 'two-hand', label: 'Two-Hand' },
            { value: 'ranged', label: 'Ranged' },
            { value: 'off-hand', label: 'Off-hand' },
        ],
    },

    consumables: {
        label: 'Consumables',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'potions', label: 'Potions' },
            { value: 'elixirs', label: 'Elixirs' },
            { value: 'food-drink', label: 'Food & Drink' },
        ],
    },

    reagents: {
        label: 'Reagents',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'herbs', label: 'Herbs' },
            { value: 'ores', label: 'Ores' },
            { value: 'cloth', label: 'Cloth' },
            { value: 'leather', label: 'Leather' },
        ],
    },

    recipes: {
        label: 'Recipes',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'alchemy', label: 'Alchemy' },
            { value: 'inscription', label: 'Inscription' },
            { value: 'jewelcrafting', label: 'Jewelcrafting' },
        ],
    },

    miscellaneous: {
        label: 'Miscellaneous',
        subcategories: [
            { value: 'all', label: 'All' },
            { value: 'mounts', label: 'Mounts' },
            { value: 'pets', label: 'Pets' },
            { value: 'other', label: 'Other' },
        ],
    },
}

const activeCategory = ref(props.category || 'all')
const activeSubcategory = ref(props.subcategory || 'all')

const subcategories = computed(() => {
    return categories[activeCategory.value]?.subcategories ?? []
})

watch(
    () => props.category,
    (value) => {
        activeCategory.value = value || 'all'
    }
)

watch(
    () => props.subcategory,
    (value) => {
        activeSubcategory.value = value || 'all'
    }
)

function selectCategory(category) {
    activeCategory.value = category
    activeSubcategory.value = 'all'

    emit('change', {
        category,
        subcategory: 'all',
    })
}

function selectSubcategory(subcategory) {
    activeSubcategory.value = subcategory

    emit('change', {
        category: activeCategory.value,
        subcategory,
    })
}
</script>

<template>
    <div class="flex w-full flex-col gap-2.5">

        <!-- CATEGORÍAS -->
        <div class="flex flex-wrap gap-2">

            <button
                v-for="(data, key) in categories"
                :key="key"
                type="button"
                @click="selectCategory(key)"
                class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                :class="
                    activeCategory === key
                        ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                        : 'border-white/10 text-slate-300 hover:border-white/20 hover:text-white'
                "
            >
                {{ data.label }}

                <ChevronDown
                    v-if="data.subcategories.length"
                    class="size-3.5 transition-transform duration-200"
                    :class="{
                        'rotate-180': activeCategory === key,
                    }"
                />
            </button>

        </div>

        <!-- SUBCATEGORÍAS -->
        <div
            v-if="subcategories.length"
            class="flex flex-wrap gap-4 px-1"
        >
            <button
                v-for="sub in subcategories"
                :key="sub.value"
                type="button"
                @click="selectSubcategory(sub.value)"
                class="text-sm transition-colors"
                :class="
                    activeSubcategory === sub.value
                        ? 'font-semibold text-indigo-300'
                        : 'text-slate-500 hover:text-slate-300'
                "
            >
                {{ sub.label }}
            </button>
        </div>

    </div>
</template>