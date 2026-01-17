<template>
  <div class="flex flex-col gap-2 flex-1">
    <!-- Input Display -->
    <div class="bg-gray-100 rounded-lg p-3 text-center min-h-[3rem] flex items-center justify-center">
      <span class="text-2xl font-bold text-gray-700">
        {{ currentInput || '0' }}
      </span>
    </div>

    <!-- Number Buttons (1-9) -->
    <div class="grid grid-cols-3 gap-2">
      <button
        v-for="n in 9"
        :key="n"
        @click="appendDigit(n)"
        class="btn btn-score aspect-square"
      >
        {{ n }}
      </button>
    </div>

    <!-- Zero and Ten Buttons -->
    <div class="grid grid-cols-3 gap-2">
      <button @click="appendDigit(0)" class="btn btn-score">
        0
      </button>
      <button @click="addQuickPoints(10)" class="btn btn-score col-span-2">
        1-
      </button>
    </div>

    <!-- Quick Add Buttons -->
    <div class="grid grid-cols-2 gap-2">
      <button @click="addQuickPoints(20)" class="btn btn-score">
        2-
      </button>
      <button @click="submitPoints" class="btn btn-primary">
        Add Points
      </button>
    </div>

    <!-- Undo Button -->
    <button @click="handleUndo" class="btn btn-secondary w-full">
      Undo Last Move
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['add-points', 'undo'])

const currentInput = ref('')

function appendDigit(digit) {
  // Limit to 3 digits (max 999)
  if (currentInput.value.length < 3) {
    currentInput.value += digit.toString()
  }
}

function addQuickPoints(points) {
  currentInput.value = points.toString()
  submitPoints()
}

function submitPoints() {
  const points = parseInt(currentInput.value) || 0
  if (points > 0) {
    emit('add-points', points)
    currentInput.value = ''
  }
}

function handleUndo() {
  currentInput.value = ''
  emit('undo')
}
</script>
