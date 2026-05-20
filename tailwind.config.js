/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './admin/*.php',
    './includes/*.php',
  ],
  theme: {
    extend: {
      colors: {
        epl: {
          blue:  '#1C2F48',
          gold:  '#C9A762',
          dark:  '#0A1421',
          light: '#F5F7F8',
        }
      },
      fontFamily: {
        primary:   ['Anton', 'sans-serif'],
        secondary: ['Montserrat', 'sans-serif'],
      }
    }
  },
  plugins: [],
}
