import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/mesa/revelacao.js',
                'resources/js/mesa/tempo-real.js',
                'resources/js/mesa/tela-do-jogo.js',
                'resources/js/jogos/labirinto/entrada.js',
                'resources/js/jogos/labirinto/entrada-partida.js',
                'resources/js/jogos/labirinto/entrada-assistir.js',
            ],
            refresh: true,
            fonts: [
                // Fonte provisória até a tipografia ser documentada (ver docs/identidade-visual.md).
                bunny('Inter', {
                    weights: [400, 500, 600, 700, 800, 900],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
