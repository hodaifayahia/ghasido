<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import Celebration from '@/components/learning/Celebration.vue';
import { seen } from '@/routes/welcome';

/*
 * The one-time welcome after a user's very first sign-in (client request
 * 2026-09-26). The server decides it (`auth.user.show_welcome`, backed by
 * `users.welcomed_at`) and is told as soon as it starts, so it plays once
 * per account on any device, never again after a reload. Adult and
 * brand-only: the palm mark springs in inside a soft halo, the greeting
 * rises line by line, one confetti burst, then it fades away on its own
 * after five seconds or on "Let's begin" / Escape. Reduced motion gets a
 * plain fade (AGENTS.md §3 Motion).
 */
const DURATION_MS = 5000;

const page = usePage();
const open = ref(false);
const leaving = ref(false);

// Once per page load as well, so an Inertia visit carrying the old props
// before the server has answered can never replay it.
let played = false;
let timer: ReturnType<typeof setTimeout> | null = null;

const user = page.props.auth.user;
const firstName = (user?.name ?? '').trim().split(/\s+/u)[0] ?? '';
const isEmployee = user?.role === 'employee';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

function markSeen(): void {
    void fetch(seen.url(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    }).catch(() => {
        // Not recorded: it may play once more next time, nothing breaks.
    });
}

function close(): void {
    if (!open.value || leaving.value) {
        return;
    }

    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    leaving.value = true;
    setTimeout(() => {
        open.value = false;
        leaving.value = false;
    }, 420);
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    if (played || user?.show_welcome !== true) {
        return;
    }

    played = true;
    open.value = true;
    markSeen();
    timer = setTimeout(close, DURATION_MS);
    window.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    window.removeEventListener('keydown', onKey);
});

const orbs = [
    'bg-brand-200/60 -top-24 -start-20 size-80',
    'bg-aqua-tint -bottom-28 -end-16 size-96 [animation-delay:-2s]',
    'bg-ai-tint top-1/3 -end-24 size-64 [animation-delay:-4s]',
    'bg-gold-tint bottom-10 start-10 size-40 [animation-delay:-1s]',
];
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            role="dialog"
            aria-modal="true"
            aria-labelledby="welcome-splash-title"
            aria-describedby="welcome-splash-text"
            :class="[
                'bg-app fixed inset-0 z-[100] grid place-items-center overflow-hidden p-6',
                leaving
                    ? 'animate-welcome-exit motion-reduce:animate-none'
                    : 'animate-in fade-in duration-300',
            ]"
            data-test="welcome-splash"
            @click.self="close"
        >
            <!-- Drifting light orbs in the brand tints. -->
            <span
                v-for="orb in orbs"
                :key="orb"
                :class="[
                    'animate-welcome-float pointer-events-none absolute rounded-full blur-3xl motion-reduce:animate-none',
                    orb,
                ]"
                aria-hidden="true"
            />

            <div
                class="relative flex max-w-xl flex-col items-center text-center"
            >
                <div class="relative grid size-40 place-items-center">
                    <span
                        class="animate-welcome-halo bg-brand-100 absolute inset-0 rounded-full motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    <span
                        class="animate-welcome-halo bg-brand-50 absolute inset-0 rounded-full [animation-delay:1.2s] motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    <span
                        class="bg-surface shadow-pop animate-welcome-mark relative grid size-32 place-items-center rounded-full motion-reduce:animate-none"
                    >
                        <img
                            src="/brand/ghasido-mark.png"
                            alt=""
                            width="96"
                            height="96"
                            class="size-24 object-contain"
                            draggable="false"
                        />
                    </span>
                    <Celebration />
                </div>

                <img
                    src="/brand/ghasido-wordmark.png"
                    alt="GHASIDO"
                    class="animate-fade-up mt-7 h-9 w-auto [animation-delay:350ms] motion-reduce:animate-none"
                    draggable="false"
                />

                <p
                    class="animate-fade-up text-brand-600 mt-6 flex items-center gap-2 text-[13px] font-bold tracking-[0.16em] uppercase [animation-delay:550ms] motion-reduce:animate-none"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    Your first sign-in
                </p>
                <h2
                    id="welcome-splash-title"
                    class="animate-fade-up font-heading text-ink-royal mt-3 text-[clamp(2rem,6vw,3rem)] leading-[1.1] font-bold tracking-[-0.03em] [animation-delay:700ms] motion-reduce:animate-none"
                >
                    Welcome{{ firstName ? `, ${firstName}` : '' }}!
                </h2>
                <p
                    id="welcome-splash-text"
                    class="animate-fade-up text-ink-slate mt-4 max-w-md text-[17px] leading-7 [animation-delay:900ms] motion-reduce:animate-none"
                >
                    {{
                        isEmployee
                            ? 'Your hotel English journey starts today — one short step at a time.'
                            : 'Your GHASIDO workspace is ready. Let’s help your team speak with confidence.'
                    }}
                </p>

                <button
                    type="button"
                    class="animate-fade-up bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/30 font-heading mt-8 inline-flex h-12 items-center gap-2 rounded-md px-7 text-[15px] font-semibold text-white transition-colors [animation-delay:1100ms] focus-visible:ring-4 focus-visible:outline-none active:scale-[.97] motion-reduce:animate-none"
                    data-test="welcome-splash-start"
                    @click="close"
                >
                    Let’s begin
                    <ArrowRight class="size-5" aria-hidden="true" />
                </button>
            </div>

            <!-- Time left before it closes by itself. -->
            <span
                class="bg-tint-track absolute inset-x-0 bottom-0 h-1"
                aria-hidden="true"
            >
                <span
                    class="animate-welcome-bar bg-brand-600 block h-full origin-left motion-reduce:animate-none rtl:origin-right"
                />
            </span>
        </div>
    </Teleport>
</template>
