<script setup>
import { ref, watch } from 'vue'
import { X } from '@lucide/vue'
import PriceHistoryChart from './PriceHistoryChart.vue'

const props = defineProps({
    itemId: { type: Number, default: null },
})
const emit = defineEmits(['close'])

const item = ref(null)
const loading = ref(false)

watch(() => props.itemId, async (id) => {
    if (!id) return

    loading.value = true
    item.value = null

    try {
        const res = await fetch(`/commodities/${id}/item-detail`)
        const data = await res.json()
        item.value = data.item
    } finally {
        loading.value = false
    }
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