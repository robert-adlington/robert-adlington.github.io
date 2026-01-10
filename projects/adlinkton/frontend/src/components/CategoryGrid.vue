<template>
  <div class="category-grid-container">
    <div v-if="loading" class="text-center py-8 text-gray-500">
      Loading categories...
    </div>

    <div v-else-if="allCards.length === 0" class="text-center py-8 text-gray-500">
      No categories found. (Debug: categories={{ categories.length }}, systemViews={{ systemViews.length }})
    </div>

    <!-- Grid with draggable cards -->
    <VueDraggableNext
      v-else
      v-model="allCards"
      class="category-grid"
      :animation="200"
      ghost-class="ghost-card"
      @change="handleDragChange"
    >
      <CategoryCard
        v-for="card in allCards"
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
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { VueDraggableNext } from 'vue-draggable-next'
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

// Combine system views and categories into cards (now a ref, not computed)
const allCards = ref([])

// Watch categories and system views, then rebuild allCards
watch([categories, systemViews], () => {
  console.log('CategoryGrid: Watcher fired, categories:', categories.value.length, 'systemViews:', systemViews.value.length)
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

  allCards.value = cards
  console.log('CategoryGrid: allCards updated, total cards:', allCards.value.length)
}, { immediate: true })

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

// Handle drag change
function handleDragChange(event) {
  console.log('Drag change:', event)
  // allCards is automatically updated by v-model
  // We could save the new order to the database here if needed
}

// Handle category moved (from CategoryCard when dropping on another category)
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
