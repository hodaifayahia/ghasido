<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Clock,
    Eye,
    EyeOff,
    Link2,
    PencilLine,
    Trash2,
    Users,
} from '@lucide/vue';
import { stepTone } from '@/components/essentials-studio/essentialsStudioTones';
import { cn } from '@/lib/utils';
import type { EssentialsStudioLesson } from '@/types';

/*
 * One lesson of an English Essentials topic in the studio (client request
 * 2026-11-05): its number, title, visibility, the steps it holds as
 * coloured chips, how many employees used it and how many department
 * steps link to it, and every action on the row itself, nothing behind a
 * hover (AGENTS.md §7). Moving uses buttons, the keyboard-safe way.
 */
type Props = {
    lesson: EssentialsStudioLesson;
    number: number;
    first: boolean;
    last: boolean;
    manage: boolean;
    busy?: boolean;
};

withDefaults(defineProps<Props>(), { busy: false });

const emit = defineEmits<{
    move: [direction: -1 | 1];
    toggle: [];
    remove: [];
}>();

const iconButton =
    'text-ink-slate hover:bg-brand-50 hover:text-brand-800 focus-visible:ring-brand-600/20 grid size-11 shrink-0 place-items-center rounded-md transition-colors focus-visible:ring-3 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-35 md:size-9';
</script>

<template>
    <li
        class="border-line bg-surface hover:shadow-card grid grid-cols-[minmax(0,1fr)] gap-3 rounded-lg border p-3.5 transition-shadow duration-150 motion-reduce:transition-none md:grid-cols-[auto_minmax(0,1fr)] md:gap-x-4"
        :data-test="`essentials-lesson-${lesson.id}`"
    >
        <span
            class="bg-brand-50 text-brand-700 font-heading hidden size-10 place-items-center rounded-full text-[14px] font-bold md:grid"
            aria-hidden="true"
        >
            {{ number }}
        </span>

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-ink-night min-w-0 text-[14.5px] font-semibold">
                    <span class="text-ink-faint md:hidden">{{ number }}. </span>
                    {{ lesson.title }}
                </p>
                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold',
                            lesson.published
                                ? 'bg-success-tint text-success-text'
                                : 'bg-warning-tint text-warning-text',
                        )
                    "
                >
                    <component
                        :is="lesson.published ? Eye : EyeOff"
                        class="size-3"
                        aria-hidden="true"
                    />
                    {{ lesson.published ? $t('Published') : $t('Draft') }}
                </span>
            </div>

            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                <span
                    v-for="(step, index) in lesson.steps"
                    :key="index"
                    :class="
                        cn(
                            'rounded-pill px-2 py-0.5 text-[10.5px] font-semibold',
                            stepTone(step.type),
                        )
                    "
                >
                    {{ step.label }}
                </span>
                <span
                    v-if="lesson.steps.length === 0"
                    class="text-danger-text text-[11.5px]"
                >
                    {{ $t('No steps yet') }}
                </span>
            </div>

            <p
                class="text-ink-slate mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px]"
            >
                <span
                    v-if="lesson.estimatedMinutes"
                    class="inline-flex items-center gap-1"
                >
                    <Clock class="size-3" aria-hidden="true" />
                    {{
                        $t(':minutes min', { minutes: lesson.estimatedMinutes })
                    }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <Users class="size-3" aria-hidden="true" />
                    {{
                        $tc(':count employee|:count employees', lesson.learners)
                    }}
                </span>
                <span
                    class="inline-flex items-center gap-1"
                    :title="
                        $t(
                            'Department lesson steps whose “Learn it now” link opens this lesson',
                        )
                    "
                >
                    <Link2 class="size-3" aria-hidden="true" />
                    {{
                        $tc(
                            'Linked from :count step|Linked from :count steps',
                            lesson.helpLinks,
                        )
                    }}
                </span>
            </p>
        </div>

        <div
            class="border-line flex flex-wrap items-center gap-1 border-t pt-2.5 md:col-start-2"
        >
            <Link
                v-if="manage"
                :href="lesson.editUrl"
                class="border-brand-600 text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/20 inline-flex h-11 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:h-9"
                :data-test="`essentials-lesson-${lesson.id}-edit`"
            >
                <PencilLine class="size-4" aria-hidden="true" />
                {{ $t('Fill in steps') }}
            </Link>
            <a
                :href="lesson.previewUrl"
                target="_blank"
                rel="noopener"
                :class="iconButton"
                :aria-label="$t('Preview as an employee')"
                :title="$t('Preview as an employee')"
            >
                <Eye class="size-4" aria-hidden="true" />
            </a>
            <template v-if="manage">
                <button
                    type="button"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600/20 inline-flex h-11 items-center rounded-md px-3 text-[12.5px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none disabled:opacity-50 md:h-9',
                            lesson.published
                                ? 'text-ink-slate hover:bg-tint-header'
                                : 'bg-brand-600 hover:bg-brand-700 text-white',
                        )
                    "
                    :disabled="busy"
                    :data-test="`essentials-lesson-${lesson.id}-toggle`"
                    @click="emit('toggle')"
                >
                    {{ lesson.published ? $t('Unpublish') : $t('Publish') }}
                </button>
                <button
                    type="button"
                    :class="iconButton"
                    :disabled="first || busy"
                    :aria-label="$t('Move up')"
                    :title="$t('Move up')"
                    @click="emit('move', -1)"
                >
                    <ArrowUp class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="iconButton"
                    :disabled="last || busy"
                    :aria-label="$t('Move down')"
                    :title="$t('Move down')"
                    @click="emit('move', 1)"
                >
                    <ArrowDown class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="
                        cn(
                            iconButton,
                            'hover:bg-danger-tint hover:text-danger-text',
                        )
                    "
                    :disabled="busy"
                    :aria-label="$t('Delete lesson')"
                    :title="$t('Delete lesson')"
                    :data-test="`essentials-lesson-${lesson.id}-delete`"
                    @click="emit('remove')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </template>
        </div>
    </li>
</template>
