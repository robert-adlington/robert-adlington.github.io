<template>
  <div class="card flex-1 overflow-hidden">
    <!-- Classic Wooden Board -->
    <div v-if="theme === 'classic'" class="h-full flex flex-col">
      <h3 class="text-center font-bold text-lg mb-4">Cribbage Board</h3>
      <div class="flex-1 wood-texture rounded-lg p-6 relative">
        <!-- Track Layout: Two rows of 60 holes each + finish -->
        <div class="h-full flex flex-col justify-around">
          <!-- First Row (1-60) -->
          <div class="flex justify-between items-center">
            <div class="flex gap-1">
              <div
                v-for="pos in 60"
                :key="`row1-${pos}`"
                class="hole"
                :class="{ 'has-peg': hasPlayer1Peg(pos) || hasPlayer2Peg(pos) }"
              >
                <div
                  v-if="hasPlayer1Peg(pos)"
                  class="peg peg-player1"
                  :class="{ 'leading-peg': isLeadingPeg(1, pos) }"
                ></div>
                <div
                  v-if="hasPlayer2Peg(pos)"
                  class="peg peg-player2"
                  :class="{ 'leading-peg': isLeadingPeg(2, pos) }"
                ></div>
                <span class="hole-number">{{ pos }}</span>
              </div>
            </div>
          </div>

          <!-- Second Row (61-120) -->
          <div class="flex justify-between items-center">
            <div class="flex gap-1 flex-row-reverse">
              <div
                v-for="pos in 60"
                :key="`row2-${pos}`"
                class="hole"
                :class="{ 'has-peg': hasPlayer1Peg(60 + pos) || hasPlayer2Peg(60 + pos) }"
              >
                <div
                  v-if="hasPlayer1Peg(60 + pos)"
                  class="peg peg-player1"
                  :class="{ 'leading-peg': isLeadingPeg(1, 60 + pos) }"
                ></div>
                <div
                  v-if="hasPlayer2Peg(60 + pos)"
                  class="peg peg-player2"
                  :class="{ 'leading-peg': isLeadingPeg(2, 60 + pos) }"
                ></div>
                <span class="hole-number">{{ 60 + pos }}</span>
              </div>
            </div>
          </div>

          <!-- Finish (121) -->
          <div class="flex justify-center">
            <div
              class="hole hole-finish"
              :class="{ 'has-peg': hasPlayer1Peg(121) || hasPlayer2Peg(121) }"
            >
              <div v-if="hasPlayer1Peg(121)" class="peg peg-player1 leading-peg"></div>
              <div v-if="hasPlayer2Peg(121)" class="peg peg-player2 leading-peg"></div>
              <span class="hole-number font-bold">121</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mountain Theme -->
    <div v-else-if="theme === 'mountain'" class="h-full flex flex-col">
      <h3 class="text-center font-bold text-lg mb-4">Mountain Climb</h3>
      <div class="flex-1 relative bg-gradient-to-t from-green-700 via-gray-400 to-white rounded-lg p-6">
        <!-- Winding path up the mountain -->
        <svg class="w-full h-full" viewBox="0 0 300 400" preserveAspectRatio="xMidYMid meet">
          <!-- Mountain path -->
          <path
            d="M 10 390 Q 50 350, 90 310 Q 130 270, 170 230 Q 210 190, 150 150 Q 90 110, 130 70 Q 170 30, 150 10"
            fill="none"
            stroke="#8B4513"
            stroke-width="8"
            stroke-dasharray="5,5"
          />
          <!-- Player 1 climber -->
          <circle
            :cx="getMountainX(player1Score)"
            :cy="getMountainY(player1Score)"
            r="8"
            fill="#DC2626"
          />
          <!-- Player 2 climber -->
          <circle
            :cx="getMountainX(player2Score)"
            :cy="getMountainY(player2Score)"
            r="8"
            fill="#2563EB"
          />
          <!-- Peak marker -->
          <text x="150" y="15" text-anchor="middle" fill="white" font-weight="bold">⛰️ 121</text>
        </svg>
      </div>
    </div>

    <!-- Skiing Theme -->
    <div v-else-if="theme === 'skiing'" class="h-full flex flex-col">
      <h3 class="text-center font-bold text-lg mb-4">Ski Down the Mountain</h3>
      <div class="flex-1 relative bg-gradient-to-b from-white via-blue-200 to-green-700 rounded-lg p-6">
        <svg class="w-full h-full" viewBox="0 0 300 400" preserveAspectRatio="xMidYMid meet">
          <!-- Ski slope path -->
          <path
            d="M 150 10 Q 190 50, 210 90 Q 230 130, 180 170 Q 130 210, 170 250 Q 210 290, 150 330 Q 90 370, 150 390"
            fill="none"
            stroke="#0EA5E9"
            stroke-width="8"
            stroke-dasharray="5,5"
          />
          <!-- Player 1 skier -->
          <circle
            :cx="getSkiingX(player1Score)"
            :cy="getSkiingY(player1Score)"
            r="8"
            fill="#DC2626"
          />
          <!-- Player 2 skier -->
          <circle
            :cx="getSkiingX(player2Score)"
            :cy="getSkiingY(player2Score)"
            r="8"
            fill="#2563EB"
          />
          <!-- Finish marker -->
          <text x="150" y="395" text-anchor="middle" fill="white" font-weight="bold">🎿 121</text>
        </svg>
      </div>
    </div>

    <!-- Moon Theme -->
    <div v-else-if="theme === 'moon'" class="h-full flex flex-col">
      <h3 class="text-center font-bold text-lg mb-4">Fly to the Moon</h3>
      <div class="flex-1 relative bg-gradient-to-t from-blue-900 via-purple-900 to-black rounded-lg p-6">
        <!-- Stars background -->
        <div class="absolute inset-0 opacity-50">
          <div v-for="i in 50" :key="i" class="star" :style="starStyle(i)"></div>
        </div>
        <svg class="w-full h-full relative z-10" viewBox="0 0 300 400" preserveAspectRatio="xMidYMid meet">
          <!-- Flight path -->
          <path
            d="M 150 390 Q 100 350, 120 310 Q 140 270, 100 230 Q 60 190, 100 150 Q 140 110, 120 70 Q 100 30, 150 10"
            fill="none"
            stroke="#FCD34D"
            stroke-width="4"
            stroke-dasharray="3,3"
            opacity="0.5"
          />
          <!-- Player 1 rocket -->
          <text
            :x="getMoonX(player1Score)"
            :y="getMoonY(player1Score)"
            text-anchor="middle"
            font-size="20"
          >🚀</text>
          <!-- Player 2 rocket -->
          <text
            :x="getMoonX(player2Score) + 15"
            :y="getMoonY(player2Score)"
            text-anchor="middle"
            font-size="20"
          >🚀</text>
          <!-- Moon marker -->
          <text x="150" y="20" text-anchor="middle" font-size="30">🌙</text>
        </svg>
      </div>
    </div>

    <!-- Score Legend -->
    <div class="mt-4 flex justify-around text-sm">
      <div class="flex items-center gap-2">
        <div class="w-4 h-4 rounded-full bg-peg-player1"></div>
        <span>Player 1</span>
      </div>
      <div class="flex items-center gap-2">
        <div class="w-4 h-4 rounded-full bg-peg-player2"></div>
        <span>Player 2</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  player1Score: {
    type: Number,
    required: true
  },
  player2Score: {
    type: Number,
    required: true
  },
  player1LastPoints: {
    type: Number,
    default: 0
  },
  player2LastPoints: {
    type: Number,
    default: 0
  },
  theme: {
    type: String,
    default: 'classic'
  }
})

// Classic board peg positions
function hasPlayer1Peg(position) {
  const score = props.player1Score
  const lastPoints = props.player1LastPoints
  const leadingPos = score
  const trailingPos = score - lastPoints

  return position === leadingPos || (lastPoints > 0 && position === trailingPos)
}

function hasPlayer2Peg(position) {
  const score = props.player2Score
  const lastPoints = props.player2LastPoints
  const leadingPos = score
  const trailingPos = score - lastPoints

  return position === leadingPos || (lastPoints > 0 && position === trailingPos)
}

function isLeadingPeg(player, position) {
  if (player === 1) {
    return position === props.player1Score
  } else {
    return position === props.player2Score
  }
}

// Mountain theme positions
function getMountainX(score) {
  const progress = score / 121
  const positions = [
    { x: 10, y: 390 },
    { x: 50, y: 350 },
    { x: 90, y: 310 },
    { x: 130, y: 270 },
    { x: 170, y: 230 },
    { x: 210, y: 190 },
    { x: 150, y: 150 },
    { x: 90, y: 110 },
    { x: 130, y: 70 },
    { x: 170, y: 30 },
    { x: 150, y: 10 }
  ]
  const index = Math.floor(progress * (positions.length - 1))
  return positions[index]?.x || 150
}

function getMountainY(score) {
  const progress = score / 121
  return 390 - (progress * 380)
}

// Skiing theme positions
function getSkiingX(score) {
  const progress = score / 121
  return 150 + Math.sin(progress * Math.PI * 3) * 50
}

function getSkiingY(score) {
  const progress = score / 121
  return 10 + (progress * 380)
}

// Moon theme positions
function getMoonX(score) {
  const progress = score / 121
  return 150 + Math.sin(progress * Math.PI * 2) * 40
}

function getMoonY(score) {
  const progress = score / 121
  return 390 - (progress * 380)
}

// Star positions for moon theme
function starStyle(index) {
  const x = (index * 37) % 100
  const y = (index * 53) % 100
  const size = 1 + (index % 3)
  return {
    position: 'absolute',
    left: `${x}%`,
    top: `${y}%`,
    width: `${size}px`,
    height: `${size}px`,
    backgroundColor: 'white',
    borderRadius: '50%'
  }
}
</script>

<style scoped>
.hole {
  @apply relative w-3 h-3 bg-wood-dark rounded-full flex items-center justify-center;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hole-finish {
  @apply w-6 h-6;
}

.hole-number {
  @apply absolute text-[6px] text-white font-bold opacity-0 transition-opacity;
  top: -12px;
}

.hole:hover .hole-number {
  @apply opacity-100;
}

.peg {
  @apply absolute rounded-full shadow-lg z-10;
  width: 10px;
  height: 10px;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.peg-player1 {
  @apply bg-peg-player1;
}

.peg-player2 {
  @apply bg-peg-player2;
}

.leading-peg {
  @apply ring-2 ring-yellow-400;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

.star {
  animation: twinkle 3s infinite;
}

@keyframes twinkle {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}
</style>
