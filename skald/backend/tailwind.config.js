/** @type {import('tailwindcss').Config} */
module.exports = {
    // Purge : ne garde que les classes réellement utilisées dans les vues.
    content: [
        './templates/**/*.html.twig',
        './assets/**/*.js',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
