<script setup>
defineProps({
    type: String,
    placeholder: String,
    width: String,
    modelValue: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
    <div class="relative" :style="{ width }">
        <input :type="type" :placeholder="placeholder" :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)" class="w-full h-2/3 rounded-lg border border-[#f8fafc9f] bg-transparent py-2 pl-9 pr-3 text-md
             text-slate-100 placeholder:text-slate-400 outline-none
             transition-all duration-300 ease-out
             shadow-[0_0_3px_0_rgba(129,140,248,0.1)]
             focus:border-indigo-400
             focus:shadow-[0_0_5px_0_rgba(129,140,248,0.3),0_0_10px_2px_rgba(129,140,248,0.15)]" />
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 size-6
             text-gray-400 transition-colors duration-300 peer-focus:text-indigo-400">
            <path fill="currentColor"
                d="M9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l5.6 5.6q.275.275.275.7t-.275.7t-.7.275t-.7-.275l-5.6-5.6q-.75.6-1.725.95T9.5 16m0-2q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14" />
        </svg>
    </div>
</template>