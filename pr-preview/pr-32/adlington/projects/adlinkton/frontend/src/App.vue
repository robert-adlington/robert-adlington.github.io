<template>
  <div id="app" class="h-full flex flex-col bg-white">
    <!-- Header -->
    <header class="flex items-center justify-between px-6 py-3 border-b bg-white shadow-sm">
      <div class="flex items-center gap-4">
        <h1 class="text-2xl font-bold text-primary-600">Adlinkton</h1>
      </div>

      <div class="flex items-center gap-4">
        <input
          type="search"
          placeholder="Search links..."
          class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        />
        <button class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
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
            <a href="#" class="block px-3 py-2 rounded hover:bg-gray-200">📥 Inbox</a>
            <a href="#" class="block px-3 py-2 rounded hover:bg-gray-200">⭐ Favorites</a>
            <a href="#" class="block px-3 py-2 rounded hover:bg-gray-200">🕐 Recent</a>
          </nav>

          <h2 class="text-xs font-semibold text-gray-500 uppercase mt-6 mb-2">Categories</h2>
          <nav class="space-y-1">
            <div class="text-sm text-gray-500 px-3 py-2">No categories yet</div>
          </nav>

          <h2 class="text-xs font-semibold text-gray-500 uppercase mt-6 mb-2">Smart Categories</h2>
          <nav class="space-y-1">
            <div class="text-sm text-gray-500 px-3 py-2">No smart categories</div>
          </nav>
        </div>
      </aside>

      <!-- Main Content Area -->
      <main class="flex-1 overflow-hidden">
        <div class="h-full flex flex-col">
          <!-- Pane Header -->
          <div class="pane-header">
            <h2 class="font-semibold">All Links</h2>
            <div class="flex gap-2">
              <select class="px-2 py-1 border rounded text-sm">
                <option>Sort: Manual</option>
                <option>Sort: Name</option>
                <option>Sort: Created</option>
                <option>Sort: Accessed</option>
              </select>
            </div>
          </div>

          <!-- Link List -->
          <div class="pane-content">
            <div class="text-center py-12 text-gray-500">
              <p class="text-lg mb-2">No links yet</p>
              <p class="text-sm">Add your first link to get started</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  console.log('Adlinkton initialized')
})
</script>

<style scoped>
/* Component-specific styles */
</style>
