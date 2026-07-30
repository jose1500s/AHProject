<script setup>
import InputText from './Components/InputText.vue';

</script>

<template>
    <slot></slot>
    <nav class=" flex items-center justify-center gap-5 w-[80vw] h-20 mx-auto border-amber-200 border-solid border-2">
        <div class="w-1/6 flex h-full gap-2.5 items-center border-red-500 border-solid border-2">
            <div>
                <h3>LOGO</h3>
            </div>
            <div>
                <div>
                    <h1>Auction Terminal</h1>
                </div>
                <div>
                    <h4 class="text-sm">Market Analytics</h4>
                </div>
            </div>
        </div>
        <div class="w-1/2 h-full flex items-center border-red-500 border-solid border-2">
            <InputText
            type="text"
            placeholder="Buscar Objeto..."
             />
        </div>
        <div class="w-[30%] h-full flex items-center border-red-500 border-solid border-2">
            <h3>filtros etc</h3>
        </div>
    </nav>
</template>