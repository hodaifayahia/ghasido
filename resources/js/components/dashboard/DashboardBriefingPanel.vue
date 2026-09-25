<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Sparkles,
    SquareCheckBig,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { DashboardBriefing } from '@/types';

/*
 * AI Briefing (spec 0005 §4.1): a short reading of the figures above, what
 * is going well, what needs attention and what to do this week. Written by
 * a queued job from aggregate figures only (no learner is named), marked
 * as AI-written, and polled while it is being written (PERF-04). Each list
 * item carries an icon and a word as well as colour (ACC-02).
 */
type Props = {
    briefing: DashboardBriefing;
};

const props = defineProps<Props>();

const writing = computed(
    () =>
        props.briefing.status === 'pending' ||
        props.briefing.status === 'refreshing',
);

const { start, stop } = usePoll(
    5000,
    { only: ['briefing'] },
    { autoStart: false },
);

watch(
    writing,
    (busy) => {
        if (busy) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

const updated = computed((): string | null =>
    props.briefing.generatedAt
        ? new Date(props.briefing.generatedAt).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : null,
);
</script>

<template>
    <PanelCard
        title="AI Briefing"
        title-id="ai-briefing"
        class="px-3 pt-1 pb-5"
        title-class="text-[17px]"
        body-class="mt-[5px]"
        aria-live="polite"
        data-test="briefing-panel"
    >
        <template #icon>
            <Sparkles
                class="text-ai -my-1 size-6 shrink-0"
                :stroke-width="1.75"
                aria-hidden="true"
            />
        </template>

        <template #actions>
            <span
                class="bg-ai-tint text-ai rounded-pill px-2 py-0.5 text-[11px] font-semibold"
                title="Written by AI from the figures on this page"
            >
                AI
            </span>
        </template>

        <div v-if="briefing.status === 'pending'" class="grid gap-2">
            <p class="text-ink/75 text-[13px]">Reading this week's figures…</p>
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-5/6" />
            <Skeleton class="h-3 w-2/3" />
        </div>

        <p
            v-else-if="briefing.status === 'empty'"
            class="bg-app-alt text-ink/80 rounded-[8px] px-3 py-4 text-[13px]"
        >
            The briefing appears once learners are enrolled.
        </p>

        <p
            v-else-if="briefing.status === 'failed'"
            class="bg-app-alt text-ink/80 rounded-[8px] px-3 py-4 text-[13px]"
        >
            The briefing could not be written just now. It will try again within
            the hour; every figure above is live.
        </p>

        <div v-else class="grid gap-3">
            <p
                class="font-heading text-ink text-[15px] leading-6 font-semibold"
            >
                {{ briefing.headline }}
            </p>

            <section v-if="briefing.highlights.length > 0" class="grid gap-1.5">
                <h3 class="text-ink/75 text-[12px] font-semibold">
                    Going well
                </h3>
                <p
                    v-for="item in briefing.highlights"
                    :key="item"
                    class="flex items-start gap-2 text-[13px] leading-5"
                >
                    <CircleCheck
                        class="text-success-text mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-ink/85">{{ item }}</span>
                </p>
            </section>

            <section v-if="briefing.concerns.length > 0" class="grid gap-1.5">
                <h3 class="text-ink/75 text-[12px] font-semibold">
                    Needs attention
                </h3>
                <p
                    v-for="item in briefing.concerns"
                    :key="item"
                    class="flex items-start gap-2 text-[13px] leading-5"
                >
                    <CircleAlert
                        class="text-warning mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-ink/85">{{ item }}</span>
                </p>
            </section>

            <section
                v-if="briefing.actions.length > 0"
                class="bg-brand-50 grid gap-1.5 rounded-[8px] p-3"
            >
                <h3 class="text-brand-800 text-[12px] font-semibold">
                    Suggested this week
                </h3>
                <p
                    v-for="item in briefing.actions"
                    :key="item"
                    class="flex items-start gap-2 text-[13px] leading-5"
                >
                    <SquareCheckBig
                        class="text-brand-600 mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-ink/85">{{ item }}</span>
                </p>
            </section>

            <p class="text-ink-faint text-[11.5px]">
                <template v-if="briefing.status === 'refreshing'"
                    >Updating with the latest figures…</template
                >
                <template v-else-if="updated">Updated {{ updated }}</template>
            </p>
        </div>
    </PanelCard>
</template>
