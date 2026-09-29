<script setup lang="ts">
import { useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import InputError from '@/components/InputError.vue';
import FieldMeaningButton from '@/components/translations/FieldMeaningButton.vue';
import { cn } from '@/lib/utils';

/**
 * One labelled form control in the CMS style: 12px semibold brand label,
 * 6px-radius line border, brand focus ring (AGENTS.md §3, §7).
 *
 * `translatable` puts the Translation button beside the label, for text the
 * learner can open with Show Meaning (user request 2026-09-26).
 */
type Props = {
    label: string;
    modelValue: string | number | null;
    type?: 'text' | 'textarea' | 'number';
    rows?: number;
    placeholder?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    dir?: 'ltr' | 'rtl';
    name?: string;
    min?: number;
    max?: number;
    translatable?: boolean;
    readOnly?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    rows: 3,
    placeholder: '',
    required: false,
    dir: 'ltr',
    translatable: false,
    readOnly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
    blur: [];
}>();

const id = useId();

const controlClass =
    'border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 text-[13px] shadow-none focus-visible:ring-3 focus-visible:outline-none';

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement | HTMLTextAreaElement;

    if (props.type === 'number') {
        emit(
            'update:modelValue',
            target.value === '' ? null : Number(target.value),
        );

        return;
    }

    emit('update:modelValue', target.value);
}
</script>

<template>
    <div :class="cn('grid gap-1.5', props.class)">
        <div class="flex flex-wrap items-center gap-2">
            <label
                :for="id"
                class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
            >
                {{ label }}
                <span v-if="required" class="text-danger" aria-hidden="true">
                    *
                </span>
            </label>
            <FieldMeaningButton
                v-if="translatable && type !== 'number'"
                :text="modelValue === null ? '' : String(modelValue)"
                :read-only="readOnly"
            />
        </div>
        <textarea
            v-if="type === 'textarea'"
            :id="id"
            :name="name"
            :rows="rows"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :dir="dir"
            :class="
                cn(
                    controlClass,
                    'py-2 leading-[1.6]',
                    dir === 'rtl' && 'font-arabic text-end',
                )
            "
            @input="onInput"
            @blur="emit('blur')"
        />
        <input
            v-else
            :id="id"
            :name="name"
            :type="type"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :dir="dir"
            :min="min"
            :max="max"
            :class="
                cn(
                    controlClass,
                    'h-10',
                    dir === 'rtl' && 'font-arabic text-end',
                )
            "
            @input="onInput"
            @blur="emit('blur')"
        />
        <p v-if="hint" class="text-ink-faint text-[11.5px]">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
