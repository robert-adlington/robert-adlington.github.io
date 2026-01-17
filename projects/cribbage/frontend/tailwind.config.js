/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        wood: {
          light: '#DEB887',
          DEFAULT: '#8B4513',
          dark: '#654321',
        },
        peg: {
          player1: '#DC2626',
          player2: '#2563EB',
        }
      }
    },
  },
  plugins: [],
}
