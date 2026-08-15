<script setup>
import { ref, computed } from 'vue'
import { ChevronDown } from '@lucide/vue'

const categories = {
  'All Items': [],
  'Armor': ['All', 'Head', 'Shoulders', 'Chest', 'Hands', 'Legs', 'Feet'],
  'Weapons': ['All', 'One-Hand', 'Two-Hand', 'Ranged', 'Off-hand'],
  'Consumables': ['All', 'Potions', 'Elixirs', 'Food & Drink'],
  'Reagents': ['All', 'Herbs', 'Ores', 'Cloth', 'Leather'],
  'Recipes': ['All', 'Alchemy', 'Inscription', 'Jewelcrafting'],
  'Miscellaneous': ['All', 'Mounts', 'Pets', 'Toys'],
}

const activeCategory = ref('Armor')
const activeSubcategory = ref('All')

const subcategories = computed(() => categories[activeCategory.value] ?? [])

function selectCategory(cat) {
  activeCategory.value = cat
  activeSubcategory.value = 'All'
}
</script>

<template>
  <div class="flex flex-col gap-2.5 w-full">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="cat in Object.keys(categories)"
        :key="cat"
        type="button"
        @click="selectCategory(cat)"
        class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
        :class="activeCategory === cat
          ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
          : 'border-white/10 text-slate-300 hover:border-white/20 hover:text-white'"
      >
        {{ cat }}
        <ChevronDown
          v-if="categories[cat].length"
          class="size-3.5 transition-transform duration-200"
          :class="{ 'rotate-180': activeCategory === cat }"
        />
      </button>
    </div>

    <div v-if="subcategories.length" class="flex flex-wrap gap-4 px-1">
      <button
        v-for="sub in subcategories"
        :key="sub"
        type="button"
        @click="activeSubcategory = sub"
        class="text-sm transition-colors"
        :class="activeSubcategory === sub
          ? 'font-semibold text-indigo-300'
          : 'text-slate-500 hover:text-slate-300'"
      >
        {{ sub }}
      </button>
    </div>
  </div>
</template>