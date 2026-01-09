<template>
  <div id="app" class="h-full flex flex-col bg-white">
    <!-- Header -->
    <header class="flex items-center justify-between px-6 py-3 border-b bg-white shadow-sm">
      <div class="flex items-center gap-4">
        <h1 class="text-2xl font-bold text-primary-600">Adlinkton</h1>
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
    <div class="flex-1 flex overflow-hidden">
      <!-- Sidebar -->
      <aside class="w-64 border-r bg-gray-50 overflow-y-auto">
        <div class="p-4">
          <h2 class="text-xs font-semibold text-gray-500 uppercase mb-2">System</h2>
          <nav class="space-y-1">
            <a
              href="#"
              class="block px-3 py-2 rounded hover:bg-gray-200"
              :class="{ 'bg-gray-200': currentView === 'inbox' }"
              @click.prevent="selectView('inbox')"
            >
              📥 Inbox
            </a>
            <a
              href="#"
              class="block px-3 py-2 rounded hover:bg-gray-200"
              :class="{ 'bg-gray-200': currentView === 'favorites' }"
              @click.prevent="selectView('favorites')"
            >
              ⭐ Favorites
            </a>
            <a
              href="#"
              class="block px-3 py-2 rounded hover:bg-gray-200"
              :class="{ 'bg-gray-200': currentView === 'all' }"
              @click.prevent="selectView('all')"
            >
              📚 All Links
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
            @select-category="handleCategorySelect"
            @edit-category="handleEditCategory"
            @delete-category="handleDeleteCategory"
          />
        </div>
      </aside>

      <!-- Main Content Area -->
      <main class="flex-1 overflow-hidden">
        <div class="h-full flex flex-col">
          <!-- Pane Header -->
          <div class="pane-header">
            <h2 class="font-semibold">{{ currentViewTitle }}</h2>
            <div class="flex gap-2">
              <select
                v-model="sortMode"
                class="px-2 py-1 border rounded text-sm"
                @change="handleSortChange"
              >
                <option value="created">Sort: Created</option>
                <option value="name">Sort: Name</option>
                <option value="accessed">Sort: Accessed</option>
                <option value="frequency">Sort: Frequency</option>
              </select>
              <select
                v-model="sortOrder"
                class="px-2 py-1 border rounded text-sm"
                @change="handleSortChange"
              >
                <option value="desc">Descending</option>
                <option value="asc">Ascending</option>
              </select>
            </div>
          </div>

          <!-- Link List -->
          <div class="pane-content">
            <LinkList
              ref="linkList"
              :category-id="selectedCategoryId"
              :favorite="favoriteFilter"
              :search="searchQuery"
              :sort="sortMode"
              :order="sortOrder"
              :empty-message="emptyMessage"
              @link-click="handleLinkClick"
              @link-updated="handleLinkUpdated"
            />
          </div>
        </div>
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import LinkList from './components/LinkList.vue'
import CategoryTree from './components/CategoryTree.vue'
import AddLinkModal from './components/AddLinkModal.vue'
import AddCategoryModal from './components/AddCategoryModal.vue'
import ImportBookmarksModal from './components/ImportBookmarksModal.vue'
import { categoriesApi } from './api/categories'

// Refs
const linkList = ref(null)
const categoryTree = ref(null)

// State
const currentView = ref('all')
const selectedCategoryId = ref(null)
const showAddLinkModal = ref(false)
const showAddCategoryModal = ref(false)
const showImportModal = ref(false)
const editingCategory = ref(null)
const categories = ref([])
const searchQuery = ref('')
const sortMode = ref('created')
const sortOrder = ref('desc')

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

// Computed
const favoriteFilter = computed(() => {
  return currentView.value === 'favorites' ? true : null
})

const currentViewTitle = computed(() => {
  if (searchQuery.value) {
    return `Search: "${searchQuery.value}"`
  }

  switch (currentView.value) {
    case 'inbox':
      return 'Inbox'
    case 'favorites':
      return 'Favorites'
    case 'category':
      return 'Category'
    default:
      return 'All Links'
  }
})

const emptyMessage = computed(() => {
  if (searchQuery.value) {
    return 'No links found matching your search'
  }

  switch (currentView.value) {
    case 'inbox':
      return 'No uncategorized links'
    case 'favorites':
      return 'No favorite links yet'
    case 'category':
      return 'No links in this category'
    default:
      return 'Add your first link to get started'
  }
})

// Methods
function selectView(view) {
  currentView.value = view
  selectedCategoryId.value = null
  if (linkList.value) {
    linkList.value.loadLinks()
  }
}

function handleCategorySelect(category) {
  currentView.value = 'category'
  selectedCategoryId.value = category.id
  if (linkList.value) {
    linkList.value.loadLinks()
  }
}

function handleSearch() {
  if (linkList.value) {
    linkList.value.loadLinks()
  }
}

function handleSortChange() {
  if (linkList.value) {
    linkList.value.loadLinks()
  }
}

function handleLinkClick(link) {
  console.log('Link clicked:', link)
}

function handleLinkUpdated(link) {
  console.log('Link updated:', link)
}

function handleLinkAdded(link) {
  console.log('Link added:', link)
  // Reload the link list
  if (linkList.value) {
    linkList.value.loadLinks()
  }
  // Reload categories in case the link was categorized
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
    // Reload categories
    if (categoryTree.value) {
      categoryTree.value.loadCategories()
    }
    loadCategories()
    // If we were viewing this category, go back to all links
    if (selectedCategoryId.value === category.id) {
      selectView('all')
    }
  } catch (error) {
    console.error('Failed to delete category:', error)
    alert('Failed to delete category. Please try again.')
  }
}

function handleCategorySaved(category) {
  console.log('Category saved:', category)
  // Reload categories
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
  // Reload links and categories
  if (linkList.value) {
    linkList.value.loadLinks()
  }
  if (categoryTree.value) {
    categoryTree.value.loadCategories()
  }
  loadCategories()
}
</script>

<style scoped>
/* Component-specific styles */
</style>
