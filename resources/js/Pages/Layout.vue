<script setup>
import { ref } from 'vue'
import InputText from './Components/InputText.vue';
import CustomSelect from './Components/CustomSelect.vue';

const region = ref('US')
const realm = ref('Illidan')

const realms = ['Illidan', 'Stormrage', 'Tichondrius', "Mal'Ganis", 'Area 52', 'Sargeras', 'Bleeding Hollow', 'Frostmourne']

</script>

<template>
    <slot></slot>
    <div class="w-full" id="navbar">
        <nav class=" flex items-center justify-center gap-5 w-[90vw] h-20 mx-auto">
            <div class="w-1/6 flex h-full gap-2.5 items-center">
                <div>
                    <img src="../../../public/imgs/Logo.svg">
                </div>
            </div>
            <div class="w-1/2 h-full flex items-center">
                <InputText type="text" placeholder="Buscar por objeto..." width="75%" />
            </div>
            <div class="w-[30%] h-full flex items-center">
                <div class="flex gap-3">
                    <CustomSelect v-model="region" :options="['US', 'EU', 'KR', 'TW']" label="Region" />
                    <CustomSelect v-model="realm" :options="realms" label="Realm" />
                </div>
            </div>
        </nav>
    </div>
</template>

<style scoped>
#navbar {
    border-bottom: 0.5px solid #f8fafc48;
}
</style>