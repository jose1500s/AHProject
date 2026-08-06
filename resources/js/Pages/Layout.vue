<script setup>
import { ref } from 'vue'
import InputText from './Components/InputText.vue';
import CustomSelect from './Components/CustomSelect.vue';
import TimeAgoBadge from './Components/TimeAgoBadge.vue';
import RefreshButton from './Components/RefreshButton.vue';
import { usePersistedRef } from '../Composables/usePersistedRef.js'


const props = defineProps({
    realms: Array
})

const region = ref('US')
const DEFAULT_REALM = 'Illidan'
const fallbackRealm = props.realms.find(r => r.name === DEFAULT_REALM)?.name ?? props.realms[0]?.name ?? ''

const realm = usePersistedRef('preferred_realm', fallbackRealm)

if (!props.realms.some(r => r.name === realm.value)) {
  realm.value = fallbackRealm
}


function onRefresh() {
    // llamada para refrescar datos
}

</script>

<template>
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
                    <CustomSelect v-model="region" :options="['US']" label="Region" />
                    <CustomSelect v-model="realm" :options="realms.map(r => r.name)" label="Realm" />
                </div>
                <div class="flex items-center gap-3 mt-5 ml-4">
                    <TimeAgoBadge label="1h ago" />
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