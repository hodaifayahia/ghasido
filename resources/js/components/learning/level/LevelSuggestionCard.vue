<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowUpCircle, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/*
 * After a strong Pre-test the next level is suggested; the learner moves up
 * or stays at their level to review (client decision 2026-09-30). Renders
 * nothing when there is no suggestion.
 */
type Props = { class?: HTMLAttributes['class'] };

const props = defineProps<Props>();

const page = usePage();
const level = computed(() => page.props.learnerLevel);
const sending = ref(false);

function answer(decision: 'move' | 'stay'): void {
    if (level.value === null || sending.value) {
        return;
    }

    router.post(
        level.value.answerUrl,
        { decision },
        {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
        },
    );
}
</script>

<template>
    <section
        v-if="level?.suggestion"
        :class="
            cn(
                'border-success/30 bg-success-tint flex min-w-0 flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:p-5',
                props.class,
            )
        "
        data-test="level-suggestion"
        aria-live="polite"
    >
        <ArrowUpCircle
            class="text-success size-9 shrink-0"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
            <h2 class="font-heading text-success-text text-base font-semibold">
                {{ $t('Great result! You are ready for the next level.') }}
            </h2>
            <p class="text-ink mt-1 text-sm leading-6">
                {{
                    $t(
                        'Move up to :next, or stay at :current to review first.',
                        {
                            next: level.suggestionLabel ?? '',
                            current: level.currentLabel ?? '',
                        },
                    )
                }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                :disabled="sending"
                class="bg-brand-600 font-heading shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/40 inline-flex h-11 items-center gap-2 rounded-md px-4 text-sm font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                data-test="level-move-up-button"
                @click="answer('move')"
            >
                <ArrowUpCircle class="size-4" aria-hidden="true" />
                {{
                    $t('Move up to :level', {
                        level: level.suggestionLabel ?? '',
                    })
                }}
            </button>
            <button
                type="button"
                :disabled="sending"
                class="border-line bg-surface text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/40 inline-flex h-11 items-center gap-2 rounded-md border px-4 text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-60"
                data-test="level-stay-button"
                @click="answer('stay')"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                {{ $t('Stay and review') }}
            </button>
        </div>
    </section>
</template>
