import { createInertiaApp } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'
import '../css/app.css'

createInertiaApp({
    withApp(app) {
        app.use(VueApexCharts)
        app.component('apexchart', VueApexCharts)
    },
})