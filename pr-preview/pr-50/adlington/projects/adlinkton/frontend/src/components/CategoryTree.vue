<template>
  <div class="category-tree">
    <!-- Loading state -->
    <div v-if="loading" class="text-sm text-gray-500 px-3 py-2">
      Loading categories...
    </div>

    <!-- Empty state -->
    <div v-else-if="categories.length === 0" class="text-sm text-gray-500 px-3 py-2">
      No categories yet
    </div>

    <!-- Categories tree -->
    <div v-else class="space-y-1">
      <CategoryNode
        v-for="category in categories"
        :key="category.id"
        :category="category"
        :selected-id="selectedCategoryId"
        :expanded-ids="expandedCategoryIds"
        @select="handleSelect"
        @toggle-expand="handleToggleExpand"
        @edit="handleEdit"
        @delete="handleDelete"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import CategoryNode from './CategoryNode.vue'
import { categoriesApi } from '@/api/categories'

const emit = defineEmits(['select-category', 'edit-category', 'delete-category'])

const categories = ref([])
const loading = ref(true)
const selectedCategoryId = ref(null)
const expandedCategoryIds = ref(new Set())

onMounted(async () => {
  await loadCategories()
})

async function loadCategories() {
  loading.value = true
  try {
    const response = await categoriesApi.getCategories()
    categories.value = response.categories
  } catch (error) {
    console.error('Failed to load categories:', error)
  } finally {
    loading.value = false
  }
}

function handleSelect(category) {
  selectedCategoryId.value = category.id
  emit('select-category', category)
}

function handleToggleExpand(categoryId) {
  if (expandedCategoryIds.value.has(categoryId)) {
    expandedCategoryIds.value.delete(categoryId)
  } else {
    expandedCategoryIds.value.add(categoryId)
  }
}

function handleEdit(category) {
  emit('edit-category', category)
}

function handleDelete(category) {
  emit('delete-category', category)
}

// Expose methods to parent
defineExpose({
  loadCategories
})
</script>
