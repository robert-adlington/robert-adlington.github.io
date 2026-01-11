<template>
  <div class="category-grid-container">
    <div v-if="loading" class="text-center py-8 text-gray-500">
      Loading categories...
    </div>

    <div v-else-if="getTotalCardCount() === 0" class="text-center py-8 text-gray-500">
      No categories found. (Debug: categories={{ categories.length }}, systemViews={{ systemViews.length }})
    </div>

    <!-- 4 Column Layout with independent draggable containers -->
    <div v-else class="column-container">
      <div
        v-for="columnNum in 4"
        :key="columnNum"
        class="column"
      >
        <VueDraggableNext
          :model-value="getColumnCards(columnNum)"
          @update:model-value="updateColumn(columnNum, $event)"
          group="items"
          class="column-drop-zone"
          :animation="200"
          ghost-class="ghost-card"
          @change="handleDragChange($event, columnNum)"
        >
          <CategoryCard
            v-for="card in getColumnCards(columnNum)"
            :key="card.type + '-' + card.id"
            :category="card.data"
            :is-expanded="expandedIds.has(card.id)"
            :category-map="categoryMap"
            @expand="handleExpand(card.id)"
            @collapse="handleCollapse(card.id)"
            @edit="handleEdit"
            @delete="handleDelete"
            @link-updated="handleLinkUpdated"
            @category-moved="handleCategoryMoved"
          />
        </VueDraggableNext>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { VueDraggableNext } from 'vue-draggable-next'
import CategoryCard from './CategoryCard.vue'
import { categoriesApi } from '@/api/categories'
import { linksApi } from '@/api/links'
import { useDebugStore } from '@/stores/debugStore'

const debugStore = useDebugStore()
const emit = defineEmits(['category-selected', 'edit-category', 'delete-category', 'link-updated'])

// State
const categories = ref([])
const systemViews = ref([])
const expandedIds = ref(new Set())
const categoryMap = ref({})
const loading = ref(true)

// Load data on mount
onMounted(async () => {
  await Promise.all([
    loadCategories(),
    loadSystemViewCounts()
  ])
})

// Load categories
async function loadCategories() {
  loading.value = true
  debugStore.addLog('api', 'CategoryGrid: Loading categories from server')
  try {
    const response = await categoriesApi.getCategories()
    categories.value = response.categories || []
    categoryMap.value = buildCategoryMap(response.categories || [])
    debugStore.addLog('api', 'CategoryGrid: Loaded categories', {
      count: categories.value.length,
      rootCategories: categories.value.map(c => ({ id: c.id, name: c.name, childCount: c.children?.length || 0 }))
    })
  } catch (error) {
    console.error('Failed to load categories:', error)
    debugStore.addLog('error', 'CategoryGrid: Failed to load categories', { error: error.message })
  } finally {
    loading.value = false
  }
}

// Build category map for quick lookup
function buildCategoryMap(cats) {
  const map = {}
  function processCategory(cat) {
    map[cat.id] = cat
    if (cat.children) {
      cat.children.forEach(processCategory)
    }
  }
  cats.forEach(processCategory)
  return map
}

// Load system view counts
async function loadSystemViewCounts() {
  try {
    // Load counts for each system view
    const [inboxRes, favoritesRes, allLinksRes] = await Promise.all([
      linksApi.getLinks({ category_id: null }), // Inbox = uncategorized
      linksApi.getLinks({ favorite: true }), // Favorites
      linksApi.getLinks({}) // All links
    ])

    systemViews.value = [
      {
        id: 'inbox',
        type: 'system',
        name: 'Inbox',
        icon: '📥',
        link_count: inboxRes.links?.length || 0,
        description: 'Uncategorized links',
        is_system: true
      },
      {
        id: 'favorites',
        type: 'system',
        name: 'Favorites',
        icon: '⭐',
        link_count: favoritesRes.links?.length || 0,
        description: 'Your favorite links',
        is_system: true
      },
      {
        id: 'all',
        type: 'system',
        name: 'All Links',
        icon: '🔗',
        link_count: allLinksRes.links?.length || 0,
        description: 'All your links',
        is_system: true
      }
    ]
  } catch (error) {
    console.error('Failed to load system view counts:', error)
  }
}

// Column-based organization (4 columns)
const columnCards = ref({
  1: [],
  2: [],
  3: [],
  4: []
})

// Watch categories and system views, then distribute into columns
watch([categories, systemViews], () => {
  console.log('CategoryGrid: Watcher fired, categories:', categories.value.length, 'systemViews:', systemViews.value.length)
  debugStore.addLog('watcher', 'CategoryGrid: Rebuilding column cards', {
    categoriesCount: categories.value.length,
    systemViewsCount: systemViews.value.length
  })

  // Reset all columns
  const newColumns = {
    1: [],
    2: [],
    3: [],
    4: []
  }

  // Add system views to column 1
  systemViews.value.forEach((view, index) => {
    newColumns[1].push({
      id: view.id,
      type: 'system',
      data: view,
      order: index,
      column_id: 1
    })
  })

  // Add root categories to their respective columns
  categories.value.forEach((cat) => {
    const columnId = cat.column_id || 1 // Default to column 1 if not set
    if (columnId >= 1 && columnId <= 4) {
      newColumns[columnId].push({
        id: cat.id,
        type: 'category',
        data: cat,
        order: cat.sort_order || 0,
        column_id: columnId
      })
    }
  })

  columnCards.value = newColumns
  console.log('CategoryGrid: Column cards updated', newColumns)
  debugStore.addLog('watcher', 'CategoryGrid: Column cards updated', {
    column1Count: newColumns[1].length,
    column2Count: newColumns[2].length,
    column3Count: newColumns[3].length,
    column4Count: newColumns[4].length
  })
}, { immediate: true })

// Get cards for a specific column
function getColumnCards(columnNum) {
  return columnCards.value[columnNum] || []
}

// Update a specific column after drag
function updateColumn(columnNum, newCards) {
  columnCards.value[columnNum] = newCards
}

// Get total card count across all columns
function getTotalCardCount() {
  return Object.values(columnCards.value).reduce((sum, cards) => sum + cards.length, 0)
}

// Methods
function handleExpand(cardId) {
  expandedIds.value.add(cardId)
}

function handleCollapse(cardId) {
  expandedIds.value.delete(cardId)
}

function handleEdit(category) {
  emit('edit-category', category)
}

function handleDelete(category) {
  emit('delete-category', category)
}

function handleLinkUpdated(link) {
  emit('link-updated', link)
}

// Check if a category has another category in its descendant tree
function hasDescendant(categoryId, potentialDescendantId) {
  const category = categoryMap.value[categoryId]
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

// Handle drag change (column-aware)
async function handleDragChange(event, columnNum) {
  debugStore.addLog('drag', 'CategoryGrid: handleDragChange fired', { event, columnNum })
  console.log('Drag change in column', columnNum, ':', event)

  try {
    let updated = false

    // Handle item added to column (from another column or from within category)
    if (event.added) {
      const item = event.added.element
      console.log('Added item to column', columnNum, ':', item)
      const newIndex = event.added.newIndex
      debugStore.addLog('drag', 'CategoryGrid: Item added to column', {
        item: item ? { id: item.id, type: item.type, name: item.data?.name } : null,
        columnNum,
        newIndex
      })

      if (!item) {
        console.warn('Added element is undefined')
        debugStore.addLog('warn', 'CategoryGrid: Added element is undefined')
        return
      }

      if (item.type === 'category') {
        // Moving to this column at root level - no circular reference possible
        debugStore.addLog('api', 'CategoryGrid: Calling updateCategory to move to column', {
          categoryId: item.id,
          parent_id: null,
          column_id: columnNum,
          sort_order: newIndex
        })
        const updateResult = await categoriesApi.updateCategory(item.id, {
          parent_id: null,
          column_id: columnNum,
          sort_order: newIndex
        })
        debugStore.addLog('api', 'CategoryGrid: updateCategory response', {
          success: updateResult?.success,
          category: updateResult?.category,
          fullResponse: updateResult
        })
        updated = true
      }
      // Note: Links and system views cannot be moved to root level
    }

    // Handle item moved within same column (reorder)
    if (event.moved) {
      const item = event.moved.element
      console.log('Moved item within column', columnNum, ':', item)
      const newIndex = event.moved.newIndex
      debugStore.addLog('drag', 'CategoryGrid: Item moved within column', {
        item: item ? { id: item.id, type: item.type, name: item.data?.name } : null,
        columnNum,
        newIndex
      })

      if (!item) {
        console.warn('Moved element is undefined')
        debugStore.addLog('warn', 'CategoryGrid: Moved element is undefined')
        return
      }

      if (item.type === 'category') {
        debugStore.addLog('api', 'CategoryGrid: Calling reorderCategory within column', {
          categoryId: item.id,
          parent_id: null,
          column_id: columnNum,
          sort_order: newIndex
        })
        await categoriesApi.reorderCategory(item.id, {
          parent_id: null,
          column_id: columnNum,
          sort_order: newIndex
        })
        debugStore.addLog('api', 'CategoryGrid: reorderCategory succeeded')
        updated = true
      }
    }

    // Handle item removed from column
    if (event.removed) {
      const item = event.removed.element
      debugStore.addLog('drag', 'CategoryGrid: Item removed from column', {
        item: item ? { id: item.id, type: item.type, name: item.data?.name } : null,
        columnNum,
        oldIndex: event.removed.oldIndex
      })
    }

    // Reload entire category tree to reflect changes
    if (updated) {
      debugStore.addLog('api', 'CategoryGrid: Reloading categories after drag')
      await loadCategories()
      await loadSystemViewCounts()
    }
  } catch (error) {
    console.error('Failed to update category position:', error)
    debugStore.addLog('error', 'CategoryGrid: Failed to update category position', { error: error.message })
    alert('Failed to update category position. Please refresh the page.')
    // Reload anyway to restore consistent state
    await loadCategories()
  }
}

// Handle category moved (from CategoryCard/SubcategoryItem after drag operations)
async function handleCategoryMoved(payload) {
  debugStore.addLog('drag', 'CategoryGrid: handleCategoryMoved called', { payload })
  try {
    // If payload provided, update via API (legacy usage)
    if (payload && payload.categoryId) {
      debugStore.addLog('api', 'CategoryGrid: Updating category via payload', {
        categoryId: payload.categoryId,
        newParentId: payload.newParentId
      })
      await categoriesApi.updateCategory(payload.categoryId, {
        parent_id: payload.newParentId
      })
      debugStore.addLog('api', 'CategoryGrid: Category update via payload succeeded')
    }

    // Always reload categories to reflect changes
    debugStore.addLog('api', 'CategoryGrid: Reloading categories after category moved')
    await loadCategories()
    await loadSystemViewCounts()
  } catch (error) {
    console.error('Failed to reload categories:', error)
    debugStore.addLog('error', 'CategoryGrid: Failed to reload categories', { error: error.message })
    alert('Failed to reload categories. Please refresh the page.')
  }
}

// Expose methods to parent
defineExpose({
  loadCategories,
  loadSystemViewCounts
})
</script>

<style scoped>
.category-grid-container {
  height: 100%;
  overflow-y: auto;
  padding: 1.5rem;
  background-color: #f9fafb;
}

/* 4 Column Layout */
.column-container {
  display: flex;
  gap: 1.5rem;
  height: 100%;
  align-items: stretch;
}

.column {
  flex: 1;
  min-width: 0; /* Prevent flex items from overflowing */
  display: flex;
  flex-direction: column;
}

.column-drop-zone {
  flex: 1;
  min-height: 200px; /* Ensure columns are droppable even when empty */
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Responsive adjustments */
@media (max-width: 1280px) {
  .column-container {
    gap: 1rem;
  }
}

@media (max-width: 960px) {
  .column-container {
    flex-direction: column;
  }

  .column {
    width: 100%;
  }
}

/* Draggable ghost/chosen/drag states */
.ghost-card {
  opacity: 0.4;
  background-color: #e0e7ff;
}

.chosen-card {
  opacity: 0.9;
  border: 2px solid #3b82f6;
}

.drag-card {
  opacity: 0.5;
  transform: rotate(2deg);
}
</style>
