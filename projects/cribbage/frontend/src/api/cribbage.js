const API_BASE = '/projects/cribbage/api'

async function request(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...options.headers
    },
    ...options
  })

  const data = await response.json()

  if (!response.ok) {
    throw new Error(data.error || 'Request failed')
  }

  return data.data || data
}

// Session endpoints
export async function createSession(sessionData) {
  return request('/sessions.php', {
    method: 'POST',
    body: JSON.stringify(sessionData)
  })
}

export async function getSession(sessionId) {
  return request(`/sessions.php?id=${sessionId}`)
}

export async function getSessions() {
  return request('/sessions.php')
}

export async function updateSession(sessionId, updates) {
  return request(`/sessions.php?id=${sessionId}`, {
    method: 'PUT',
    body: JSON.stringify(updates)
  })
}

// Game endpoints
export async function createGame(sessionId) {
  return request('/games.php', {
    method: 'POST',
    body: JSON.stringify({ session_id: sessionId })
  })
}

export async function getGame(gameId) {
  return request(`/games.php?id=${gameId}`)
}

// Move endpoints
export async function addMove(gameId, moveData) {
  return request('/moves.php', {
    method: 'POST',
    body: JSON.stringify({
      game_id: gameId,
      ...moveData
    })
  })
}

export async function undoMove(gameId, moveId) {
  return request(`/moves.php?id=${moveId}`, {
    method: 'DELETE'
  })
}

export async function getMoves(gameId) {
  return request(`/moves.php?game_id=${gameId}`)
}
