<script setup>
import Layout from './Layout.vue'
import ItemCard from './Components/ItemCard.vue';
import CategoryFilter from './Components/CategoryFilter.vue';

import { ref } from 'vue'
const props = defineProps({
    realms: Array
})
</script>

<template>
    <Layout :realms="realms">
        <main class="flex flex-col mt-5 items-center justify-center gap-5 w-[90vw] mx-auto">
            <CategoryFilter />
            <div class="grid grid-cols-5 gap-2 w-full">
                <ItemCard name="Storm Dust" quality="uncommon" :gold="95" :listings="2100" :volume="8908" />
                <ItemCard name="Runed Adamantite Rod" quality="rare" :gold="74" :listings="74" :volume="320" />
                <ItemCard name="Anillo Luminalba deslustrado" quality="epic" :gold="560" :listings="16" :volume="52" />
                <ItemCard name="Shadowed Parchment..." quality="common" :gold="1" :silver="20" :listings="1200"
                    :volume="5400" />
                <ItemCard name="Legguards of Spectral..." quality="epic" :gold="560" :listings="16" :volume="52" />
                <ItemCard name="Shadowed Parchment..." quality="common" :gold="1" :silver="20" :listings="1200"
                    :volume="5400" />
                <ItemCard name="Legguards of Spectral..." quality="epic" :gold="560" :listings="16" :volume="52" />
                <ItemCard name="Shadowed Parchment..." quality="common" :gold="1" :silver="20" :listings="1200"
                    :volume="5400" />
                <ItemCard name="Legguards of Spectral..." quality="epic" :gold="560" :listings="16" :volume="52" />
                <ItemCard name="Shadowed Parchment..." quality="common" :gold="1" :silver="20" :listings="1200"
                    :volume="5400" />
            </div>
        </main>
    </Layout>
</template>