<script setup lang="ts">
import { ClipboardCheck } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { TestResultSummary } from '@/types';

/*
 * A Pre-test / Post-test result as its visibility setting allows (TEST-04):
 * a score when the admin shows one, otherwise only that it was submitted.
 * Never assumes results are shown.
 */
type Props = {
    title: string;
    result: TestResultSummary | null;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const submitted = computed(() =>
    props.result?.submittedAt
        ? new Date(props.result.submittedAt).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : null,
);
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card flex min-w-0 items-center gap-4 rounded-lg border p-5',
                props.class,
            )
        "
        :aria-label="title"
    >
        <span
            class="bg-ai-tint text-ai grid size-11 shrink-0 place-items-center rounded-xl"
        >
            <ClipboardCheck class="size-[22px]" aria-hidden="true" />
        </span>
        <div class="min-w-0 flex-1">
            <h2
                class="font-heading text-ink-night text-lg leading-6 font-semibold"
            >
                {{ title }}
            </h2>
            <p v-if="!result" class="text-ink-slate text-sm">Not taken yet</p>
            <p
                v-else-if="result.percent !== null"
                class="text-ink-slate text-sm"
            >
                Submitted {{ submitted }}
            </p>
            <p v-else class="text-ink-slate text-sm">
                Submitted {{ submitted }} · your trainer keeps the score
            </p>
        </div>
        <p
            v-if="result?.percent !== null && result?.percent !== undefined"
            class="font-heading text-brand-700 text-[26px] leading-7 font-bold"
        >
            {{ result.percent }}%
        </p>
    </section>
</template>
