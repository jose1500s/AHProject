import { ref, computed, watch, onMounted } from 'vue'

// declarados FUERA de la función = una sola instancia compartida por toda la app
const region = ref('US')
const realm = ref('')
let hydrated = false

export function useRealmSelection(realms) {
  if (!realm.value && realms?.length) {
    const DEFAULT_REALM = 'Illidan'
    realm.value = realms.find(r => r.name === DEFAULT_REALM)?.name ?? realms[0]?.name ?? ''
  }

  onMounted(() => {
    if (hydrated) return
    hydrated = true

    try {
      const stored = localStorage.getItem('preferred_realm')
      if (stored && realms?.some(r => r.name === stored)) {
        realm.value = stored
      }
    } catch {}
  })

  watch(realm, (value) => {
    try { localStorage.setItem('preferred_realm', value) } catch {}
  })

  const realmSlug = computed(() => realms?.find(r => r.name === realm.value)?.slug)

  return { region, realm, realmSlug }
}