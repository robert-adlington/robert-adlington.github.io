<template>
  <div class="category-node">
    <!-- Category item -->
    <div
      class="group flex items-center px-3 py-2 rounded hover:bg-gray-200 cursor-pointer"
      :class="{ 'bg-gray-200': isSelected }"
      @click="handleClick"
    >
      <!-- Expand/collapse icon -->
      <button
        v-if="category.children && category.children.length > 0"
        class="mr-1 text-gray-500 hover:text-gray-700"
        @click.stop="toggleExpanded"
      >
        <span v-if="isExpanded">▼</span>
        <span v-else>▶</span>
      </button>
      <span v-else class="w-4 mr-1"></span>

      <!-- Category name and count -->
      <span class="flex-1 text-sm truncate">
        {{ category.name }}
      </span>
      <span v-if="category.link_count > 0" class="text-xs text-gray-500 ml-2">
        {{ category.link_count }}
      </span>

      <!-- Action buttons (show on hover) -->
      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 ml-2">
        <button
          class="text-xs text-gray-600 hover:text-primary-600"
          @click.stop="handleEdit"
          title="Edit category"
        >
          ✏️
        </button>
        <button
          class="text-xs text-gray-600 hover:text-red-600"
          @click.stop="handleDelete"
          title="Delete category"
        >
          🗑️
        </button>
      </div>
    </div>

    <!-- Children (recursive) -->
    <div
      v-if="isExpanded && category.children && category.children.length > 0"
      class="ml-4 mt-1 space-y-1"
    >
      <CategoryNode
        v-for="child in category.children"
        :key="child.id"
        :category="child"
        :selected-id="selectedId"
        :expanded-ids="expandedIds"
        @select="$emit('select', $event)"
        @toggle-expand="$emit('toggle-expand', $event)"
        @edit="$emit('edit', $event)"
        @delete="$emit('delete', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  category: {
    type: Object,
    required: true
  },
  selectedId: {
    type: Number,
    default: null
  },
  expandedIds: {
    type: Set,
    default: () => new Set()
  }
})

const emit = defineEmits(['select', 'toggle-expand', 'edit', 'delete'])

const isSelected = computed(() => props.category.id === props.selectedId)
const isExpanded = computed(() => props.expandedIds.has(props.category.id))

function handleClick() {
  emit('select', props.category)
}

function toggleExpanded() {
  emit('toggle-expand', props.category.id)
}

function handleEdit() {
  emit('edit', props.category)
}

function handleDelete() {
  emit('delete', props.category)
}
</script>
