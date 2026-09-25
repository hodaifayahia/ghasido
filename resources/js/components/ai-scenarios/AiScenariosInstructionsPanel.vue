<script setup lang="ts">
import { Bot, Braces, Check, ShieldCheck, Sparkles } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    AiScenarioInstructions,
    AiScenarioInstructionsSavePayload,
} from '@/types';

type Props = {
    instructions: AiScenarioInstructions;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    save: [payload: AiScenarioInstructionsSavePayload];
}>();

const systemPrompt = ref(props.instructions.systemPrompt);
const tone = ref(props.instructions.tone);
const strictness = ref(props.instructions.strictness);
const guardrails = ref(
    props.instructions.guardrails.map((rule) => ({ ...rule })),
);
const newGuardrail = ref('');
const defaultPrompt = props.instructions.systemPrompt;
const defaultTone = props.instructions.tone;
const defaultStrictness = props.instructions.strictness;
const defaultGuardrails = props.instructions.guardrails.map((rule) => ({
    ...rule,
}));
const promptCount = computed(() => String(systemPrompt.value.length) + '/2000');

watch(
    () => props.instructions,
    (value) => {
        systemPrompt.value = value.systemPrompt;
        tone.value = value.tone;
        strictness.value = value.strictness;
        guardrails.value = value.guardrails.map((rule) => ({ ...rule }));
    },
    { deep: true },
);

function onSelect(target: 'tone' | 'strictness', value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'tone') {
        tone.value = value;
        return;
    }

    strictness.value = value;
}

function addGuardrail(): void {
    const text = newGuardrail.value.trim();
    if (text === '') return;

    guardrails.value.push({
        id: 'custom-' + Date.now(),
        text,
    });
    newGuardrail.value = '';
}

function removeGuardrail(id: string): void {
    guardrails.value = guardrails.value.filter((rule) => rule.id !== id);
}

function restoreDefault(): void {
    systemPrompt.value = defaultPrompt;
    tone.value = defaultTone;
    strictness.value = defaultStrictness;
    guardrails.value = defaultGuardrails.map((rule) => ({ ...rule }));
}

function save(): void {
    emit('save', {
        systemPrompt: systemPrompt.value.trim(),
        tone: tone.value,
        strictness: strictness.value,
        guardrails: guardrails.value
            .map((rule) => ({ id: rule.id, text: rule.text.trim() }))
            .filter((rule) => rule.text !== ''),
    });
}
</script>

<template>
    <div :class="cn('ai-instructions-layout grid min-w-0 gap-3', props.class)">
        <section
            class="border-line bg-surface shadow-card min-w-0 rounded-lg border p-4 md:p-5"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-ai-tint text-ai grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <Bot class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        class="font-heading text-brand-800 text-base font-semibold"
                    >
                        AI Instructions
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12.5px]">
                        The base system prompt every scenario uses. Per-scenario
                        roles are inserted through the variables on the right.
                    </p>
                </div>
            </div>

            <div class="mt-4 grid gap-1.5">
                <div class="flex items-center justify-between gap-3">
                    <label
                        for="ai-system-prompt"
                        class="text-brand-900 text-[12px] font-semibold"
                    >
                        System Prompt *
                    </label>
                    <span class="text-ink-faint text-[11px] font-medium">
                        {{ promptCount }}
                    </span>
                </div>
                <textarea
                    id="ai-system-prompt"
                    rows="7"
                    class="border-line text-ink bg-surface min-h-[168px] w-full resize-y rounded-md border px-3 py-2.5 text-[13px] leading-[1.6] outline-none"
                    v-model="systemPrompt"
                />
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Conversation Tone
                    </label>
                    <Select
                        :model-value="tone"
                        @update:model-value="onSelect('tone', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in instructions.toneOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Evaluation Focus
                    </label>
                    <Select
                        :model-value="strictness"
                        @update:model-value="onSelect('strictness', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in instructions.strictnessOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="mt-4">
                <h3
                    class="text-brand-900 flex items-center gap-2 text-[12px] font-semibold"
                >
                    <ShieldCheck
                        class="text-brand-600 size-4"
                        aria-hidden="true"
                    />
                    Guardrails
                </h3>
                <div class="mt-2 space-y-2">
                    <div
                        v-for="rule in guardrails"
                        :key="rule.id"
                        class="border-line bg-app-alt flex items-start gap-2.5 rounded-md border px-3 py-2"
                    >
                        <span
                            class="bg-success-tint text-success rounded-pill mt-px grid size-5 shrink-0 place-items-center"
                        >
                            <Check class="size-3" aria-hidden="true" />
                        </span>
                        <span class="text-ink text-[12px] leading-[1.45]">
                            {{ rule.text }}
                        </span>
                        <button
                            type="button"
                            class="text-ink-faint hover:text-danger ms-auto shrink-0 text-xs"
                            aria-label="Remove guardrail"
                            @click="removeGuardrail(rule.id)"
                        >
                            ×
                        </button>
                    </div>
                </div>
                <div class="mt-2 flex gap-2">
                    <input
                        v-model="newGuardrail"
                        type="text"
                        placeholder="Add a safety or coaching rule"
                        class="border-line text-ink bg-surface h-9 min-w-0 flex-1 rounded-md border px-3 text-[12px] outline-none"
                        @keyup.enter="addGuardrail"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line h-9 text-[12px] shadow-none"
                        @click="addGuardrail"
                    >
                        Add
                    </Button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="restoreDefault"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    Restore Default
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12px] font-semibold text-white"
                    @click="save"
                >
                    Save Instructions
                </Button>
            </div>
        </section>

        <aside
            class="border-line bg-surface shadow-card min-w-0 rounded-lg border p-4 md:p-5"
        >
            <h3
                class="text-brand-900 flex items-center gap-2 text-[12px] font-semibold"
            >
                <Braces class="text-brand-600 size-4" aria-hidden="true" />
                Available Variables
            </h3>
            <p class="text-ink-slate mt-1 text-[11.5px] leading-[1.5]">
                These are replaced with each scenario's own settings before the
                conversation starts.
            </p>
            <div class="mt-3 space-y-2">
                <div
                    v-for="variable in instructions.variables"
                    :key="variable.token"
                    class="border-line bg-app-alt rounded-md border px-3 py-2"
                >
                    <code
                        class="text-brand-700 font-mono text-[12px] font-semibold"
                    >
                        {{ variable.token }}
                    </code>
                    <p class="text-ink-slate mt-0.5 text-[11.5px] leading-4.5">
                        {{ variable.description }}
                    </p>
                </div>
            </div>
        </aside>
    </div>
</template>

<style scoped>
@media (min-width: 1280px) {
    .ai-instructions-layout {
        align-items: start;
        grid-template-columns: minmax(0, 1fr) 300px;
    }
}
</style>
