import { ref, computed, watch } from 'vue'

const region = ref('US')
const realm = ref('')
let storageHydrated = false

export function useRealmSelection(realms) {
  if (!realm.value && realms?.length) {
    const DEFAULT_REALM = 'Illidan'
    realm.value = realms.find(r => r.name === DEFAULT_REALM)?.name ?? realms[0]?.name ?? ''
  }

  watch(realm, (value) => {
    if (typeof window === 'undefined') return
    try { localStorage.setItem('preferred_realm', value) } catch {}
  })

  const realmSlug = computed(() => realms?.find(r => r.name === realm.value)?.slug)

  function hydrateFromStorage() {
    if (storageHydrated || typeof window === 'undefined') return
    storageHydrated = true
    try {
      const stored = localStorage.getItem('preferred_realm')
      if (stored && realms?.some(r => r.name === stored)) {
        realm.value = stored
      }
    } catch {}
  }

  return { region, realm, realmSlug, hydrateFromStorage }
}