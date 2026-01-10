<template>
  <div
    class="category-card"
    :class="{
      'expanded': isExpanded,
      'drag-over': isDragOver && !category.is_system
    }"
    :data-card-id="category.id"
    :draggable="!category.is_system"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
    @dragover.prevent="handleDragOver"
    @dragleave="handleDragLeave"
    @drop.prevent="handleDrop"
  >
    <!-- Collapsed State -->
    <div
      v-if="!isExpanded"
      class="card-collapsed"
      @click="handleExpand"
    >
      <div class="card-icon">{{ categoryIcon }}</div>
      <div class="card-title">{{ category.name }} ({{ totalLinkCount }})</div>
    </div>

    <!-- Expanded State -->
    <div v-else class="card-expanded">
      <!-- Header -->
      <div class="card-expanded-header">
        <div class="flex items-center gap-2 flex-1 min-w-0" style="max-width: 100%; overflow: hidden;">
          <span class="text-sm flex-shrink-0">▼</span>
          <span class="card-icon flex-shrink-0">{{ categoryIcon }}</span>
          <span class="flex-1 min-w-0 truncate text-sm font-medium" :title="category.name + ' (' + totalLinkCount + ')'">{{ category.name }} ({{ totalLinkCount }})</span>
        </div>
        <div class="flex items-center gap-2">
          <button
            class="action-btn"
            @click.stop="toggleInfoPanel"
            title="Info & Actions"
          >
            ℹ️
          </button>
          <button
            class="action-btn"
            @click.stop="handleCollapse"
            title="Close"
          >
            ×
          </button>
        </div>
      </div>

      <!-- Info Panel (when ℹ️ clicked) -->
      <div v-if="showInfoPanel" class="info-panel">
        <div class="info-panel-header">ℹ️ Info</div>
        <div class="info-panel-content">
          <p v-if="category.description" class="text-sm text-gray-700 mb-3">
            {{ category.description }}
          </p>
          <p v-else class="text-sm text-gray-500 mb-3 italic">
            No description
          </p>
          <div class="flex flex-col gap-2">
            <button
              class="info-panel-btn"
              @click="handleEdit"
            >
              ✏️ Edit
            </button>
            <button
              class="info-panel-btn"
              @click="handleDelete"
            >
              🗑️ Delete
            </button>
            <button
              class="info-panel-btn"
              @click="handleStats"
            >
              📊 Stats
            </button>
          </div>
        </div>
      </div>

      <!-- Content: Mixed Links and Subcategories -->
      <div class="card-content">
        <!-- Render content items in order -->
        <template v-for="item in categoryContent" :key="item.type + '-' + item.id">
          <!-- Subcategory -->
          <div v-if="item.type === 'category'" class="content-item">
            <SubcategoryItem
              :subcategory="item.data"
              :depth="0"
              :expanded-ids="expandedSubcategoryIds"
              @toggle="handleToggleSubcategory"
              @edit="handleEditSubcategory"
              @delete="handleDeleteSubcategory"
              @link-click="handleLinkClick"
              @toggle-favorite="handleToggleFavorite"
              @category-moved="$emit('category-moved', $event)"
              @drag-start="$emit('drag-start')"
              @drag-end="$emit('drag-end')"
            />
          </div>

          <!-- Link -->
          <div v-else-if="item.type === 'link'" class="content-item">
            <LinkItem
              :link="item.data"
              :depth="0"
              @click="handleLinkClick(item.data)"
              @toggle-favorite="handleToggleFavorite(item.data)"
              @menu="handleLinkMenu(item.data)"
            />
          </div>
        </template>

        <!-- Empty state -->
        <div v-if="categoryContent.length === 0" class="text-sm text-gray-500 py-2 px-3">
          No links or subcategories
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import SubcategoryItem from './SubcategoryItem.vue'
import LinkItem from './LinkItem.vue'
import { linksApi } from '@/api/links'

const props = defineProps({
  category: {
    type: Object,
    required: true
  },
  isExpanded: {
    type: Boolean,
    default: false
  },
  categoryMap: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['expand', 'collapse', 'edit', 'delete', 'link-updated', 'category-moved', 'drag-start', 'drag-end'])

// State
const showInfoPanel = ref(false)
const categoryLinks = ref([])
const expandedSubcategoryIds = ref(new Set())
const loading = ref(false)
const isDragOver = ref(false)

// Computed
const categoryIcon = computed(() => {
  // You can customize this based on category metadata
  return props.category.icon || '📁'
})

// Recursive link count (includes subcategories)
const totalLinkCount = computed(() => {
  function countLinks(cat) {
    let count = cat.link_count || 0
    if (cat.children && cat.children.length > 0) {
      cat.children.forEach(child => {
        count += countLinks(child)
      })
    }
    return count
  }
  return countLinks(props.category)
})

// Build mixed content array of links and subcategories in their original order
const categoryContent = computed(() => {
  const content = []

  // Add subcategories
  if (props.category.children && props.category.children.length > 0) {
    props.category.children.forEach(child => {
      content.push({
        type: 'category',
        id: child.id,
        data: child,
        order: child.order_position || 0
      })
    })
  }

  // Add links
  categoryLinks.value.forEach(link => {
    content.push({
      type: 'link',
      id: link.id,
      data: link,
      order: link.order_position || 0
    })
  })

  // Sort by order_position if available, otherwise maintain array order
  content.sort((a, b) => a.order - b.order)

  return content
})

// Methods
function handleExpand() {
  emit('expand', props.category)
  loadCategoryLinks()
}

function handleCollapse() {
  emit('collapse', props.category)
  showInfoPanel.value = false
}

function toggleInfoPanel() {
  showInfoPanel.value = !showInfoPanel.value
}

function handleEdit() {
  emit('edit', props.category)
  showInfoPanel.value = false
}

function handleDelete() {
  emit('delete', props.category)
}

function handleStats() {
  // TODO: Implement stats view
  console.log('Show stats for category:', props.category)
}

function handleToggleSubcategory(subcategoryId) {
  if (expandedSubcategoryIds.value.has(subcategoryId)) {
    expandedSubcategoryIds.value.delete(subcategoryId)
  } else {
    expandedSubcategoryIds.value.add(subcategoryId)
  }
}

async function handleLinkClick(link) {
  try {
    await linksApi.recordAccess(link.id)
    window.open(link.url, '_blank')
  } catch (error) {
    console.error('Failed to record link access:', error)
  }
}

async function handleToggleFavorite(link) {
  try {
    await linksApi.updateLink(link.id, {
      is_favorite: !link.is_favorite
    })

    // Update local state
    const index = categoryLinks.value.findIndex(l => l.id === link.id)
    if (index !== -1) {
      categoryLinks.value[index].is_favorite = !categoryLinks.value[index].is_favorite
    }

    emit('link-updated', link)
  } catch (error) {
    console.error('Failed to toggle favorite:', error)
  }
}

function handleLinkMenu(event) {
  console.log('Link menu:', event)
  // TODO: Implement link context menu
}

function handleEditSubcategory(subcategory) {
  emit('edit', subcategory)
}

function handleDeleteSubcategory(subcategory) {
  emit('delete', subcategory)
}

// Drag and Drop handlers
function handleDragStart(event) {
  console.log('CategoryCard: handleDragStart called for', props.category.name)

  // Don't allow dragging system views
  if (props.category.is_system) {
    event.preventDefault()
    console.log('CategoryCard: Prevented drag of system view')
    return
  }

  // Collect all descendant IDs to prevent dropping into them
  const descendantIds = []
  function collectDescendants(cat) {
    if (cat.children && cat.children.length > 0) {
      cat.children.forEach(child => {
        descendantIds.push(child.id)
        collectDescendants(child)
      })
    }
  }
  collectDescendants(props.category)

  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('application/json', JSON.stringify({
    type: 'category',
    id: props.category.id,
    name: props.category.name,
    currentParentId: props.category.parent_id || null,
    descendantIds: descendantIds
  }))

  // Add a visual indicator
  event.currentTarget.style.opacity = '0.5'

  // Notify parent that dragging started
  console.log('CategoryCard: Emitting drag-start')
  emit('drag-start')
}

function handleDragEnd(event) {
  console.log('CategoryCard: handleDragEnd called')
  event.currentTarget.style.opacity = '1'
  isDragOver.value = false

  // Notify parent that dragging ended
  emit('drag-end')
}

function handleDragOver(event) {
  // Don't allow dropping on system views
  if (props.category.is_system) {
    event.dataTransfer.dropEffect = 'none'
    return
  }

  const data = event.dataTransfer.types.includes('application/json')
  if (data) {
    event.dataTransfer.dropEffect = 'move'
    isDragOver.value = true
  }
}

function handleDragLeave(event) {
  // Only clear if we're actually leaving the card
  if (event.target === event.currentTarget) {
    isDragOver.value = false
  }
}

async function handleDrop(event) {
  isDragOver.value = false

  // Don't allow dropping on system views
  if (props.category.is_system) {
    return
  }

  try {
    const data = JSON.parse(event.dataTransfer.getData('application/json'))

    // Don't drop on itself
    if (data.id === props.category.id) {
      return
    }

    // Don't drop a parent onto its own descendant
    // Check if the drop target (this category) is in the dragged category's descendant list
    if (data.descendantIds && data.descendantIds.includes(props.category.id)) {
      alert('Cannot move a category into its own descendant')
      return
    }

    // Emit event to parent to handle the API call
    emit('category-moved', {
      categoryId: data.id,
      newParentId: props.category.id,
      oldParentId: data.currentParentId
    })
  } catch (error) {
    console.error('Error handling drop:', error)
  }
}

function isDescendant(category, targetId) {
  if (!category.children || category.children.length === 0) {
    return false
  }

  for (const child of category.children) {
    if (child.id === targetId) {
      return true
    }
    if (isDescendant(child, targetId)) {
      return true
    }
  }

  return false
}

async function loadCategoryLinks() {
  if (loading.value) return

  loading.value = true
  try {
    const response = await linksApi.getLinks({
      category_id: props.category.id,
      sort: 'order_position',
      order: 'asc'
    })
    categoryLinks.value = response.links || []
  } catch (error) {
    console.error('Failed to load category links:', error)
  } finally {
    loading.value = false
  }
}

// Load links when component is mounted if already expanded
onMounted(() => {
  if (props.isExpanded) {
    loadCategoryLinks()
  }
})
</script>

<style scoped>
.category-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  transition: all 0.2s;
  overflow: hidden;
  width: 100%;
  max-width: 100%;
}

.category-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Collapsed State */
.card-collapsed {
  height: 120px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 1rem;
  gap: 0.5rem;
  transition: background-color 0.2s;
}

.card-collapsed:hover {
  background-color: #f9fafb;
}

.card-icon {
  font-size: 2rem;
}

.card-title {
  font-size: 0.875rem;
  font-weight: 500;
  text-align: center;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

/* Expanded State */
.card-expanded {
  display: flex;
  flex-direction: column;
  max-height: 600px;
}

.card-expanded-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  background-color: #f9fafb;
  gap: 0.5rem;
  min-width: 0;
  max-width: 100%;
}

.action-btn {
  padding: 0.25rem 0.5rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.2s;
  line-height: 1;
}

.action-btn:hover {
  background-color: #f3f4f6;
  border-color: #9ca3af;
}

/* Info Panel */
.info-panel {
  background-color: #fffbeb;
  border-bottom: 1px solid #fcd34d;
  padding: 0.75rem 1rem;
  max-width: 100%;
  overflow: hidden;
  word-wrap: break-word;
}

.info-panel-header {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
  color: #92400e;
}

.info-panel-content {
  font-size: 0.875rem;
}

.info-panel-btn {
  width: 100%;
  padding: 0.5rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.info-panel-btn:hover {
  background-color: #f9fafb;
  border-color: #9ca3af;
}

/* Content Area */
.card-content {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  width: 100%;
}

.content-item {
  width: 100%;
  max-width: 100%;
}

/* Drag and Drop */
.category-card[draggable="true"] {
  cursor: grab;
}

.category-card[draggable="true"]:active {
  cursor: grabbing;
}

.category-card.drag-over {
  border: 2px dashed #3b82f6;
  background-color: #eff6ff;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
</style>
