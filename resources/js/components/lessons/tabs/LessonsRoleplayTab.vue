<script setup lang="ts">
import { Link as InertiaLink, router } from '@inertiajs/vue3';
import { Bot, Check, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { valueLabel } from '@/components/lessons/lessonsBlocks';
import TtsVoiceSettingsTable from '@/components/tts/TtsVoiceSettingsTable.vue';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { aiScenarios } from '@/routes';
import { scenarios as saveScenarios } from '@/routes/lessons';
import type {
    LessonBlockRow,
    LessonDirectoryRow,
    LessonScenarioOption,
    TtsSettings,
} from '@/types';

/**
 * AI Role-play tab: pick the scenarios this lesson offers (RP-01, RP-05).
 * Every scenario of the lesson's department is listed; a tap adds or
 * removes it and saves at once (lessons.scenarios). A lesson without an AI
 * Role-play step gets one on the first pick.
 */
type Props = {
    lessonId: number | null;
    readOnly: boolean;
    blocks: LessonBlockRow[];
    lessons: LessonDirectoryRow[];
    scenarios: LessonScenarioOption[];
    tts: TtsSettings;
};

const props = defineProps<Props>();

const saving = ref(false);
const error = ref<string | null>(null);

const roleplayBlocks = computed(() =>
    props.blocks.filter((block) => block.type === 'ai_roleplay'),
);

/** The ids the lesson's first role-play block offers, in order. */
const assigned = computed((): number[] => [
    ...(roleplayBlocks.value[0]?.scenarioIds ?? []),
]);

const assignedSet = computed(() => new Set(assigned.value));

function toggle(scenario: LessonScenarioOption): void {
    if (props.readOnly || props.lessonId === null || saving.value) {
        return;
    }

    const next = assignedSet.value.has(scenario.id)
        ? assigned.value.filter((id) => id !== scenario.id)
        : [...assigned.value, scenario.id];

    saving.value = true;
    error.value = null;

    router.put(
        saveScenarios.url(props.lessonId),
        { scenario_ids: next },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (bag) => {
                error.value =
                    Object.values(bag)[0] ??
                    t('The scenarios could not be saved. Try again.');
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="grid gap-3">
        <p class="text-ink-slate text-[12px]">
            {{
                $tc(
                    ':count scenario in this lesson. Employees may try each one up to 3 times by default (RP-05).|:count scenarios in this lesson. Employees may try each one up to 3 times by default (RP-05).',
                    assigned.length,
                )
            }}
            <template v-if="!readOnly">
                {{ $t('Tap a scenario to add it or remove it.') }}
            </template>
        </p>

        <p
            v-if="error"
            class="text-danger-text bg-danger-tint rounded-md px-3 py-2 text-[12.5px]"
            role="alert"
        >
            {{ error }}
        </p>

        <div
            v-if="scenarios.length === 0"
            class="border-line bg-brand-50/40 text-ink-slate grid justify-items-center gap-3 rounded-md border border-dashed px-4 py-8 text-center text-[13px]"
        >
            <p>
                {{
                    $t(
                        "No AI role-play scenario exists for this lesson's department yet.",
                    )
                }}
            </p>
            <InertiaLink
                :href="aiScenarios()"
                class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 inline-flex min-h-11 items-center gap-1.5 rounded-md px-3 text-[13px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
            >
                <Plus class="size-4" aria-hidden="true" />
                {{ $t('Create one in AI Role-play Scenarios') }}
            </InertiaLink>
        </div>

        <ul
            v-else
            class="grid gap-2"
            :aria-busy="saving"
            data-test="lesson-scenario-picker"
        >
            <li v-for="scenario in scenarios" :key="scenario.id">
                <button
                    type="button"
                    :disabled="readOnly || lessonId === null || saving"
                    :aria-pressed="assignedSet.has(scenario.id)"
                    :class="
                        cn(
                            'focus-visible:ring-brand-600 flex min-h-11 w-full items-start gap-3 rounded-md border p-3 text-start transition focus-visible:ring-2 focus-visible:outline-none disabled:cursor-default',
                            assignedSet.has(scenario.id)
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-line bg-surface hover:bg-app-alt',
                            saving && 'opacity-70',
                        )
                    "
                    :data-test="`lesson-scenario-${scenario.id}`"
                    @click="toggle(scenario)"
                >
                    <span
                        class="bg-ai-tint text-ai grid size-9 shrink-0 place-items-center rounded-xl"
                    >
                        <Bot class="size-4.5" aria-hidden="true" />
                    </span>
                    <span class="grid min-w-0 flex-1 gap-0.5">
                        <span
                            class="text-brand-900 line-clamp-2 text-[13px] font-semibold"
                        >
                            {{ scenario.title }}
                        </span>
                        <span
                            v-if="scenario.description"
                            class="text-ink-slate line-clamp-2 text-[12px]"
                        >
                            {{ scenario.description }}
                        </span>
                        <span class="text-ink-faint text-[11px] capitalize">
                            {{ $t(valueLabel(scenario.difficulty)) }} ·
                            {{ $t(valueLabel(scenario.status)) }}
                        </span>
                        <span
                            v-if="
                                assignedSet.has(scenario.id) &&
                                scenario.status !== 'published'
                            "
                            class="text-warning-text text-[11px]"
                        >
                            {{
                                $t(
                                    'Draft: employees see it once it is published.',
                                )
                            }}
                        </span>
                    </span>
                    <span
                        :class="
                            cn(
                                'grid size-5 shrink-0 place-items-center rounded-sm border',
                                assignedSet.has(scenario.id)
                                    ? 'border-brand-600 bg-brand-600 text-surface'
                                    : 'border-line-strong bg-surface',
                            )
                        "
                        aria-hidden="true"
                    >
                        <Check
                            v-if="assignedSet.has(scenario.id)"
                            class="size-3.5"
                        />
                    </span>
                </button>
            </li>
        </ul>

        <TtsVoiceSettingsTable :lessons="lessons" :settings="tts" />
    </div>
</template>
