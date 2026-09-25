<script setup>
import { ref, watch, onMounted, computed, onBeforeUnmount } from 'vue'
import {
    GitCompare,
    Sparkles,
    X,
    Star,
    Search,
    ArrowLeftRight,
    RefreshCw,
    Filter,
    Loader2,
    Folder,
    FolderPlus,
    Plus,
    Trash2,
    Pencil,
    Check,
    MoreVertical,
} from '@lucide/vue'

import ItemPicker from './ItemPicker.vue'
import RealmMultiSelect from './RealmMultiSelect.vue'
import RefreshButton from './RefreshButton.vue'
import { useRealmSelection } from '../../Composables/useRealmSelection.js'

const props = defineProps({
    realms: { type: Array, required: true },
})

const emit = defineEmits(['select-item'])

const STORAGE_KEY = 'compare_items'
const FAVORITES_KEY = 'compare_favorites'
const GROUPS_STORAGE_KEY = 'compare_item_groups'


/*
|--------------------------------------------------------------------------
| Comparación
|--------------------------------------------------------------------------
*/

const selectedItems = ref([])
const selectedRealms = ref([])
const rows = ref([])
const lastSynced = ref({})
const loading = ref(false)
const openCells = ref(new Set())
const userTouchedRealms = ref(false)
const favorites = ref(new Set())
const tableSearch = ref('')
const selectedRowKeys = ref(new Set())

/*
|--------------------------------------------------------------------------
| Grupos
|--------------------------------------------------------------------------
*/

const groups = ref([])
const activeGroupId = ref('')
const groupMenuOpen = ref(false)
const itemGroupMenu = ref(null)
const showCreateGroup = ref(false)
const newGroupName = ref('')
const editingGroupId = ref(null)
const editingGroupName = ref('')

/*
|--------------------------------------------------------------------------
| Arbitraje entre reinos seleccionados
|--------------------------------------------------------------------------
*/

const selectedRealmsArbitrageSearch = ref('')
const selectedRealmsBuyFilter = ref('')
const selectedRealmsSellFilter = ref('')
const selectedRealmsQualityFilter = ref('')
const selectedRealmsIlvlFilter = ref('')

const selectedRealmsBuyMenuOpen = ref(false)
const selectedRealmsSellMenuOpen = ref(false)
const selectedRealmsQualityMenuOpen = ref(false)
const selectedRealmsIlvlMenuOpen = ref(false)

const { realm } = useRealmSelection(props.realms)

/*
|--------------------------------------------------------------------------
| Realm selection
|--------------------------------------------------------------------------
*/

watch(
    realm,
    (newRealmName) => {
        if (!newRealmName || userTouchedRealms.value) return

        const slug = props.realms.find(
            r => r.name === newRealmName
        )?.slug

        if (slug) {
            selectedRealms.value = [slug]
        }
    },
    { immediate: true }
)

function onRealmsChange(newVal) {
    userTouchedRealms.value = true
    selectedRealms.value = newVal
}

function removeRealm(slug) {
    userTouchedRealms.value = true
    selectedRealms.value = selectedRealms.value.filter(
        s => s !== slug
    )
}

/*
|--------------------------------------------------------------------------
| LocalStorage
|--------------------------------------------------------------------------
*/

onMounted(() => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY)

        if (stored) {
            selectedItems.value = JSON.parse(stored)
        }
    } catch { }

    try {
        const storedFavorites = localStorage.getItem(FAVORITES_KEY)

        if (storedFavorites) {
            favorites.value = new Set(
                JSON.parse(storedFavorites)
            )
        }
    } catch { }

    try {
        const storedGroups = localStorage.getItem(
            GROUPS_STORAGE_KEY
        )

        if (storedGroups) {
            const parsedGroups = JSON.parse(storedGroups)

            if (Array.isArray(parsedGroups)) {
                groups.value = parsedGroups
            }
        }
    } catch { }
})

watch(
    selectedItems,
    (val) => {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(val)
            )
        } catch { }
    },
    { deep: true }
)

watch(
    favorites,
    (val) => {
        try {
            localStorage.setItem(
                FAVORITES_KEY,
                JSON.stringify([...val])
            )
        } catch { }
    },
    { deep: true }
)

watch(
    groups,
    (val) => {
        try {
            localStorage.setItem(
                GROUPS_STORAGE_KEY,
                JSON.stringify(val)
            )
        } catch { }
    },
    { deep: true }
)

/*
|--------------------------------------------------------------------------
| Close menus when clicking outside
|--------------------------------------------------------------------------
*/

function handleDocumentClick(event) {
    const target = event.target

    if (
        !target.closest('.groups-menu-container') &&
        !target.closest('.item-group-menu-container')
    ) {
        groupMenuOpen.value = false
        itemGroupMenu.value = null
    }
}

onMounted(() => {
    document.addEventListener(
        'click',
        handleDocumentClick
    )
})

onBeforeUnmount(() => {
    document.removeEventListener(
        'click',
        handleDocumentClick
    )
})

/*
|--------------------------------------------------------------------------
| Fetch comparison
|--------------------------------------------------------------------------
*/

let debounceTimeout = null

watch(
    [selectedItems, selectedRealms],
    () => {
        clearTimeout(debounceTimeout)

        if (
            !selectedItems.value.length ||
            !selectedRealms.value.length
        ) {
            rows.value = []
            return
        }

        debounceTimeout = setTimeout(
            () => fetchComparison(),
            300
        )
    },
    { deep: true }
)

async function fetchComparison(force = false) {
    loading.value = true

    const previousSerialized = force
        ? JSON.stringify(
            rows.value.map(r => r.prices)
        )
        : null

    if (force) {
        rows.value = []
    }

    try {
        const params = new URLSearchParams()

        params.append(
            'items',
            JSON.stringify(
                selectedItems.value.map(i => ({
                    item_id: i.id,
                    ilvl: i.ilvl,
                }))
            )
        )

        selectedRealms.value.forEach(slug => {
            params.append(
                'realm_slugs[]',
                slug
            )
        })

        if (force) {
            params.append('force', '1')
        }

        const res = await fetch(
            `/api/realm-comparison?${params}`
        )

        const data = await res.json()

        rows.value = data.items
        lastSynced.value = data.last_synced

        if (force) {
            const newSerialized = JSON.stringify(
                data.items.map(r => r.prices)
            )

            if (
                previousSerialized !== null &&
                previousSerialized !== newSerialized
            ) {
                push.success({
                    title: 'Precios actualizados',
                    message:
                        'Se detectaron cambios de precio en la comparación.',
                })
            } else {
                push.info({
                    title: 'Sin cambios',
                    message:
                        'Los precios siguen igual desde la última actualización.',
                })
            }
        }
    } finally {
        loading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/

function rowSelectionKey(row) {
    return `${row.item_id}-${row.ilvl ?? 'no-ilvl'}`
}

function isRowSelected(row) {
    return selectedRowKeys.value.has(rowSelectionKey(row))
}

function toggleRowSelection(row) {
    const next = new Set(selectedRowKeys.value)
    const key = rowSelectionKey(row)

    next.has(key)
        ? next.delete(key)
        : next.add(key)

    selectedRowKeys.value = next
}

function removeSelectedItems() {
    if (!selectedRowKeys.value.size) return

    const keysToRemove = new Set(selectedRowKeys.value)

    selectedItems.value = selectedItems.value.filter(item => {
        const key = `${item.id}-${item.ilvl ?? 'no-ilvl'}`
        return !keysToRemove.has(key)
    })

    groups.value = groups.value.map(group => ({
        ...group,
        items: group.items.filter(key => !keysToRemove.has(key)),
    }))

    selectedRowKeys.value = new Set()
}

function cellKey(row, slug) {
    return `${row.item_id}-${row.ilvl}-${slug}`
}

function toggleCell(key) {
    const next = new Set(
        openCells.value
    )

    next.has(key)
        ? next.delete(key)
        : next.add(key)

    openCells.value = next
}

function favoriteKey(row) {
    return `${row.item_id}-${row.ilvl}`
}

function toggleFavorite(row) {
    const key = favoriteKey(row)

    const next = new Set(
        favorites.value
    )

    next.has(key)
        ? next.delete(key)
        : next.add(key)

    favorites.value = next
}

function openItemDetail(row) {
    emit('select-item', {
        itemId: row.item_id,
        ilvl: row.ilvl,
        realms: selectedRealms.value.map(slug => ({
            slug,
            name:
                props.realms.find(
                    r => r.slug === slug
                )?.name ?? slug,
        })),
    })
}

function realmName(slug) {
    return (
        props.realms.find(
            r => r.slug === slug
        )?.name ?? slug
    )
}

/*
|--------------------------------------------------------------------------
| Groups
|--------------------------------------------------------------------------
*/

function groupItemKey(row) {
    return `${row.item_id}-${row.ilvl}`
}

function createGroup() {
    const name = newGroupName.value.trim()

    if (!name) return

    const group = {
        id:
            `group-${Date.now()}-${Math.random()
                .toString(36)
                .slice(2, 8)}`,
        name,
        items: [],
    }

    groups.value.push(group)

    activeGroupId.value = group.id

    newGroupName.value = ''
    showCreateGroup.value = false
}

function deleteGroup(groupId) {
    const group = groups.value.find(
        g => g.id === groupId
    )

    if (!group) return

    if (
        !confirm(
            `¿Eliminar el grupo "${group.name}"?`
        )
    ) {
        return
    }

    groups.value =
        groups.value.filter(
            g => g.id !== groupId
        )

    if (
        activeGroupId.value === groupId
    ) {
        activeGroupId.value = ''
    }

    if (
        editingGroupId.value === groupId
    ) {
        editingGroupId.value = null
    }
}

function startEditingGroup(group) {
    editingGroupId.value = group.id
    editingGroupName.value = group.name
}

function saveEditingGroup(group) {
    const name =
        editingGroupName.value.trim()

    if (!name) return

    group.name = name

    editingGroupId.value = null
    editingGroupName.value = ''
}

function cancelEditingGroup() {
    editingGroupId.value = null
    editingGroupName.value = ''
}

function toggleItemGroupMenu(row) {
    const key = groupItemKey(row)

    if (itemGroupMenu.value === key) {
        itemGroupMenu.value = null
        return
    }

    itemGroupMenu.value = key
}

function isItemInGroup(row, group) {
    return group.items.includes(
        groupItemKey(row)
    )
}

function toggleItemInGroup(row, group) {
    const key = groupItemKey(row)

    if (isItemInGroup(row, group)) {
        group.items =
            group.items.filter(
                itemKey => itemKey !== key
            )
    } else {
        group.items.push(key)
    }
}

function createGroupFromItem() {
    showCreateGroup.value = true
    groupMenuOpen.value = true
}

function clearGroupFilter() {
    activeGroupId.value = ''
}

function selectGroup(groupId) {
    activeGroupId.value = groupId
    groupMenuOpen.value = false
}

function isItemInAnyGroup(row) {
    const key = groupItemKey(row)

    return groups.value.some(
        group =>
            group.items.includes(key)
    )
}

function groupItemCount(group) {
    return group.items.length
}

const ungroupedItemCount = computed(() => {
    return rows.value.filter(
        row => !isItemInAnyGroup(row)
    ).length
})

/*
|--------------------------------------------------------------------------
| Sorted / filtered rows
|--------------------------------------------------------------------------
*/

const sortedRows = computed(() => {
    return [...rows.value].sort((a, b) => {
        const aFav = favorites.value.has(
            favoriteKey(a)
        )

        const bFav = favorites.value.has(
            favoriteKey(b)
        )

        if (aFav === bFav) return 0

        return aFav ? -1 : 1
    })
})

function normalizeText(str) {
    return String(str ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(
            /[\u0300-\u036f]/g,
            ''
        )
}

const filteredRows = computed(() => {
    let list = sortedRows.value

    /*
    |--------------------------------------------------------------------------
    | Group filter
    |--------------------------------------------------------------------------
    */

    if (activeGroupId.value === '__ungrouped__') {
        list = list.filter(
            row => !isItemInAnyGroup(row)
        )
    } else if (activeGroupId.value) {
        const group = groups.value.find(
            g =>
                g.id ===
                activeGroupId.value
        )

        if (group) {
            list = list.filter(
                row =>
                    group.items.includes(
                        groupItemKey(row)
                    )
            )
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    const q = normalizeText(
        tableSearch.value.trim()
    )

    if (!q) return list

    return list.filter(
        row =>
            normalizeText(
                row?.name
            ).includes(q)
    )
})

/*
|--------------------------------------------------------------------------
| Prices
|--------------------------------------------------------------------------
*/

function priceToCopper(price) {
    return (
        (price.gold || 0) * 10000 +
        (price.silver || 0) * 100 +
        (price.copper || 0)
    )
}

function copperToGsc(copper) {
    const c = Math.round(
        Math.abs(copper)
    )

    return {
        gold: Math.floor(
            c / 10000
        ),
        silver: Math.floor(
            (c % 10000) / 100
        ),
        copper: c % 100,
    }
}

function formatGoldNumber(value) {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0))
}

/*
|--------------------------------------------------------------------------
| Arbitrage calculations
|--------------------------------------------------------------------------
*/

function computeArbitrageFromRows(
    sourceRows,
    realmSlugs
) {
    const opportunities = []

    for (const row of sourceRows) {
        const pricesBySlug =
            realmSlugs
                .map(slug => {
                    const price =
                        row.prices?.[slug]?.[0]

                    if (!price) return null

                    return {
                        slug,
                        copper:
                            priceToCopper(
                                price
                            ),
                    }
                })
                .filter(Boolean)

        if (pricesBySlug.length < 2) {
            continue
        }

        const buy =
            pricesBySlug.reduce(
                (a, b) =>
                    b.copper <
                        a.copper
                        ? b
                        : a
            )

        const sell =
            pricesBySlug.reduce(
                (a, b) =>
                    b.copper >
                        a.copper
                        ? b
                        : a
            )

        if (
            buy.slug === sell.slug ||
            buy.copper <= 0
        ) {
            continue
        }

        const netSell =
            Math.round(
                sell.copper * 0.95
            )

        const netProfit =
            netSell -
            buy.copper

        if (netProfit <= 0) {
            continue
        }

        opportunities.push({
            row,
            buySlug: buy.slug,
            buyCopper: buy.copper,
            sellSlug: sell.slug,
            sellCopper: sell.copper,
            netProfit,
        })
    }

    return opportunities.sort(
        (a, b) =>
            b.netProfit -
            a.netProfit
    )
}

/*
|--------------------------------------------------------------------------
| Selected realms arbitrage
|--------------------------------------------------------------------------
*/

const selectedRealmsArbitrageOpportunities =
    computed(() =>
        computeArbitrageFromRows(
            rows.value,
            selectedRealms.value
        )
    )
const selectedRealmsBuyOptions =
    computed(() =>
        [
            ...new Set(
                selectedRealmsArbitrageOpportunities.value.map(
                    o => o.buySlug
                )
            ),
        ].sort()
    )

const selectedRealmsSellOptions =
    computed(() =>
        [
            ...new Set(
                selectedRealmsArbitrageOpportunities.value.map(
                    o => o.sellSlug
                )
            ),
        ].sort()
    )
const selectedRealmsQualityOptions =
    computed(() =>
        [
            ...new Set(
                selectedRealmsArbitrageOpportunities.value
                    .map(
                        o =>
                            o.row
                                .quality
                    )
                    .filter(Boolean)
            ),
        ]
    )

const selectedRealmsIlvlOptions = computed(() => {
    const ilvls =
        selectedRealmsArbitrageOpportunities.value
            .map(o => o.row?.ilvl)
            .filter(
                ilvl =>
                    ilvl !== null &&
                    ilvl !== undefined &&
                    ilvl !== '' &&
                    Number(ilvl) !== 1 &&
                    Number.isFinite(Number(ilvl))
            )
            .map(Number)

    return [...new Set(ilvls)].sort((a, b) => a - b)
})

function selectSelectedRealmsBuyFilter(slug) {
    selectedRealmsBuyFilter.value = slug
    selectedRealmsBuyMenuOpen.value = false
}

function selectSelectedRealmsSellFilter(slug) {
    selectedRealmsSellFilter.value = slug
    selectedRealmsSellMenuOpen.value = false
}

function selectSelectedRealmsQualityFilter(quality) {
    selectedRealmsQualityFilter.value = quality
    selectedRealmsQualityMenuOpen.value = false
}

function selectSelectedRealmsIlvlFilter(ilvl) {
    selectedRealmsIlvlFilter.value = ilvl
    selectedRealmsIlvlMenuOpen.value = false
}

function closeSelectedRealmsBuyMenuOnBlur() {
    setTimeout(() => {
        selectedRealmsBuyMenuOpen.value = false
    }, 150)
}

function closeSelectedRealmsSellMenuOnBlur() {
    setTimeout(() => {
        selectedRealmsSellMenuOpen.value = false
    }, 150)
}

function closeSelectedRealmsQualityMenuOnBlur() {
    setTimeout(() => {
        selectedRealmsQualityMenuOpen.value = false
    }, 150)
}

function closeSelectedRealmsIlvlMenuOnBlur() {
    setTimeout(() => {
        selectedRealmsIlvlMenuOpen.value = false
    }, 150)
}

const filteredSelectedRealmsArbitrage =
    computed(() => {
        let list =
            selectedRealmsArbitrageOpportunities.value

        if (selectedRealmsBuyFilter.value) {
            list = list.filter(
                o =>
                    o.buySlug ===
                    selectedRealmsBuyFilter.value
            )
        }

        if (selectedRealmsSellFilter.value) {
            list = list.filter(
                o =>
                    o.sellSlug ===
                    selectedRealmsSellFilter.value
            )
        }

        if (selectedRealmsQualityFilter.value) {
            list = list.filter(
                o =>
                    o.row?.quality ===
                    selectedRealmsQualityFilter.value
            )
        }

        // FILTRO POR ILVL
        if (selectedRealmsIlvlFilter.value === 'no-ilvl') {
            // Mostrar únicamente items SIN ilvl
            list = list.filter(o => {
                const ilvl = o.row?.ilvl

                return (
                    ilvl === null ||
                    ilvl === undefined ||
                    ilvl === '' ||
                    Number(ilvl) === 1
                )
            })
        } else if (selectedRealmsIlvlFilter.value !== '') {
            // Mostrar únicamente el ilvl seleccionado
            list = list.filter(
                o =>
                    Number(o.row?.ilvl) ===
                    Number(selectedRealmsIlvlFilter.value)
            )
        }

        const q =
            normalizeText(
                selectedRealmsArbitrageSearch.value.trim()
            )

        if (q) {
            list = list.filter(
                o =>
                    normalizeText(
                        o.row?.name
                    ).includes(q)
            )
        }

        return list
    })

const totalSelectedRealmsArbitrageProfit =
    computed(() =>
        selectedRealmsArbitrageOpportunities.value.reduce(
            (sum, o) =>
                sum + o.netProfit,
            0
        )
    )

/*
|--------------------------------------------------------------------------
| Time
|--------------------------------------------------------------------------
*/

function timeAgo(dateStr) {
    if (!dateStr) return '—'

    const diffMinutes =
        Math.floor(
            (Date.now() -
                new Date(dateStr)) /
            60000
        )

    if (diffMinutes < 1) {
        return 'ahora'
    }

    if (diffMinutes < 60) {
        return `hace ${diffMinutes}m`
    }

    return `hace ${Math.floor(
        diffMinutes / 60
    )}h`
}

/*
|--------------------------------------------------------------------------
| Quality colors
|--------------------------------------------------------------------------
*/

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
    <section class="rounded-2xl border border-white/10 bg-[#12142b]/55 backdrop-blur-xl p-5 w-full">

        <!-- HEADER -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                <GitCompare class="size-4 text-indigo-400" />
                Realm Price Comparison
            </h2>

            <RefreshButton v-if="rows.length || loading" :loading="loading" @click="fetchComparison(true)" />
        </div>

        <!-- SELECTORES -->
        <div class="grid grid-cols-2 gap-6">
            <ItemPicker v-model="selectedItems" />

            <RealmMultiSelect :model-value="selectedRealms" @update:model-value="onRealmsChange" :realms="realms" />
        </div>

        <!-- SEARCH + GROUPS -->
        <div v-if="selectedItems.length && selectedRealms.length" class="mt-4 flex items-center justify-center gap-2">
            <!-- SEARCH -->
            <div class="relative w-1/3">
                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />

                <input v-model="tableSearch" type="text" placeholder="Buscar en la tabla..."
                    class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-md text-slate-100 placeholder:text-slate-500 outline-none focus:border-indigo-400/60 focus:bg-white/10 text-center" />
            </div>

            <!-- GROUPS -->
            <div class="groups-menu-container relative">

                <button type="button" @click.stop="groupMenuOpen = !groupMenuOpen"
                    class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:border-indigo-400/50 hover:bg-white/10 hover:text-white"
                    :class="{
                        'border-indigo-400/50 text-indigo-300':
                            activeGroupId
                    }">
                    <Folder class="size-3.5" />

                    <span>
                        {{
                            activeGroupId === '__ungrouped__'
                                ? 'Sin grupo'
                                : groups.find(g => g.id === activeGroupId)?.name ?? 'Grupos'
                        }}
                    </span>

                    <span v-if="activeGroupId" class="rounded-full bg-indigo-500/20 px-1.5 text-[10px] text-indigo-300">
                        {{
                            activeGroupId === '__ungrouped__'
                                ? ungroupedItemCount
                                : groupItemCount(groups.find(g => g.id === activeGroupId))
                        }}
                    </span>
                </button>

                <!-- GROUP MENU -->
                <div v-if="groupMenuOpen" @click.stop
                    class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 shadow-[0_0_25px_3px_rgba(99,102,241,0.12)] backdrop-blur-xl">

                    <div class="border-b border-white/10 px-3 py-2">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            Filtrar por grupo
                        </div>
                    </div>

                    <!-- ALL -->
                    <button type="button" @click="clearGroupFilter(); groupMenuOpen = false"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition hover:bg-white/5"
                        :class="!activeGroupId
                            ? 'bg-indigo-500/15 text-indigo-300'
                            : 'text-slate-300'
                            ">
                        <span class="flex items-center gap-2">
                            <Folder class="size-3.5" />
                            Todos los objetos
                        </span>

                        <span class="text-[10px] text-slate-500">
                            {{ rows.length }}
                        </span>
                    </button>

                    <!-- UNGROUPED -->
                    <button type="button" @click="selectGroup('__ungrouped__')"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition hover:bg-white/5"
                        :class="activeGroupId === '__ungrouped__'
                            ? 'bg-indigo-500/15 text-indigo-300'
                            : 'text-slate-300'
                            ">
                        <span class="flex items-center gap-2">
                            <Folder class="size-3.5 text-slate-500" />
                            Sin grupo
                        </span>

                        <span class="text-[10px] text-slate-500">
                            {{ ungroupedItemCount }}
                        </span>
                    </button>

                    <!-- GROUP LIST -->
                    <div v-if="groups.length" class="max-h-64 overflow-y-auto border-t border-white/10">
                        <div v-for="group in groups" :key="group.id" class="group-row flex items-center gap-1" :class="activeGroupId === group.id
                            ? 'bg-indigo-500/10'
                            : ''
                            ">
                            <!-- EDITING -->
                            <template v-if="editingGroupId === group.id">
                                <div class="flex flex-1 items-center gap-1 px-2 py-1.5">
                                    <input v-model="editingGroupName" @keyup.enter="saveEditingGroup(group)"
                                        @keyup.esc="cancelEditingGroup" autofocus
                                        class="min-w-0 flex-1 rounded border border-indigo-400/40 bg-white/5 px-2 py-1 text-xs text-slate-100 outline-none" />

                                    <button type="button" @click="saveEditingGroup(group)"
                                        class="rounded p-1 text-emerald-400 hover:bg-white/10">
                                        <Check class="size-3" />
                                    </button>

                                    <button type="button" @click="cancelEditingGroup"
                                        class="rounded p-1 text-slate-500 hover:bg-white/10 hover:text-white">
                                        <X class="size-3" />
                                    </button>
                                </div>
                            </template>

                            <!-- NORMAL -->
                            <template v-else>
                                <button type="button" @click="selectGroup(group.id)"
                                    class="flex min-w-0 flex-1 items-center justify-between gap-2 px-3 py-2 text-left text-xs transition hover:bg-white/5">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Folder class="size-3.5 shrink-0 text-indigo-400" />

                                        <span class="truncate">
                                            {{ group.name }}
                                        </span>
                                    </span>

                                    <span class="shrink-0 text-[10px] text-slate-500">
                                        {{ groupItemCount(group) }}
                                    </span>
                                </button>

                                <button type="button" @click.stop="startEditingGroup(group)"
                                    class="mr-0.5 rounded p-1 text-slate-600 hover:bg-white/10 hover:text-slate-300"
                                    title="Renombrar grupo">
                                    <Pencil class="size-3" />
                                </button>

                                <button type="button" @click.stop="deleteGroup(group.id)"
                                    class="mr-1 rounded p-1 text-slate-600 hover:bg-red-500/10 hover:text-red-400"
                                    title="Eliminar grupo">
                                    <Trash2 class="size-3" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- CREATE -->
                    <div class="border-t border-white/10 p-2">

                        <template v-if="showCreateGroup">
                            <div class="flex items-center gap-1">
                                <input v-model="newGroupName" @keyup.enter="createGroup"
                                    @keyup.esc="showCreateGroup = false" autofocus type="text"
                                    placeholder="Nombre del grupo..."
                                    class="min-w-0 flex-1 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-xs text-slate-100 placeholder:text-slate-600 outline-none focus:border-indigo-400/50" />

                                <button type="button" @click="createGroup"
                                    class="rounded-lg bg-indigo-500/20 p-1.5 text-indigo-300 transition hover:bg-indigo-500/30">
                                    <Check class="size-3.5" />
                                </button>

                                <button type="button" @click="showCreateGroup = false; newGroupName = ''"
                                    class="rounded-lg p-1.5 text-slate-500 hover:bg-white/5 hover:text-white">
                                    <X class="size-3.5" />
                                </button>
                            </div>
                        </template>

                        <button v-else type="button" @click="showCreateGroup = true"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-indigo-300 transition hover:bg-indigo-500/10 hover:text-indigo-200">
                            <FolderPlus class="size-3.5" />
                            Crear grupo
                        </button>
                    </div>
                </div>
            </div>
            <!-- DELETE SELECTED ITEMS -->
            <button
                type="button"
                @click="removeSelectedItems"
                :disabled="selectedRowKeys.size === 0"
                class="flex size-8 items-center justify-center rounded-lg border transition"
                :class="selectedRowKeys.size
                    ? 'border-red-400/30 bg-red-500/10 text-red-300 hover:border-red-400/60 hover:bg-red-500/20 hover:text-red-200'
                    : 'cursor-not-allowed border-white/5 bg-white/[0.02] text-slate-700'"
                :title="selectedRowKeys.size
                    ? `Eliminar ${selectedRowKeys.size} item${selectedRowKeys.size === 1 ? '' : 's'} seleccionado${selectedRowKeys.size === 1 ? '' : 's'}`
                    : 'Selecciona uno o más items para eliminarlos'"
            >
                <Trash2 class="size-4" />
            </button>
        </div>

        <!-- TABLE -->
        <div v-if="selectedItems.length && selectedRealms.length"
            class="mt-3 overflow-x-auto rounded-xl border border-white/5 bg-white/[0.02] backdrop-blur-md">
            <div class="app-scroll max-h-[28rem] overflow-y-auto">

                <table class="w-full text-sm">

                    <thead class="sticky top-0 z-10 bg-[#181b3a] text-[11px] uppercase tracking-wider text-slate-500">

                        <tr>

                            <th class="px-4 py-2.5 text-left">
                                Item
                            </th>

                            <th v-for="slug in selectedRealms" :key="slug" class="px-4 py-2.5 text-left">
                                <div class="flex items-center gap-1.5">

                                    <span>
                                        {{
                                            realms.find(
                                                r => r.slug === slug
                                            )?.name ?? slug
                                        }}
                                    </span>

                                    <span class="text-slate-600">
                                        —
                                    </span>

                                    <span class="font-normal normal-case text-slate-500">
                                        {{
                                            timeAgo(
                                                lastSynced[slug]
                                            )
                                        }}
                                    </span>

                                    <button type="button" @click="removeRealm(slug)"
                                        class="shrink-0 text-slate-500 hover:text-red-400">
                                        <X class="size-3" />
                                    </button>

                                </div>
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <!-- LOADING -->
                        <tr v-if="loading">

                            <td :colspan="selectedRealms.length + 1" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-2 text-slate-500">

                                    <Loader2 class="size-6 animate-spin text-indigo-400" />

                                    <span class="text-sm">
                                        Actualizando precios...
                                    </span>

                                </div>
                            </td>

                        </tr>

                        <!-- EMPTY -->
                        <tr v-else-if="!filteredRows.length">

                            <td :colspan="selectedRealms.length + 1" class="px-4 py-6 text-center text-slate-500">
                                {{
                                    activeGroupId
                                        ? 'No hay objetos en este grupo que coincidan con la búsqueda.'
                                        : `Sin resultados para "${tableSearch}"`
                                }}
                            </td>

                        </tr>

                        <!-- ROWS -->
                        <template v-else>

                            <tr
                                v-for="row in filteredRows"
                                :key="`${row.item_id}-${row.ilvl}`"
                                class="border-t border-white/5 align-top transition-colors"
                                :class="isRowSelected(row)
                                    ? 'bg-indigo-500/10'
                                    : favorites.has(favoriteKey(row))
                                        ? 'bg-amber-400/4'
                                        : ''
                                "
                            >

                                <!-- ITEM -->
                                <td class="px-4 py-2.5">

                                    <span class="flex items-center gap-2" :class="QUALITY_COLORS[row.quality]
                                        ?? 'text-slate-100'
                                        ">

                                        <!-- SELECT -->
                                        <button
                                            type="button"
                                            @click.stop="toggleRowSelection(row)"
                                            class="flex size-4 shrink-0 items-center justify-center rounded border transition"
                                            :class="isRowSelected(row)
                                                ? 'border-indigo-400 bg-indigo-500/30 text-indigo-200'
                                                : 'border-white/15 text-transparent hover:border-indigo-400/60'"
                                            :title="isRowSelected(row) ? 'Deseleccionar item' : 'Seleccionar item'"
                                        >
                                            <Check v-if="isRowSelected(row)" class="size-3" />
                                        </button>

                                        <!-- FAVORITE -->
                                        <button type="button" @click.stop="toggleFavorite(row)" class="shrink-0">
                                            <Star class="size-4 transition-colors" :class="favorites.has(
                                                favoriteKey(row)
                                            )
                                                ? 'fill-amber-400 text-amber-400'
                                                : 'text-slate-600 hover:text-slate-400'
                                                " />
                                        </button>

                                        <!-- ITEM DETAIL -->
                                        <button
                                            type="button"
                                            @click.stop="openItemDetail(row)"
                                            class="flex min-w-0 items-center gap-2 rounded px-1 py-0.5 text-left hover:bg-white/5"
                                            title="Abrir detalle del item"
                                        >
                                            <img v-if="row.icon_url" :src="row.icon_url" class="size-5 rounded shrink-0" />

                                            <Sparkles v-else class="size-5 shrink-0" />

                                            <span class="truncate">
                                                {{ row.name }}
                                            </span>

                                            <span
                                                class="shrink-0 rounded bg-white/5 px-1.5 py-0.5 text-[12px] font-semibold text-slate-100">
                                                {{
                                                    row.ilvl !== null
                                                        ? `ilvl ${row.ilvl}`
                                                        : 'Sin ilvl'
                                                }}
                                            </span>
                                        </button>

                                        <!-- GROUP BUTTON -->
                                        <div class="item-group-menu-container relative shrink-0">

                                            <button type="button" @click.stop="toggleItemGroupMenu(row)"
                                                class="rounded p-1 transition" :class="isItemInAnyGroup(row)
                                                    ? 'text-indigo-400 hover:bg-indigo-500/10 hover:text-indigo-300'
                                                    : 'text-slate-600 hover:bg-white/5 hover:text-slate-300'
                                                    " title="Agregar a grupo">
                                                <Folder class="size-3.5" />
                                            </button>

                                            <!-- ITEM GROUP POPOVER -->
                                            <div v-if="
                                                itemGroupMenu ===
                                                groupItemKey(row)
                                            " @click.stop
                                                class="absolute left-0 top-full z-40 mt-1 w-56 overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 shadow-[0_0_25px_3px_rgba(99,102,241,0.15)] backdrop-blur-xl">

                                                <div class="border-b border-white/10 px-3 py-2">
                                                    <div
                                                        class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                                        Agregar a grupos
                                                    </div>

                                                    <div class="mt-0.5 truncate text-xs text-slate-300">
                                                        {{ row.name }}
                                                    </div>
                                                </div>

                                                <!-- GROUPS -->
                                                <div v-if="groups.length" class="max-h-56 overflow-y-auto">

                                                    <button v-for="group in groups" :key="group.id" type="button"
                                                        @click="toggleItemInGroup(row, group)"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs transition hover:bg-white/5">

                                                        <span
                                                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                                                            :class="isItemInGroup(row, group)
                                                                ? 'border-indigo-400 bg-indigo-500/30 text-indigo-200'
                                                                : 'border-white/15 text-transparent'
                                                                ">
                                                            <Check v-if="isItemInGroup(row, group)" class="size-3" />
                                                        </span>

                                                        <Folder class="size-3.5 shrink-0 text-indigo-400" />

                                                        <span class="truncate text-slate-300">
                                                            {{ group.name }}
                                                        </span>

                                                    </button>

                                                </div>

                                                <!-- NO GROUPS -->
                                                <div v-else class="px-3 py-4 text-center text-xs text-slate-500">
                                                    Todavía no tienes grupos.
                                                </div>

                                                <!-- CREATE GROUP -->
                                                <div class="border-t border-white/10 p-2">

                                                    <button type="button" @click="createGroupFromItem"
                                                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-indigo-300 transition hover:bg-indigo-500/10">
                                                        <Plus class="size-3.5" />
                                                        Crear grupo
                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </span>

                                </td>

                                <!-- REALM PRICES -->
                                <td v-for="slug in selectedRealms" :key="slug" class="px-4 py-2.5">

                                    <template v-if="
                                        row.prices[slug]?.length
                                    ">

                                        <button type="button" @click="
                                            toggleCell(
                                                cellKey(
                                                    row,
                                                    slug
                                                )
                                            )
                                            "
                                            class="inline-flex items-center gap-1 rounded hover:bg-white/5 px-1 py-0.5">

                                            <span class="inline-flex items-center gap-0.5 text-amber-400 font-semibold">
                                                <span class="size-2 rounded-full bg-amber-400"></span>
                                                {{
                                                    row.prices[slug][0].gold
                                                }}
                                            </span>

                                            <span class="inline-flex items-center gap-0.5 text-slate-300 font-semibold">
                                                <span class="size-2 rounded-full bg-slate-300"></span>
                                                {{
                                                    row.prices[slug][0].silver
                                                }}
                                            </span>

                                            <span v-if="
                                                row.prices[slug].length >
                                                1
                                            " class="ml-1 text-[10px] text-slate-500">
                                                +{{
                                                    row.prices[slug].length -
                                                    1
                                                }}
                                            </span>

                                        </button>

                                        <!-- ADDITIONAL PRICES -->
                                        <div v-if="
                                            openCells.has(
                                                cellKey(
                                                    row,
                                                    slug
                                                )
                                            )
                                        " class="mt-1 flex flex-col gap-1 border-l border-white/10 pl-2">

                                            <span v-for="(
price,
    i
                                                ) in row.prices[slug].slice(1)" :key="i"
                                                class="inline-flex items-center gap-1 text-xs">

                                                <span class="inline-flex items-center gap-0.5 text-amber-400/80">
                                                    <span class="size-1.5 rounded-full bg-amber-400"></span>
                                                    {{ price.gold }}
                                                </span>

                                                <span class="inline-flex items-center gap-0.5 text-slate-400">
                                                    <span class="size-1.5 rounded-full bg-slate-300"></span>
                                                    {{ price.silver }}
                                                </span>

                                            </span>

                                        </div>

                                    </template>

                                    <span v-else class="text-slate-600">
                                        —
                                    </span>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>
        </div>

        <!-- SELECTED REALMS ARBITRAGE -->
        <div v-if="
            selectedItems.length &&
            selectedRealms.length >= 2
        " class="mt-5 border-t border-white/10 pt-5">

            <div class="mb-3">

                <h3 class="flex items-center gap-2 text-sm font-bold text-slate-100">
                    <ArrowLeftRight class="size-4 text-cyan-400" />
                    Arbitraje entre reinos seleccionados
                </h3>

                <p class="mt-0.5 text-xs text-slate-500">
                    Compara los
                    {{ selectedItems.length }}
                    ítems de tu lista entre los
                    {{ selectedRealms.length }}
                    reinos que tienes seleccionados arriba
                    ({{ selectedRealms.map(realmName).join(', ') }}).
                    Sin llamadas nuevas al servidor — usa los datos que ya están cargados en la tabla.
                </p>

            </div>

            <div v-if="
                !selectedRealmsArbitrageOpportunities.length
            " class="rounded-lg border border-white/5 bg-white/3 py-8 text-center text-sm text-slate-500">
                No se encontraron oportunidades de arbitraje rentables entre los reinos seleccionados.
            </div>

            <div v-else class="rounded-xl border border-cyan-400/20 bg-cyan-500/5 p-4">

                <div class="mb-3 flex items-center justify-between">

                    <h4 class="text-sm font-bold text-slate-100">
                        Compra barato. Vende mejor.
                    </h4>

                    <div class="text-right">

                        <div class="text-[10px] uppercase tracking-wide text-slate-500">
                            Beneficio total estimado
                        </div>

                        <div class="flex items-center justify-end gap-1 text-sm font-bold text-emerald-400">

                            <span class="inline-flex items-center gap-0.5">
                                <span class="size-1.5 rounded-full bg-amber-400"></span>
                                {{
                                    formatGoldNumber(
                                        copperToGsc(
                                            totalSelectedRealmsArbitrageProfit
                                        ).gold
                                    )
                                }}
                            </span>

                            <span class="inline-flex items-center gap-0.5">
                                <span class="size-1.5 rounded-full bg-slate-300"></span>
                                {{
                                    formatGoldNumber(
                                        copperToGsc(
                                            totalSelectedRealmsArbitrageProfit
                                        ).silver
                                    )
                                }}
                            </span>

                        </div>

                    </div>

                </div>

                <div class="relative mb-3 w-full max-w-sm">

                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-500" />

                    <input v-model="selectedRealmsArbitrageSearch" type="text" placeholder="Filtrar oportunidades..."
                        class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-3 text-sm text-slate-100 placeholder:text-slate-500 outline-none focus:border-cyan-400/60" />

                </div>

                <div class="app-scroll max-h-96 overflow-y-auto rounded-lg border border-white/10">

                    <table class="w-full text-sm">

                        <thead
                            class="sticky top-0 z-10 bg-[#181b3a] text-[11px] uppercase tracking-wider text-slate-500">

                            <tr>

                                <th class="px-3 py-2 text-left">
                                    <div class="flex items-center gap-1.5">
                                        Item

                                        <div class="relative">

                                            <button type="button" @click="
                                                selectedRealmsQualityMenuOpen =
                                                !selectedRealmsQualityMenuOpen
                                                " @blur="
                                                    closeSelectedRealmsQualityMenuOnBlur
                                                "
                                                class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-cyan-300"
                                                :class="{
                                                    'text-cyan-400':
                                                        selectedRealmsQualityFilter
                                                }">
                                                <Filter class="size-3" />
                                            </button>

                                            <div v-if="
                                                selectedRealmsQualityMenuOpen
                                            "
                                                class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-cyan-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(34,211,238,0.15)]">

                                                <div @mousedown="
                                                    selectSelectedRealmsQualityFilter('')
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                    :class="!selectedRealmsQualityFilter
                                                        ? 'bg-cyan-500/15 text-cyan-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                        ">
                                                    Todas las calidades
                                                </div>

                                                <div v-for="q in selectedRealmsQualityOptions" :key="q" @mousedown="
                                                    selectSelectedRealmsQualityFilter(q)
                                                    "
                                                    class="cursor-pointer px-3 py-2 text-xs capitalize transition-colors"
                                                    :class="[
                                                        QUALITY_COLORS[q] ??
                                                        'text-slate-300',
                                                        selectedRealmsQualityFilter ===
                                                            q
                                                            ? 'bg-cyan-500/15'
                                                            : 'hover:bg-white/5'
                                                    ]">
                                                    {{ q }}
                                                </div>

                                            </div>

                                        </div>

                                        <!-- ILVL FILTER -->
                                        <div class="relative">

                                            <button type="button" @click.stop="
                                                selectedRealmsIlvlMenuOpen =
                                                !selectedRealmsIlvlMenuOpen
                                                " class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-indigo-300" :class="{
                                                    'text-indigo-400':
                                                        selectedRealmsIlvlFilter !== ''
                                                }" title="Filtrar por nivel de objeto">
                                                <Filter class="size-3" />
                                            </button>

                                            <div v-if="selectedRealmsIlvlMenuOpen"
                                                class="absolute left-0 z-20 mt-1 max-h-64 w-32 overflow-y-auto rounded-lg border border-indigo-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(99,102,241,0.15)]"
                                                @mousedown.stop>

                                                <!-- TODOS -->
                                                <div @mousedown.prevent="
                                                    selectSelectedRealmsIlvlFilter('')
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors" :class="selectedRealmsIlvlFilter === ''
                                                        ? 'bg-indigo-500/15 text-indigo-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                    ">
                                                    Todos
                                                </div>

                                                <!-- SIN ILVL -->
                                                <div @mousedown.prevent="
                                                    selectSelectedRealmsIlvlFilter('no-ilvl')
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors" :class="selectedRealmsIlvlFilter === 'no-ilvl'
                                                        ? 'bg-indigo-500/15 text-indigo-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                    ">
                                                    Sin ilvl
                                                </div>

                                                <!-- ILVLS -->
                                                <div v-for="ilvl in selectedRealmsIlvlOptions" :key="ilvl"
                                                    @mousedown.prevent="
                                                        selectSelectedRealmsIlvlFilter(ilvl)
                                                        " class="cursor-pointer px-3 py-2 text-xs transition-colors" :class="Number(selectedRealmsIlvlFilter) === Number(ilvl) &&
                                                        selectedRealmsIlvlFilter !== 'no-ilvl'
                                                        ? 'bg-indigo-500/15 text-indigo-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                    ">
                                                    {{ ilvl }}
                                                </div>

                                            </div>

                                        </div>
                                    </div>
                                </th>

                                <th class="px-3 py-2 text-left">
                                    <div class="flex items-center gap-1.5">
                                        Comprar en

                                        <div class="relative">

                                            <button type="button" @click="
                                                selectedRealmsBuyMenuOpen =
                                                !selectedRealmsBuyMenuOpen
                                                " @blur="
                                                    closeSelectedRealmsBuyMenuOnBlur
                                                "
                                                class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-cyan-300"
                                                :class="{
                                                    'text-cyan-400':
                                                        selectedRealmsBuyFilter
                                                }">
                                                <Filter class="size-3" />
                                            </button>

                                            <div v-if="
                                                selectedRealmsBuyMenuOpen
                                            "
                                                class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-cyan-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(34,211,238,0.15)]">

                                                <div @mousedown="
                                                    selectSelectedRealmsBuyFilter('')
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                    :class="!selectedRealmsBuyFilter
                                                        ? 'bg-cyan-500/15 text-cyan-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                        ">
                                                    Todos los reinos
                                                </div>

                                                <div v-for="slug in selectedRealmsBuyOptions" :key="slug" @mousedown="
                                                    selectSelectedRealmsBuyFilter(slug)
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                    :class="selectedRealmsBuyFilter ===
                                                        slug
                                                        ? 'bg-cyan-500/15 text-cyan-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                        ">
                                                    {{ realmName(slug) }}
                                                </div>

                                            </div>

                                        </div>

                                    </div>
                                </th>

                                <th class="px-3 py-2 text-left">
                                    Precio
                                </th>

                                <th class="px-3 py-2 text-left">
                                    <div class="flex items-center gap-1.5">
                                        Vender en

                                        <div class="relative">

                                            <button type="button" @click="
                                                selectedRealmsSellMenuOpen =
                                                !selectedRealmsSellMenuOpen
                                                " @blur="
                                                    closeSelectedRealmsSellMenuOnBlur
                                                "
                                                class="rounded p-0.5 text-slate-500 transition hover:bg-white/10 hover:text-cyan-300"
                                                :class="{
                                                    'text-cyan-400':
                                                        selectedRealmsSellFilter
                                                }">
                                                <Filter class="size-3" />
                                            </button>

                                            <div v-if="
                                                selectedRealmsSellMenuOpen
                                            "
                                                class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-cyan-400/20 bg-[#12142b]/95 normal-case backdrop-blur-sm shadow-[0_0_20px_2px_rgba(34,211,238,0.15)]">

                                                <div @mousedown="
                                                    selectSelectedRealmsSellFilter('')
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                    :class="!selectedRealmsSellFilter
                                                        ? 'bg-cyan-500/15 text-cyan-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                        ">
                                                    Todos los reinos
                                                </div>

                                                <div v-for="slug in selectedRealmsSellOptions" :key="slug" @mousedown="
                                                    selectSelectedRealmsSellFilter(slug)
                                                    " class="cursor-pointer px-3 py-2 text-xs transition-colors"
                                                    :class="selectedRealmsSellFilter ===
                                                        slug
                                                        ? 'bg-cyan-500/15 text-cyan-300'
                                                        : 'text-slate-300 hover:bg-white/5'
                                                        ">
                                                    {{ realmName(slug) }}
                                                </div>

                                            </div>

                                        </div>

                                    </div>
                                </th>

                                <th class="px-3 py-2 text-left">
                                    Precio
                                </th>

                                <th class="px-3 py-2 text-right">
                                    Beneficio
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr v-for="o in filteredSelectedRealmsArbitrage" :key="`${o.row.item_id}-${o.row.ilvl}`"
                                class="border-t border-white/5">

                                <td class="px-3 py-2">

                                    <span class="flex items-center gap-2" :class="QUALITY_COLORS[
                                        o.row.quality
                                    ] ??
                                        'text-slate-100'
                                        ">

                                        <img v-if="o.row.icon_url" :src="o.row.icon_url"
                                            class="size-6 shrink-0 rounded" />

                                        <Sparkles v-else class="size-6 shrink-0" />

                                        <span class="truncate">
                                            {{ o.row.name }}
                                        </span>

                                        <span
                                            class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold text-slate-400">
                                            {{ o.row.ilvl && Number(o.row.ilvl) !== 1
                                                ? `ilvl ${o.row.ilvl}`
                                                : 'Sin ilvl'
                                            }}
                                        </span>

                                    </span>

                                </td>

                                <td class="px-3 py-2 text-amber-300">
                                    {{ realmName(o.buySlug) }}
                                </td>

                                <td class="px-3 py-2">

                                    <span class="inline-flex items-center gap-1">

                                        <span class="inline-flex items-center gap-0.5 text-amber-400">
                                            <span class="size-1.5 rounded-full bg-amber-400"></span>
                                            {{ copperToGsc(o.buyCopper).gold }}
                                        </span>

                                        <span class="inline-flex items-center gap-0.5 text-slate-300">
                                            <span class="size-1.5 rounded-full bg-slate-300"></span>
                                            {{ copperToGsc(o.buyCopper).silver }}
                                        </span>

                                    </span>

                                </td>

                                <td class="px-3 py-2 text-emerald-300">
                                    {{ realmName(o.sellSlug) }}
                                </td>

                                <td class="px-3 py-2">

                                    <span class="inline-flex items-center gap-1">

                                        <span class="inline-flex items-center gap-0.5 text-amber-400">
                                            <span class="size-1.5 rounded-full bg-amber-400"></span>
                                            {{ copperToGsc(o.sellCopper).gold }}
                                        </span>

                                        <span class="inline-flex items-center gap-0.5 text-slate-300">
                                            <span class="size-1.5 rounded-full bg-slate-300"></span>
                                            {{ copperToGsc(o.sellCopper).silver }}
                                        </span>

                                    </span>

                                </td>

                                <td class="px-3 py-2 text-right">

                                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-400">

                                        +

                                        <span class="inline-flex items-center gap-0.5">
                                            <span class="size-1.5 rounded-full bg-amber-400"></span>
                                            {{
                                                formatGoldNumber(
                                                    copperToGsc(o.netProfit).gold
                                                )
                                            }}
                                        </span>

                                        <span class="inline-flex items-center gap-0.5">
                                            <span class="size-1.5 rounded-full bg-slate-300"></span>
                                            {{
                                                formatGoldNumber(
                                                    copperToGsc(o.netProfit).silver
                                                )
                                            }}
                                        </span>

                                    </span>

                                </td>

                            </tr>

                            <tr v-if="
                                !filteredSelectedRealmsArbitrage.length
                            ">
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500">
                                    Sin resultados
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Beneficios ya con comisión del AH (5%) restada.
                    Comparativa actualizada
                    {{ timeAgo(Object.values(lastSynced)[0]) }}.
                </p>

            </div>

        </div>


    </section>
</template>

<style scoped>
.app-scroll {
    scrollbar-width: thin;
    scrollbar-color: #312e5c #12142b;
}

.app-scroll::-webkit-scrollbar {
    width: 6px;
}

.app-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.app-scroll::-webkit-scrollbar-thumb {
    background-color: #312e5c;
    border-radius: 9999px;
}

.app-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #4338ca;
}

.group-row {
    transition: background-color 0.15s ease;
}
</style>