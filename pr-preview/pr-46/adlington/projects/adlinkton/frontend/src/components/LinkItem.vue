<template>
  <div
    class="link-item group"
    :class="{ 'bg-primary-50': isFocused }"
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
    </div>

    <!-- Favorite indicator -->
    <button
      v-if="link.is_favorite"
      class="flex-shrink-0 text-yellow-500 hover:text-yellow-600"
      @click.stop="$emit('toggle-favorite', link)"
      title="Favorited"
    >
      ⭐
    </button>

    <!-- Menu button (shows on hover) -->
    <button
      class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity text-gray-500 hover:text-gray-700"
      @click.stop="$emit('menu', $event)"
      title="More options"
    >
      ⋮
    </button>
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
  }
})

const emit = defineEmits(['click', 'contextmenu', 'menu', 'toggle-favorite'])

const isFocused = computed(() => props.focused)

function handleClick() {
  emit('click', props.link)
}

function handleFaviconError(event) {
  event.target.style.display = 'none'
}
</script>

<style scoped>
.link-item {
  cursor: pointer;
}
</style>
