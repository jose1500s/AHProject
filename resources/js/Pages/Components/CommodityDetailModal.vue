<script setup>
import { ref, watch, computed } from 'vue'
import { X, Calculator } from '@lucide/vue'
import PriceHistoryChart from './PriceHistoryChart.vue'
import CoinAmount from './CoinAmount.vue'

const props = defineProps({
    itemId: { type: Number, default: null },
})
const emit = defineEmits(['close'])

const item = ref(null)
const loading = ref(false)

const ladder = ref([])
const ladderUnitPrice = ref(null)
const ladderTotalPaid = ref(null)
const ladderBreakevenPercent = ref(null)
const ladderLoading = ref(false)

const customGold = ref(null)
const customSilver = ref(null)
const customCopper = ref(null)
const customQuantity = ref(1)

let ladderTimeout = null

async function fetchLadder() {
    if (!props.itemId) return

    ladderLoading.value = true
    try {
        const params = new URLSearchParams()

        if (customGold.value !== null || customSilver.value !== null || customCopper.value !== null) {
            const total = (customGold.value || 0) * 10000 + (customSilver.value || 0) * 100 + (customCopper.value || 0)
            if (total > 0) params.append('unit_price', total)
        }

        if (customQuantity.value && customQuantity.value > 1) {
            params.append('quantity', customQuantity.value)
        }

        const res = await fetch(`/api/commodities/${props.itemId}/profit-ladder?${params}`)
        const data = await res.json()
        ladder.value = data.ladder ?? []
        ladderUnitPrice.value = data.unit_price
        ladderTotalPaid.value = data.total_paid
        ladderBreakevenPercent.value = data.breakeven_percent

        if (customGold.value === null && data.unit_price) {
            customGold.value = data.unit_price.gold
            customSilver.value = data.unit_price.silver
            customCopper.value = data.unit_price.copper
        }
    } finally {
        ladderLoading.value = false
    }
}

watch(() => props.itemId, async (id) => {
    if (!id) return

    loading.value = true
    item.value = null
    customGold.value = null
    customSilver.value = null
    customCopper.value = null
    customQuantity.value = 1

    try {
        const res = await fetch(`/commodities/${id}/item-detail`)
        const data = await res.json()
        item.value = data.item
    } finally {
        loading.value = false
    }

    fetchLadder()
})

watch([customGold, customSilver, customCopper, customQuantity], () => {
    clearTimeout(ladderTimeout)
    ladderTimeout = setTimeout(fetchLadder, 400)
})

const QUALITY_COLORS = {
    poor: 'text-slate-400',
    common: 'text-slate-100',
    uncommon: 'text-emerald-400',
    rare: 'text-sky-400',
    epic: 'text-purple-400',
    legendary: 'text-orange-400',
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="itemId" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" @click.self="emit('close')"></div>
        </Transition>

        <Transition name="slide">
            <aside v-if="itemId"
                class="fixed right-0 top-0 z-50 flex h-screen w-full max-w-xl flex-col border-l border-white/10 bg-[#141224]">
                <div class="flex items-center gap-3 border-b border-white/5 px-5 py-4 shrink-0">
                    <img v-if="item?.icon_url" :src="item.icon_url" class="size-9 rounded-md shrink-0" />
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-bold" :class="QUALITY_COLORS[item?.quality] ?? 'text-slate-100'">
                            {{ loading ? 'Cargando...' : (item?.name ?? 'Ítem') }}
                        </h2>
                        <p class="text-xs text-slate-500">Mercado regional (todos los reinos)</p>
                    </div>
                    <button type="button" @click="emit('close')" class="shrink-0 text-slate-500 hover:text-white">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <PriceHistoryChart
                        v-if="itemId"
                        :item-id="itemId"
                        :ilvl="null"
                        :realms="[{ slug: 'region', name: 'Región' }]"
                        endpoint-prefix="commodities"
                    />

                    <div class="border-t border-white/5 p-5">
                        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-indigo-400">
                            <Calculator class="size-3.5" />
                            Tabla de ganancia
                        </div>
                        <p class="mb-4 text-sm text-slate-500">
                            Si compras a este precio y el mercado sube, esto ganarías (comisión del 5% ya restada).
                        </p>

                        <div class="mb-3 flex items-end gap-2">
                            <div>
                                <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Oro</div>
                                <input v-model.number="customGold" type="number" min="0"
                                    class="w-24 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-amber-400 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div>
                                <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Plata</div>
                                <input v-model.number="customSilver" type="number" min="0" max="99"
                                    class="w-20 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-slate-300 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div>
                                <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Cobre</div>
                                <input v-model.number="customCopper" type="number" min="0" max="99"
                                    class="w-20 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-orange-400 outline-none focus:border-indigo-400/60" />
                            </div>
                            <div class="pb-2 text-xs text-slate-500">
                                por unidad
                            </div>
                        </div>

                        <div class="mb-4 flex items-end gap-3">
                            <div>
                                <div class="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Cantidad</div>
                                <input v-model.number="customQuantity" type="number" min="1"
                                    class="w-28 rounded-lg border border-white/10 bg-white/5 px-2 py-1.5 text-sm text-slate-100 outline-none focus:border-indigo-400/60" />
                            </div>

                            <div v-if="ladderTotalPaid" class="rounded-lg border border-white/10 bg-white/3 px-3 py-1.5">
                                <div class="text-[10px] uppercase tracking-wide text-slate-500">Total pagado</div>
                                <CoinAmount v-bind="ladderTotalPaid" size="text-sm" />
                            </div>
                        </div>

                        <div v-if="ladderBreakevenPercent" class="mb-3 text-xs text-slate-500">
                            Punto de equilibrio: necesitas que suba <span class="font-semibold text-slate-300">{{ ladderBreakevenPercent }}%</span> solo para cubrir la comisión del AH.
                        </div>

                        <div v-if="ladderLoading" class="py-4 text-center text-sm text-slate-500">Calculando...</div>

                        <div v-else-if="ladder.length" class="overflow-hidden rounded-lg border border-white/10">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-white/10 bg-white/3 text-left text-[10px] uppercase tracking-wide text-slate-500">
                                        <th class="px-3 py-2 font-semibold">Sube</th>
                                        <th class="px-3 py-2 font-semibold">Vendes a</th>
                                        <th class="px-3 py-2 text-right font-semibold">Ganancia neta total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in ladder" :key="row.step_gold" class="border-b border-white/5 last:border-b-0">
                                        <td class="px-3 py-2 text-slate-400">+{{ row.step_gold }}g</td>
                                        <td class="px-3 py-2">
                                            <CoinAmount v-bind="row.sell_price" size="text-xs" />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <span class="inline-flex items-center gap-1 font-semibold" :class="row.is_profit ? 'text-emerald-400' : 'text-red-400'">
                                                {{ row.is_profit ? '+' : '-' }}
                                                <CoinAmount v-bind="row.profit" size="text-xs" />
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.25s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>