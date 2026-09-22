import { createInertiaApp } from '@inertiajs/vue3'
import { createNotivue } from 'notivue'
import '../css/app.css'
import 'notivue/notification.css'
import 'notivue/animations.css'

const notivue = createNotivue()

createInertiaApp({
    withApp(app) {
        app.use(notivue)
    },
})