import { ref, watch } from 'vue'

export function usePersistedRef(key, defaultValue) {
    let initial = defaultValue

    try {
        const stored = localStorage.getItem(key)
        if (stored !== null) initial = stored
    } catch {
        console.log("localStorage bloqueado (modo privado, cuota llena, etc.)")
    }

    const state = ref(initial)

    watch(state, (value) => {
        try {
            localStorage.setItem(key, value)
        } catch {
        }
    })

    return state
}