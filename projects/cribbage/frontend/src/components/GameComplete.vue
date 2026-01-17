<template>
  <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
    <div class="card max-w-lg w-full mx-4 text-center">
      <!-- Trophy/Victory -->
      <div class="text-6xl mb-4">
        {{ isSkunk ? '🏆🏆' : '🏆' }}
      </div>

      <!-- Winner Announcement -->
      <h2 class="text-4xl font-bold mb-2">
        {{ winnerName }} Wins!
      </h2>

      <!-- Skunk Message -->
      <div v-if="isSkunk" class="text-2xl text-yellow-600 font-bold mb-4">
        SKUNK! (2 Game Wins)
      </div>

      <!-- Final Score -->
      <div class="text-xl mb-6 text-gray-600">
        Final Score: {{ player1Score }} - {{ player2Score }}
      </div>

      <!-- Session Stats -->
      <div class="bg-gray-100 rounded-lg p-4 mb-6">
        <h3 class="font-semibold mb-2">Session Standings</h3>
        <div class="text-lg">
          {{ sessionStats.player1 }} - {{ sessionStats.player2 }}
        </div>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <button @click="$emit('new-game')" class="btn btn-primary w-full text-lg py-3">
          Play Another Game
        </button>
        <button @click="$emit('new-session')" class="btn btn-secondary w-full">
          New Session (Different Players)
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  winner: {
    type: Number,
    required: true
  },
  winnerName: {
    type: String,
    required: true
  },
  isSkunk: {
    type: Boolean,
    default: false
  },
  player1Score: {
    type: Number,
    required: true
  },
  player2Score: {
    type: Number,
    required: true
  },
  sessionStats: {
    type: Object,
    required: true
  }
})

defineEmits(['new-game', 'new-session'])
</script>
