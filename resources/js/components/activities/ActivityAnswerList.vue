<script setup lang="ts">
import { CirclePlus, Trash2 } from '@lucide/vue';

/**
 * A short list of accepted answers (a Short Answer, one blank of a Fill in
 * the Blank; client report 2026-09-29). Any of them counts as right; case,
 * spaces and punctuation are ignored when the learner's answer is compared
 * (App\Services\Learning\ActivityScorer).
 */
type Props = {
    label: string;
    placeholder: string;
    readOnly: boolean;
    /** Kept at least this many rows. */
    min?: number;
    max?: number;
    testId: string;
};

const props = withDefaults(defineProps<Props>(), { min: 1, max: 8 });

const answers = defineModel<string[]>({ required: true });

function set(index: number, value: string): void {
    answers.value = answers.value.map((answer, position) =>
        position === index ? value : answer,
    );
}

function add(): void {
    answers.value = [...answers.value, ''];
}

function remove(index: number): void {
    answers.value = answers.value.filter((_, position) => position !== index);
}
</script>

<template>
    <div class="grid gap-1.5">
        <div
            v-for="(answer, index) in answers"
            :key="index"
            class="flex items-center gap-2"
        >
            <input
                :value="answer"
                type="text"
                :aria-label="`${label} ${index + 1}`"
                :placeholder="placeholder"
                :readonly="readOnly"
                :data-test="`${testId}-${index + 1}`"
                class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full min-w-0 rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                @input="set(index, ($event.target as HTMLInputElement).value)"
            />
            <button
                v-if="!readOnly"
                type="button"
                class="text-danger-text hover:bg-danger-tint focus-visible:ring-danger/15 inline-flex size-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
                :disabled="answers.length <= props.min"
                :aria-label="$t('Remove answer')"
                @click="remove(index)"
            >
                <Trash2 class="size-4" aria-hidden="true" />
            </button>
        </div>
        <button
            v-if="!readOnly"
            type="button"
            class="border-line text-brand-700 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-9 items-center gap-1.5 self-start rounded-md border border-dashed px-3 text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40"
            :disabled="answers.length >= props.max"
            :data-test="`${testId}-add`"
            @click="add"
        >
            <CirclePlus class="size-4" aria-hidden="true" />
            {{ $t('Add accepted answer') }}
        </button>
    </div>
</template>
