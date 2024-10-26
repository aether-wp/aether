// webpack.mix.js

const mix = require('laravel-mix');

mix.disableSuccessNotifications();

mix.sass('assets/src/scss/index.scss', 'assets/dist/css/style.css')
    .options({
        processCssUrls: false
    });

mix.js('/assets/src/js/main.js', '/assets/dist/js/main.js');
