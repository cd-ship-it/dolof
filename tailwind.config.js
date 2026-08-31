/** @type {import('tailwindcss').Config} */
module.exports = {
  // Scan every PHP file — class names appear both in markup and in the inline
  // <script> blocks (campus/box highlight, dish-name spans, autocomplete list…).
  content: ['./*.php', './admin/*.php', './includes/*.php', './emails/*.html'],
  theme: { extend: {} },
  plugins: [],
};
