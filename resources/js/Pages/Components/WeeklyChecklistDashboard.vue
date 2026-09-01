<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { ListChecks, Check, Shield, Sparkles, Wand2, Link2, Diamond, Calendar, X, Info, ListCheck  } from '@lucide/vue'
import CoinAmount from './CoinAmount.vue'

const props = defineProps({
    realmSlug: { type: String, default: null },
})

const characters = ref([])

const summary = ref(null)

const vault = ref(null)
const vaultCharacterKey = ref(null)
const vaultLoading = ref(false)

const allConcentration = ref([])
const concentrationLoading = ref(false)
const professionFilter = ref('Todas')

const craftEntries = ref([])
const craftSummary = ref(null)
const craftRange = ref('7d')
const craftLoading = ref(false)
const craftProfessionFilter = ref('Todas')

async function fetchCharacters() {
    const res = await fetch('/api/wow/characters')
    const data = await res.json()
    characters.value = data.characters ?? data
    if (!vaultCharacterKey.value && characters.value.length) {
        vaultCharacterKey.value = characters.value[0].key
    }
}

async function fetchSummary() {
    const res = await fetch('/api/wow/checklist/summary')
    const data = await res.json()
    summary.value = data
}

async function fetchAllConcentration() {
    concentrationLoading.value = true
    try {
        const res = await fetch('/api/wow/checklist/concentration')
        const data = await res.json()
        allConcentration.value = data.concentration ?? []
    } finally {
        concentrationLoading.value = false
    }
}

async function fetchVault() {
    if (!vaultCharacterKey.value) return
    vaultLoading.value = true
    try {
        const params = new URLSearchParams({ character: vaultCharacterKey.value })
        const res = await fetch(`/api/wow/checklist?${params}`)
        const data = await res.json()
        vault.value = data.vault
    } finally {
        vaultLoading.value = false
    }
}

async function fetchCraftHistory() {
    if (!props.realmSlug) return
    craftLoading.value = true
    try {
        const params = new URLSearchParams({
            realm_slug: props.realmSlug,
            range: craftRange.value,
        })
        const res = await fetch(`/api/wow/craft-history?${params}`)
        const data = await res.json()
        craftEntries.value = data.entries ?? []
        craftSummary.value = data.summary
    } finally {
        craftLoading.value = false
    }
}

async function deleteCraft(craftId) {
    craftEntries.value = craftEntries.value.filter(e => e.craft_id !== craftId)

    try {
        await fetch(`/api/wow/craft-history/${craftId}`, { method: 'DELETE' })
    } catch {
        fetchCraftHistory()
    }
}

const professionOptions = computed(() => {
    const names = [...new Set(allConcentration.value.map(c => c.profession))].sort()
    return ['Todas', ...names]
})

const filteredConcentration = computed(() => {
    if (professionFilter.value === 'Todas') return allConcentration.value
    return allConcentration.value.filter(c => c.profession === professionFilter.value)
})

const craftProfessionOptions = computed(() => {
    const names = [...new Set(craftEntries.value.map(e => e.profession).filter(Boolean))].sort()
    return ['Todas', ...names]
})

const filteredCraftEntries = computed(() => {
    if (craftProfessionFilter.value === 'Todas') return craftEntries.value
    return craftEntries.value.filter(e => e.profession === craftProfessionFilter.value)
})

const craftItemBreakdown = computed(() => {
    const counts = {}
    for (const e of filteredCraftEntries.value) {
        const key = e.item_name ?? `Ítem #${e.item_id}`
        if (!counts[key]) {
            counts[key] = { name: key, icon_url: e.icon_url, total: 0 }
        }
        counts[key].total += e.quantity
    }
    return Object.values(counts).sort((a, b) => b.total - a.total).slice(0, 8)
})

const VAULT_LABELS = {
    raid: { title: 'Bandas', subtitle: 'jefes derrotados', bg: '/imgs/bckBanda.jpg' },
    dungeon: { title: 'Calabozos', subtitle: 'completados', bg: '/imgs/bckCalabozo.jpg' },
    world: { title: 'Mundo', subtitle: 'actividades completadas', bg: '/imgs/bckMundoAbierto.jpg' },
}

const vaultCategories = computed(() => {
    if (!vault.value) return []
    return Object.entries(VAULT_LABELS).map(([key, label]) => ({
        key,
        ...label,
        slots: vault.value.categories[key] ?? [],
        unlockedCount: (vault.value.categories[key] ?? []).filter(s => s.unlocked).length,
    }))
})

const RANGE_OPTIONS = [
    { value: 'today', label: 'Hoy' },
    { value: '1d', label: '1 día' },
    { value: '7d', label: '7 días' },
    { value: '30d', label: '30 días' },
]

function copperToGsc(copper) {
    const c = Math.round(Math.abs(copper))
    return {
        gold: Math.floor(c / 10000),
        silver: Math.floor((c % 10000) / 100),
        copper: c % 100,
    }
}

function timeAgo(dateStr) {
    const diffMs = Date.now() - new Date(dateStr).getTime()
    const diffMinutes = Math.floor(diffMs / 60000)
    if (diffMinutes < 1) return 'ahora'
    if (diffMinutes < 60) return `hace ${diffMinutes}m`
    const diffHours = Math.floor(diffMinutes / 60)
    if (diffHours < 24) return `hace ${diffHours}h`
    const diffDays = Math.floor(diffHours / 24)
    return diffDays === 1 ? 'ayer' : `hace ${diffDays}d`
}

function timeAgoShort(dateStr) {
    if (!dateStr) return '—'
    const diffMs = Date.now() - new Date(dateStr).getTime()
    const diffMinutes = Math.floor(diffMs / 60000)
    if (diffMinutes < 1) return 'ahora'
    if (diffMinutes < 60) return `hace ${diffMinutes} min`
    const diffHours = Math.floor(diffMinutes / 60)
    if (diffHours < 24) return `hace ${diffHours}h`
    return `hace ${Math.floor(diffHours / 24)}d`
}

const currentWeekRange = computed(() => {
    const now = new Date()
    const day = now.getDay()
    const diffToMonday = day === 0 ? -6 : 1 - day
    const monday = new Date(now)
    monday.setDate(now.getDate() + diffToMonday)
    const sunday = new Date(monday)
    sunday.setDate(monday.getDate() + 6)

    const fmt = (d) => d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }).replace('.', '').toUpperCase()

    return `${fmt(monday)} — ${fmt(sunday)}`
})

watch(() => props.realmSlug, fetchCraftHistory)
watch(craftRange, fetchCraftHistory)
watch(vaultCharacterKey, fetchVault)

onMounted(async () => {
    await fetchCharacters()
    await fetchSummary()
    await fetchAllConcentration()
    await fetchVault()
    await fetchCraftHistory()
})
</script>

<template>
    <div class="flex flex-col gap-5 w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-100">
                    <ListChecks class="size-6 text-indigo-400" />
                    To Do List
                </h1>
                <p class="text-sm text-slate-500">Concentración, bóveda y objetivos semanales en un solo lugar.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-white/10 bg-[#141224]/70 backdrop-blur-xl p-5">
            <div class="grid flex-1 grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <div class="flex items-center gap-1.5">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Progreso vault</div>

                        <div class="group relative inline-block">
                            <button type="button"
                                class="flex size-4 items-center justify-center rounded-full text-slate-500 transition hover:text-indigo-300">
                                <Info class="size-3.5" />
                            </button>

                            <div
                                class="invisible absolute left-full top-1/2 z-20 ml-3 w-72 -translate-y-1/2 -translate-x-1 opacity-0 transition-all duration-200 ease-out group-hover:visible group-hover:translate-x-0 group-hover:opacity-100">
                                <div
                                    class="relative rounded-xl border border-white/10 bg-[#141224]/95 p-4 shadow-[0_0_30px_rgba(99,102,241,0.15)] backdrop-blur-md">
                                    <div class="mb-2 flex items-center gap-2">
                                        <div class="flex size-7 items-center justify-center rounded-full bg-indigo-500/20">
                                            <ListCheck  class="size-3.5 text-indigo-300" />
                                        </div>
                                        <h3 class="text-sm font-semibold text-slate-100">Gran Bóveda por personaje</h3>
                                    </div>

                                    <div class="app-scroll flex max-h-56 flex-col gap-1 overflow-y-auto pr-1">
                                        <div v-for="c in summary?.vault_progress?.characters ?? []" :key="c.character_key"
                                            class="flex items-center justify-between rounded-lg px-2 py-1.5 text-xs">
                                            <span class="text-slate-300">
                                                {{ c.character_name }} <span class="text-slate-500">· {{ c.realm }}</span>
                                            </span>
                                            <span class="flex items-center gap-1 font-semibold"
                                                :class="c.has_rewards ? 'text-emerald-400' : 'text-slate-500'">
                                                <Check v-if="c.has_rewards" class="size-3" />
                                                {{ c.has_rewards ? `${c.unlocked_count}/${c.total_slots}` : 'Sin recompensas' }}
                                            </span>
                                        </div>
                                        <div v-if="!(summary?.vault_progress?.characters?.length)"
                                            class="py-2 text-center text-xs text-slate-500">
                                            Sin datos aún
                                        </div>
                                    </div>

                                    <div
                                        class="absolute top-1/2 -left-1.5 size-3 -translate-y-1/2 rotate-45 border-b border-l border-white/10 bg-[#141224]">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-1 text-lg font-bold text-emerald-400">
                        {{ summary?.vault_progress?.with_rewards ?? 0 }}/{{ summary?.vault_progress?.total ?? 0 }}
                    </div>
                    <div class="text-xs text-slate-500">personajes con recompensas</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Tareas hechas</div>
                    <div class="mt-1 text-lg font-bold text-slate-100">
                        {{ summary?.tasks_completed?.done ?? 0 }}/{{ summary?.tasks_completed?.total ?? 0 }}
                    </div>
                    <div class="text-xs text-slate-500">total de la semana</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Personajes seguidos</div>
                    <div class="mt-1 text-lg font-bold text-slate-100">{{ summary?.characters_tracked ?? 0 }}</div>
                    <div class="text-xs text-slate-500">personajes</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Última actualización</div>
                    <div class="mt-1 flex items-center gap-1.5 text-lg font-bold text-slate-100">
                        <span class="size-2 rounded-full bg-emerald-400"></span>
                        {{ timeAgoShort(summary?.last_updated_at) }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/3 px-4 py-2">
                <Calendar class="size-4 text-indigo-400" />
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Semana actual</div>
                    <div class="text-sm font-semibold text-slate-100">{{ currentWeekRange }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#141224]/70 backdrop-blur-xl p-5">
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-indigo-400">
                <Sparkles class="size-3.5" />
                Recurso de personaje
            </div>

            <h2 class="text-lg font-bold text-slate-100">Concentración</h2>
            <p class="mb-4 text-sm text-slate-500">Controla el recurso disponible de cada personaje</p>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="p in professionOptions"
                    :key="p"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm transition"
                    :class="professionFilter === p
                        ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                        : 'border-white/10 text-slate-400 hover:border-white/20'"
                    @click="professionFilter = p"
                >
                    {{ p }}
                </button>
            </div>

            <div v-if="concentrationLoading" class="py-6 text-center text-sm text-slate-500">Cargando...</div>

            <div v-else-if="!filteredConcentration.length" class="py-6 text-center text-sm text-slate-500">
                Sin datos de Concentración. Abre el panel de una profesión en el juego al menos una vez.
            </div>

            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="c in filteredConcentration"
                    :key="c.character_key + c.profession"
                    class="rounded-lg border border-white/10 bg-white/3 p-4"
                >
                    <div class="mb-2 flex items-center gap-2">
                        <img
                            v-if="c.icon_url"
                            :src="c.icon_url"
                            class="size-8 rounded-full border border-white/10"
                        />
                        <div
                            v-else
                            class="flex size-8 items-center justify-center rounded-full bg-indigo-500/20 text-xs font-bold text-indigo-300"
                        >
                            {{ c.character_name[0] }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-100">{{ c.character_name }} · {{ c.realm }}</div>
                            <div class="truncate text-xs text-slate-500">{{ c.profession }}</div>
                        </div>
                    </div>

                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Concentración</div>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-xl font-bold text-slate-100">
                            {{ c.quantity }} <span class="text-sm font-normal text-slate-500">/ {{ c.max_quantity }}</span>
                        </span>
                        <span class="text-xs font-semibold" :class="c.is_max ? 'text-amber-400' : 'text-emerald-400'">
                            {{ c.is_max ? 'MÁXIMO' : 'RECARGANDO' }}
                        </span>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="c.is_max ? 'bg-amber-400' : 'bg-emerald-400'"
                            :style="{ width: c.percent + '%' }"
                        ></div>
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span class="flex items-center gap-1">
                            <Check v-if="c.is_max" class="size-3 text-emerald-400" />
                            {{ c.is_max ? 'Listo para gastar' : 'Recargando' }}
                        </span>
                        <span>{{ c.percent }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#141224]/70 backdrop-blur-xl p-5">
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-indigo-400">
                <Wand2 class="size-3.5" />
                Actividad de concentración
            </div>

            <h2 class="text-lg font-bold text-slate-100">En qué se gastó</h2>
            <p class="mb-4 text-sm text-slate-500">Historial de objetos creados de todos tus personajes</p>

            <div class="mb-3 flex flex-wrap gap-2">
                <button
                    v-for="opt in RANGE_OPTIONS"
                    :key="opt.value"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm transition"
                    :class="craftRange === opt.value
                        ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                        : 'border-white/10 text-slate-400 hover:border-white/20'"
                    @click="craftRange = opt.value"
                >
                    {{ opt.label }}
                </button>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="p in craftProfessionOptions"
                    :key="p"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm transition"
                    :class="craftProfessionFilter === p
                        ? 'border-indigo-400/60 bg-indigo-500/10 text-indigo-300'
                        : 'border-white/10 text-slate-400 hover:border-white/20'"
                    @click="craftProfessionFilter = p"
                >
                    {{ p }}
                </button>
            </div>

            <div v-if="craftLoading" class="py-6 text-center text-sm text-slate-500">Cargando...</div>

            <template v-else>
                <div v-if="craftSummary" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Concentración gastada</div>
                        <div class="mt-1 text-2xl font-bold text-amber-400">{{ craftSummary.concentration_spent }}</div>
                        <div class="text-xs text-slate-500">esta selección</div>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/3 p-4">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Valor de lo crafteado</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-400">
                            <CoinAmount v-bind="copperToGsc(craftSummary.revenue_copper)" size="text-2xl" />
                        </div>
                        <div class="text-xs text-slate-500">precio de venta estimado</div>
                    </div>
                </div>

                <div v-if="craftItemBreakdown.length" class="mb-4 flex flex-wrap gap-2">
                    <div
                        v-for="item in craftItemBreakdown"
                        :key="item.name"
                        class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/3 px-2.5 py-1.5"
                    >
                        <img v-if="item.icon_url" :src="item.icon_url" class="size-5 rounded" />
                        <span class="text-xs text-slate-300">{{ item.name }}</span>
                        <span class="rounded-full bg-indigo-500/20 px-1.5 text-xs font-bold text-indigo-300">x{{ item.total }}</span>
                    </div>
                </div>

                <div v-if="!filteredCraftEntries.length" class="py-6 text-center text-sm text-slate-500">
                    No hay crafteos registrados en este rango.
                </div>

                <div v-else class="app-scroll flex max-h-96 flex-col gap-2 overflow-y-auto pr-1">
                    <div
                        v-for="entry in filteredCraftEntries"
                        :key="entry.craft_id"
                        class="flex items-center justify-between rounded-lg border border-white/10 bg-white/3 p-3"
                    >
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <img
                                    v-if="entry.icon_url"
                                    :src="entry.icon_url"
                                    class="size-9 rounded-lg border border-white/10"
                                />
                                <div v-else class="flex size-9 items-center justify-center rounded-lg bg-white/5 text-slate-500">
                                    <Link2 class="size-4" />
                                </div>
                                <Diamond
                                    v-if="entry.crafting_quality >= 1"
                                    class="absolute -bottom-1 -right-1 size-4 rounded-full border-2 border-[#141224] bg-[#141224] p-0.5"
                                    :class="entry.crafting_quality >= 2 ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-slate-300'"
                                    :title="entry.crafting_quality >= 2 ? 'Calidad oro' : 'Calidad plata'"
                                />
                            </div>
                            <div>
                                <div class="font-semibold text-slate-100">
                                    {{ entry.item_name ?? `Ítem #${entry.item_id}` }}
                                    <span v-if="entry.quantity > 1" class="text-sm font-normal text-slate-500">x{{ entry.quantity }}</span>
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ entry.character_name }} · {{ timeAgo(entry.occurred_at) }}
                                    <span v-if="entry.concentration_spent > 0"> · Concentración usada: {{ entry.concentration_spent }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div v-if="entry.resolved" class="text-right">
                                <div class="text-[11px] uppercase tracking-wide text-slate-500">Precio actual</div>
                                <CoinAmount v-bind="copperToGsc(entry.revenue_copper)" class="justify-end" />
                            </div>
                            <div v-else class="text-xs text-slate-500">Sin receta asociada</div>

                            <button
                                type="button"
                                class="shrink-0 rounded-lg p-1.5 text-slate-600 transition hover:bg-red-500/10 hover:text-red-400"
                                title="Eliminar de la lista"
                                @click="deleteCraft(entry.craft_id)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="rounded-xl border border-white/10 bg-[#141224]/70 backdrop-blur-xl p-5">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-indigo-400">
                <Shield class="size-3.5" />
                Progreso semanal
            </div>

            <h2 class="text-lg font-bold text-slate-100">Gran bóveda</h2>
            <p class="mb-4 text-sm text-slate-500">Completa actividades para desbloquear tus recompensas</p>

            <div v-if="!vault" class="py-6 text-center text-sm text-slate-500">
                Sin datos de la Gran Bóveda todavía.
            </div>

            <template v-else>
                <div class="overflow-hidden rounded-xl border border-white/10">
                    <div
                        v-for="(cat, i) in vaultCategories"
                        :key="cat.key"
                        class="flex items-stretch"
                        :class="i > 0 ? 'border-t border-white/10' : ''"
                    >
                        <div
                            class="relative flex w-1/4 flex-col justify-center overflow-hidden bg-cover bg-center p-4"
                            :style="{ backgroundImage: `url(${cat.bg})` }"
                        >
                            <div class="absolute inset-0 bg-[#0b0d1f]/60"></div>
                            <div class="relative font-semibold text-white">{{ cat.title }}</div>
                            <div class="relative text-xs text-white/70">{{ cat.unlockedCount }} desbloqueadas</div>
                        </div>

                        <div class="flex w-3/4 items-center gap-3 bg-[#12142b] p-3">
                            <div
                                v-for="slot in cat.slots"
                                :key="slot.slot_index"
                                class="flex-1 rounded-lg p-3"
                                :class="slot.unlocked
                                    ? 'bg-emerald-500/10 ring-1 ring-emerald-400/30'
                                    : 'bg-white/3 ring-1 ring-white/10'"
                            >
                                <div class="flex items-center gap-1.5 text-xs" :class="slot.unlocked ? 'text-emerald-400' : 'text-slate-500'">
                                    <Check v-if="slot.unlocked" class="size-3" />
                                    <Shield v-else class="size-3" />
                                    <span>{{ cat.title }} {{ cat.subtitle }}</span>
                                </div>
                                <div class="mt-1 font-bold" :class="slot.unlocked ? 'text-emerald-400' : 'text-amber-400'">
                                    {{ slot.progress }} / {{ slot.threshold }}
                                </div>
                                <div v-if="slot.unlocked && slot.ilvl" class="mt-1 text-[11px] font-semibold text-indigo-300">
                                    ilvl {{ slot.ilvl }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-xs text-slate-500">
                    {{ vault.unlocked_count }} de {{ vault.total_slots }} recompensas desbloqueadas
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.app-scroll {
  scrollbar-width: thin;
  scrollbar-color: #312e5c #12142b;
}

.app-scroll::-webkit-scrollbar {
  width: 8px;
}

.app-scroll::-webkit-scrollbar-track {
  background: #12142b;
}

.app-scroll::-webkit-scrollbar-thumb {
  background-color: #312e5c;
  border-radius: 9999px;
  border: 2px solid #12142b;
}

.app-scroll::-webkit-scrollbar-thumb:hover {
  background-color: #4338ca;
}
</style>