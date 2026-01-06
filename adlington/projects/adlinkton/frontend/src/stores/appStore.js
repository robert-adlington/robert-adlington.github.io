import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

/**
 * Main application store
 */
export const useAppStore = defineStore('app', () => {
  // Data
  const links = ref(new Map())
  const categories = ref(new Map())
  const smartCategories = ref(new Map())
  const tags = ref(new Map())

  // UI State
  const selectedLinkIds = ref(new Set())
  const focusedLinkId = ref(null)
  const focusedCategoryId = ref(null)
  const expandedCategoryIds = ref(new Set())

  // Layout
  const panes = ref([])
  const fullscreenPaneId = ref(null)

  // Search
  const searchQuery = ref('')
  const searchResults = ref([])

  // Settings
  const settings = ref({
    link_open_behavior: 'new_tab',
    default_display_mode: 'collapsible_tile',
    default_sort: 'manual',
    keyboard_shortcuts: null,
  })

  // Computed
  const linksArray = computed(() => Array.from(links.value.values()))
  const categoriesArray = computed(() => Array.from(categories.value.values()))
  const tagsArray = computed(() => Array.from(tags.value.values()))

  // Actions
  function addLink(link) {
    links.value.set(link.id, link)
  }

  function removeLink(linkId) {
    links.value.delete(linkId)
  }

  function updateLink(linkId, updates) {
    const link = links.value.get(linkId)
    if (link) {
      links.value.set(linkId, { ...link, ...updates })
    }
  }

  function addCategory(category) {
    categories.value.set(category.id, category)
  }

  function toggleCategoryExpanded(categoryId) {
    if (expandedCategoryIds.value.has(categoryId)) {
      expandedCategoryIds.value.delete(categoryId)
    } else {
      expandedCategoryIds.value.add(categoryId)
    }
  }

  function selectLink(linkId) {
    selectedLinkIds.value.add(linkId)
  }

  function deselectLink(linkId) {
    selectedLinkIds.value.delete(linkId)
  }

  function clearSelection() {
    selectedLinkIds.value.clear()
  }

  return {
    // State
    links,
    categories,
    smartCategories,
    tags,
    selectedLinkIds,
    focusedLinkId,
    focusedCategoryId,
    expandedCategoryIds,
    panes,
    fullscreenPaneId,
    searchQuery,
    searchResults,
    settings,

    // Computed
    linksArray,
    categoriesArray,
    tagsArray,

    // Actions
    addLink,
    removeLink,
    updateLink,
    addCategory,
    toggleCategoryExpanded,
    selectLink,
    deselectLink,
    clearSelection,
  }
})
