<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="close"
  >
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
      <h2 class="text-xl font-bold mb-4">Add New Link</h2>

      <form @submit.prevent="handleSubmit">
        <!-- URL -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            URL <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.url"
            type="url"
            required
            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="https://example.com"
          />
        </div>

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
            placeholder="Link name"
          />
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Description
          </label>
          <textarea
            v-model="form.description"
            rows="3"
            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Optional description"
          ></textarea>
        </div>

        <!-- Favorite -->
        <div class="mb-4">
          <label class="flex items-center">
            <input
              v-model="form.is_favorite"
              type="checkbox"
              class="mr-2"
            />
            <span class="text-sm text-gray-700">Mark as favorite</span>
          </label>
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
            {{ submitting ? 'Adding...' : 'Add Link' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { linksApi } from '@/api/links'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'link-added'])

const form = ref({
  url: '',
  name: '',
  description: '',
  is_favorite: false
})

const submitting = ref(false)
const error = ref(null)

// Reset form when modal opens/closes
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

function resetForm() {
  form.value = {
    url: '',
    name: '',
    description: '',
    is_favorite: false
  }
  error.value = null
}

async function handleSubmit() {
  error.value = null
  submitting.value = true

  try {
    const response = await linksApi.createLink(form.value)
    emit('link-added', response)
    close()
  } catch (err) {
    console.error('Failed to create link:', err)
    error.value = err.response?.data?.error || 'Failed to create link. Please try again.'
  } finally {
    submitting.value = false
  }
}

function close() {
  emit('close')
}
</script>
