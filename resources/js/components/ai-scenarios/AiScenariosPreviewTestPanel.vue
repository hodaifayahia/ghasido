<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Check,
    Eye,
    Info,
    Lightbulb,
    LoaderCircle,
    Play,
    RotateCcw,
    Send,
    Sparkles,
    Star,
    User,
    Volume2,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { AiScenarioPreviewTest } from '@/types';

type Props = {
    previewTest: AiScenarioPreviewTest;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const attempt = computed(() => props.previewTest.attempt);
const selected = ref(props.previewTest.selected);
const draft = ref('');
const starting = ref(false);

const selectedScenario = computed(
    () =>
        props.previewTest.scenarios.find((s) => s.value === selected.value) ??
        null,
);

// Poll the server while the guest reply or the evaluation is being produced
// by the queued job (PERF-04). Under the sync queue it is already done, so
// this simply never starts.
const isBusy = computed(
    () =>
        attempt.value !== null &&
        (attempt.value.pendingReply || attempt.value.status === 'evaluating'),
);

let timer: ReturnType<typeof setInterval> | null = null;

function stopPolling(): void {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

watch(
    isBusy,
    (busy) => {
        stopPolling();

        if (busy) {
            timer = setInterval(() => {
                router.reload({ only: ['previewTest'] });
            }, 1500);
        }
    },
    { immediate: true },
);

watch(
    () => props.previewTest.selected,
    (value) => {
        if (attempt.value === null) {
            selected.value = value;
        }
    },
);

onBeforeUnmount(stopPolling);

function onScenario(value: AcceptableValue): void {
    if (typeof value === 'string') {
        selected.value = value;
    }
}

function start(): void {
    if (selected.value === '' || starting.value) {
        return;
    }

    router.post(
        props.previewTest.startUrl,
        { scenario: Number(selected.value) },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (starting.value = true),
            onFinish: () => (starting.value = false),
        },
    );
}

function send(): void {
    const active = attempt.value;
    const text = draft.value.trim();

    if (active === null || text === '' || isBusy.value) {
        return;
    }

    router.post(
        active.messageUrl,
        { text },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (draft.value = ''),
        },
    );
}

function end(): void {
    const active = attempt.value;

    if (active !== null) {
        router.post(
            active.endUrl,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }
}

function reset(): void {
    const active = attempt.value;

    if (active !== null) {
        router.delete(active.resetUrl, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function play(url: string | null): void {
    if (url !== null) {
        void new Audio(url).play().catch(() => {});
    }
}

function titleCase(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}
</script>

<template>
    <div :class="cn('ai-preview-layout grid min-w-0 gap-3', props.class)">
        <!-- Setup / scenario column -->
        <section
            class="border-line bg-surface shadow-card flex min-w-0 flex-col gap-4 rounded-lg border p-4 md:p-5"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <Eye class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        class="font-heading text-brand-800 text-base font-semibold"
                    >
                        Preview &amp; Test
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12.5px]">
                        Run a real AI conversation to test a scenario before you
                        publish it.
                    </p>
                </div>
            </div>

            <template v-if="attempt === null">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Scenario
                    </label>
                    <Select
                        :model-value="selected"
                        @update:model-value="onScenario"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue placeholder="Choose a scenario" />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in previewTest.scenarios"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="selectedScenario" class="flex flex-wrap gap-2">
                    <span
                        class="rounded-pill bg-brand-50 text-brand-700 inline-flex min-h-6 items-center px-2.5 text-[11px] font-medium"
                    >
                        {{ selectedScenario.department }}
                    </span>
                    <span
                        class="rounded-pill bg-ai-tint text-ai inline-flex min-h-6 items-center px-2.5 text-[11px] font-medium"
                    >
                        Level: {{ selectedScenario.level }}
                    </span>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                selectedScenario.status === 'published'
                                    ? 'bg-success-tint text-success-text'
                                    : 'bg-warning-tint text-warning-text',
                            )
                        "
                    >
                        {{
                            selectedScenario.status === 'published'
                                ? 'Published'
                                : 'Draft'
                        }}
                    </span>
                </div>

                <div
                    v-if="selectedScenario"
                    class="border-line bg-app-alt grid gap-3 rounded-md border p-3"
                >
                    <h3 class="text-brand-900 text-[12px] font-semibold">
                        Get Ready
                    </h3>
                    <dl class="grid gap-2.5">
                        <div class="grid gap-0.5">
                            <dt
                                class="text-ink-faint text-[11px] font-semibold uppercase"
                            >
                                Situation
                            </dt>
                            <dd class="text-ink text-[12.5px] leading-[1.45]">
                                {{ selectedScenario.situation }}
                            </dd>
                        </div>
                        <div class="grid gap-0.5">
                            <dt
                                class="text-ink-faint text-[11px] font-semibold uppercase"
                            >
                                AI Role
                            </dt>
                            <dd class="text-ink text-[12.5px] leading-[1.45]">
                                {{ selectedScenario.aiRole }}
                            </dd>
                        </div>
                        <div class="grid gap-0.5">
                            <dt
                                class="text-ink-faint text-[11px] font-semibold uppercase"
                            >
                                Your Role
                            </dt>
                            <dd class="text-ink text-[12.5px] leading-[1.45]">
                                {{ selectedScenario.employeeRole }}
                            </dd>
                        </div>
                        <div class="grid gap-0.5">
                            <dt
                                class="text-ink-faint text-[11px] font-semibold uppercase"
                            >
                                Objective
                            </dt>
                            <dd class="text-ink text-[12.5px] leading-[1.45]">
                                {{ selectedScenario.objective }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="border-warning/40 bg-warning-tint text-warning-text flex items-start gap-2 rounded-md border px-3 py-2"
                >
                    <Info class="mt-px size-4 shrink-0" aria-hidden="true" />
                    <p class="text-[11.5px] leading-[1.45]">
                        {{ previewTest.notSavedNote }}
                    </p>
                </div>

                <Button
                    type="button"
                    :disabled="selected === '' || starting"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 w-full gap-1.5 rounded-md px-4 text-[13px] font-semibold text-white disabled:opacity-60"
                    @click="start"
                >
                    <LoaderCircle
                        v-if="starting"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <Play v-else class="size-4" aria-hidden="true" />
                    {{ starting ? 'Starting…' : 'Run Test Conversation' }}
                </Button>
            </template>

            <template v-else>
                <div class="border-line bg-app-alt rounded-md border p-3">
                    <p class="text-brand-900 text-[13px] font-semibold">
                        {{ attempt.scenarioTitle }}
                    </p>
                    <p class="text-ink-slate mt-0.5 text-[11.5px]">
                        {{ attempt.employeeTurns }} of {{ attempt.minTurns }}
                        replies · testing as an employee
                    </p>
                </div>

                <div
                    class="border-warning/40 bg-warning-tint text-warning-text flex items-start gap-2 rounded-md border px-3 py-2"
                >
                    <Info class="mt-px size-4 shrink-0" aria-hidden="true" />
                    <p class="text-[11.5px] leading-[1.45]">
                        {{ previewTest.notSavedNote }}
                    </p>
                </div>

                <div class="mt-auto grid gap-2">
                    <Button
                        v-if="attempt.canEnd"
                        type="button"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 gap-1.5 rounded-md px-4 text-[12px] font-semibold text-white"
                        @click="end"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        End &amp; get feedback
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-4 text-[12px] font-semibold shadow-none"
                        @click="reset"
                    >
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                        Start a new test
                    </Button>
                </div>
            </template>
        </section>

        <!-- Conversation + feedback column -->
        <div class="flex min-w-0 flex-col gap-3">
            <section
                class="border-line bg-surface shadow-card flex min-h-[360px] flex-col rounded-lg border p-4 md:p-5"
            >
                <h3 class="font-heading text-brand-800 text-base font-semibold">
                    Test Conversation
                </h3>

                <div
                    v-if="attempt === null"
                    class="text-ink-slate flex flex-1 flex-col items-center justify-center gap-2 py-8 text-center"
                >
                    <span
                        class="bg-ai-tint text-ai grid size-12 place-items-center rounded-xl"
                    >
                        <Sparkles class="size-6" aria-hidden="true" />
                    </span>
                    <p class="text-[12.5px]">
                        Pick a scenario and start the test to chat with the AI
                        guest.
                    </p>
                </div>

                <template v-else>
                    <div class="mt-3 grid flex-1 content-start gap-3">
                        <div
                            v-for="turn in attempt.transcript"
                            :key="turn.id"
                            :class="
                                cn(
                                    'flex items-end gap-2.5',
                                    turn.role === 'employee' && 'justify-end',
                                )
                            "
                        >
                            <span
                                v-if="turn.role === 'guest'"
                                class="bg-ai/12 text-ai rounded-pill grid size-9 shrink-0 place-items-center"
                            >
                                <Sparkles class="size-4" aria-hidden="true" />
                            </span>

                            <div
                                :class="
                                    cn(
                                        'max-w-[280px] rounded-2xl px-3 py-2 text-[12.5px] leading-[1.45]',
                                        turn.role === 'guest'
                                            ? 'bg-tint-header text-ink'
                                            : 'bg-brand-100/70 text-brand-900',
                                    )
                                "
                            >
                                <p>{{ turn.text }}</p>
                                <button
                                    v-if="turn.role === 'guest' && turn.audio"
                                    type="button"
                                    class="text-brand-600 hover:text-brand-700 mt-1 inline-flex items-center gap-1 text-[11px] font-semibold"
                                    @click="play(turn.audio)"
                                >
                                    <Volume2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Listen
                                </button>
                            </div>

                            <span
                                v-if="turn.role === 'employee'"
                                class="bg-brand-600 rounded-pill grid size-9 shrink-0 place-items-center text-white"
                            >
                                <User class="size-4" aria-hidden="true" />
                            </span>
                        </div>

                        <div
                            v-if="attempt.pendingReply"
                            class="flex items-center gap-2.5"
                        >
                            <span
                                class="bg-ai/12 text-ai rounded-pill grid size-9 shrink-0 place-items-center"
                            >
                                <Sparkles class="size-4" aria-hidden="true" />
                            </span>
                            <div
                                class="bg-tint-header text-ink-slate inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-[12px]"
                            >
                                <LoaderCircle
                                    class="size-3.5 animate-spin"
                                    aria-hidden="true"
                                />
                                The guest is replying…
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            attempt.aiStatus === 'failed' &&
                            !attempt.pendingReply
                        "
                        class="border-danger/30 bg-danger-tint text-danger-text mt-3 flex items-start gap-2 rounded-md border px-3 py-2"
                    >
                        <Info
                            class="mt-px size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p class="text-[11.5px] leading-[1.45]">
                            The AI could not reply. Start a new test to try
                            again.
                        </p>
                    </div>

                    <div
                        v-if="attempt.status === 'evaluating'"
                        class="text-ink-slate mt-3 flex items-center justify-center gap-2 text-[12px]"
                    >
                        <LoaderCircle
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        Scoring the conversation…
                    </div>

                    <div
                        v-else-if="attempt.status === 'in_progress'"
                        class="border-line bg-surface mt-3 flex items-center gap-2 rounded-md border px-3 py-2"
                    >
                        <Input
                            v-model="draft"
                            :placeholder="previewTest.placeholder"
                            :disabled="isBusy"
                            class="h-auto border-0 bg-transparent px-0 py-0 text-[12.5px] shadow-none focus-visible:ring-0"
                            @keyup.enter="send"
                        />
                        <button
                            type="button"
                            :disabled="isBusy || draft.trim() === ''"
                            class="text-brand-600 hover:bg-brand-50 rounded-pill inline-flex size-8 shrink-0 items-center justify-center disabled:opacity-40"
                            aria-label="Send message"
                            @click="send"
                        >
                            <Send class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </template>
            </section>

            <section
                v-if="
                    attempt &&
                    attempt.status === 'completed' &&
                    attempt.feedback
                "
                class="border-line bg-surface shadow-card rounded-lg border p-4 md:p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3
                        class="font-heading text-brand-800 text-base font-semibold"
                    >
                        AI Feedback
                    </h3>
                    <span
                        v-if="attempt.overallScore !== null"
                        class="rounded-pill bg-brand-600 inline-flex min-h-6 items-center px-2.5 text-[12px] font-bold text-white"
                    >
                        {{ attempt.overallScore }}/100
                    </span>
                </div>

                <p
                    v-if="attempt.feedback.summary_text"
                    class="text-ink mt-2 text-[12.5px] leading-[1.5]"
                >
                    {{ attempt.feedback.summary_text }}
                </p>

                <div class="mt-3 grid gap-2.5">
                    <div
                        v-for="(score, key) in attempt.criteriaScores"
                        :key="key"
                        class="grid gap-1"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-[11.5px]"
                        >
                            <span class="text-ink font-medium">
                                {{ titleCase(String(key)) }}
                            </span>
                            <span class="text-brand-700 font-semibold">
                                {{ score }}/100
                            </span>
                        </div>
                        <div class="bg-tint-track rounded-pill h-2 w-full">
                            <div
                                class="bg-brand-600 rounded-pill h-2"
                                :style="{ width: `${score}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-2.5 md:grid-cols-2">
                    <div
                        v-if="attempt.feedback.did_well?.length"
                        class="border-success/25 bg-success-tint rounded-md border px-3 py-2.5"
                    >
                        <p
                            class="text-success-text flex items-center gap-1.5 text-[11.5px] font-semibold"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                            What you did well
                        </p>
                        <ul
                            class="text-ink mt-1 list-disc space-y-0.5 ps-4 text-[12px] leading-[1.45]"
                        >
                            <li
                                v-for="(item, index) in attempt.feedback
                                    .did_well"
                                :key="index"
                            >
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div
                        v-if="attempt.feedback.improve?.length"
                        class="border-warning/30 bg-warning-tint rounded-md border px-3 py-2.5"
                    >
                        <p
                            class="text-warning-text flex items-center gap-1.5 text-[11.5px] font-semibold"
                        >
                            <Lightbulb class="size-3.5" aria-hidden="true" />
                            What to improve
                        </p>
                        <ul
                            class="text-ink mt-1 list-disc space-y-0.5 ps-4 text-[12px] leading-[1.45]"
                        >
                            <li
                                v-for="(item, index) in attempt.feedback
                                    .improve"
                                :key="index"
                            >
                                {{ item.text || item.title }}
                            </li>
                        </ul>
                    </div>
                    <div
                        v-if="attempt.feedback.better_expression?.better"
                        class="border-brand-200 bg-brand-50 rounded-md border px-3 py-2.5"
                    >
                        <p
                            class="text-brand-700 flex items-center gap-1.5 text-[11.5px] font-semibold"
                        >
                            <Sparkles class="size-3.5" aria-hidden="true" />
                            Better expression
                        </p>
                        <p class="text-ink mt-1 text-[12px] leading-[1.45]">
                            “{{ attempt.feedback.better_expression.better }}”
                        </p>
                    </div>
                    <div
                        v-if="attempt.feedback.key_phrase"
                        class="border-ai/25 bg-ai-tint rounded-md border px-3 py-2.5"
                    >
                        <p
                            class="text-ai flex items-center gap-1.5 text-[11.5px] font-semibold"
                        >
                            <Star class="size-3.5" aria-hidden="true" />
                            Key phrase to remember
                        </p>
                        <p class="text-ink mt-1 text-[12px] leading-[1.45]">
                            {{ attempt.feedback.key_phrase }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .ai-preview-layout {
        align-items: start;
        grid-template-columns: 340px minmax(0, 1fr);
    }
}
</style>
