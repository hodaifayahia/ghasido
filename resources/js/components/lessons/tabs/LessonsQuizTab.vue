<script setup lang="ts">
import { ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import type { LessonActivityRow, LessonBlockRow } from '@/types';

/**
 * Quiz / Practice tab: every activity placed in the lesson's practice,
 * email and phone blocks, with its type, version and attempt rule
 * (PRAC-01..07). Edit them from the block's editor.
 */
type Props = {
    blocks: LessonBlockRow[];
};

type Row = LessonActivityRow & { block: string };

const props = defineProps<Props>();

const rows = computed((): Row[] =>
    props.blocks.flatMap((block) =>
        block.activities.map((activity) => ({
            ...activity,
            block: block.label,
        })),
    ),
);
</script>

<template>
    <div class="grid gap-3">
        <p class="text-ink-slate text-[12px]">
            {{
                $tc(
                    ':count practice activity in this lesson. Every edit to a question writes a new version; answers already given keep the version they answered (DATA-11).|:count practice activities in this lesson. Every edit to a question writes a new version; answers already given keep the version they answered (DATA-11).',
                    rows.length,
                )
            }}
        </p>

        <p
            v-if="rows.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            {{
                $t(
                    'No activity yet. Open a Practice or Quiz block and add one.',
                )
            }}
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
    </div>
</template>
