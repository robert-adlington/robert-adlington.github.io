import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Debug logging store
 * Captures application events for debugging
 */
export const useDebugStore = defineStore('debug', () => {
  // State
  const logs = ref([])
  const maxLogs = ref(500)
  const isEnabled = ref(true)

  // Actions
  function addLog(type, message, data = null) {
    if (!isEnabled.value) return

    const entry = {
      id: Date.now() + Math.random(),
      timestamp: new Date(),
      type, // 'info', 'warn', 'error', 'drag', 'api', 'watcher'
      message,
      data: data ? JSON.parse(JSON.stringify(data)) : null // Deep clone to avoid reactivity issues
    }

    logs.value.unshift(entry) // Add to beginning

    // Trim to max logs
    if (logs.value.length > maxLogs.value) {
      logs.value = logs.value.slice(0, maxLogs.value)
    }
  }

  function clearLogs() {
    logs.value = []
  }

  function toggleEnabled() {
    isEnabled.value = !isEnabled.value
  }

  return {
    // State
    logs,
    maxLogs,
    isEnabled,

    // Actions
    addLog,
    clearLogs,
    toggleEnabled
  }
})
