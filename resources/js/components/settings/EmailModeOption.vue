<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

/*
 * One choice of "How emails leave" on Settings → Email: a radio drawn as
 * a tile, selected by border + tint + a filled dot, never colour alone.
 */
type Props = {
    value: string;
    title: string;
    description: string;
    icon: Component;
    name: string;
};

const props = defineProps<Props>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <label
        :class="
            cn(
                'flex min-h-11 min-w-0 cursor-pointer items-start gap-3 rounded-md border p-4 transition-colors',
                'has-[:focus-visible]:ring-brand-600/15 has-[:focus-visible]:ring-3',
                model === props.value
                    ? 'border-brand-600 bg-brand-50'
                    : 'border-line bg-surface hover:border-brand-300',
            )
        "
    >
        <input
            v-model="model"
            type="radio"
            :name="name"
            :value="value"
            class="sr-only"
        />
        <span
            :class="
                cn(
                    'mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2',
                    model === props.value
                        ? 'border-brand-600'
                        : 'border-line-strong',
                )
            "
            aria-hidden="true"
        >
            <span
                v-if="model === props.value"
                class="bg-brand-600 size-2.5 rounded-full"
            />
        </span>
        <span class="grid min-w-0 gap-0.5">
            <span class="text-ink flex items-start gap-2 text-sm font-semibold">
                <component
                    :is="icon"
                    class="text-brand-600 mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ title }}
            </span>
            <span class="text-ink-muted text-xs">{{ description }}</span>
        </span>
    </label>
</template>
