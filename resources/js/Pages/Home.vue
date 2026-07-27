<script setup>
import { ref } from 'vue'
const props = defineProps({
    test: String
})
</script>

<template>
        <h1>Hello world{{ props.test }}</h1>
</template>