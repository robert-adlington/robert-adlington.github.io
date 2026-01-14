<template>
  <!-- POC Mode: Show TreeDndPOC component -->
  <TreeDndPOC v-if="showPOC" />

  <!-- Nested Sort POC Mode -->
  <NestedSortPOC v-else-if="showNestedSortPOC" />

  <!-- Normal App Mode -->
  <div v-else id="app" class="h-full flex flex-col bg-white">
    <!-- Header -->
    <header class="flex items-center justify-between px-6 py-3 border-b bg-white shadow-sm">
      <div class="flex items-center gap-4">
        <button
          class="p-2 hover:bg-gray-100 rounded transition-colors"
          @click="toggleSidebar"
          title="Toggle sidebar"
        >
          ☰
        </button>
        <h1 class="text-2xl font-bold text-primary-600">adlinkton</h1>
      </div>

      <div class="flex items-center gap-4">
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search links..."
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          @input="handleSearch"
        />
        <button
          class="px-3 py-2 border border-primary-600 text-primary-600 rounded-lg hover:bg-primary-50"
          @click="showImportModal = true"
          title="Import bookmarks from Chrome, Firefox, Safari, or Edge"
        >
          📥 Import
        </button>
        <button
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
          @click="showAddLinkModal = true"
        >
          + Add Link
        </button>
      </div>
    </header>

    <!-- Main Layout -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- Sidebar Overlay -->
      <transition name="sidebar">
        <div v-if="sidebarVisible" class="sidebar-overlay-container">
          <!-- Backdrop -->
          <div class="sidebar-backdrop" @click="closeSidebar"></div>

          <!-- Sidebar Panel -->
          <aside class="sidebar-panel">
            <div class="p-4">
              <h2 class="text-xs font-semibold text-gray-500 uppercase mb-2">System Views</h2>
              <nav class="space-y-1">
                <a
                  href="#"
                  class="block px-3 py-2 rounded hover:bg-gray-200 transition-colors"
                  :class="{ 'bg-gray-200 font-medium': highlightedCategoryId === 'inbox' }"
                  @click.prevent="handleSidebarCategoryClick('inbox')"
                >
                  📥 Inbox
                </a>
                <a
                  href="#"
                  class="block px-3 py-2 rounded hover:bg-gray-200 transition-colors"
                  :class="{ 'bg-gray-200 font-medium': highlightedCategoryId === 'favorites' }"
                  @click.prevent="handleSidebarCategoryClick('favorites')"
                >
                  ⭐ Favorites
                </a>
                <a
                  href="#"
                  class="block px-3 py-2 rounded hover:bg-gray-200 transition-colors"
                  :class="{ 'bg-gray-200 font-medium': highlightedCategoryId === 'all' }"
                  @click.prevent="handleSidebarCategoryClick('all')"
                >
                  🔗 All Links
                </a>
              </nav>

              <div class="flex items-center justify-between mt-6 mb-2">
                <h2 class="text-xs font-semibold text-gray-500 uppercase">Categories</h2>
                <button
                  class="text-xs text-primary-600 hover:text-primary-700 font-medium"
                  @click="showAddCategoryModal = true"
                  title="Add new category"
                >
                  + Add
                </button>
              </div>
              <CategoryTree
                ref="categoryTree"
                :highlighted-id="highlightedCategoryId"
                @select-category="handleSidebarCategorySelect"
                @edit-category="handleEditCategory"
                @delete-category="handleDeleteCategory"
              />
            </div>
          </aside>
        </div>
      </transition>

      <!-- Main Content Area - Category Grid -->
      <main class="flex-1 overflow-hidden">
        <CategoryGrid
          ref="categoryGrid"
          @edit-category="handleEditCategory"
          @delete-category="handleDeleteCategory"
          @link-updated="handleLinkUpdated"
        />
      </main>
    </div>

    <!-- Add Link Modal -->
    <AddLinkModal
      :is-open="showAddLinkModal"
      @close="showAddLinkModal = false"
      @link-added="handleLinkAdded"
    />

    <!-- Add/Edit Category Modal -->
    <AddCategoryModal
      :is-open="showAddCategoryModal"
      :category="editingCategory"
      :categories="categories"
      @close="closeAddCategoryModal"
      @category-saved="handleCategorySaved"
    />

    <!-- Import Bookmarks Modal -->
    <ImportBookmarksModal
      :is-open="showImportModal"
      @close="showImportModal = false"
      @import-completed="handleImportCompleted"
    />

    <!-- Debug Panel -->
    <DebugPanel />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import CategoryGrid from './components/CategoryGrid.vue'
import CategoryTree from './components/CategoryTree.vue'
import AddLinkModal from './components/AddLinkModal.vue'
import AddCategoryModal from './components/AddCategoryModal.vue'
import ImportBookmarksModal from './components/ImportBookmarksModal.vue'
import DebugPanel from './components/DebugPanel.vue'
import TreeDndPOC from './components/TreeDndPOC.vue'
import NestedSortPOC from './components/NestedSortPOC.vue'
import { categoriesApi } from './api/categories'

// Check if we should show the POC (via URL hash #poc)
const showPOC = ref(window.location.hash === '#poc')
const showNestedSortPOC = ref(window.location.hash === '#nested-sort-poc')

// Refs
const categoryGrid = ref(null)
const categoryTree = ref(null)

// State
const sidebarVisible = ref(false)
const highlightedCategoryId = ref(null)
const showAddLinkModal = ref(false)
const showAddCategoryModal = ref(false)
const showImportModal = ref(false)
const editingCategory = ref(null)
const categories = ref([])
const searchQuery = ref('')

// Load categories on mount
onMounted(async () => {
  await loadCategories()
})

async function loadCategories() {
  try {
    const response = await categoriesApi.getCategories()
    categories.value = response.categories
  } catch (error) {
    console.error('Failed to load categories:', error)
  }
}

// Sidebar methods
function toggleSidebar() {
  sidebarVisible.value = !sidebarVisible.value
}

function closeSidebar() {
  sidebarVisible.value = false
}

function handleSidebarCategoryClick(viewId) {
  highlightedCategoryId.value = viewId
  scrollToCard(viewId)
}

function handleSidebarCategorySelect(category) {
  highlightedCategoryId.value = category.id
  scrollToCard(category.id)
}

async function scrollToCard(cardId) {
  // Wait for next tick to ensure DOM is updated
  await nextTick()

  // Find the card element and scroll to it
  const cardElement = document.querySelector(`[data-card-id="${cardId}"]`)
  if (cardElement) {
    cardElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}

function handleSearch() {
  // TODO: Implement search functionality in grid view
  console.log('Search:', searchQuery.value)
}

function handleLinkUpdated(link) {
  console.log('Link updated:', link)
  // Reload categories to update counts
  if (categoryGrid.value) {
    categoryGrid.value.loadSystemViewCounts()
  }
}

function handleLinkAdded(link) {
  console.log('Link added:', link)
  // Reload the grid
  if (categoryGrid.value) {
    categoryGrid.value.loadCategories()
    categoryGrid.value.loadSystemViewCounts()
  }
  // Reload sidebar categories
  if (categoryTree.value) {
    categoryTree.value.loadCategories()
  }
  loadCategories()
}

function handleEditCategory(category) {
  editingCategory.value = category
  showAddCategoryModal.value = true
}

async function handleDeleteCategory(category) {
  if (!confirm(`Are you sure you want to delete "${category.name}"? This will also delete all subcategories.`)) {
    return
  }

  try {
    await categoriesApi.deleteCategory(category.id)
    // Reload categories in grid and sidebar
    if (categoryGrid.value) {
      categoryGrid.value.loadCategories()
    }
    if (categoryTree.value) {
      categoryTree.value.loadCategories()
    }
    loadCategories()
  } catch (error) {
    console.error('Failed to delete category:', error)
    alert('Failed to delete category. Please try again.')
  }
}

function handleCategorySaved(category) {
  console.log('Category saved:', category)
  // Reload categories
  if (categoryGrid.value) {
    categoryGrid.value.loadCategories()
  }
  if (categoryTree.value) {
    categoryTree.value.loadCategories()
  }
  loadCategories()
}

function closeAddCategoryModal() {
  showAddCategoryModal.value = false
  editingCategory.value = null
}

function handleImportCompleted(result) {
  console.log('Import completed:', result)
  // Reload grid and categories
  if (categoryGrid.value) {
    categoryGrid.value.loadCategories()
    categoryGrid.value.loadSystemViewCounts()
  }
  if (categoryTree.value) {
    categoryTree.value.loadCategories()
  }
  loadCategories()
}
</script>

<style scoped>
/* Sidebar Overlay */
.sidebar-overlay-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 50;
  display: flex;
}

.sidebar-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
}

.sidebar-panel {
  position: relative;
  width: 16rem; /* 64 * 4px = 256px = w-64 */
  background-color: #f9fafb;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
  max-height: 100vh;
}

/* Sidebar transition */
.sidebar-enter-active,
.sidebar-leave-active {
  transition: opacity 0.3s ease;
}

.sidebar-enter-active .sidebar-panel,
.sidebar-leave-active .sidebar-panel {
  transition: transform 0.3s ease;
}

.sidebar-enter-from,
.sidebar-leave-to {
  opacity: 0;
}

.sidebar-enter-from .sidebar-panel {
  transform: translateX(-100%);
}

.sidebar-leave-to .sidebar-panel {
  transform: translateX(-100%);
}
</style>
