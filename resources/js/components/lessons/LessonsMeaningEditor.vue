<script setup lang="ts">
import { ChevronDown, Languages } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { cn } from '@/lib/utils';
import type { LessonMeaning } from '@/types';

/**
 * Show Meaning for one piece of lesson text (CTRL-01..03; user request
 * 2026-09-25): the Arabic meaning and an optional simple explanation the
 * learner sees only after tapping Show Meaning. Collapsed to one line until
 * opened, so every field of the builder can carry it. Clearing the Arabic
 * removes the meaning (the model becomes null).
 */
type Props = {
    modelValue: LessonMeaning | null;
    /** What the meaning is for, for screen readers ("Subtitle"). */
    label: string;
    readOnly?: boolean;
};

const props = withDefaults(defineProps<Props>(), { readOnly: false });

const emit = defineEmits<{
    'update:modelValue': [value: LessonMeaning | null];
    blur: [];
}>();

const id = useId();
const open = ref(false);

const hasMeaning = computed(
    () => (props.modelValue?.arabic ?? '').trim() !== '',
);

function update(patch: Partial<LessonMeaning>): void {
    const next: LessonMeaning = {
        arabic: props.modelValue?.arabic ?? '',
        explanation: props.modelValue?.explanation ?? null,
        ...patch,
    };

    emit(
        'update:modelValue',
        next.arabic.trim() === '' && (next.explanation ?? '').trim() === ''
            ? null
            : next,
    );
}

const controlClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 text-[13px] shadow-none focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <div class="grid gap-1.5">
        <button
            type="button"
            :aria-expanded="open"
            :aria-controls="`${id}-meaning`"
            :aria-label="`Show Meaning for ${label}`"
            :class="
                cn(
                    'focus-visible:ring-brand-600 hover:bg-brand-50 inline-flex min-h-11 w-fit items-center gap-1.5 rounded-md px-2 text-[11.5px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none md:min-h-8',
                    hasMeaning ? 'text-success-text' : 'text-brand-700',
                )
            "
            :data-test="`meaning-toggle-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`"
            @click="open = !open"
        >
            <Languages class="size-3.5" aria-hidden="true" />
            <span>
                {{
                    hasMeaning
                        ? 'Show Meaning: Arabic added'
                        : 'Add Show Meaning'
                }}
            </span>
            <ChevronDown
                :class="
                    cn('size-3.5 transition-transform', open && 'rotate-180')
                "
                aria-hidden="true"
            />
        </button>

        <div
            v-if="open"
            :id="`${id}-meaning`"
            class="border-line bg-brand-50/50 grid gap-2 rounded-md border p-2.5"
        >
            <label class="grid gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">
                    Arabic meaning
                </span>
                <textarea
                    :value="modelValue?.arabic ?? ''"
                    rows="2"
                    dir="rtl"
                    lang="ar"
                    maxlength="600"
                    :readonly="readOnly"
                    placeholder="المعنى بالعربية"
                    :class="
                        cn(
                            controlClass,
                            'font-arabic py-2 text-end leading-[1.8]',
                        )
                    "
                    @input="
                        update({
                            arabic: ($event.target as HTMLTextAreaElement)
                                .value,
                        })
                    "
                    @blur="emit('blur')"
                />
            </label>
            <label class="grid gap-1">
                <span class="text-brand-900 text-[11.5px] font-semibold">
                    Simple explanation
                    <span class="text-ink-slate font-normal">(optional)</span>
                </span>
                <input
                    :value="modelValue?.explanation ?? ''"
                    maxlength="300"
                    :readonly="readOnly"
                    placeholder="A short, simple English explanation"
                    :class="cn(controlClass, 'h-10')"
                    @input="
                        update({
                            explanation: ($event.target as HTMLInputElement)
                                .value,
                        })
                    "
                    @blur="emit('blur')"
                />
            </label>
            <p class="text-ink-slate text-[11px]">
                Learners see this only after they tap Show Meaning. Never in a
                test.
            </p>
        </div>
    </div>
</template>
