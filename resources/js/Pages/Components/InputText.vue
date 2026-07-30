<script setup>
import { ref } from 'vue';
const props = defineProps({
    type: String,
    placeholder: String
})
</script>
<template>
    <input :type="type"
    :placeholder="placeholder"
    class="border-red-50 border-solid border-2">
</template>