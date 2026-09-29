<script setup lang="ts">
import { Link as InertiaLink } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { settingField, valueLabel } from '@/components/lessons/lessonsBlocks';
import { cn } from '@/lib/utils';
import { aiScenarios } from '@/routes';
import type { BlockSettings, LessonScenarioOption } from '@/types';

/**
 * AI Role-play block (spec 0003 B.10, photo_15): the intro copy and the set of
 * scenarios the learner may practise. The scenario ids are saved with the
 * block (blocks.update); the scenarios themselves are authored on the AI
 * Scenarios screen (RP-04).
 */
type Props = {
    scenarios: LessonScenarioOption[];
    readOnly: boolean;
};

const props = defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });
const scenarioIds = defineModel<number[]>('scenarioIds', {
    default: () => [],
});

const subtitle = settingField(settings, 'subtitle');
const attemptsNote = settingField(settings, 'attempts_note');
const tip = settingField(settings, 'tip');

const selected = computed(() => new Set(scenarioIds.value));

function toggle(id: number): void {
    if (props.readOnly) {
        return;
    }

    const next = new Set(scenarioIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    scenarioIds.value = [...next];
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            meaning
            v-model="subtitle"
            :label="$t('Subtitle')"
            type="textarea"
            :rows="2"
        />
        <LessonsField
            meaning
            v-model="attemptsNote"
            :label="$t('Attempts note')"
        />
        <LessonsField
            meaning
            v-model="tip"
            :label="$t('Tip')"
            type="textarea"
            :rows="2"
        />

        <div class="grid gap-2">
            <span
                class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
            >
                {{ $t('Scenarios in this block') }}
            </span>
            <p
                v-if="scenarios.length === 0"
                class="text-ink-muted text-[12.5px]"
            >
                {{
                    $t(
                        'No role-play scenarios exist for this lesson’s department yet.',
                    )
                }}
                <InertiaLink
                    :href="aiScenarios()"
                    class="text-brand-700 hover:underline"
                >
                    {{ $t('Create one in AI Role-play Scenarios.') }}
                </InertiaLink>
            </p>
            <div v-else class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="scenario in scenarios"
                    :key="scenario.id"
                    type="button"
                    :disabled="readOnly"
                    :aria-pressed="selected.has(scenario.id)"
                    :class="
                        cn(
                            'flex items-center gap-2 rounded-md border px-3 py-2 text-start text-[13px] transition',
                            selected.has(scenario.id)
                                ? 'border-brand-600 bg-brand-50 text-brand-700'
                                : 'border-line bg-surface text-ink hover:bg-app-alt',
                        )
                    "
                    @click="toggle(scenario.id)"
                >
                    <span
                        :class="
                            cn(
                                'grid size-5 shrink-0 place-items-center rounded-sm border',
                                selected.has(scenario.id)
                                    ? 'border-brand-600 bg-brand-600 text-white'
                                    : 'border-line-strong bg-surface',
                            )
                        "
                    >
                        <Check
                            v-if="selected.has(scenario.id)"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="grid gap-0.5">
                        <span class="font-semibold">{{ scenario.title }}</span>
                        <span class="text-ink-muted text-[11.5px] capitalize">
                            {{ $t(valueLabel(scenario.difficulty)) }} ·
                            {{ $t(valueLabel(scenario.status)) }}
                        </span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
