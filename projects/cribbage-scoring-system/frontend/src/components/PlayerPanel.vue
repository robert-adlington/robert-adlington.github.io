<template>
  <div class="flex flex-col h-full">
    <div class="card flex-1 flex flex-col">
      <!-- Player Info -->
      <div class="text-center mb-4 pb-4 border-b border-gray-200">
        <div class="flex items-center justify-center gap-2 mb-2">
          <h2 class="text-2xl font-bold">{{ name }}</h2>
          <span
            v-if="isDealer"
            class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-semibold"
          >
            DEALER
          </span>
        </div>

        <!-- Score Display -->
        <div class="mt-3">
          <div class="text-6xl font-bold" :class="isLeading ? 'text-green-600' : 'text-gray-700'">
            {{ score }}
          </div>
          <div class="text-sm text-gray-500 mt-1">
            <span v-if="scoreDiff > 0">
              {{ isLeading ? '+' : '-' }}{{ scoreDiff }}
            </span>
            <span v-else>Tied</span>
          </div>
          <div v-if="lastPoints > 0" class="text-sm text-blue-600 font-semibold mt-1">
            Last: +{{ lastPoints }}
          </div>
        </div>

        <!-- Win Stats -->
        <div class="mt-3 text-sm text-gray-600">
          <div>Session: {{ sessionWins }} wins</div>
          <div v-if="gameWins > 0">Games: {{ gameWins }}</div>
        </div>
      </div>

      <!-- Scoring Keypad -->
      <div class="flex-1 flex flex-col">
        <h3 class="text-sm font-semibold mb-3 text-gray-700">Add Points</h3>
        <ScoringKeypad
          @add-points="$emit('add-points', $event)"
          @undo="$emit('undo')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import ScoringKeypad from './ScoringKeypad.vue'

defineProps({
  player: {
    type: String,
    required: true
  },
  name: {
    type: String,
    required: true
  },
  score: {
    type: Number,
    required: true
  },
  lastPoints: {
    type: Number,
    default: 0
  },
  isDealer: {
    type: Boolean,
    default: false
  },
  isLeading: {
    type: Boolean,
    default: false
  },
  scoreDiff: {
    type: Number,
    default: 0
  },
  sessionWins: {
    type: Number,
    default: 0
  },
  gameWins: {
    type: Number,
    default: 0
  }
})

defineEmits(['add-points', 'undo'])
</script>
