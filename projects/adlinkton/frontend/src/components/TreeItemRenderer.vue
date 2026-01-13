<script setup>
import { computed } from 'vue'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    required: true
  },
  expanded: {
    type: Boolean,
    required: true
  },
  setExpanded: {
    type: Function,
    required: true
  }
})

// Determine if item is a category or link
const isCategory = computed(() => props.item.type === 'category')
const hasChildren = computed(() => props.item.children && props.item.children.length > 0)

// Calculate indentation based on depth
const indentStyle = computed(() => ({
  paddingLeft: `${props.depth * 20}px`
}))

// Icon based on type and expanded state
const icon = computed(() => {
  if (!isCategory.value) {
    return '🔗' // Link icon
  }
  if (!hasChildren.value) {
    return '📁' // Empty folder
  }
  return props.expanded ? '📂' // Open folder
                        : '📁' // Closed folder
})

const toggleExpand = () => {
  if (hasChildren.value) {
    props.setExpanded(!props.expanded)
  }
}
</script>

<template>
  <div
    class="tree-item"
    :class="{
      'is-category': isCategory,
      'is-link': !isCategory,
      'is-expanded': expanded,
      'has-children': hasChildren
    }"
    :style="indentStyle"
  >
    <button
      v-if="hasChildren"
      @click="toggleExpand"
      class="expand-button"
    >
      {{ expanded ? '▼' : '▶' }}
    </button>
    <span v-else class="expand-placeholder"></span>

    <span class="item-icon">{{ icon }}</span>

    <span class="item-name">{{ item.name }}</span>

    <span v-if="isCategory && hasChildren" class="item-count">
      ({{ item.children.length }})
    </span>
  </div>
</template>

<style scoped>
.tree-item {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  margin: 2px 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: grab;
  user-select: none;
  transition: all 0.15s ease;
}

.tree-item:hover {
  background: #f9fafb;
  border-color: #d1d5db;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tree-item:active {
  cursor: grabbing;
}

.tree-item.is-category {
  background: #eff6ff;
  border-color: #bfdbfe;
  font-weight: 500;
}

.tree-item.is-link {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.expand-button {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  cursor: pointer;
  color: #6b7280;
  font-size: 12px;
  margin-right: 4px;
  padding: 0;
}

.expand-button:hover {
  color: #1f2937;
}

.expand-placeholder {
  width: 20px;
  height: 20px;
  display: inline-block;
  margin-right: 4px;
}

.item-icon {
  margin-right: 8px;
  font-size: 16px;
}

.item-name {
  flex: 1;
  font-size: 14px;
  color: #1f2937;
}

.item-count {
  font-size: 12px;
  color: #6b7280;
  margin-left: 8px;
}

/* Dragging state styles (applied by vue-tree-dnd) */
:deep(.dragging) {
  opacity: 0.5;
}

:deep(.drop-zone) {
  background: #dbeafe;
  border: 2px dashed #3b82f6;
}
</style>
