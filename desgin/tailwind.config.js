/** Guesvia — tailwind.config.js
 *  Tailwind 3.x. Pair with resources/css/tokens.css
 */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#EFF5FF',
                    100: '#DBE8FF',
                    200: '#BDD5FF',
                    300: '#8FB7FF',
                    400: '#5A92FF',
                    500: '#2B6CFF',
                    600: '#0B5CFF', // primary
                    700: '#1249C9', // headings / hover
                    800: '#0F3A9E',
                    900: '#0C2E7A',
                },
                success: {
                    DEFAULT: '#2FBE7A',
                    tint: '#E7F8F0',
                    text: '#1B7F4F',
                },
                warning: {
                    DEFAULT: '#F5A623',
                    tint: '#FEF3E2',
                    text: '#8A5A06',
                },
                danger: {
                    DEFAULT: '#EF4444',
                    tint: '#FDECEC',
                    text: '#B42318',
                },
                ai: { DEFAULT: '#8B6BF7', tint: '#F1ECFE' },
                teal: { DEFAULT: '#17B8C6', tint: '#E4F7FA' },
                gold: { DEFAULT: '#F9C338', tint: '#FEF6DC' },
                excel: { DEFAULT: '#1E8E5A', tint: '#E6F5EE' },

                surface: '#FFFFFF',
                app: { DEFAULT: '#F4F9FE', alt: '#EFF7FD' },
                line: { DEFAULT: '#E4EDF8', strong: '#CBDCF0' },
                ink: { DEFAULT: '#12233D', muted: '#64748B', faint: '#94A3B8' },
            },

            fontFamily: {
                heading: [
                    'Poppins',
                    'ui-sans-serif',
                    'system-ui',
                    'sans-serif',
                ],
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                script: ['Caveat', 'cursive'],
                arabic: ['Cairo', 'Tajawal', 'sans-serif'],
            },

            fontSize: {
                display: ['2.125rem', { lineHeight: '1.2', fontWeight: '700' }],
                h1: ['1.75rem', { lineHeight: '1.25', fontWeight: '700' }],
                h2: ['1.25rem', { lineHeight: '1.3', fontWeight: '600' }],
                h3: ['1rem', { lineHeight: '1.4', fontWeight: '600' }],
                stat: ['1.875rem', { lineHeight: '1.1', fontWeight: '700' }],
            },

            borderRadius: {
                sm: '6px',
                md: '10px',
                lg: '14px',
                xl: '20px',
                pill: '999px',
            },

            boxShadow: {
                card: '0 1px 2px rgba(18,35,61,.04), 0 4px 16px rgba(11,92,255,.06)',
                hover: '0 4px 8px rgba(18,35,61,.06), 0 12px 28px rgba(11,92,255,.10)',
                pop: '0 12px 40px rgba(18,35,61,.16)',
                btn: '0 2px 8px rgba(11,92,255,.28)',
            },

            backgroundImage: {
                'grad-page':
                    'linear-gradient(180deg, #F7FBFF 0%, #EAF3FD 100%)',
                'grad-brand':
                    'linear-gradient(135deg, #0B5CFF 0%, #1249C9 100%)',
                'grad-header':
                    'linear-gradient(90deg, #FFFFFF 55%, rgba(255,255,255,0) 100%)',
            },

            spacing: {
                sidebar: '200px',
                topbar: '72px',
                bottomnav: '64px',
            },

            maxWidth: { content: '1400px' },

            transitionTimingFunction: { out: 'cubic-bezier(.22,.61,.36,1)' },

            keyframes: {
                fadeUp: {
                    '0%': { opacity: 0, transform: 'translateY(12px)' },
                    '100%': { opacity: 1, transform: 'none' },
                },
                shake: {
                    '0%,100%': { transform: 'translateX(0)' },
                    '25%': { transform: 'translateX(-6px)' },
                    '75%': { transform: 'translateX(6px)' },
                },
                pulseRing: {
                    '0%,100%': { boxShadow: '0 0 0 0 rgba(11,92,255,.35)' },
                    '50%': { boxShadow: '0 0 0 10px rgba(11,92,255,0)' },
                },
                countUp: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
            },
            animation: {
                'fade-up': 'fadeUp 220ms cubic-bezier(.22,.61,.36,1) both',
                shake: 'shake 260ms cubic-bezier(.22,.61,.36,1)',
                'pulse-ring': 'pulseRing 1.4s infinite',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
};
