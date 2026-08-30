<script setup>
import { ref, computed } from 'vue'
import { TrendingUp, TrendingDown } from '@lucide/vue'
import CoinAmount from './CoinAmount.vue'

const props = defineProps({
    recipe: { type: Object, required: true },
    ignoredItemIds: { type: Set, required: true },
})

const emit = defineEmits(['toggle-ignore', 'open-item'])

const useHighestQuality = ref(false)

const qualityColors = {
    poor: 'text-slate-400',
    common: 'text-slate-100',
    uncommon: 'text-green-400',
    rare: 'text-blue-400',
    epic: 'text-purple-400',
    legendary: 'text-orange-400',
}

function copperToGsc(copper) {
    const c = Math.max(0, Math.round(copper))
    return {
        gold: Math.floor(c / 10000),
        silver: Math.floor((c % 10000) / 100),
        copper: c % 100,
    }
}

function reagentUnitPrice(reagent) {
    return useHighestQuality.value ? reagent.unit_price_high_copper : reagent.unit_price_low_copper
}

const sellUnitCopper = computed(() => {
    return useHighestQuality.value ? props.recipe.sell_unit_high_copper : props.recipe.sell_unit_low_copper
})

const adjustedCostCopper = computed(() => {
    return props.recipe.reagents.reduce((sum, r) => {
        if (props.ignoredItemIds.has(r.item_id)) return sum
        return sum + reagentUnitPrice(r) * r.quantity_needed
    }, 0)
})

const sellTotalCopper = computed(() => sellUnitCopper.value * props.recipe.produces_quantity)

const adjustedProfitCopper = computed(() => sellTotalCopper.value - adjustedCostCopper.value)

const costGsc = computed(() => copperToGsc(adjustedCostCopper.value))
const sellGsc = computed(() => copperToGsc(sellTotalCopper.value))
const profitGsc = computed(() => copperToGsc(Math.abs(adjustedProfitCopper.value)))
const isProfitable = computed(() => adjustedProfitCopper.value >= 0)

function reagentGsc(reagent) {
    return copperToGsc(reagentUnitPrice(reagent) * reagent.quantity_needed)
}

function reagentHasHighQuality(reagent) {
    return reagent.item_id_high !== null
        && reagent.item_id_high !== undefined
        && reagent.unit_price_high_copper !== reagent.unit_price_low_copper
}
</script>

<template>
    <div class="rounded-xl border border-white/10 bg-[#141224]/90 backdrop-blur-xl p-4">
        <div class="flex items-start justify-between gap-4">
            <button
                type="button"
                class="flex items-center gap-3 rounded-lg text-left transition hover:bg-white/5 -m-1 p-1"
                @click="emit('open-item', recipe.produces_item_id)"
            >
                <img
                    v-if="recipe.produces_icon_url"
                    :src="recipe.produces_icon_url"
                    class="h-10 w-10 rounded-lg border border-white/10"
                />
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-100">{{ recipe.name }}</span>
                        <span v-if="recipe.quantity > 1" class="text-sm text-slate-400">x{{ recipe.quantity }}</span>
                    </div>
                    <div class="text-xs text-slate-500">
                        Produce x{{ recipe.produces_quantity }} ·
                        <span :class="qualityColors[recipe.produces_quality] ?? qualityColors.common">
                            {{ recipe.produces_quality }}
                        </span>
                    </div>
                </div>
            </button>

            <div class="flex shrink-0 items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-slate-500">Calidad máx.</span>
                    <label class="switch">
                        <input type="checkbox" v-model="useHighestQuality" />
                        <span class="slider"></span>
                    </label>
                </div>

                <div
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium"
                    :class="isProfitable ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'"
                >
                    <TrendingUp v-if="isProfitable" class="size-4" />
                    <TrendingDown v-else class="size-4" />
                    <span>PROFIT {{ isProfitable ? '+' : '-' }}</span>
                    <CoinAmount v-bind="profitGsc" />
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                v-for="reagent in recipe.reagents"
                :key="reagent.item_id"
                type="button"
                class="flex flex-col items-start gap-1 rounded-lg border px-2 py-1.5 text-xs transition"
                :class="ignoredItemIds.has(reagent.item_id)
                    ? 'border-white/5 bg-white/5 text-slate-600 line-through'
                    : 'border-white/10 bg-white/10 text-slate-300 hover:border-indigo-400/40'"
                @click="emit('toggle-ignore', reagent.item_id)"
            >
                <div class="flex items-center gap-1.5">
                    <img v-if="reagent.icon_url" :src="reagent.icon_url" class="h-4 w-4 rounded" />
                    <span v-if="reagent.quantity_needed > 1">x{{ reagent.quantity_needed }}</span>
                    <span>{{ reagent.name }}</span>
                    <span
                        v-if="reagentHasHighQuality(reagent)"
                        class="inline-block size-2 rounded-full"
                        :class="useHighestQuality ? 'bg-amber-400' : 'bg-slate-300'"
                        :title="useHighestQuality ? 'Calidad oro' : 'Calidad plata'"
                    ></span>
                </div>
                <CoinAmount v-bind="reagentGsc(reagent)" />
            </button>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-white/5 bg-white/5 p-3">
                <div class="text-[11px] uppercase tracking-wide text-slate-500">Costo mats</div>
                <CoinAmount v-bind="costGsc" class="mt-1" />
            </div>
            <div class="rounded-lg border border-white/5 bg-white/5 p-3">
                <div class="text-[11px] uppercase tracking-wide text-slate-500">Venta estim.</div>
                <CoinAmount v-bind="sellGsc" class="mt-1" />
            </div>
            <div
                class="rounded-lg p-3"
                :class="isProfitable ? 'bg-emerald-500/10' : 'bg-red-500/10'"
            >
                <div class="text-[11px] uppercase tracking-wide" :class="isProfitable ? 'text-emerald-400' : 'text-red-400'">
                    Profit {{ isProfitable ? '+' : '-' }}
                </div>
                <CoinAmount v-bind="profitGsc" class="mt-1" />
            </div>
            <div class="rounded-lg border border-white/5 bg-white/5 p-3">
                <div class="text-[11px] uppercase tracking-wide text-slate-500">Fuente</div>
                <div class="mt-1 text-sm text-slate-400">Auction House</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.switch {
    --secondary-container: #445369;
    --primary: #818cf8;
    font-size: 15px;
    position: relative;
    display: inline-block;
    width: 3.4em;
    height: 1.7em;
}

.switch input {
    display: none;
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #313033;
    transition: .2s;
    border-radius: 30px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 1.3em;
    width: 1.3em;
    border-radius: 20px;
    left: 0.2em;
    bottom: 0.2em;
    background-color: #aeaaae;
    transition: .4s;
}

input:checked + .slider::before {
    background-color: var(--primary);
}

input:checked + .slider {
    background-color: var(--secondary-container);
}

input:focus + .slider {
    box-shadow: 0 0 1px var(--secondary-container);
}

input:checked + .slider:before {
    transform: translateX(1.7em);
}
</style>