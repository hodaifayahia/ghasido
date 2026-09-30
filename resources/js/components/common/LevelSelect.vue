<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/*
 * Which learner level a course, lesson or test is for (client decision
 * 2026-09-30): every level, or Beginner, Intermediate or Advanced. With a
 * `name`, it also posts the value in a <Form> ('' = every level).
 */
type Props = {
    name?: string;
    id?: string;
    class?: HTMLAttributes['class'];
    triggerClass?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

/** '' = every level. */
const level = defineModel<string>({ default: '' });

const ALL = 'all';

const options = [
    { value: ALL, label: tk('All levels') },
    { value: 'beginner', label: tk('Beginner') },
    { value: 'intermediate', label: tk('Intermediate') },
    { value: 'advanced', label: tk('Advanced') },
];

function onChange(value: unknown): void {
    if (typeof value === 'string') {
        level.value = value === ALL ? '' : value;
    }
}
</script>

<template>
    <div :class="cn('min-w-0', props.class)">
        <input v-if="name" type="hidden" :name="name" :value="level" />
        <Select
            :model-value="level === '' ? ALL : level"
            @update:model-value="onChange"
        >
            <SelectTrigger
                :id="id"
                :class="
                    cn(
                        'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm text-[13px] shadow-none focus-visible:ring-3',
                        triggerClass,
                    )
                "
                data-test="level-select"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent class="border-line shadow-pop">
                <SelectItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                    class="text-[13px]"
                >
                    {{ $t(option.label) }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
