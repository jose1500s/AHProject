<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import {
    Sparkles,
    X,
    Plus,
    Search,
    ArrowLeft,
    Info,
    Loader2,
    Upload,
    CheckCircle2,
    AlertCircle,
} from '@lucide/vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])
const isOpen = ref(false)
const mode = ref('search')
const query = ref('')
const results = ref([])
const activeItem = ref(null)
const variants = ref([])
const loadingVariants = ref(false)
const rootRef = ref(null)
let searchTimeout = null

// ============================================================
// IMPORTAR DESDE ADDON
// ============================================================
const isImportModalOpen = ref(false)
const importJson = ref('')
const importing = ref(false)
const importResult = ref(null)
const importError = ref('')

function openImportModal() {
    importJson.value = ''
    importResult.value = null
    importError.value = ''
    isImportModalOpen.value = true
}

function closeImportModal() {
    if (importing.value) return

    isImportModalOpen.value = false
    importJson.value = ''
    importResult.value = null
    importError.value = ''
}

/*
|--------------------------------------------------------------------------
| Normalización de ilvl
|--------------------------------------------------------------------------
|
| En el addon:
|
|   itemLevel = 1
|
| significa que el item realmente no tiene un ilvl útil.
|
| Para Realm Comparison:
|
|   1    -> null
|   null -> null
|   ''   -> null
|   > 1  -> valor real
|
|--------------------------------------------------------------------------
*/
function normalizeIlvl(ilvl) {
    if (
        ilvl === null ||
        ilvl === undefined ||
        ilvl === ''
    ) {
        return null
    }

    const number = Number(ilvl)

    if (Number.isNaN(number)) {
        return null
    }

    if (number === 1) {
        return null
    }

    return number
}

/*
|--------------------------------------------------------------------------
| Importar JSON del addon
|--------------------------------------------------------------------------
|
| Si ya existe itemID + ilvl:
|
|   NO agrega otro registro
|   ACTUALIZA el registro existente
|
| La comparación se hace después de normalizar el ilvl.
|
| Por lo tanto:
|
|   itemLevel 1 == ilvl null
|
|--------------------------------------------------------------------------
*/
function importFromAddon() {
    if (importing.value) return

    importError.value = ''
    importResult.value = null

    if (!importJson.value.trim()) {
        importError.value =
            'Pega primero el JSON exportado desde el addon.'
        return
    }

    importing.value = true

    try {
        const data = JSON.parse(importJson.value)

        if (!data || typeof data !== 'object') {
            throw new Error(
                'El JSON no contiene un objeto válido.'
            )
        }

        if (!Array.isArray(data.items)) {
            throw new Error(
                'El JSON no contiene una lista de items válida.'
            )
        }

        const importedItems = data.items

        /*
         * Copiamos el array actual para trabajar sobre él
         * sin mutar directamente props.modelValue.
         */
        const currentItems = [...props.modelValue]

        let added = 0
        let updated = 0
        let invalid = 0

        for (const item of importedItems) {
            const itemID = Number(
                item.itemID ??
                item.item_id ??
                item.id
            )

            const itemLevelRaw =
                item.itemLevel ??
                item.item_level ??
                item.ilvl

            /*
             * IMPORTANTE:
             *
             * itemLevel 1 se convierte en null.
             */
            const itemLevel =
                normalizeIlvl(itemLevelRaw)

            const name = item.name

            /*
             * El addon debe proporcionar al menos
             * ID y nombre.
             */
            if (!itemID || !name) {
                invalid++
                continue
            }

            /*
             * Buscar por:
             *
             * itemID + ilvl normalizado
             *
             * Ejemplos:
             *
             * 12345 + 250
             * 12345 + null
             *
             * itemLevel 1 termina siendo null,
             * por lo que coincide con un item sin ilvl.
             */
            const existingIndex =
                currentItems.findIndex(existingItem => {
                    return (
                        Number(existingItem.id) === itemID &&
                        normalizeIlvl(existingItem.ilvl) === itemLevel
                    )
                })

            /*
             * YA EXISTE
             *
             * Actualizamos el registro existente
             * en lugar de crear otro.
             */
            if (existingIndex !== -1) {
                const existingItem =
                    currentItems[existingIndex]

                currentItems[existingIndex] = {
                    ...existingItem,

                    /*
                     * Datos provenientes del addon.
                     */
                    id: itemID,
                    ilvl: itemLevel,
                    name,
                    icon_url:
                        item.icon_url ??
                        existingItem.icon_url ??
                        null,
                    quality:
                        item.quality ??
                        existingItem.quality ??
                        null,
                }

                updated++
                continue
            }

            /*
             * NO EXISTE
             *
             * Agregamos como nuevo item.
             */
            currentItems.push({
                id: itemID,
                ilvl: itemLevel,
                name,
                icon_url: item.icon_url ?? null,
                quality: item.quality ?? null,
            })

            added++
        }

        /*
         * Emitimos TODO el resultado final.
         *
         * El watch del componente padre se encargará
         * de actualizar localStorage.
         */
        emit(
            'update:modelValue',
            currentItems
        )

        importResult.value = {
            added,
            updated,
            invalid,
            total: importedItems.length,
        }

    } catch (error) {
        console.error(
            'Error importando items del addon:',
            error
        )

        importError.value =
            error instanceof SyntaxError
                ? 'El contenido pegado no es un JSON válido.'
                : error.message ||
                  'No se pudo importar el JSON.'
    } finally {
        importing.value = false
    }
}

// ============================================================
// BUSQUEDA MANUAL
// ============================================================

watch(query, (q) => {
    clearTimeout(searchTimeout)

    if (q.trim().length < 2) {
        results.value = []
        return
    }

    searchTimeout = setTimeout(async () => {
        const res = await fetch(
            `/api/items/search?q=${encodeURIComponent(q)}`
        )

        results.value = await res.json()
    }, 300)
})

async function selectItem(item) {
    activeItem.value = item
    mode.value = 'variants'
    loadingVariants.value = true

    try {
        const res = await fetch(
            `/api/items/${item.blizzard_id}/variants`
        )

        const data = await res.json()

        variants.value = data.variants
    } finally {
        loadingVariants.value = false
    }
}

function addVariant(variant) {
    const id =
        activeItem.value.blizzard_id

    const normalizedIlvl =
        normalizeIlvl(variant.ilvl)

    const alreadyAdded =
        props.modelValue.some(
            i =>
                Number(i.id) === Number(id) &&
                normalizeIlvl(i.ilvl) ===
                    normalizedIlvl
        )

    if (!alreadyAdded) {
        emit(
            'update:modelValue',
            [
                ...props.modelValue,
                {
                    id,
                    ilvl: normalizedIlvl,
                    name:
                        activeItem.value.name,
                    icon_url:
                        activeItem.value.icon_url,
                    quality:
                        activeItem.value.quality,
                },
            ]
        )
    }

    closeAndReset()
}

function backToSearch() {
    mode.value = 'search'
    activeItem.value = null
    variants.value = []
}

function closeAndReset() {
    isOpen.value = false
    mode.value = 'search'
    query.value = ''
    results.value = []
    activeItem.value = null
    variants.value = []
}

function onClickOutside(e) {
    if (
        rootRef.value &&
        !rootRef.value.contains(e.target) &&
        !isImportModalOpen.value
    ) {
        closeAndReset()
    }
}

onMounted(() => {
    document.addEventListener(
        'mousedown',
        onClickOutside
    )
})

onUnmounted(() => {
    document.removeEventListener(
        'mousedown',
        onClickOutside
    )

    clearTimeout(searchTimeout)
})
</script>

<template>
    <div
        ref="rootRef"
        class="relative"
    >
        <label
            class="mb-1 block text-[10px] font-semibold tracking-widest text-slate-400 uppercase"
        >
            Items
        </label>

        <div
            class="flex flex-wrap items-center gap-2"
        >
            <!-- ========================================================= -->
            <!-- ADD MANUAL -->
            <!-- ========================================================= -->
            <button
                type="button"
                @click="isOpen = !isOpen"
                class="flex items-center gap-1 rounded-lg border border-white/10 px-3 py-1.5 text-sm text-slate-400 hover:border-white/20 hover:text-white"
            >
                <Plus class="size-3.5" />
                Add
            </button>

            <!-- ========================================================= -->
            <!-- IMPORT FROM ADDON -->
            <!-- ========================================================= -->
            <div class="group relative">
                <button
                    type="button"
                    @click="openImportModal"
                    class="flex items-center gap-1.5 rounded-lg border border-indigo-400/20 bg-indigo-500/5 px-3 py-1.5 text-sm text-indigo-300 transition hover:border-indigo-400/40 hover:bg-indigo-500/10 hover:text-indigo-200"
                >
                    <Upload class="size-3.5" />

                    Importar desde addon

                    <Info
                        class="size-3 text-indigo-400/70"
                    />
                </button>

                <!-- Tooltip -->
                <div
                    class="pointer-events-none absolute left-0 top-full z-30 mt-2 w-72 rounded-lg border border-white/10 bg-[#0d1022] px-3 py-2 text-xs leading-relaxed text-slate-400 opacity-0 shadow-xl transition-opacity group-hover:opacity-100"
                >
                    <div
                        class="mb-1 flex items-center gap-1.5 text-slate-200"
                    >
                        <Info
                            class="size-3.5 text-indigo-400"
                        />

                        Importar datos del addon
                    </div>

                    Instala el addon Auction Terminal
                    Tracker, escanea la Auction House y
                    usa el botón
                    <span class="text-indigo-300">
                        Exportar
                    </span>.
                    Después pega aquí el JSON generado.
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- SEARCH / ADD DROPDOWN -->
        <!-- ============================================================= -->
        <div
            v-if="isOpen"
            class="absolute z-10 mt-2 w-80 overflow-hidden rounded-xl border border-indigo-400/20 bg-[#12142b]/95 shadow-[0_0_20px_2px_rgba(99,102,241,0.15)] backdrop-blur-sm"
        >
            <!-- paso 1: buscar por nombre -->
            <template
                v-if="mode === 'search'"
            >
                <div
                    class="relative border-b border-white/5 p-2"
                >
                    <Search
                        class="pointer-events-none absolute left-4 top-1/2 size-3.5 -translate-y-1/2 text-slate-500"
                    />

                    <input
                        v-model="query"
                        type="text"
                        placeholder="Buscar objeto..."
                        class="w-full rounded-md bg-white/5 py-1.5 pl-7 pr-2 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:bg-white/10"
                    />
                </div>

                <ul
                    class="max-h-56 overflow-y-auto"
                >
                    <li
                        v-for="result in results"
                        :key="result.blizzard_id"
                        @click="selectItem(result)"
                        class="flex cursor-pointer items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white"
                    >
                        <img
                            v-if="result.icon_url"
                            :src="result.icon_url"
                            class="size-5 rounded"
                        />

                        {{ result.name }}
                    </li>

                    <li
                        v-if="
                            query.length >= 2 &&
                            !results.length
                        "
                        class="px-4 py-3 text-sm text-slate-500"
                    >
                        Sin resultados
                    </li>
                </ul>
            </template>

            <!-- paso 2: elegir variante por ilvl -->
            <template v-else>
                <div
                    class="flex items-center gap-2 border-b border-white/5 px-3 py-2"
                >
                    <button
                        type="button"
                        @click="backToSearch"
                        class="text-slate-500 hover:text-white"
                    >
                        <ArrowLeft
                            class="size-4"
                        />
                    </button>

                    <img
                        v-if="activeItem?.icon_url"
                        :src="activeItem.icon_url"
                        class="size-5 rounded"
                    />

                    <span
                        class="truncate text-sm font-medium text-slate-200"
                    >
                        {{ activeItem?.name }}
                    </span>
                </div>

                <div
                    v-if="loadingVariants"
                    class="px-4 py-4 text-center text-sm text-slate-500"
                >
                    Cargando variantes...
                </div>

                <template v-else>
                    <ul
                        v-if="variants.length"
                        class="max-h-56 overflow-y-auto"
                    >
                        <li
                            v-for="variant in variants"
                            :key="
                                variant.ilvl ??
                                'none'
                            "
                            @click="
                                addVariant(
                                    variant
                                )
                            "
                            class="flex cursor-pointer items-center justify-between px-4 py-2 text-sm hover:bg-white/5"
                        >
                            <span
                                class="text-slate-200"
                            >
                                {{
                                    normalizeIlvl(
                                        variant.ilvl
                                    ) !== null
                                        ? `ilvl ${normalizeIlvl(variant.ilvl)}`
                                        : 'Sin ilvl'
                                }}
                            </span>

                            <span
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="inline-flex items-center gap-0.5 font-semibold text-amber-400"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-amber-400"
                                    ></span>

                                    {{
                                        variant
                                            .cheapest
                                            .gold
                                    }}
                                </span>

                                <span
                                    class="text-[10px] text-slate-500"
                                >
                                    {{
                                        variant
                                            .auction_count
                                    }}x
                                </span>
                            </span>
                        </li>
                    </ul>

                    <!-- sin auctions activas en ningún realm sincronizado -->
                    <div
                        v-else
                        class="px-4 py-4 text-center"
                    >
                        <p
                            class="mb-2 text-sm text-slate-500"
                        >
                            Sin subastas activas para
                            este objeto
                        </p>

                        <button
                            type="button"
                            @click="
                                addVariant({
                                    ilvl: null
                                })
                            "
                            class="rounded-lg border border-indigo-400/30 bg-indigo-500/10 px-3 py-1.5 text-xs text-indigo-300 hover:border-indigo-400/60"
                        >
                            Agregar de todas formas
                        </button>
                    </div>
                </template>
            </template>
        </div>

        <!-- ============================================================= -->
        <!-- IMPORT MODAL -->
        <!-- ============================================================= -->
        <Teleport to="body">
            <div
                v-if="isImportModalOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-2xl overflow-hidden rounded-2xl border border-indigo-400/20 bg-[#101226] shadow-[0_0_50px_10px_rgba(99,102,241,0.12)]"
                    @mousedown.stop
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between border-b border-white/5 px-5 py-4"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 text-sm font-semibold text-slate-100"
                            >
                                <Upload
                                    class="size-4 text-indigo-400"
                                />

                                Importar desde addon
                            </div>

                            <p
                                class="mt-1 text-xs text-slate-500"
                            >
                                Pega aquí el JSON generado
                                por Auction Terminal Tracker.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="
                                closeImportModal
                            "
                            :disabled="importing"
                            class="rounded-lg p-1.5 text-slate-500 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <X
                                class="size-4"
                            />
                        </button>
                    </div>

                    <!-- Instructions -->
                    <div
                        class="mx-5 mt-4 rounded-lg border border-indigo-400/10 bg-indigo-500/5 px-3 py-2.5"
                    >
                        <div class="flex gap-2">
                            <Info
                                class="mt-0.5 size-4 shrink-0 text-indigo-400"
                            />

                            <div
                                class="text-xs leading-relaxed text-slate-400"
                            >
                                <p
                                    class="mb-1 font-medium text-slate-300"
                                >
                                    ¿Cómo obtener el JSON?
                                </p>

                                <p>
                                    Abre la Auction House en
                                    WoW, ejecuta un escaneo
                                    con el addon y pulsa
                                    <span
                                        class="text-indigo-300"
                                    >
                                        Exportar
                                    </span>.
                                    Luego selecciona todo
                                    el contenido, copia con
                                    <span
                                        class="text-slate-300"
                                    >
                                        Ctrl+C
                                    </span>
                                    y pégalo aquí con
                                    <span
                                        class="text-slate-300"
                                    >
                                        Ctrl+V
                                    </span>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Textarea -->
                    <div class="px-5 pt-4">
                        <textarea
                            v-model="importJson"
                            :disabled="importing"
                            rows="12"
                            spellcheck="false"
                            placeholder='Pega aquí el JSON del addon...
Ejemplo:
{
  "version": 1,
  "realm": "Illidan",
  "region": "US",
  "items": [...]
}'
                            class="w-full resize-none rounded-xl border border-white/10 bg-black/20 px-3 py-3 font-mono text-xs leading-relaxed text-slate-300 outline-none placeholder:text-slate-600 focus:border-indigo-400/40 focus:ring-1 focus:ring-indigo-400/20 disabled:cursor-not-allowed disabled:opacity-60"
                        ></textarea>
                    </div>

                    <!-- Error -->
                    <div
                        v-if="importError"
                        class="mx-5 mt-3 flex items-start gap-2 rounded-lg border border-red-400/20 bg-red-500/5 px-3 py-2.5 text-xs text-red-300"
                    >
                        <AlertCircle
                            class="mt-0.5 size-4 shrink-0"
                        />

                        <span>
                            {{ importError }}
                        </span>
                    </div>

                    <!-- Result -->
                    <div
                        v-if="importResult"
                        class="mx-5 mt-3 rounded-lg border border-emerald-400/20 bg-emerald-500/5 px-4 py-3"
                    >
                        <div
                            class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-300"
                        >
                            <CheckCircle2
                                class="size-4"
                            />

                            Importación completada
                        </div>

                        <div
                            class="space-y-1 text-xs"
                        >
                            <!-- AGREGADOS -->
                            <div
                                class="flex items-center gap-2 text-slate-300"
                            >
                                <span
                                    class="text-emerald-400"
                                >
                                    ✓
                                </span>

                                <span>
                                    {{
                                        importResult.added
                                    }}
                                    items agregados
                                </span>
                            </div>

                            <!-- ACTUALIZADOS -->
                            <div
                                class="flex items-center gap-2 text-sky-400"
                            >
                                <span>
                                    ↻
                                </span>

                                <span>
                                    {{
                                        importResult.updated
                                    }}
                                    items actualizados
                                </span>
                            </div>

                            <!-- INVALIDOS -->
                            <div
                                v-if="
                                    importResult.invalid
                                "
                                class="flex items-center gap-2 text-amber-400"
                            >
                                <span>
                                    ⚠
                                </span>

                                <span>
                                    {{
                                        importResult.invalid
                                    }}
                                    items omitidos por datos
                                    incompletos
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="mt-4 flex items-center justify-end gap-2 border-t border-white/5 px-5 py-4"
                    >
                        <button
                            type="button"
                            @click="
                                closeImportModal
                            "
                            :disabled="importing"
                            class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-400 transition hover:border-white/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Cerrar
                        </button>

                        <button
                            type="button"
                            @click="
                                importFromAddon
                            "
                            :disabled="
                                importing ||
                                !importJson.trim()
                            "
                            class="flex items-center gap-2 rounded-lg border border-indigo-400/30 bg-indigo-500/15 px-4 py-2 text-sm font-medium text-indigo-300 transition hover:border-indigo-400/50 hover:bg-indigo-500/20 hover:text-indigo-200 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <Loader2
                                v-if="importing"
                                class="size-4 animate-spin"
                            />

                            <Upload
                                v-else
                                class="size-4"
                            />

                            {{
                                importing
                                    ? 'Importando...'
                                    : 'Importar items'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>