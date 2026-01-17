<template>
  <div class="h-full flex items-center justify-center">
    <div class="card max-w-md w-full mx-4">
      <h2 class="text-3xl font-bold mb-6 text-center">New Cribbage Game</h2>
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">Player 1 Name</label>
          <input
            v-model="player1Name"
            type="text"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Enter player 1 name"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Player 2 Name</label>
          <input
            v-model="player2Name"
            type="text"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Enter player 2 name"
          />
        </div>
        <div class="pt-4">
          <button type="submit" class="btn btn-primary w-full text-lg py-3">
            Start Game
          </button>
        </div>
      </form>
      <div class="mt-6 pt-6 border-t border-gray-200">
        <h3 class="font-semibold mb-2">How to Play:</h3>
        <ul class="text-sm text-gray-600 space-y-1">
          <li>• First player to 121 points wins</li>
          <li>• Win before opponent reaches 90 = Skunk (2 game wins)</li>
          <li>• Use the keypad to enter points scored</li>
          <li>• Track is completed twice (60 points each lap) + 1 point</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['start'])

const player1Name = ref('')
const player2Name = ref('')

function handleSubmit() {
  if (player1Name.value.trim() && player2Name.value.trim()) {
    emit('start', {
      player1Name: player1Name.value.trim(),
      player2Name: player2Name.value.trim()
    })
  }
}
</script>
