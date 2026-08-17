<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { Loader2 } from '@lucide/vue'
import InputText from './Components/InputText.vue';
import CustomSelect from './Components/CustomSelect.vue';
import TimeAgoBadge from './Components/TimeAgoBadge.vue';
import RefreshButton from './Components/RefreshButton.vue';
import { useRealmSelection } from '../Composables/useRealmSelection.js'

const props = defineProps({
  realms: Array,
  lastSyncedAt: String,
})

const timeAgoLabel = computed(() => {
  if (!props.lastSyncedAt) return '—'
  const diffMinutes = Math.floor((Date.now() - new Date(props.lastSyncedAt)) / 60000)
  if (diffMinutes < 1) return 'now'
  if (diffMinutes < 60) return `${diffMinutes}m ago`
  return `${Math.floor(diffMinutes / 60)}h ago`
})

const { region, realm, realmSlug, hydrateFromStorage } = useRealmSelection(props.realms)

const search = ref('')
const isLoadingAuctions = ref(false)

function fetchAuctions() {
  if (!realmSlug.value) return

  router.get(window.location.pathname, { realm: realmSlug.value, search: search.value || undefined }, {
    only: ['auctions'],
    preserveState: true,
    preserveScroll: true,
    onStart: () => { isLoadingAuctions.value = true },
    onFinish: () => { isLoadingAuctions.value = false },
  })
}

let searchTimeout = null
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchAuctions, 400)
})

onMounted(() => {
  hydrateFromStorage()
  fetchAuctions()
  watch(realmSlug, fetchAuctions)
})

function onRefresh() {
  fetchAuctions()
}
</script>

<template>
  <div v-if="isLoadingAuctions"
    class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-[#0b0d1f]/80 backdrop-blur-sm">
    <Loader2 class="size-8 animate-spin text-indigo-400" />
    <p class="text-sm text-slate-400">Descargando datos del Auction House...</p>
  </div>

  <div class="w-full" id="navbar">
    <nav class="flex items-center justify-between gap-5 w-[90vw] h-20 mx-auto">
      <div class="w-1/6 flex h-full gap-2.5 items-center">
        <div>
          <img src="../../../public/imgs/Logo.svg">
        </div>
      </div>
      <div class="w-1/2 h-full flex items-center">
        <InputText v-model="search" type="text" placeholder="Buscar por objeto..." width="75%" />
      </div>
      <div class="w-[30%] h-full flex items-center">
        <div class="flex gap-3">
          <CustomSelect v-model="region" :options="['US']" label="Region" />
          <CustomSelect v-model="realm" :options="realms.map(r => r.name)" label="Realm" />
        </div>
        <div class="flex items-center gap-3 mt-5 ml-4">
          <TimeAgoBadge :label="timeAgoLabel" />
          <RefreshButton @click="onRefresh" />
        </div>
      </div>
    </nav>
  </div>
  <slot></slot>
</template>

<style scoped>
#navbar {
  border-bottom: 0.5px solid #f8fafc48;
}
</style>