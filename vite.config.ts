import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defaultAllowedOrigins } from 'vite';
import { defineConfig, lazyPlugins } from 'vite-plus';

const vitePort = Number(process.env.VITE_PORT ?? 5173);
const vitePublicHost = '127.0.0.1';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                // Guesvia type system — desgin/10-design-system.md §10.2.
                // Inter = body/tables/forms, Poppins = headings/buttons/stats,
                // Caveat = handwritten accents only, Cairo = Arabic in the
                // Show Meaning panels. Loaded here, never via a CSS @import.
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Poppins', { weights: [500, 600, 700] }),
                bunny('Caveat', { weights: [500, 700] }),
                // `subsets` defaults to ['latin']; without 'arabic' Cairo ships
                // no U+0600-06FF glyphs and Show Meaning falls back to a system
                // font (CTRL-02, I18N-03).
                bunny('Cairo', {
                    weights: [400, 600],
                    subsets: ['latin', 'arabic'],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        // The server binds inside Sail, while the browser reaches it through
        // the published Windows loopback port. `localhost` resolves to `::1`
        // on some Windows setups, and WSL can accept then drop those IPv6
        // requests. Advertise an IPv4 loopback origin instead.
        host: '0.0.0.0',
        port: vitePort,
        strictPort: true,
        origin: `http://${vitePublicHost}:${vitePort}`,
        // laravel-vite-plugin ≥3 reuses `server.origin` as the *only* CORS
        // origin, so the page at http://localhost (APP_URL) is refused with
        // "Access-Control-Allow-Origin ... not equal to the supplied origin".
        // Allow every loopback origin, as Vite does when `origin` is unset.
        cors: { origin: defaultAllowedOrigins },
        hmr: {
            host: vitePublicHost,
        },
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
