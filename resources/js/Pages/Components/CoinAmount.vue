<script setup>
defineProps({
    gold: { type: [Number, String], default: 0 },
    silver: { type: [Number, String], default: 0 },
    copper: { type: [Number, String], default: 0 },
    size: { type: String, default: 'text-sm' },
})

function formatNumber(n) {
    return (n ?? 0).toLocaleString('es-MX')
}
</script>

<template>
    <span class="inline-flex items-center gap-1.5" :class="size">
        <span class="inline-flex items-center gap-1 font-semibold text-amber-400">
            {{ formatNumber(gold) }}<span class="size-2 rounded-full bg-amber-400"></span>
        </span>
        <span class="inline-flex items-center gap-1 font-semibold text-slate-300">
            {{ silver }}<span class="size-2 rounded-full bg-slate-300"></span>
        </span>
        <span class="inline-flex items-center gap-1 font-semibold text-shadow-amber-900">
            {{ copper }}<span class="size-2 rounded-full bg-amber-900"></span>
        </span>
    </span>
</template>