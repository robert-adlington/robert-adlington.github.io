<template>
  <div v-if="isVisible" class="debug-panel">
    <!-- Header -->
    <div class="debug-header">
      <span class="debug-title">Debug Logs ({{ debugStore.logs.length }})</span>
      <div class="debug-controls">
        <button @click="debugStore.clearLogs()" class="debug-btn" title="Clear logs">
          🗑️
        </button>
        <button @click="debugStore.toggleEnabled()" class="debug-btn" :class="{ inactive: !debugStore.isEnabled }" title="Toggle logging">
          {{ debugStore.isEnabled ? '⏸️' : '▶️' }}
        </button>
        <button @click="isVisible = false" class="debug-btn" title="Close">
          ×
        </button>
      </div>
    </div>

    <!-- Logs -->
    <div class="debug-content">
      <div v-for="log in debugStore.logs" :key="log.id" class="debug-log-entry" :class="`log-${log.type}`">
        <div class="log-header">
          <span class="log-time">{{ formatTime(log.timestamp) }}</span>
          <span class="log-type">{{ log.type }}</span>
          <span class="log-message">{{ log.message }}</span>
        </div>
        <div v-if="log.data" class="log-data">
          <pre>{{ formatData(log.data) }}</pre>
        </div>
      </div>
      <div v-if="debugStore.logs.length === 0" class="debug-empty">
        No logs yet. Perform actions to see debug information.
      </div>
    </div>
  </div>

  <!-- Toggle Button (always visible) -->
  <button v-if="!isVisible" @click="isVisible = true" class="debug-toggle" title="Open debug panel">
    🐛
  </button>
</template>

<script setup>
import { ref } from 'vue'
import { useDebugStore } from '../stores/debugStore'

const debugStore = useDebugStore()
const isVisible = ref(false)

function formatTime(timestamp) {
  return timestamp.toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    fractionalSecondDigits: 3
  })
}

function formatData(data) {
  if (!data) return ''
  return JSON.stringify(data, null, 2)
}
</script>

<style scoped>
.debug-panel {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 600px;
  max-height: 500px;
  background: white;
  border: 2px solid #3b82f6;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  z-index: 9999;
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 12px;
}

.debug-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #3b82f6;
  color: white;
  border-radius: 6px 6px 0 0;
  font-weight: 600;
}

.debug-title {
  font-size: 13px;
}

.debug-controls {
  display: flex;
  gap: 4px;
}

.debug-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  transition: background 0.2s;
}

.debug-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

.debug-btn.inactive {
  background: rgba(255, 0, 0, 0.3);
}

.debug-content {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
  background: #f9fafb;
}

.debug-log-entry {
  margin-bottom: 8px;
  padding: 6px 8px;
  background: white;
  border-left: 3px solid #9ca3af;
  border-radius: 4px;
}

.log-drag {
  border-left-color: #8b5cf6;
}

.log-api {
  border-left-color: #10b981;
}

.log-watcher {
  border-left-color: #f59e0b;
}

.log-error {
  border-left-color: #ef4444;
  background: #fef2f2;
}

.log-warn {
  border-left-color: #f59e0b;
  background: #fffbeb;
}

.log-header {
  display: flex;
  gap: 8px;
  align-items: baseline;
}

.log-time {
  color: #6b7280;
  font-size: 10px;
  font-weight: 600;
}

.log-type {
  color: #3b82f6;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 10px;
}

.log-message {
  color: #1f2937;
  flex: 1;
}

.log-data {
  margin-top: 4px;
  padding: 6px;
  background: #f3f4f6;
  border-radius: 3px;
  max-height: 200px;
  overflow: auto;
}

.log-data pre {
  margin: 0;
  font-size: 11px;
  color: #374151;
  white-space: pre-wrap;
  word-break: break-all;
}

.debug-empty {
  text-align: center;
  color: #9ca3af;
  padding: 40px 20px;
  font-size: 13px;
}

.debug-toggle {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 48px;
  height: 48px;
  background: #3b82f6;
  border: none;
  border-radius: 50%;
  font-size: 24px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
  z-index: 9998;
  transition: transform 0.2s, box-shadow 0.2s;
}

.debug-toggle:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.5);
}
</style>
