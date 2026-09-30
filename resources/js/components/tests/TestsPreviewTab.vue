<script setup lang="ts">
import { ArrowLeft, ArrowRight, Eye, Languages, Shuffle } from '@lucide/vue';
import { computed, provide, ref, watch } from 'vue';
import GenericActivity from '@/components/learning/activities/GenericActivity.vue';
import { Button } from '@/components/ui/button';
import { MEANING_ENABLED } from '@/composables/useMeaning';
import type { ActivityView } from '@/types';

/*
 * The builder's Preview tab: the employee experience, one question at a
 * time, drawn by the learner's own runner component in test mode — no
 * correct answers in the data, no correctness colours (TEST-03). Show
 * Meaning follows the Settings tab as it stands, saved or not, so the admin
 * sees what switching it off does (client decision 2026-09-26). Nothing
 * typed here is submitted.
 */
type Props = {
    activities: ActivityView[];
    /** 'Pre-test' or 'Post-test', already translated. */
    label: string;
    title: string;
    showMeaning: boolean;
    shuffleQuestions: boolean;
};

const props = defineProps<Props>();

provide(
    MEANING_ENABLED,
    computed(() => props.showMeaning),
);

const index = ref(0);
const total = computed(() => props.activities.length);
const activity = computed(() => props.activities[index.value] ?? null);
const percent = computed(() =>
    total.value === 0 ? 0 : Math.round(((index.value + 1) / total.value) * 100),
);

watch(total, (count) => {
    index.value = Math.min(index.value, Math.max(count - 1, 0));
});

function move(delta: number): void {
    index.value = Math.min(Math.max(index.value + delta, 0), total.value - 1);
}
</script>

<template>
    <div class="grid min-w-0 gap-4">
        <p
            class="border-line bg-brand-50/40 text-ink-slate flex items-start gap-2 rounded-md border px-3 py-2 text-[12px] leading-5"
        >
            <Eye
                class="text-brand-600 mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            <span class="min-w-0">
                {{
                    $t(
                        'This is what the employee sees. Nothing you answer here is saved.',
                    )
                }}
            </span>
        </p>

        <div class="text-ink-slate flex flex-wrap gap-x-4 gap-y-1 text-[12px]">
            <span class="inline-flex items-center gap-1.5">
                <Languages class="size-3.5" aria-hidden="true" />
                {{
                    showMeaning
                        ? $t('Show Meaning (Arabic): on')
                        : $t('Show Meaning (Arabic): off')
                }}
            </span>
            <span
                v-if="shuffleQuestions"
                class="inline-flex items-center gap-1.5"
            >
                <Shuffle class="size-3.5" aria-hidden="true" />
                {{ $t('Employees see the questions in a random order.') }}
            </span>
        </div>

        <template v-if="activity">
            <header class="grid gap-2">
                <p
                    class="font-heading text-ink-royal text-[22px] leading-8 font-bold tracking-[-0.02em]"
                >
                    {{ label }}
                    <span class="text-ink-slate text-[14px] font-medium">
                        · {{ title }}
                    </span>
                </p>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-ink-slate text-[14px]">
                        {{
                            $t('Question :current of :total', {
                                current: index + 1,
                                total,
                            })
                        }}
                    </span>
                    <span class="text-brand-700 text-[13px] font-semibold">
                        {{ percent }}%
                    </span>
                </div>
                <div class="bg-tint-track rounded-pill h-2 overflow-hidden">
                    <div
                        class="bg-brand-600 rounded-pill h-full transition-[width] duration-500 ease-out motion-reduce:transition-none"
                        :style="{ width: `${percent}%` }"
                    />
                </div>
            </header>

            <div class="min-w-0" data-test="test-preview-question">
                <GenericActivity
                    :key="activity.id"
                    :activity="activity"
                    :result="null"
                    answer-url=""
                    :block-id="0"
                />
            </div>

            <div
                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-4 text-[13px] font-semibold shadow-none"
                    :disabled="index === 0"
                    data-test="test-preview-previous"
                    @click="move(-1)"
                >
                    <ArrowLeft
                        class="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                    {{ $t('Previous question') }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-1.5 rounded-md px-4 text-[13px] font-semibold text-white"
                    :disabled="index >= total - 1"
                    data-test="test-preview-next"
                    @click="move(1)"
                >
                    {{ $t('Next question') }}
                    <ArrowRight
                        class="size-4 rtl:rotate-180"
                        aria-hidden="true"
                    />
                </Button>
            </div>
        </template>

        <p
            v-else
            class="border-line text-ink-slate rounded-md border border-dashed px-4 py-10 text-center text-[12.5px]"
        >
            {{ $t('Add a question before opening the preview.') }}
        </p>
    </div>
</template>
