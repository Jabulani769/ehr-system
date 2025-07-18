// tailwind.config.js
module.exports = {
  content: [
    "./pages/**/*.php",
    "./includes/**/*.php",
    "./index.php"
  ],
  theme: {
    extend: {
      screens: {
        'sm': '640px',
        'md': '768px',
        'lg': '1024px',
        'xl': '1280px',
      },
    },
  },
  plugins: [],
}
