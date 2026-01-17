<template>
  <div class="h-screen w-screen flex flex-col overflow-hidden">
    <!-- Header -->
    <header class="bg-gray-900 text-white py-3 px-6 shadow-lg flex justify-between items-center">
      <h1 class="text-2xl font-bold">Cribbage Scoring</h1>
      <div class="flex gap-4 items-center">
        <select
          v-if="session"
          v-model="gameStore.boardTheme"
          class="bg-gray-800 text-white px-3 py-1 rounded-lg text-sm"
          @change="gameStore.setBoardTheme($event.target.value)"
        >
          <option value="classic">Classic Board</option>
          <option value="mountain">Mountain Climb</option>
          <option value="skiing">Skiing</option>
          <option value="moon">Moon Flight</option>
        </select>
        <button
          v-if="session"
          @click="showMenu = !showMenu"
          class="btn btn-secondary text-sm"
        >
          Menu
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-hidden">
      <!-- Setup Screen -->
      <NewGameSetup
        v-if="!session"
        @start="handleStartSession"
      />

      <!-- Game Screen -->
      <div v-else class="h-full flex gap-4 p-4">
        <!-- Player 1 Panel -->
        <PlayerPanel
          player="1"
          :name="session.player1_name"
          :score="player1Score"
          :lastPoints="player1LastPoints"
          :isDealer="currentGame?.player1_is_dealer"
          :isLeading="player1Score > player2Score"
          :scoreDiff="scoreDifference"
          :sessionWins="sessionStats.player1"
          :gameWins="countPlayer1GameWins"
          @add-points="handleAddPoints(1, $event)"
          @undo="handleUndo"
          class="flex-1"
        />

        <!-- Board -->
        <div class="flex-[2] flex flex-col gap-4">
          <CribbageBoard
            :player1Score="player1Score"
            :player2Score="player2Score"
            :player1LastPoints="player1LastPoints"
            :player2LastPoints="player2LastPoints"
            :theme="gameStore.boardTheme"
          />
          <div class="text-center text-white text-sm bg-black/30 rounded-lg p-2">
            Game {{ currentGameNumber }}
            <span v-if="sessionStats.player1 > 0 || sessionStats.player2 > 0">
              | Session: {{ session.player1_name }} {{ sessionStats.player1 }} - {{ sessionStats.player2 }} {{ session.player2_name }}
            </span>
          </div>
        </div>

        <!-- Player 2 Panel -->
        <PlayerPanel
          player="2"
          :name="session.player2_name"
          :score="player2Score"
          :lastPoints="player2LastPoints"
          :isDealer="currentGame?.player1_is_dealer === false"
          :isLeading="player2Score > player1Score"
          :scoreDiff="scoreDifference"
          :sessionWins="sessionStats.player2"
          :gameWins="countPlayer2GameWins"
          @add-points="handleAddPoints(2, $event)"
          @undo="handleUndo"
          class="flex-1"
        />
      </div>

      <!-- Game Complete Modal -->
      <GameComplete
        v-if="currentGame?.is_complete"
        :winner="currentGame.winner"
        :winnerName="currentGame.winner === 1 ? session.player1_name : session.player2_name"
        :isSkunk="currentGame.is_skunk"
        :player1Score="player1Score"
        :player2Score="player2Score"
        :sessionStats="sessionStats"
        @new-game="handleNewGame"
        @new-session="handleNewSession"
      />

      <!-- Menu Modal -->
      <div
        v-if="showMenu"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showMenu = false"
      >
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
          <h2 class="text-2xl font-bold mb-4">Menu</h2>
          <div class="space-y-3">
            <button @click="handleNewGame" class="btn btn-primary w-full">
              Start New Game
            </button>
            <button @click="handleNewSession" class="btn btn-secondary w-full">
              New Session (Different Players)
            </button>
            <button @click="showMenu = false" class="btn btn-secondary w-full">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Error Toast -->
    <div
      v-if="gameStore.error"
      class="fixed bottom-4 right-4 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg"
    >
      {{ gameStore.error }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useGameStore } from './stores/gameStore'
import NewGameSetup from './components/NewGameSetup.vue'
import PlayerPanel from './components/PlayerPanel.vue'
import CribbageBoard from './components/CribbageBoard.vue'
import GameComplete from './components/GameComplete.vue'

const gameStore = useGameStore()
const showMenu = ref(false)

const session = computed(() => gameStore.session)
const currentGame = computed(() => gameStore.currentGame)
const player1Score = computed(() => gameStore.player1Score)
const player2Score = computed(() => gameStore.player2Score)
const scoreDifference = computed(() => gameStore.scoreDifference)
const player1LastPoints = computed(() => gameStore.player1LastPoints)
const player2LastPoints = computed(() => gameStore.player2LastPoints)
const sessionStats = computed(() => gameStore.sessionStats)
const currentGameNumber = computed(() => gameStore.currentGameNumber)

// Count actual completed games won (not including skunk bonuses)
const countPlayer1GameWins = computed(() => {
  // This would need to come from the backend
  return 0
})

const countPlayer2GameWins = computed(() => {
  // This would need to come from the backend
  return 0
})

async function handleStartSession({ player1Name, player2Name }) {
  try {
    await gameStore.createSession(player1Name, player2Name)
  } catch (err) {
    console.error('Failed to start session:', err)
  }
}

async function handleAddPoints(player, points) {
  try {
    await gameStore.addPoints(player, points)
  } catch (err) {
    console.error('Failed to add points:', err)
  }
}

async function handleUndo() {
  try {
    await gameStore.undoLastMove()
  } catch (err) {
    console.error('Failed to undo:', err)
  }
}

async function handleNewGame() {
  showMenu.value = false
  try {
    await gameStore.startNewGame()
  } catch (err) {
    console.error('Failed to start new game:', err)
  }
}

function handleNewSession() {
  showMenu.value = false
  gameStore.resetStore()
}
</script>
