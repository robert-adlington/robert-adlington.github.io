<template>
  <div class="subcategory-item">
    <!-- Subcategory Header -->
    <div
      class="subcategory-header"
      :style="{ paddingLeft: `${depth * 1}rem` }"
    >
      <button
        class="expand-btn"
        @click.stop="toggleExpand"
      >
        {{ isExpanded ? '▼' : '▶' }}
      </button>
      <span
        class="subcategory-name"
        @click="toggleExpand"
        :title="subcategory.name + ' (' + recursiveLinkCount + ')'"
      >
        {{ subcategory.name }} ({{ recursiveLinkCount }})
      </span>
      <button
        class="info-btn"
        @click.stop="toggleInfoPanel"
        title="Info & Actions"
      >
        ℹ️
      </button>
    </div>

    <!-- Info Panel (when ℹ️ clicked) -->
    <div v-if="showInfoPanel" class="subcategory-info-panel" :style="{ marginLeft: `${depth * 1}rem` }">
      <div class="info-panel-header">ℹ️ Info</div>
      <div class="info-panel-content">
        <p v-if="subcategory.description" class="text-sm text-gray-700 mb-2">
          {{ subcategory.description }}
        </p>
        <p v-else class="text-sm text-gray-500 mb-2 italic">
          No description
        </p>
        <div class="flex flex-col gap-1">
          <button class="info-panel-btn" @click="handleEdit">
            ✏️ Edit
          </button>
          <button class="info-panel-btn" @click="handleDelete">
            🗑️ Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Expanded Content -->
    <div v-if="isExpanded" class="subcategory-content">
      <VueDraggableNext
        v-model="contentItems"
        group="items"
        :animation="200"
        @change="handleContentChange"
      >
        <template v-for="item in contentItems" :key="item.type + '-' + item.id">
          <!-- Nested Subcategory -->
          <SubcategoryItem
            v-if="item.type === 'category'"
            :subcategory="item.data"
            :depth="depth + 1"
            :expanded-ids="expandedIds"
            :category-map="categoryMap"
            @toggle="$emit('toggle', $event)"
            @edit="$emit('edit', $event)"
            @delete="$emit('delete', $event)"
            @link-click="$emit('link-click', $event)"
            @toggle-favorite="$emit('toggle-favorite', $event)"
            @category-moved="$emit('category-moved', $event)"
          />

          <!-- Link -->
          <LinkItem
            v-else-if="item.type === 'link'"
            :link="item.data"
            :depth="depth + 1"
            @click="$emit('link-click', item.data)"
            @toggle-favorite="$emit('toggle-favorite', item.data)"
          />
        </template>
      </VueDraggableNext>

      <!-- Loading state -->
      <div v-if="loading" class="text-xs text-gray-500 py-1 px-3" :style="{ paddingLeft: `${(depth + 1) * 1}rem` }">
        Loading...
      </div>

      <!-- Empty state -->
      <div v-else-if="contentItems.length === 0" class="text-xs text-gray-500 py-1 px-3" :style="{ paddingLeft: `${(depth + 1) * 1}rem` }">
        No items
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { VueDraggableNext } from 'vue-draggable-next'
import LinkItem from './LinkItem.vue'
import { linksApi } from '@/api/links'
import { categoriesApi } from '@/api/categories'

const props = defineProps({
  subcategory: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    default: 0
  },
  expandedIds: {
    type: Set,
    default: () => new Set()
  },
  categoryMap: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['toggle', 'edit', 'delete', 'link-click', 'toggle-favorite', 'category-moved'])

// State
const showInfoPanel = ref(false)
const links = ref([])
const loading = ref(false)

// Computed
const isExpanded = computed(() => {
  return props.expandedIds.has(props.subcategory.id)
})

// Recursive link count
const recursiveLinkCount = computed(() => {
  function countLinks(cat) {
    let count = cat.link_count || 0
    if (cat.children && cat.children.length > 0) {
      cat.children.forEach(child => {
        count += countLinks(child)
      })
    }
    return count
  }
  return countLinks(props.subcategory)
})

// Build content items (subcategories + links) - now a ref for draggable
const contentItems = ref([])

// Watch subcategory.children and links to rebuild content
watch([() => props.subcategory.children, links], () => {
  const items = []

  // Add child subcategories
  if (props.subcategory.children && props.subcategory.children.length > 0) {
    props.subcategory.children.forEach(child => {
      items.push({
        type: 'category',
        id: child.id,
        data: child,
        order: child.sort_order || 0
      })
    })
  }

  // Add links
  links.value.forEach(link => {
    items.push({
      type: 'link',
      id: link.id,
      data: link,
      order: link.sort_order || 0
    })
  })

  // Sort by sort_order
  items.sort((a, b) => a.order - b.order)

  contentItems.value = items
}, { immediate: true, deep: true })

// Methods
function toggleExpand() {
  emit('toggle', props.subcategory.id)
}

function toggleInfoPanel() {
  showInfoPanel.value = !showInfoPanel.value
}

function handleEdit() {
  emit('edit', props.subcategory)
  showInfoPanel.value = false
}

function handleDelete() {
  emit('delete', props.subcategory)
}

// Check if a category has another category in its descendant tree
function hasDescendant(categoryId, potentialDescendantId) {
  const category = props.categoryMap[categoryId]
  if (!category || !category.children) return false

  for (const child of category.children) {
    if (child.id === potentialDescendantId) {
      return true
    }
    if (hasDescendant(child.id, potentialDescendantId)) {
      return true
    }
  }

  return false
}

// Handle drag and drop changes within this subcategory
async function handleContentChange(event) {
  console.log('Subcategory content changed:', event, 'in subcategory:', props.subcategory.name)

  try {
    let updated = false

    // Handle item added to this subcategory (from another category)
    if (event.added) {
      const item = event.added.element
      console.log('Added item:', item)
      const newIndex = event.added.newIndex

      if (!item) {
        console.warn('Added element is undefined')
        return
      }

      if (item.type === 'category') {
        // Check for circular reference: can't move category into its own descendant
        if (hasDescendant(item.id, props.subcategory.id)) {
          console.warn('Cannot move category into its own descendant')
          alert('Cannot move a category into its own subcategory')
          emit('category-moved') // Trigger reload to restore UI state
          return
        }

        // Update category parent_id and sort_order
        await categoriesApi.updateCategory(item.id, {
          parent_id: props.subcategory.id,
          sort_order: newIndex
        })
        updated = true
      } else if (item.type === 'link') {
        // TODO: Link reordering not yet supported by backend
        console.warn('Link reordering not yet implemented in backend')
        return
      }
    }

    // Handle item moved within this subcategory (reorder)
    if (event.moved) {
      const item = event.moved.element
      console.log('Moved item:', item)
      const newIndex = event.moved.newIndex

      if (!item) {
        console.warn('Moved element is undefined')
        return
      }

      if (item.type === 'category') {
        await categoriesApi.reorderCategory(item.id, {
          parent_id: props.subcategory.id,
          sort_order: newIndex
        })
        updated = true
      } else if (item.type === 'link') {
        // TODO: Link reordering not yet supported by backend
        console.warn('Link reordering not yet implemented in backend')
        return
      }
    }

    // Trigger parent to reload entire category tree
    if (updated) {
      emit('category-moved')
    }
  } catch (error) {
    console.error('Failed to update item position:', error)
    alert('Failed to update item position. Please refresh the page.')
    // Trigger reload to restore consistent state
    emit('category-moved')
  }
}

async function loadLinks() {
  loading.value = true
  try {
    const response = await linksApi.getLinks({
      category_id: props.subcategory.id,
      sort: 'manual',
      order: 'asc'
    })
    links.value = response.links || []
  } catch (error) {
    console.error('Failed to load subcategory links:', error)
  } finally {
    loading.value = false
  }
}

// Watch for expansion and load links
watch(isExpanded, (newVal) => {
  if (newVal && links.value.length === 0) {
    loadLinks()
  }
})
</script>

<style scoped>
.subcategory-item {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.subcategory-header {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  gap: 0.5rem;
  transition: all 0.2s;
  min-height: 36px;
  min-width: 0;
  max-width: 100%;
  cursor: grab;
}

.subcategory-header:hover {
  background-color: #f9fafb;
}

.subcategory-header:active {
  cursor: grabbing;
}

.expand-btn {
  font-size: 0.75rem;
  color: #6b7280;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
  width: 1.5rem;
  text-align: center;
  flex-shrink: 0;
}

.subcategory-name {
  flex: 1;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.info-btn {
  font-size: 0.875rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  padding: 0.125rem 0.375rem;
  cursor: pointer;
  opacity: 0;
  transition: all 0.2s;
  flex-shrink: 0;
}

.subcategory-header:hover .info-btn {
  opacity: 1;
}

.info-btn:hover {
  background-color: #f3f4f6;
  border-color: #9ca3af;
}

.subcategory-info-panel {
  background-color: #fffbeb;
  border-left: 3px solid #fcd34d;
  padding: 0.5rem 0.75rem;
  margin-top: 0.25rem;
  margin-bottom: 0.25rem;
  max-width: 100%;
  overflow: hidden;
  word-wrap: break-word;
}

.info-panel-header {
  font-weight: 600;
  font-size: 0.75rem;
  margin-bottom: 0.25rem;
  color: #92400e;
}

.info-panel-btn {
  width: 100%;
  padding: 0.375rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.75rem;
}

.info-panel-btn:hover {
  background-color: #f9fafb;
  border-color: #9ca3af;
}

.subcategory-content {
  width: 100%;
}

/* Drag and Drop */
.subcategory-header[draggable="true"] {
  cursor: grab;
}

.subcategory-header[draggable="true"]:active {
  cursor: grabbing;
}

.subcategory-item.drag-over > .subcategory-header {
  background-color: #eff6ff;
  border-left: 3px solid #3b82f6;
  box-shadow: inset 0 0 0 1px #3b82f6;
}
</style>
