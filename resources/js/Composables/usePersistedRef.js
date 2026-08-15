import { ref, watch, onMounted } from 'vue'

export function usePersistedRef(key, defaultValue) {
    const state = ref(defaultValue) // inicia igual en servidor y cliente, sin mismatch

    onMounted(() => {
        try {
            const stored = localStorage.getItem(key)
            if (stored !== null) state.value = stored
        } catch {
            // localStorage bloqueado, se queda con el default
        }
    })

    watch(state, (value) => {
        try {
            localStorage.setItem(key, value)
        } catch { }
    })

    return state
}