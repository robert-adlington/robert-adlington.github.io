<template>
  <div class="content-item">
    <!-- Subcategory -->
    <div v-if="item.type === 'category'" class="subcategory-item">
      <!-- Subcategory Header -->
      <div
        class="subcategory-header"
        :class="{ 'expanded': isSubcategoryExpanded }"
        @click="toggleSubcategory"
      >
        <span class="expand-icon">{{ isSubcategoryExpanded ? '▼' : '▶' }}</span>
        <span class="subcategory-name">{{ item.data.name }} ({{ item.data.link_count || 0 }})</span>
      </div>

      <!-- Subcategory Content (when expanded) -->
      <div v-if="isSubcategoryExpanded" class="subcategory-content">
        <CategoryContentItem
          v-for="subItem in subcategoryContent"
          :key="subItem.type + '-' + subItem.id"
          :item="subItem"
          :category-map="categoryMap"
          :expanded-ids="expandedIds"
          :depth="depth + 1"
          @toggle-subcategory="$emit('toggle-subcategory', $event)"
          @link-click="$emit('link-click', $event)"
          @toggle-favorite="$emit('toggle-favorite', $event)"
          @link-menu="$emit('link-menu', $event)"
        />

        <!-- Loading state -->
        <div v-if="loadingLinks" class="text-xs text-gray-500 py-1 px-3" :style="indentStyle">
          Loading...
        </div>

        <!-- Empty state -->
        <div v-else-if="subcategoryContent.length === 0" class="text-xs text-gray-500 py-1 px-3" :style="indentStyle">
          No items
        </div>
      </div>
    </div>

    <!-- Link -->
    <div
      v-else-if="item.type === 'link'"
      class="link-item"
      :style="indentStyle"
      @click="handleLinkClick"
    >
      <div class="link-favicon">{{ getFavicon(item.data) }}</div>
      <div class="link-name">{{ item.data.name }}</div>
      <div class="link-actions">
        <button
          class="favorite-btn"
          :class="{ 'is-favorite': item.data.is_favorite }"
          @click.stop="handleToggleFavorite"
          title="Toggle favorite"
        >
          {{ item.data.is_favorite ? '⭐' : '☆' }}
        </button>
        <button
          class="menu-btn"
          @click.stop="handleLinkMenu"
          title="More options"
        >
          ⋮
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { linksApi } from '@/api/links'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  categoryMap: {
    type: Object,
    default: () => ({})
  },
  expandedIds: {
    type: Set,
    default: () => new Set()
  },
  depth: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['toggle-subcategory', 'link-click', 'toggle-favorite', 'link-menu'])

// State
const subcategoryLinks = ref([])
const loadingLinks = ref(false)

// Computed
const isSubcategoryExpanded = computed(() => {
  return props.item.type === 'category' && props.expandedIds.has(props.item.id)
})

const indentStyle = computed(() => {
  return {
    paddingLeft: `${props.depth * 1}rem`
  }
})

const subcategoryContent = computed(() => {
  if (props.item.type !== 'category') return []

  const content = []

  // Add child subcategories
  if (props.item.data.children && props.item.data.children.length > 0) {
    props.item.data.children.forEach(child => {
      content.push({
        type: 'category',
        id: child.id,
        data: child,
        order: child.order_position || 0
      })
    })
  }

  // Add links
  subcategoryLinks.value.forEach(link => {
    content.push({
      type: 'link',
      id: link.id,
      data: link,
      order: link.order_position || 0
    })
  })

  // Sort by order
  content.sort((a, b) => a.order - b.order)

  return content
})

// Methods
function toggleSubcategory() {
  if (props.item.type === 'category') {
    emit('toggle-subcategory', props.item.id)
  }
}

function handleLinkClick() {
  emit('link-click', props.item.data)
}

function handleToggleFavorite() {
  emit('toggle-favorite', props.item.data)
}

function handleLinkMenu() {
  emit('link-menu', props.item.data)
}

function getFavicon(link) {
  // Simple favicon - could be enhanced with actual favicon fetching
  return '🌐'
}

async function loadSubcategoryLinks() {
  if (props.item.type !== 'category') return

  loadingLinks.value = true
  try {
    const response = await linksApi.getLinks({
      category_id: props.item.data.id,
      sort: 'order_position',
      order: 'asc'
    })
    subcategoryLinks.value = response.links || []
  } catch (error) {
    console.error('Failed to load subcategory links:', error)
  } finally {
    loadingLinks.value = false
  }
}

// Watch for expansion and load links
watch(isSubcategoryExpanded, (newVal) => {
  if (newVal && subcategoryLinks.value.length === 0) {
    loadSubcategoryLinks()
  }
})
</script>

<style scoped>
.content-item {
  font-size: 0.875rem;
}

/* Subcategory */
.subcategory-item {
  border-left: 2px solid #e5e7eb;
  margin-left: 0.5rem;
}

.subcategory-header {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  transition: background-color 0.2s;
  gap: 0.5rem;
}

.subcategory-header:hover {
  background-color: #f9fafb;
}

.subcategory-header.expanded {
  background-color: #f3f4f6;
}

.expand-icon {
  font-size: 0.75rem;
  color: #6b7280;
  width: 1rem;
  display: inline-block;
}

.subcategory-name {
  font-weight: 500;
  color: #374151;
}

.subcategory-content {
  /* Nested content */
}

/* Link */
.link-item {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  gap: 0.5rem;
  cursor: pointer;
  transition: background-color 0.2s;
  min-height: 40px;
}

.link-item:hover {
  background-color: #f9fafb;
}

.link-favicon {
  font-size: 1rem;
  flex-shrink: 0;
}

.link-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #374151;
}

.link-actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  opacity: 0;
  transition: opacity 0.2s;
}

.link-item:hover .link-actions {
  opacity: 1;
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
  transition: color 0.2s;
}

.favorite-btn:hover,
.menu-btn:hover {
  color: #374151;
}

.favorite-btn.is-favorite {
  color: #fbbf24;
  opacity: 1;
}
</style>
