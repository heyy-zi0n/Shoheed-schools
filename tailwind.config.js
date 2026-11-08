/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './admin/**/*.php',
    './bursar/**/*.php',
    './principal/**/*.php',
    './student/**/*.php',
    './teacher/**/*.php',
    './profile/**/*.php',
    './templates/**/*.php',
    './core/**/*.php',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'school-navy': '#111B3F',
        'school-blue': '#263B8F',
        'school-red': '#C62828',
        'school-gold': '#E5B926',
        ivory: '#F5F1E8',
        paper: '#FFFDFC',
        charcoal: '#20242C',
        'slate-gray': '#697386',
        sage: '#5D7A68',
        amber: '#B7813B',
        'dusty-red': '#B95656',
      },
      fontFamily: {
        sans: ['Manrope', 'sans-serif'],
        serif: ['"DM Serif Display"', 'serif'],
      },
    },
  },
  plugins: [],
};
