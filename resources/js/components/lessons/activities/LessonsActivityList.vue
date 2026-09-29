<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Check,
    CirclePlus,
    SquarePen,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { BlockActivityMode } from '@/components/lessons/activities/activityCatalog';
import {
    activitySummary,
    activityTypeSpec,
    correctAnswer,
} from '@/components/lessons/activities/activityCatalog';
import LessonsActivityDeleteDialog from '@/components/lessons/activities/LessonsActivityDeleteDialog.vue';
import LessonsActivityDialog from '@/components/lessons/activities/LessonsActivityDialog.vue';
import { toneClass } from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { destroy, reorder } from '@/routes/blocks/activities';
import type {
    LessonActivityRow,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * The activities of a Practice block, or the questions of a Quiz block
 * (PRAC-01..07, BLD-03; client report 2026-09-29): each with its type, its
 * first question and correct answer, move up / down, Edit and Delete, and
 * the Add button that opens the exercise-type chooser. Every change is
 * saved on the server straight away (PROG-01 for content: nothing is held
 * only in the browser).
 */
type Props = {
    block: LessonBlockRow;
    mode: BlockActivityMode;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

const props = defineProps<Props>();

const editorOpen = ref(false);
const editing = ref<LessonActivityRow | null>(null);
const deleting = ref<LessonActivityRow | null>(null);
const deleteOpen = ref(false);
const busy = ref(false);

const activities = computed(() => props.block.activities);
const isQuiz = computed(() => props.mode === 'quiz');

function label(activity: LessonActivityRow): string {
    return activity.title ?? t(activityTypeSpec(activity.type).label);
}

function add(): void {
    editing.value = null;
    editorOpen.value = true;
}

// The Quiz / Practice tab opens the chooser of a block from outside.
defineExpose({ add });

function edit(activity: LessonActivityRow): void {
    editing.value = activity;
    editorOpen.value = true;
}

const visitOptions = {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
        busy.value = false;
    },
};

function move(index: number, delta: number): void {
    const ids = activities.value
        .map((activity) => activity.placementId)
        .filter((id): id is number => id !== null);
    const target = index + delta;

    if (target < 0 || target >= ids.length || busy.value) {
        return;
    }

    const [id] = ids.splice(index, 1);

    if (id === undefined) {
        return;
    }

    ids.splice(target, 0, id);
    busy.value = true;
    router.put(reorder.url(props.block.id), { order: ids }, visitOptions);
}

function askDelete(activity: LessonActivityRow): void {
    deleting.value = activity;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    const activity = deleting.value;

    if (activity === null || activity.placementId === null) {
        return;
    }

    busy.value = true;
    router.delete(
        destroy.url({ block: props.block.id, placement: activity.placementId }),
        {
            ...visitOptions,
            onSuccess: () => {
                deleteOpen.value = false;
                deleting.value = null;
            },
        },
    );
}

const iconButton =
    'text-brand-800 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none disabled:opacity-40';
</script>

<template>
    <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span
                class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
            >
                {{
                    isQuiz
                        ? $tc(
                              '{0} Questions|{1} :count question|[2,*] :count questions',
                              activities.length,
                          )
                        : $tc(
                              '{0} Activities|{1} :count activity|[2,*] :count activities',
                              activities.length,
                          )
                }}
            </span>
            <Button
                v-if="!readOnly"
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12.5px] font-semibold text-white active:scale-[.97]"
                :data-test="
                    isQuiz ? 'add-question-button' : 'add-activity-button'
                "
                @click="add"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ isQuiz ? $t('Add Question') : $t('Add Activity') }}
            </Button>
        </div>

        <p
            v-if="activities.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-6 text-center text-[12.5px]"
        >
            {{
                isQuiz
                    ? $t(
                          'No question yet. Add one, choose its type and mark the correct answer.',
                      )
                    : $t(
                          'No activity yet. Add one and choose the type of exercise.',
                      )
            }}
        </p>

        <ol v-else class="grid gap-1.5">
            <li
                v-for="(activity, index) in activities"
                :key="activity.placementId ?? activity.id"
                class="border-line bg-surface flex items-start gap-3 rounded-md border px-3 py-2.5"
                :data-test="`block-activity-${index + 1}`"
            >
                <span
                    :class="
                        cn(
                            'grid size-8 shrink-0 place-items-center rounded-md',
                            toneClass(activityTypeSpec(activity.type).tone),
                        )
                    "
                >
                    <component
                        :is="activityTypeSpec(activity.type).icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
                <div class="grid min-w-0 flex-1 gap-0.5">
                    <span class="text-ink truncate text-[13px] font-semibold">
                        {{ index + 1 }}. {{ label(activity) }}
                    </span>
                    <span class="text-ink-slate line-clamp-2 text-[12px]">
                        {{ activitySummary(activity) }}
                    </span>
                    <span
                        v-if="correctAnswer(activity) !== null"
                        class="text-success-text flex min-w-0 items-center gap-1 text-[11.5px] font-medium"
                    >
                        <Check class="size-3.5 shrink-0" aria-hidden="true" />
                        <span class="truncate">
                            {{ $t('Correct answer') }}:
                            {{ correctAnswer(activity) }}
                        </span>
                    </span>
                    <span class="text-ink-faint text-[11px]">
                        {{ $t(activityTypeSpec(activity.type).label) }} ·
                        {{
                            $tc(':count item|:count items', activity.itemCount)
                        }}
                        · v{{ activity.version }}
                    </span>
                </div>
                <div class="flex shrink-0 flex-wrap items-center justify-end">
                    <template v-if="!readOnly">
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === 0 || busy"
                            :aria-label="$t('Move up')"
                            :data-test="`block-activity-${index + 1}-up`"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === activities.length - 1 || busy"
                            :aria-label="$t('Move down')"
                            :data-test="`block-activity-${index + 1}-down`"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </button>
                    </template>
                    <button
                        type="button"
                        :class="iconButton"
                        :aria-label="
                            readOnly
                                ? $t('View :item', { item: label(activity) })
                                : $t('Edit :item', { item: label(activity) })
                        "
                        :data-test="`block-activity-${index + 1}-edit`"
                        @click="edit(activity)"
                    >
                        <SquarePen class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        v-if="!readOnly"
                        type="button"
                        :class="
                            cn(
                                iconButton,
                                'text-danger-text hover:bg-danger-tint',
                            )
                        "
                        :disabled="busy"
                        :aria-label="
                            $t('Delete :item', { item: label(activity) })
                        "
                        :data-test="`block-activity-${index + 1}-delete`"
                        @click="askDelete(activity)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </li>
        </ol>

        <LessonsActivityDialog
            v-model:open="editorOpen"
            :block="block"
            :activity="editing"
            :mode="mode"
            :number="activities.length + 1"
            :library="library"
            :read-only="readOnly"
        />

        <LessonsActivityDeleteDialog
            v-model:open="deleteOpen"
            :title="
                $t('Delete “:item”?', {
                    item: deleting === null ? '' : label(deleting),
                })
            "
            :description="
                $t(
                    'It is removed from this block. Answers learners already gave are kept for the reports.',
                )
            "
            :processing="busy"
            @confirm="confirmDelete"
        />
    </div>
</template>
