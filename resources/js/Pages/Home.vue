<script setup>
import Layout from './Layout.vue'
import ItemCard from './Components/ItemCard.vue';

import { ref } from 'vue'
const props = defineProps({
    realms: Array
})
</script>

<template>
    <Layout :realms="realms">
        <div class="flex mt-5 items-center justify-center gap-5 w-[90vw] mx-auto">
            <ItemCard 
                name="Flor de Tranquilidad"
                subtitle="Planta · Herboristeria"
                :gold="118"
                :silver="40"
                :copper="20"
                :listings="312"
                :volume="1820" 
            />
        </div>
    </Layout>
</template>