<script setup>
import { ref } from 'vue'
import VueTreeDnd from 'vue-tree-dnd'
import TreeItemRenderer from './TreeItemRenderer.vue'

// Sample data structure mimicking Adlinkton hierarchy
// Mix of categories and links at various nesting levels
const tree = ref([
  {
    id: 'cat-1',
    name: 'Development',
    type: 'category',
    expanded: true,
    children: [
      {
        id: 'cat-1-1',
        name: 'Frontend',
        type: 'category',
        expanded: false,
        children: [
          {
            id: 'link-1-1-1',
            name: 'Vue.js Documentation',
            type: 'link',
            url: 'https://vuejs.org',
            expanded: false,
            children: []
          },
          {
            id: 'link-1-1-2',
            name: 'Tailwind CSS',
            type: 'link',
            url: 'https://tailwindcss.com',
            expanded: false,
            children: []
          }
        ]
      },
      {
        id: 'link-1-2',
        name: 'GitHub',
        type: 'link',
        url: 'https://github.com',
        expanded: false,
        children: []
      },
      {
        id: 'cat-1-3',
        name: 'Backend',
        type: 'category',
        expanded: false,
        children: [
          {
            id: 'link-1-3-1',
            name: 'PHP Documentation',
            type: 'link',
            url: 'https://php.net',
            expanded: false,
            children: []
          }
        ]
      }
    ]
  },
  {
    id: 'cat-2',
    name: 'Design',
    type: 'category',
    expanded: false,
    children: [
      {
        id: 'link-2-1',
        name: 'Figma',
        type: 'link',
        url: 'https://figma.com',
        expanded: false,
        children: []
      },
      {
        id: 'link-2-2',
        name: 'Dribbble',
        type: 'link',
        url: 'https://dribbble.com',
        expanded: false,
        children: []
      }
    ]
  },
  {
    id: 'cat-3',
    name: 'Tools',
    type: 'category',
    expanded: true,
    children: [
      {
        id: 'cat-3-1',
        name: 'Productivity',
        type: 'category',
        expanded: false,
        children: []
      },
      {
        id: 'link-3-2',
        name: 'Notion',
        type: 'link',
        url: 'https://notion.so',
        expanded: false,
        children: []
      }
    ]
  }
])

// Track move events for analysis
const moveHistory = ref([])
const lastMove = ref(null)

const handleMove = (mutation) => {
  console.log('Move event:', mutation)

  lastMove.value = {
    itemId: mutation.id,
    targetId: mutation.targetId,
    position: mutation.position,
    timestamp: new Date().toLocaleTimeString()
  }

  moveHistory.value.unshift({...lastMove.value})

  // Keep only last 10 moves
  if (moveHistory.value.length > 10) {
    moveHistory.value = moveHistory.value.slice(0, 10)
  }

  // Apply the move to the tree
  applyMove(mutation)
}

const applyMove = (mutation) => {
  // Find the item being moved and remove it from its current location
  const movedItem = findAndRemoveItem(tree.value, mutation.id)
  if (!movedItem) {
    console.error('Could not find item to move:', mutation.id)
    return
  }

  // Find the target and insert the item
  const success = insertItem(tree.value, mutation.targetId, movedItem, mutation.position)
  if (!success) {
    console.error('Could not insert item at target:', mutation.targetId)
    // Re-add the item at root if insertion failed
    tree.value.push(movedItem)
  }
}

const findAndRemoveItem = (items, id) => {
  for (let i = 0; i < items.length; i++) {
    if (items[i].id === id) {
      return items.splice(i, 1)[0]
    }
    if (items[i].children && items[i].children.length > 0) {
      const found = findAndRemoveItem(items[i].children, id)
      if (found) return found
    }
  }
  return null
}

const insertItem = (items, targetId, item, position) => {
  for (let i = 0; i < items.length; i++) {
    if (items[i].id === targetId) {
      switch (position) {
        case 'LEFT':
          items.splice(i, 0, item)
          return true
        case 'RIGHT':
          items.splice(i + 1, 0, item)
          return true
        case 'FIRST_CHILD':
          if (!items[i].children) items[i].children = []
          items[i].children.unshift(item)
          items[i].expanded = true // Auto-expand to show the new child
          return true
        case 'LAST_CHILD':
          if (!items[i].children) items[i].children = []
          items[i].children.push(item)
          items[i].expanded = true // Auto-expand to show the new child
          return true
      }
    }
    if (items[i].children && items[i].children.length > 0) {
      const success = insertItem(items[i].children, targetId, item, position)
      if (success) return true
    }
  }
  return false
}

const getItemName = (id) => {
  const findName = (items) => {
    for (const item of items) {
      if (item.id === id) return item.name
      if (item.children) {
        const found = findName(item.children)
        if (found) return found
      }
    }
    return null
  }
  return findName(tree.value) || id
}

const clearHistory = () => {
  moveHistory.value = []
  lastMove.value = null
}
</script>

<template>
  <div class="poc-container">
    <div class="poc-header">
      <h2>vue-tree-dnd Proof of Concept</h2>
      <p class="instructions">
        <strong>Try these interactions:</strong>
      </p>
      <ul class="instructions-list">
        <li>✅ Drag a link onto a category to nest it as a child</li>
        <li>✅ Drag a category onto another category to nest it</li>
        <li>✅ Drag items to reorder them within the same parent</li>
        <li>✅ Drag items between different levels of the hierarchy</li>
        <li>✅ Observe how the drop zone clearly indicates where items will be placed</li>
      </ul>
    </div>

    <div class="poc-content">
      <div class="tree-panel">
        <h3>Tree Structure</h3>
        <div class="tree-wrapper">
          <VueTreeDnd
            v-model="tree"
            :component="TreeItemRenderer"
            @move="handleMove"
          />
        </div>
      </div>

      <div class="debug-panel">
        <div class="debug-header">
          <h3>Move Events Log</h3>
          <button @click="clearHistory" class="clear-button">Clear</button>
        </div>

        <div v-if="lastMove" class="last-move">
          <h4>Latest Move:</h4>
          <div class="move-detail">
            <span class="move-label">Item:</span>
            <span class="move-value">{{ getItemName(lastMove.itemId) }}</span>
          </div>
          <div class="move-detail">
            <span class="move-label">Target:</span>
            <span class="move-value">{{ getItemName(lastMove.targetId) }}</span>
          </div>
          <div class="move-detail">
            <span class="move-label">Position:</span>
            <span class="move-value position-badge" :class="lastMove.position.toLowerCase()">
              {{ lastMove.position }}
            </span>
          </div>
          <div class="move-detail">
            <span class="move-label">Time:</span>
            <span class="move-value">{{ lastMove.timestamp }}</span>
          </div>
        </div>

        <div v-if="moveHistory.length > 0" class="history">
          <h4>History:</h4>
          <div v-for="(move, index) in moveHistory" :key="index" class="history-item">
            <span class="history-time">{{ move.timestamp }}</span>
            <span class="history-text">
              {{ getItemName(move.itemId) }} → {{ getItemName(move.targetId) }}
            </span>
            <span class="position-badge small" :class="move.position.toLowerCase()">
              {{ move.position }}
            </span>
          </div>
        </div>

        <div v-else class="no-moves">
          No moves yet. Try dragging items in the tree!
        </div>
      </div>
    </div>

    <div class="poc-footer">
      <div class="comparison">
        <h3>Key Differences from vue-draggable-next:</h3>
        <ul>
          <li>✅ <strong>Drop onto item</strong> - Can drag onto collapsed items to nest them</li>
          <li>✅ <strong>Clear position indicators</strong> - Shows FIRST_CHILD, LAST_CHILD, LEFT, RIGHT</li>
          <li>✅ <strong>Tree-specific design</strong> - Built for hierarchical structures</li>
          <li>⚠️ <strong>Different event model</strong> - Uses @move instead of @change</li>
          <li>⚠️ <strong>Manual tree manipulation</strong> - Need to implement findAndRemove/insert logic</li>
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
}

.poc-header {
  margin-bottom: 30px;
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

.last-move {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.last-move h4 {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #15803d;
}

.move-detail {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px solid #dcfce7;
}

.move-detail:last-child {
  border-bottom: none;
}

.move-label {
  font-size: 13px;
  color: #6b7280;
  font-weight: 500;
}

.move-value {
  font-size: 13px;
  color: #1f2937;
  font-weight: 500;
}

.position-badge {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.position-badge.small {
  padding: 2px 6px;
  font-size: 10px;
}

.position-badge.first_child {
  background: #dbeafe;
  color: #1e40af;
}

.position-badge.last_child {
  background: #e0e7ff;
  color: #4338ca;
}

.position-badge.left {
  background: #fef3c7;
  color: #92400e;
}

.position-badge.right {
  background: #fed7aa;
  color: #9a3412;
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

.no-moves {
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

@media (max-width: 1024px) {
  .poc-content {
    grid-template-columns: 1fr;
  }
}
</style>
