<template>
  <div
    class="link-item group"
    :class="{ 'bg-primary-50': isFocused }"
    :style="indentStyle"
    @click="handleClick"
    @contextmenu.prevent="$emit('contextmenu', $event)"
  >
    <!-- Favicon -->
    <div class="flex-shrink-0">
      <img
        v-if="link.favicon_path"
        :src="link.favicon_path"
        :alt="link.name"
        class="w-4 h-4"
        @error="handleFaviconError"
      />
      <div v-else class="w-4 h-4 bg-gray-300 rounded flex items-center justify-center text-xs">
        🔗
      </div>
    </div>

    <!-- Link name -->
    <div class="flex-1 min-w-0">
      <span class="text-sm truncate block" :title="link.name">
        {{ link.name }}
      </span>
      <!-- Show categories only when showCategories is true -->
      <div v-if="showCategories && link.categories && link.categories.length > 0" class="flex gap-1 flex-wrap mt-1">
        <span
          v-for="(categoryId, index) in link.categories.slice(0, 3)"
          :key="categoryId"
          class="text-xs px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded"
          :title="getCategoryTitle(categoryId)"
        >
          {{ getCategoryName(categoryId) }}
        </span>
        <span
          v-if="link.categories.length > 3"
          class="text-xs px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded"
          :title="getExtraCategoriesTitle()"
        >
          +{{ link.categories.length - 3 }}
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-1 flex-shrink-0">
      <!-- Favorite button -->
      <button
        class="favorite-btn"
        :class="{ 'is-favorite': link.is_favorite, 'always-visible': link.is_favorite }"
        @click.stop="$emit('toggle-favorite', link)"
        :title="link.is_favorite ? 'Favorited' : 'Add to favorites'"
      >
        {{ link.is_favorite ? '⭐' : '☆' }}
      </button>

      <!-- Menu button (shows on hover) -->
      <button
        class="menu-btn"
        @click.stop="$emit('menu', $event)"
        title="More options"
      >
        ⋮
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  link: {
    type: Object,
    required: true
  },
  focused: {
    type: Boolean,
    default: false
  },
  categoryMap: {
    type: Object,
    default: () => ({})
  },
  depth: {
    type: Number,
    default: 0
  },
  showCategories: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click', 'contextmenu', 'menu', 'toggle-favorite'])

const isFocused = computed(() => props.focused)

const indentStyle = computed(() => {
  if (props.depth > 0) {
    return {
      paddingLeft: `${props.depth * 1}rem`
    }
  }
  return {}
})

function handleClick() {
  emit('click', props.link)
}

function handleFaviconError(event) {
  event.target.style.display = 'none'
}

function getCategoryName(categoryId) {
  return props.categoryMap[categoryId]?.name || `Cat ${categoryId}`
}

function getCategoryTitle(categoryId) {
  const category = props.categoryMap[categoryId]
  return category ? category.name : `Category ${categoryId}`
}

function getExtraCategoriesTitle() {
  const extraCategories = props.link.categories.slice(3)
  return extraCategories
    .map(id => getCategoryName(id))
    .join(', ')
}
</script>

<style scoped>
.link-item {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  min-height: 36px;
  transition: background-color 0.2s;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
}

.link-item:hover {
  background-color: #f9fafb;
}

.favorite-btn,
.menu-btn {
  padding: 0.25rem;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  color: #6b7280;
  transition: all 0.2s;
  opacity: 0;
}

.link-item:hover .favorite-btn,
.link-item:hover .menu-btn {
  opacity: 1;
}

.favorite-btn:hover,
.menu-btn:hover {
  color: #374151;
}

.favorite-btn.is-favorite {
  color: #fbbf24;
  opacity: 1;
}

.favorite-btn.always-visible {
  opacity: 1;
}
</style>
