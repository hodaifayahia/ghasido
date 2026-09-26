<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Bot,
    CircleAlert,
    Hourglass,
    LoaderCircle,
    PenLine,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { save } from '@/routes/translations';
import type { TranslationRow, TranslationState } from '@/types';

/*
 * One English text and its Arabic, editable in place (user request
 * 2026-09-26). Saving marks it as written by hand: the AI never replaces
 * it. The state is said with an icon and words (ACC-02).
 */
type Props = { row: TranslationRow; canEdit: boolean };

const props = defineProps<Props>();

const arabic = ref(props.row.arabic ?? '');
const saving = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.row.arabic,
    (value) => {
        arabic.value = value ?? '';
    },
);

const dirty = computed(
    () => arabic.value.trim() !== (props.row.arabic ?? '').trim(),
);

const states: Record<
    TranslationState,
    { label: string; icon: Component; class: string }
> = {
    missing: {
        label: 'No meaning yet',
        icon: CircleAlert,
        class: 'bg-warning-tint text-warning-text',
    },
    drafting: {
        label: 'AI is drafting',
        icon: LoaderCircle,
        class: 'bg-brand-50 text-brand-700',
    },
    failed: {
        label: 'AI draft failed',
        icon: Hourglass,
        class: 'bg-danger-tint text-danger-text',
    },
    ai: { label: 'AI draft', icon: Bot, class: 'bg-ai-tint text-ai' },
    manual: {
        label: 'Written by hand',
        icon: PenLine,
        class: 'bg-success-tint text-success-text',
    },
};

function submit(): void {
    if (arabic.value.trim() === '') {
        error.value = 'Write the Arabic meaning first.';

        return;
    }

    error.value = null;
    router.put(
        save.url(),
        { text: props.row.text, arabic: arabic.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: (errors) => {
                error.value =
                    errors.arabic ?? errors.text ?? 'Could not save it.';
            },
        },
    );
}
</script>

<template>
    <li
        class="border-line grid min-w-0 gap-3 border-t px-4 py-3 first:border-t-0 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-start"
    >
        <div class="min-w-0">
            <p class="text-ink text-[14px] leading-6 break-words">
                {{ row.text }}
            </p>
            <p
                :class="
                    cn(
                        'rounded-pill text-pill mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1',
                        states[row.state].class,
                    )
                "
            >
                <component
                    :is="states[row.state].icon"
                    :class="
                        cn(
                            'size-3.5',
                            row.state === 'drafting' &&
                                'animate-spin motion-reduce:animate-none',
                        )
                    "
                    aria-hidden="true"
                />
                {{ states[row.state].label }}
                <span v-if="row.updatedAt" class="font-normal opacity-80"
                    >· {{ row.updatedAt }}</span
                >
            </p>
        </div>

        <label class="min-w-0">
            <span class="sr-only">Arabic meaning of: {{ row.text }}</span>
            <textarea
                v-model="arabic"
                dir="rtl"
                lang="ar"
                rows="2"
                :readonly="!canEdit"
                placeholder="المعنى بالعربية"
                class="border-line bg-surface font-arabic text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-11 w-full resize-y rounded-sm border px-3 py-2 text-[15px] leading-[1.9] focus-visible:ring-3 focus-visible:outline-none"
            />
            <span v-if="error" class="text-danger-text mt-1 block text-[12px]">
                {{ error }}
            </span>
        </label>

        <Button
            v-if="canEdit"
            type="button"
            :disabled="!dirty || saving"
            class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 rounded-md px-4 text-[13px] font-semibold text-white md:h-10"
            data-test="save-translation-button"
            @click="submit"
        >
            {{ saving ? 'Saving…' : 'Save' }}
        </Button>
    </li>
</template>
