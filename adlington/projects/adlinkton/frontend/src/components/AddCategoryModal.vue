<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="close"
  >
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
      <h2 class="text-xl font-bold mb-4">{{ isEdit ? 'Edit Category' : 'Add New Category' }}</h2>

      <form @submit.prevent="handleSubmit">
        <!-- Name -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Name <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            maxlength="255"
            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Category name"
          />
        </div>

        <!-- Parent Category -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Parent Category (optional)
          </label>
          <select
            v-model="form.parent_id"
            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option :value="null">None (Root category)</option>
            <option
              v-for="category in availableParents"
              :key="category.id"
              :value="category.id"
              :style="{ paddingLeft: (category.depth * 12 + 8) + 'px' }"
            >
              {{ category.name }}
            </option>
          </select>
        </div>

        <!-- Error message -->
        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
          {{ error }}
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 border rounded-lg hover:bg-gray-50"
            @click="close"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
            :disabled="submitting"
          >
            {{ submitting ? 'Saving...' : (isEdit ? 'Update' : 'Create') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { categoriesApi } from '@/api/categories'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  category: {
    type: Object,
    default: null
  },
  categories: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'category-saved'])

const form = ref({
  name: '',
  parent_id: null
})

const submitting = ref(false)
const error = ref(null)

const isEdit = computed(() => !!props.category)

const availableParents = computed(() => {
  // Flatten categories and exclude the current category (if editing) and its descendants
  const flatten = (cats, depth = 0) => {
    let result = []
    for (const cat of cats) {
      // Skip current category when editing
      if (isEdit.value && cat.id === props.category.id) {
        continue
      }
      result.push({ ...cat, depth })
      if (cat.children && cat.children.length > 0) {
        result = result.concat(flatten(cat.children, depth + 1))
      }
    }
    return result
  }
  return flatten(props.categories)
})

// Reset form when modal opens/closes
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

function resetForm() {
  if (props.category) {
    form.value = {
      name: props.category.name,
      parent_id: props.category.parent_id
    }
  } else {
    form.value = {
      name: '',
      parent_id: null
    }
  }
  error.value = null
}

async function handleSubmit() {
  error.value = null
  submitting.value = true

  try {
    let response
    if (isEdit.value) {
      response = await categoriesApi.updateCategory(props.category.id, form.value)
    } else {
      response = await categoriesApi.createCategory(form.value)
    }
    emit('category-saved', response)
    close()
  } catch (err) {
    console.error('Failed to save category:', err)
    error.value = err.response?.data?.error || 'Failed to save category. Please try again.'
  } finally {
    submitting.value = false
  }
}

function close() {
  emit('close')
}
</script>
