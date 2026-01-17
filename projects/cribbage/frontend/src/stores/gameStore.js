import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as api from '../api/cribbage'

export const useGameStore = defineStore('game', () => {
  // Session state
  const session = ref(null)
  const currentGame = ref(null)
  const moves = ref([])
  const boardTheme = ref('classic') // classic, mountain, skiing, moon

  // Loading states
  const loading = ref(false)
  const error = ref(null)

  // Computed properties
  const player1Score = computed(() => currentGame.value?.player1_score || 0)
  const player2Score = computed(() => currentGame.value?.player2_score || 0)

  const scoreDifference = computed(() => {
    return Math.abs(player1Score.value - player2Score.value)
  })

  const player1Leading = computed(() => {
    return player1Score.value > player2Score.value
  })

  const lastMove = computed(() => {
    return moves.value.length > 0 ? moves.value[moves.value.length - 1] : null
  })

  const player1LastPoints = computed(() => {
    const last = lastMove.value
    return (last && last.player === 1) ? last.points : 0
  })

  const player2LastPoints = computed(() => {
    const last = lastMove.value
    return (last && last.player === 2) ? last.points : 0
  })

  const sessionStats = computed(() => {
    if (!session.value) return { player1: 0, player2: 0 }
    return {
      player1: session.value.player1_total_wins || 0,
      player2: session.value.player2_total_wins || 0
    }
  })

  const currentGameNumber = computed(() => {
    return currentGame.value?.game_number || 0
  })

  // Actions
  async function createSession(player1Name, player2Name, player1Id = null, player2Id = null) {
    loading.value = true
    error.value = null
    try {
      const data = await api.createSession({
        player1_name: player1Name,
        player2_name: player2Name,
        player1_id: player1Id,
        player2_id: player2Id
      })
      session.value = data.session
      currentGame.value = data.game
      moves.value = []
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadSession(sessionId) {
    loading.value = true
    error.value = null
    try {
      const data = await api.getSession(sessionId)
      session.value = data.session
      currentGame.value = data.current_game
      moves.value = data.moves || []
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function addPoints(player, points) {
    if (!currentGame.value || currentGame.value.is_complete) {
      throw new Error('No active game')
    }

    loading.value = true
    error.value = null
    try {
      const data = await api.addMove(currentGame.value.id, {
        player,
        points
      })

      // Update local state
      currentGame.value = data.game
      moves.value.push(data.move)

      // Check if game is complete
      if (data.game.is_complete) {
        // Update session stats
        if (session.value) {
          if (data.game.winner === 1) {
            session.value.player1_total_wins += data.game.is_skunk ? 2 : 1
          } else {
            session.value.player2_total_wins += data.game.is_skunk ? 2 : 1
          }
        }
      }

      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function undoLastMove() {
    if (moves.value.length === 0) {
      throw new Error('No moves to undo')
    }

    loading.value = true
    error.value = null
    try {
      const lastMoveId = moves.value[moves.value.length - 1].id
      const data = await api.undoMove(currentGame.value.id, lastMoveId)

      // Update local state
      currentGame.value = data.game
      moves.value.pop()

      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function startNewGame() {
    if (!session.value) {
      throw new Error('No active session')
    }

    loading.value = true
    error.value = null
    try {
      const data = await api.createGame(session.value.id)
      currentGame.value = data.game
      moves.value = []
      return data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  function setBoardTheme(theme) {
    boardTheme.value = theme
  }

  function resetStore() {
    session.value = null
    currentGame.value = null
    moves.value = []
    loading.value = false
    error.value = null
    boardTheme.value = 'classic'
  }

  return {
    // State
    session,
    currentGame,
    moves,
    boardTheme,
    loading,
    error,

    // Computed
    player1Score,
    player2Score,
    scoreDifference,
    player1Leading,
    player1LastPoints,
    player2LastPoints,
    sessionStats,
    currentGameNumber,

    // Actions
    createSession,
    loadSession,
    addPoints,
    undoLastMove,
    startNewGame,
    setBoardTheme,
    resetStore
  }
})
