<script setup lang="ts">
import { onMounted, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * One confetti burst for a finished lesson, test or review (AGENTS.md §3
 * Motion: "one confetti burst ≤1.2s, once"; spec 0005 §3.3). Adult and
 * brief: brand, success, gold, aqua and AI pieces fanning out from the
 * centre of the parent, then gone. Decorative only (pointer-events-none,
 * aria-hidden), skipped entirely with reduced motion and on the server.
 * The parent must be `relative`.
 */
type Props = {
    /** Fire once when true on mount (e.g. not a preview, not a revisit). */
    active?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { active: true });

const tones = [
    'bg-brand-600',
    'bg-success',
    'bg-gold',
    'bg-aqua',
    'bg-ai',
    'bg-brand-300',
];

type Piece = {
    id: number;
    tone: string;
    round: boolean;
    style: Record<string, string>;
};

const pieces = ref<Piece[]>([]);

onMounted(() => {
    if (
        !props.active ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    const count = 28;

    pieces.value = Array.from({ length: count }, (_, id) => {
        // Evenly spread angles with a little jitter; upward bias so the
        // burst reads as a celebration rather than a spill.
        const angle = (id / count) * Math.PI * 2 + (Math.random() - 0.5) * 0.4;
        const distance = 90 + Math.random() * 110;
        const x = Math.cos(angle) * distance;
        const y = Math.sin(angle) * distance - 40;

        return {
            id,
            tone: tones[id % tones.length],
            round: id % 3 === 0,
            style: {
                '--confetti-x': `${x.toFixed(1)}px`,
                '--confetti-y': `${y.toFixed(1)}px`,
                '--confetti-r': `${Math.round((Math.random() - 0.5) * 540)}deg`,
                animationDelay: `${Math.round(Math.random() * 120)}ms`,
            },
        };
    });

    // Remove the pieces once the burst is over, so nothing lingers in the DOM.
    window.setTimeout(() => {
        pieces.value = [];
    }, 1400);
});
</script>

<template>
    <div
        v-if="pieces.length > 0"
        :class="
            cn(
                'pointer-events-none absolute inset-0 z-10 grid place-items-center overflow-visible select-none motion-reduce:hidden',
                props.class,
            )
        "
        aria-hidden="true"
        data-test="celebration"
    >
        <span
            v-for="piece in pieces"
            :key="piece.id"
            :class="
                cn(
                    'animate-confetti col-start-1 row-start-1 block',
                    piece.round
                        ? 'size-2 rounded-full'
                        : 'h-3 w-1.5 rounded-sm',
                    piece.tone,
                )
            "
            :style="piece.style"
        />
    </div>
</template>
