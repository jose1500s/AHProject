<script setup>
import { ref, watch } from 'vue'
import { X, ChevronDown } from '@lucide/vue'

const props = defineProps({
  itemId: { type: Number, default: null },
  realmSlug: String,
})
const emit = defineEmits(['close'])

const item = ref(null)
const realmName = ref('')
const groups = ref([])
const loading = ref(false)
const expandedIlvl = ref(null)

watch(() => props.itemId, async (id) => {
  if (!id) return

  loading.value = true
  item.value = null
  groups.value = []
  expandedIlvl.value = null

  try {
    const res = await fetch(`/items/${id}/auctions?realm=${props.realmSlug}`)
    const data = await res.json()
    item.value = data.item
    realmName.value = data.realm_name
    groups.value = data.rows
  } finally {
    loading.value = false
  }
})

function toggleGroup(ilvl) {
  expandedIlvl.value = expandedIlvl.value === ilvl ? null : ilvl
}

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
        class="fixed right-0 top-0 z-50 flex h-screen w-full max-w-md flex-col border-l border-white/10 bg-[#141224]">
        <!-- header -->
        <div class="flex items-center gap-3 border-b border-white/5 px-5 py-4 shrink-0">
          <img v-if="item?.icon_url" :src="item.icon_url" class="size-9 rounded-md shrink-0" />
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-sm font-bold" :class="QUALITY_COLORS[item?.quality] ?? 'text-slate-100'">
              {{ item?.name ?? 'Cargando...' }}
            </h2>
            <p class="text-xs text-slate-500">{{ realmName }}</p>
          </div>
          <button type="button" @click="emit('close')" class="shrink-0 text-slate-500 hover:text-white">
            <X class="size-4" />
          </button>
        </div>

        <!-- lista agrupada -->
        <div class="flex-1 overflow-y-auto">
          <div v-if="loading" class="px-5 py-6 text-center text-sm text-slate-500">Cargando...</div>
          <div v-else-if="!groups.length" class="px-5 py-6 text-center text-sm text-slate-500">Sin subastas activas
          </div>

          <div v-for="group in groups" :key="group.item_level ?? 'sin-nivel'" class="border-b border-white/5">
            <!-- fila colapsada: ilvl + precio más barato + total -->
            <button type="button" @click="toggleGroup(group.item_level)"
              class="flex w-full items-center justify-between px-5 py-3 text-left transition-colors hover:bg-white/5">
              <div class="flex items-center gap-3">
                <span class="w-10 text-xs font-semibold text-slate-400">{{ group.item_level ?? '—' }}</span>
                <span class="inline-flex items-center gap-1 text-sm">
                  <span v-if="group.cheapest.gold"
                    class="inline-flex items-center gap-0.5 font-semibold text-amber-400">
                    <span class="size-2 rounded-full bg-amber-400"></span>{{ group.cheapest.gold }}
                  </span>
                  <span v-if="group.cheapest.silver"
                    class="inline-flex items-center gap-0.5 font-semibold text-slate-300">
                    <span class="size-2 rounded-full bg-slate-300"></span>{{ group.cheapest.silver }}
                  </span>
                  <span v-if="group.cheapest.copper"
                    class="inline-flex items-center gap-0.5 font-semibold text-orange-600">
                    <span class="size-2 rounded-full bg-orange-700"></span>{{ group.cheapest.copper }}
                  </span>
                </span>
              </div>
              <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>{{ group.total_auctions }} auctions</span>
                <ChevronDown class="size-4 transition-transform duration-200"
                  :class="{ 'rotate-180': expandedIlvl === group.item_level }" />
              </div>
            </button>

            <!-- desplegado: todas las auctions de este ilvl, ordenadas de más barata a más cara -->
            <div v-if="expandedIlvl === group.item_level" class="bg-white/[0.02] px-5 py-2">
              <div v-for="(row, i) in group.rows" :key="i" class="flex items-center justify-between py-1.5 text-xs">
                <span class="inline-flex items-center gap-1">
                  <span v-if="row.gold" class="inline-flex items-center gap-0.5 text-amber-400">
                    <span class="size-1.5 rounded-full bg-amber-400"></span>{{ row.gold }}
                  </span>
                  <span v-if="row.silver" class="inline-flex items-center gap-0.5 text-slate-300">
                    <span class="size-1.5 rounded-full bg-slate-300"></span>{{ row.silver }}
                  </span>
                  <span v-if="row.copper" class="inline-flex items-center gap-0.5 text-orange-600">
                    <span class="size-1.5 rounded-full bg-orange-700"></span>{{ row.copper }}
                  </span>
                </span>
                <span class="text-slate-500">{{ row.auction_count }} · {{ row.total_quantity }} uds</span>
              </div>
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