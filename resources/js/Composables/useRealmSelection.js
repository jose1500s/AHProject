import { ref, computed, watch, onMounted } from 'vue'

const region = ref('US')
const realm = ref('')
let hydrationRegistered = false // se registra UNA sola vez, sin importar cuántos componentes llamen a esto

export function useRealmSelection(realms) {
  if (!realm.value && realms?.length) {
    const DEFAULT_REALM = 'Illidan'
    realm.value = realms.find(r => r.name === DEFAULT_REALM)?.name ?? realms[0]?.name ?? ''
  }

  if (!hydrationRegistered) {
    hydrationRegistered = true
    onMounted(() => {
      try {
        const stored = localStorage.getItem('preferred_realm')
        if (stored && realms?.some(r => r.name === stored)) {
          realm.value = stored
        }
      } catch {}
    })
  }

  watch(realm, (value) => {
    try { localStorage.setItem('preferred_realm', value) } catch {}
  })

  const realmSlug = computed(() => realms?.find(r => r.name === realm.value)?.slug)

  return { region, realm, realmSlug }
}