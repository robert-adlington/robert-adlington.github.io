<template>
  <div class="link-list flex flex-col h-full">
    <!-- Loading state -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="text-gray-500">Loading links...</div>
    </div>

    <!-- Empty state -->
    <div v-else-if="links.length === 0" class="flex-1 flex items-center justify-center">
      <div class="text-center text-gray-500">
        <p class="text-lg mb-2">No links yet</p>
        <p class="text-sm">{{ emptyMessage }}</p>
      </div>
    </div>

    <!-- Links list -->
    <div v-else class="flex-1 overflow-y-auto">
      <LinkItem
        v-for="link in links"
        :key="link.id"
        :link="link"
        :focused="focusedLinkId === link.id"
        @click="handleLinkClick"
        @toggle-favorite="handleToggleFavorite"
        @menu="handleMenu"
        @contextmenu="handleContextMenu"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import LinkItem from './LinkItem.vue'
import { linksApi } from '@/api/links'

const props = defineProps({
  categoryId: {
    type: Number,
    default: null
  },
  favorite: {
    type: Boolean,
    default: null
  },
  search: {
    type: String,
    default: null
  },
  sort: {
    type: String,
    default: 'created'
  },
  order: {
    type: String,
    default: 'desc'
  },
  emptyMessage: {
    type: String,
    default: 'Add your first link to get started'
  }
})

const emit = defineEmits(['link-click', 'link-updated'])

const links = ref([])
const loading = ref(true)
const focusedLinkId = ref(null)

onMounted(async () => {
  await loadLinks()
})

async function loadLinks() {
  loading.value = true
  try {
    const params = {
      sort: props.sort,
      order: props.order
    }

    if (props.categoryId) {
      params.category_id = props.categoryId
    }

    if (props.favorite !== null) {
      params.favorite = props.favorite
    }

    if (props.search) {
      params.search = props.search
    }

    const response = await linksApi.getLinks(params)
    links.value = response.links
  } catch (error) {
    console.error('Failed to load links:', error)
  } finally {
    loading.value = false
  }
}

async function handleLinkClick(link) {
  // Record link access
  try {
    await linksApi.recordAccess(link.id)
  } catch (error) {
    console.error('Failed to record link access:', error)
  }

  // Open link in new tab by default
  window.open(link.url, '_blank')

  emit('link-click', link)
}

async function handleToggleFavorite(link) {
  try {
    await linksApi.updateLink(link.id, {
      is_favorite: !link.is_favorite
    })

    // Update local state
    const index = links.value.findIndex(l => l.id === link.id)
    if (index !== -1) {
      links.value[index].is_favorite = !links.value[index].is_favorite
    }

    emit('link-updated', link)
  } catch (error) {
    console.error('Failed to toggle favorite:', error)
  }
}

function handleMenu(event) {
  console.log('Menu clicked:', event)
  // TODO: Implement context menu
}

function handleContextMenu(event) {
  console.log('Context menu:', event)
  // TODO: Implement context menu
}

// Expose methods to parent
defineExpose({
  loadLinks
})
</script>

<style scoped>
.link-list {
  background: white;
}
</style>
