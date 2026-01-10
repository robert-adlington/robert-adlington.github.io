<template>
  <div class="category-grid-container">
    <!-- Grid with 4 columns -->
    <div class="category-grid">
      <!-- Column 1 -->
      <div
        class="grid-column"
        @dragover.prevent="handleColumnDragOver($event, 1)"
        @dragleave="handleColumnDragLeave"
      >
        <CategoryCard
          v-for="card in column1Cards"
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
        <!-- Drop zone for moving to root -->
        <div
          v-show="isDragging"
          class="drop-zone"
          :class="{ 'drop-zone-active': columnDragOver === 1 }"
          @dragover.prevent="handleDropZoneDragOver($event, 1)"
          @dragleave="handleDropZoneDragLeave"
          @drop.prevent="handleDropZoneDrop($event, 1)"
        >
          <div class="drop-zone-content">
            Drop here to move to root level
          </div>
        </div>
      </div>

      <!-- Column 2 -->
      <div
        class="grid-column"
        @dragover.prevent="handleColumnDragOver($event, 2)"
        @dragleave="handleColumnDragLeave"
      >
        <CategoryCard
          v-for="card in column2Cards"
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
        <!-- Drop zone for moving to root -->
        <div
          v-show="isDragging"
          class="drop-zone"
          :class="{ 'drop-zone-active': columnDragOver === 2 }"
          @dragover.prevent="handleDropZoneDragOver($event, 2)"
          @dragleave="handleDropZoneDragLeave"
          @drop.prevent="handleDropZoneDrop($event, 2)"
        >
          <div class="drop-zone-content">
            Drop here to move to root level
          </div>
        </div>
      </div>

      <!-- Column 3 -->
      <div
        class="grid-column"
        @dragover.prevent="handleColumnDragOver($event, 3)"
        @dragleave="handleColumnDragLeave"
      >
        <CategoryCard
          v-for="card in column3Cards"
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
        <!-- Drop zone for moving to root -->
        <div
          v-show="isDragging"
          class="drop-zone"
          :class="{ 'drop-zone-active': columnDragOver === 3 }"
          @dragover.prevent="handleDropZoneDragOver($event, 3)"
          @dragleave="handleDropZoneDragLeave"
          @drop.prevent="handleDropZoneDrop($event, 3)"
        >
          <div class="drop-zone-content">
            Drop here to move to root level
          </div>
        </div>
      </div>

      <!-- Column 4 -->
      <div
        class="grid-column"
        @dragover.prevent="handleColumnDragOver($event, 4)"
        @dragleave="handleColumnDragLeave"
      >
        <CategoryCard
          v-for="card in column4Cards"
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
        <!-- Drop zone for moving to root -->
        <div
          v-show="isDragging"
          class="drop-zone"
          :class="{ 'drop-zone-active': columnDragOver === 4 }"
          @dragover.prevent="handleDropZoneDragOver($event, 4)"
          @dragleave="handleDropZoneDragLeave"
          @drop.prevent="handleDropZoneDrop($event, 4)"
        >
          <div class="drop-zone-content">
            Drop here to move to root level
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import CategoryCard from './CategoryCard.vue'
import { categoriesApi } from '@/api/categories'
import { linksApi } from '@/api/links'

const emit = defineEmits(['category-selected', 'edit-category', 'delete-category', 'link-updated'])

// State
const categories = ref([])
const systemViews = ref([])
const expandedIds = ref(new Set())
const categoryMap = ref({})
const loading = ref(true)
const columnDragOver = ref(null)
const isDragging = ref(false)

// Load data on mount
onMounted(async () => {
  await Promise.all([
    loadCategories(),
    loadSystemViewCounts()
  ])

  // Listen for drag events to track dragging state
  document.addEventListener('dragstart', handleGlobalDragStart)
  document.addEventListener('dragend', handleGlobalDragEnd)
})

// Cleanup listeners on unmount
onUnmounted(() => {
  document.removeEventListener('dragstart', handleGlobalDragStart)
  document.removeEventListener('dragend', handleGlobalDragEnd)
})

// Load categories
async function loadCategories() {
  loading.value = true
  try {
    const response = await categoriesApi.getCategories()
    categories.value = response.categories || []
    categoryMap.value = buildCategoryMap(response.categories || [])
  } catch (error) {
    console.error('Failed to load categories:', error)
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
        description: 'Uncategorized links'
      },
      {
        id: 'favorites',
        type: 'system',
        name: 'Favorites',
        icon: '⭐',
        link_count: favoritesRes.links?.length || 0,
        description: 'Your favorite links'
      },
      {
        id: 'all',
        type: 'system',
        name: 'All Links',
        icon: '🔗',
        link_count: allLinksRes.links?.length || 0,
        description: 'All your links'
      }
    ]
  } catch (error) {
    console.error('Failed to load system view counts:', error)
  }
}

// Combine system views and categories into cards
const allCards = computed(() => {
  const cards = []

  // Add system views
  systemViews.value.forEach((view, index) => {
    cards.push({
      id: view.id,
      type: 'system',
      data: view,
      order: index
    })
  })

  // Add categories (flatten to just root categories)
  categories.value.forEach((cat, index) => {
    cards.push({
      id: cat.id,
      type: 'category',
      data: cat,
      order: systemViews.value.length + index
    })
  })

  return cards
})

// Number of columns (can be made reactive based on screen size)
const numColumns = ref(4)

// Distribute cards across columns
const column1Cards = computed(() => {
  return allCards.value.filter((_, index) => index % numColumns.value === 0)
})

const column2Cards = computed(() => {
  return allCards.value.filter((_, index) => index % numColumns.value === 1)
})

const column3Cards = computed(() => {
  return allCards.value.filter((_, index) => index % numColumns.value === 2)
})

const column4Cards = computed(() => {
  return allCards.value.filter((_, index) => index % numColumns.value === 3)
})

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

// Global drag state tracking
function handleGlobalDragStart(event) {
  // Only track if it's a category drag
  const data = event.dataTransfer.types.includes('application/json')
  if (data) {
    isDragging.value = true
  }
}

function handleGlobalDragEnd(event) {
  isDragging.value = false
  columnDragOver.value = null
}

// Drag and Drop handlers for columns (general column drag over)
function handleColumnDragOver(event, columnNumber) {
  // This runs for the entire column area
  event.dataTransfer.dropEffect = 'move'
}

function handleColumnDragLeave(event) {
  // Handle at drop zone level
}

// Drop zone specific handlers
function handleDropZoneDragOver(event, columnNumber) {
  columnDragOver.value = columnNumber
  event.dataTransfer.dropEffect = 'move'
}

function handleDropZoneDragLeave(event) {
  columnDragOver.value = null
}

async function handleDropZoneDrop(event, columnNumber) {
  columnDragOver.value = null

  try {
    const data = JSON.parse(event.dataTransfer.getData('application/json'))

    // Move to root (null parent)
    await handleCategoryMoved({
      categoryId: data.id,
      newParentId: null,
      oldParentId: data.currentParentId
    })
  } catch (error) {
    console.error('Error handling drop zone drop:', error)
  }
}

async function handleCategoryMoved({ categoryId, newParentId, oldParentId }) {
  try {
    // Update category parent via API
    await categoriesApi.updateCategory(categoryId, {
      parent_id: newParentId
    })

    // Reload categories to reflect the change
    await loadCategories()
    await loadSystemViewCounts()
  } catch (error) {
    console.error('Failed to move category:', error)
    alert('Failed to move category. Please try again.')
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

.category-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  align-items: start;
}

.grid-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  min-width: 0;
  max-width: 100%;
}

/* Responsive adjustments */
@media (max-width: 1280px) {
  .category-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 960px) {
  .category-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .category-grid {
    grid-template-columns: 1fr;
  }
}

/* Drop Zone */
.drop-zone {
  min-height: 120px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  background-color: #f9fafb;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  cursor: pointer;
}

.drop-zone-content {
  color: #9ca3af;
  font-size: 0.875rem;
  font-weight: 500;
  text-align: center;
  padding: 1rem;
  pointer-events: none;
}

.drop-zone-active {
  border-color: #3b82f6;
  background-color: #eff6ff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.drop-zone-active .drop-zone-content {
  color: #3b82f6;
}
</style>
