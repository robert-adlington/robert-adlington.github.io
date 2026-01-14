<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import NestedSort from 'nested-sort'

// Sample data structure mimicking Adlinkton hierarchy
// Using parent property to define nesting
const initialData = [
  { id: 'cat-1', name: 'Development', type: 'category' },
  { id: 'cat-1-1', name: 'Frontend', type: 'category', parent: 'cat-1' },
  { id: 'link-1-1-1', name: 'Vue.js Documentation', type: 'link', url: 'https://vuejs.org', parent: 'cat-1-1' },
  { id: 'link-1-1-2', name: 'Tailwind CSS', type: 'link', url: 'https://tailwindcss.com', parent: 'cat-1-1' },
  { id: 'link-1-2', name: 'GitHub', type: 'link', url: 'https://github.com', parent: 'cat-1' },
  { id: 'cat-1-3', name: 'Backend', type: 'category', parent: 'cat-1' },
  { id: 'link-1-3-1', name: 'PHP Documentation', type: 'link', url: 'https://php.net', parent: 'cat-1-3' },

  { id: 'cat-2', name: 'Design', type: 'category' },
  { id: 'link-2-1', name: 'Figma', type: 'link', url: 'https://figma.com', parent: 'cat-2' },
  { id: 'link-2-2', name: 'Dribbble', type: 'link', url: 'https://dribbble.com', parent: 'cat-2' },

  { id: 'cat-3', name: 'Tools', type: 'category' },
  { id: 'cat-3-1', name: 'Productivity', type: 'category', parent: 'cat-3' },
  { id: 'link-3-2', name: 'Notion', type: 'link', url: 'https://notion.so', parent: 'cat-3' }
]

const sortableContainer = ref(null)
let nestedSortInstance = null
const currentData = ref([...initialData])
const dropHistory = ref([])
const lastDrop = ref(null)

// Custom render function for list items
const renderListItem = (el, item) => {
  // Clear default content
  el.innerHTML = ''

  // Determine icon based on type
  const icon = item.type === 'category' ? '📁' : '🔗'

  // Create item structure
  const wrapper = document.createElement('div')
  wrapper.className = `nested-sort-item ${item.type === 'category' ? 'is-category' : 'is-link'}`

  const iconSpan = document.createElement('span')
  iconSpan.className = 'item-icon'
  iconSpan.textContent = icon

  const nameSpan = document.createElement('span')
  nameSpan.className = 'item-name'
  nameSpan.textContent = item.name

  wrapper.appendChild(iconSpan)
  wrapper.appendChild(nameSpan)
  el.appendChild(wrapper)

  return el
}

// Initialize nested-sort on mount
onMounted(() => {
  if (sortableContainer.value) {
    nestedSortInstance = new NestedSort({
      el: sortableContainer.value,
      data: currentData.value,
      listClassNames: ['nested-sort-list'],
      listItemClassNames: ['nested-sort-list-item'],
      nestingLevels: 10, // Allow up to 10 levels of nesting
      propertyMap: {
        id: 'id',
        parent: 'parent'
      },
      renderListItem,
      actions: {
        onDrop(data) {
          // Update current data
          currentData.value = data

          // Log drop event
          const timestamp = new Date().toLocaleTimeString()
          lastDrop.value = {
            timestamp,
            itemCount: data.length
          }

          dropHistory.value.unshift({
            timestamp,
            dataSnapshot: JSON.parse(JSON.stringify(data))
          })

          // Keep only last 10 drops
          if (dropHistory.value.length > 10) {
            dropHistory.value = dropHistory.value.slice(0, 10)
          }

          console.log('Drop event - new structure:', data)
        }
      },
      init: true // Auto-initialize
    })
  }
})

// Cleanup on unmount
onBeforeUnmount(() => {
  if (nestedSortInstance) {
    nestedSortInstance.destroy()
  }
})

const clearHistory = () => {
  dropHistory.value = []
  lastDrop.value = null
}

const getItemById = (id, items = currentData.value) => {
  return items.find(item => item.id === id)
}

const getItemDepth = (id, items = currentData.value, depth = 0) => {
  const item = items.find(i => i.id === id)
  if (!item) return 0
  if (!item.parent) return depth
  return getItemDepth(item.parent, items, depth + 1)
}
</script>

<template>
  <div class="poc-container">
    <div class="poc-header">
      <h2>nested-sort Proof of Concept</h2>
      <p class="instructions">
        <strong>Try these interactions:</strong>
      </p>
      <ul class="instructions-list">
        <li>✅ Drag a link onto a category to nest it as a child</li>
        <li>✅ Drag a category onto another category to nest it</li>
        <li>✅ Drag items to reorder them within the same parent</li>
        <li>✅ Drag items left/right to change nesting level</li>
        <li>✅ Drag items between different levels of the hierarchy</li>
        <li>⚠️ Note: Does NOT support touch screens (desktop only)</li>
      </ul>
    </div>

    <div class="poc-content">
      <div class="tree-panel">
        <h3>Tree Structure</h3>
        <div class="tree-wrapper">
          <div ref="sortableContainer" class="nested-sort-container"></div>
        </div>
      </div>

      <div class="debug-panel">
        <div class="debug-header">
          <h3>Drop Events Log</h3>
          <button @click="clearHistory" class="clear-button">Clear</button>
        </div>

        <div v-if="lastDrop" class="last-drop">
          <h4>Latest Drop:</h4>
          <div class="drop-detail">
            <span class="drop-label">Time:</span>
            <span class="drop-value">{{ lastDrop.timestamp }}</span>
          </div>
          <div class="drop-detail">
            <span class="drop-label">Items:</span>
            <span class="drop-value">{{ lastDrop.itemCount }}</span>
          </div>
        </div>

        <div v-if="dropHistory.length > 0" class="history">
          <h4>History:</h4>
          <div v-for="(drop, index) in dropHistory" :key="index" class="history-item">
            <span class="history-time">{{ drop.timestamp }}</span>
            <span class="history-text">
              Reorganized {{ drop.dataSnapshot.length }} items
            </span>
          </div>
        </div>

        <div v-else class="no-drops">
          No drops yet. Try dragging items in the tree!
        </div>
      </div>
    </div>

    <div class="poc-footer">
      <div class="comparison">
        <h3>nested-sort Features:</h3>
        <ul>
          <li>✅ <strong>Vanilla JavaScript</strong> - Zero dependencies, framework agnostic</li>
          <li>✅ <strong>Drop onto item</strong> - Can drag onto collapsed items to nest them</li>
          <li>✅ <strong>Indent-based nesting</strong> - Drag left/right to change nesting level</li>
          <li>✅ <strong>Flat data structure</strong> - Uses parent property (like Adlinkton backend)</li>
          <li>✅ <strong>Auto-rendering</strong> - Library manages DOM for you</li>
          <li>⚠️ <strong>Limited customization</strong> - Less control over markup than vue-tree-dnd</li>
          <li>❌ <strong>No touch support</strong> - Desktop mouse only</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.poc-container {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
  font-family: system-ui, -apple-system, sans-serif;
  background: #f5f5f5;
  min-height: 100vh;
}

.poc-header {
  margin-bottom: 30px;
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.poc-header h2 {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 16px;
}

.instructions {
  font-size: 16px;
  color: #4b5563;
  margin-bottom: 12px;
}

.instructions-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.instructions-list li {
  padding: 6px 0;
  font-size: 14px;
  color: #6b7280;
}

.poc-content {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 24px;
  margin-bottom: 30px;
}

.tree-panel,
.debug-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tree-panel h3,
.debug-panel h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 16px;
  margin-top: 0;
}

.tree-wrapper {
  min-height: 400px;
}

.nested-sort-container {
  min-height: 400px;
}

.debug-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.clear-button {
  padding: 6px 12px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: background 0.15s;
}

.clear-button:hover {
  background: #dc2626;
}

.last-drop {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.last-drop h4 {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #15803d;
}

.drop-detail {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px solid #dcfce7;
}

.drop-detail:last-child {
  border-bottom: none;
}

.drop-label {
  font-size: 13px;
  color: #6b7280;
  font-weight: 500;
}

.drop-value {
  font-size: 13px;
  color: #1f2937;
  font-weight: 500;
}

.history h4 {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.history-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: #f9fafb;
  border-radius: 6px;
  margin-bottom: 6px;
  font-size: 12px;
}

.history-time {
  color: #9ca3af;
  font-size: 11px;
  min-width: 70px;
}

.history-text {
  flex: 1;
  color: #4b5563;
}

.no-drops {
  padding: 40px 20px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

.poc-footer {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.comparison h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.comparison ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.comparison li {
  padding: 8px 0;
  font-size: 14px;
  color: #4b5563;
  line-height: 1.6;
}

/* nested-sort library styles */
:deep(.nested-sort-list) {
  list-style: none;
  padding: 0;
  margin: 0;
}

:deep(.nested-sort-list-item) {
  margin: 4px 0;
  cursor: move;
}

:deep(.nested-sort-item) {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  transition: all 0.15s ease;
}

:deep(.nested-sort-item:hover) {
  background: #f9fafb;
  border-color: #d1d5db;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

:deep(.nested-sort-item.is-category) {
  background: #eff6ff;
  border-color: #bfdbfe;
  font-weight: 500;
}

:deep(.nested-sort-item.is-link) {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

:deep(.item-icon) {
  margin-right: 12px;
  font-size: 18px;
}

:deep(.item-name) {
  flex: 1;
  font-size: 14px;
  color: #1f2937;
}

/* Dragging states */
:deep(.ns-list--is-dragged-item-started) {
  opacity: 0.5;
  transform: rotate(2deg);
}

:deep(.ns-placeholder) {
  background: #dbeafe !important;
  border: 2px dashed #3b82f6 !important;
  min-height: 40px;
}

@media (max-width: 1024px) {
  .poc-content {
    grid-template-columns: 1fr;
  }
}
</style>
