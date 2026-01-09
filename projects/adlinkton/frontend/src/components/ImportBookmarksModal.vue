<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="close"
  >
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
      <h2 class="text-xl font-bold mb-4">Import Bookmarks</h2>

      <div v-if="!uploading && !result">
        <p class="text-sm text-gray-600 mb-4">
          Import bookmarks from Chrome, Firefox, Safari, or Edge. Export your bookmarks as an HTML file and upload it here.
        </p>

        <!-- File input -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Select Bookmark File <span class="text-red-500">*</span>
          </label>
          <input
            ref="fileInput"
            type="file"
            accept=".html,text/html"
            @change="handleFileSelect"
            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
          <p class="text-xs text-gray-500 mt-1">
            Accepted format: HTML bookmark export file
          </p>
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
            type="button"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
            :disabled="!selectedFile"
            @click="handleUpload"
          >
            Import
          </button>
        </div>
      </div>

      <!-- Uploading state -->
      <div v-if="uploading" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
        <p class="text-gray-600">Importing bookmarks...</p>
        <p class="text-xs text-gray-500 mt-2">This may take a moment</p>
      </div>

      <!-- Success result -->
      <div v-if="result && !error" class="py-4">
        <div class="text-center mb-4">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Import Successful!</h3>
        </div>

        <div class="space-y-2 mb-6">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Folders imported:</span>
            <span class="font-semibold">{{ result.stats.folders }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Links imported:</span>
            <span class="font-semibold">{{ result.stats.links }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Favicons fetched:</span>
            <span class="font-semibold">{{ result.stats.favicons_fetched }}</span>
          </div>
          <div v-if="result.stats.skipped > 0" class="flex justify-between text-sm">
            <span class="text-gray-600">Skipped (duplicates):</span>
            <span class="font-semibold">{{ result.stats.skipped }}</span>
          </div>
        </div>

        <button
          type="button"
          class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
          @click="close"
        >
          Done
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'import-completed'])

const fileInput = ref(null)
const selectedFile = ref(null)
const uploading = ref(false)
const error = ref(null)
const result = ref(null)

// Reset state when modal opens/closes
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    resetState()
  }
})

function resetState() {
  selectedFile.value = null
  uploading.value = false
  error.value = null
  result.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) {
    // Validate file type
    if (!file.name.endsWith('.html') && file.type !== 'text/html') {
      error.value = 'Please select an HTML bookmark file'
      selectedFile.value = null
      return
    }
    selectedFile.value = file
    error.value = null
  }
}

async function handleUpload() {
  if (!selectedFile.value) return

  error.value = null
  uploading.value = true

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const response = await axios.post('/api/import/bookmarks', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    result.value = response.data
    emit('import-completed', response.data)
  } catch (err) {
    console.error('Failed to import bookmarks:', err)
    error.value = err.response?.data?.error || 'Failed to import bookmarks. Please try again.'
    uploading.value = false
  } finally {
    if (!error.value) {
      uploading.value = false
    }
  }
}

function close() {
  emit('close')
}
</script>
