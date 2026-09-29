<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CirclePlus, ListChecks } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityBuilderDialog from '@/components/lessons/blocks/ActivityBuilderDialog.vue';
import { Button } from '@/components/ui/button';
import { store as storeBlock } from '@/routes/blocks';
import type {
    LessonActivityRow,
    LessonBlockRow,
    LessonsImageLibrary,
} from '@/types';

/**
 * Quiz / Practice tab: every activity placed in the lesson's practice,
 * email and phone blocks, with its type, version and attempt rule
 * (PRAC-01..07). Edit them from the block's editor.
 */
type Props = {
    blocks: LessonBlockRow[];
    lessonId: number | null;
    library: LessonsImageLibrary;
    readOnly: boolean;
};

type Row = LessonActivityRow & { block: string };

const props = defineProps<Props>();
const activityBuilderOpen = ref(false);
const practiceBlock = computed(
    () =>
        props.blocks.find((block) =>
            ['practice', 'email_activity', 'phone_activity'].includes(
                block.type,
            ),
        ) ?? null,
);

const rows = computed((): Row[] =>
    props.blocks.flatMap((block) =>
        block.activities.map((activity) => ({
            ...activity,
            block: block.label,
        })),
    ),
);

function addPracticeBlock(): void {
    if (props.lessonId === null || props.readOnly) return;

    router.post(
        storeBlock.url(props.lessonId),
        { type: 'practice' },
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <div class="grid gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-ink-slate text-[12px]">
                {{
                    $tc(
                        ':count activity in this lesson.|:count activities in this lesson.',
                        rows.length,
                    )
                }}
                {{
                    $t(
                        'Every edit creates a new version; saved answers keep the version they refer to (DATA-11).',
                    )
                }}
            </p>
            <Button
                v-if="!readOnly && practiceBlock"
                type="button"
                class="bg-brand-600 hover:bg-brand-700 h-9 gap-1.5 px-3 text-[11.5px] font-semibold text-white"
                data-test="quiz-add-activity"
                @click="activityBuilderOpen = true"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add Activity') }}
            </Button>
            <Button
                v-else-if="!readOnly && lessonId"
                type="button"
                variant="outline"
                class="border-line text-brand-700 h-9 gap-1.5 px-3 text-[11.5px] font-semibold"
                @click="addPracticeBlock"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Add Practice Block') }}
            </Button>
        </div>

        <p
            v-if="rows.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            {{ $t('No activity yet. Open the Practice block and add one.') }}
        </p>

        <ul v-else class="grid gap-2">
            <li
                v-for="row in rows"
                :key="`${row.block}-${row.id}`"
                class="border-line bg-surface flex items-center gap-3 rounded-md border p-3"
            >
                <span
                    class="bg-gold-tint text-gold grid size-9 shrink-0 place-items-center rounded-xl"
                >
                    <ListChecks class="size-4.5" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-brand-900 truncate text-[13px] font-semibold"
                    >
                        {{ row.title ?? row.label }}
                    </p>
                    <p class="text-ink-slate truncate text-[12px]">
                        {{ row.prompt }}
                    </p>
                    <p class="text-ink-faint mt-0.5 text-[11px]">
                        {{ row.label }} ·
                        {{ $tc(':count item|:count items', row.itemCount) }} ·
                        v{{ row.version }}
                        ·
                        {{
                            row.attemptsAllowed === 0
                                ? $t('unlimited attempts')
                                : $tc(
                                      ':count attempt|:count attempts',
                                      row.attemptsAllowed,
                                  )
                        }}
                        · {{ row.block }}
                    </p>
                </div>
            </li>
        </ul>

        <ActivityBuilderDialog
            v-if="practiceBlock && !readOnly"
            v-model:open="activityBuilderOpen"
            :block="practiceBlock"
            :library="library"
        />
    </div>
</template>
