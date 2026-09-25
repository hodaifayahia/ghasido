<script setup lang="ts">
import { Bot } from '@lucide/vue';
import { computed } from 'vue';
import TtsVoiceSettingsTable from '@/components/tts/TtsVoiceSettingsTable.vue';
import type {
    LessonBlockRow,
    LessonDirectoryRow,
    LessonScenarioOption,
    TtsSettings,
} from '@/types';

/**
 * AI Role-play tab: the scenarios the lesson's role-play blocks offer
 * (RP-01, RP-05). Pick or reorder them from the block's editor.
 */
type Props = {
    blocks: LessonBlockRow[];
    lessons: LessonDirectoryRow[];
    scenarios: LessonScenarioOption[];
    tts: TtsSettings;
};

type Row = LessonScenarioOption & { block: string };

const props = defineProps<Props>();

const rows = computed((): Row[] => {
    const list: Row[] = [];

    for (const block of props.blocks) {
        if (block.type !== 'ai_roleplay') {
            continue;
        }

        for (const id of block.scenarioIds) {
            const scenario = props.scenarios.find((row) => row.id === id);

            if (scenario !== undefined) {
                list.push({ ...scenario, block: block.label });
            }
        }
    }

    return list;
});

const roleplayBlocks = computed(() =>
    props.blocks.filter((block) => block.type === 'ai_roleplay'),
);
</script>

<template>
    <div class="grid gap-3">
        <p class="text-ink-slate text-[12px]">
            {{ rows.length }} scenarios across
            {{ roleplayBlocks.length }} role-play
            {{ roleplayBlocks.length === 1 ? 'block' : 'blocks' }}. Employees
            may try each one up to 3 times by default (RP-05).
        </p>

        <p
            v-if="rows.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            No scenario chosen yet. Open the AI Role-play block and pick the
            scenarios for this lesson.
        </p>

        <ul v-else class="grid gap-2 sm:grid-cols-2">
            <li
                v-for="row in rows"
                :key="`${row.block}-${row.id}`"
                class="border-line bg-surface flex items-start gap-3 rounded-md border p-3"
            >
                <span
                    class="bg-ai-tint text-ai grid size-9 shrink-0 place-items-center rounded-xl"
                >
                    <Bot class="size-4.5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-brand-900 truncate text-[13px] font-semibold"
                    >
                        {{ row.title }}
                    </p>
                    <p class="text-ink-slate line-clamp-2 text-[12px]">
                        {{ row.description }}
                    </p>
                    <p class="text-ink-faint mt-1 text-[11px] capitalize">
                        {{ row.difficulty }} · {{ row.status }} ·
                        {{ row.block }}
                    </p>
                </div>
            </li>
        </ul>

        <TtsVoiceSettingsTable :lessons="lessons" :settings="tts" />
    </div>
</template>
