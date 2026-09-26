<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    TestAiGeneratePayload,
    TestAiPanel,
    TestQuestionSkillKey,
} from '@/types';

/*
 * "Generate questions with AI" (GEN-01, GEN-03, TSTM-03; spec 0004): a
 * prompt, a question count, the skill mix and a level. On a Post-test with
 * a Pre-test to mirror, the admin may instead ask for paired questions: the
 * same skills and difficulty, different items (TEST-02). The questions
 * arrive as drafts the admin approves.
 */
const props = defineProps<{
    open: boolean;
    ai: TestAiPanel;
    testTitle: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    generate: [payload: TestAiGeneratePayload];
}>();

const prompt = ref('');
const count = ref('6');
const level = ref('A2');
const skills = ref<TestQuestionSkillKey[]>([]);
const paired = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        prompt.value = props.ai.lastRequest.prompt;
        count.value = String(props.ai.lastRequest.count || 6);
        level.value = props.ai.lastRequest.level || 'A2';
        skills.value = ['multiple_choice', 'listening', 'speaking', 'writing'];
        paired.value = false;
    },
);

const canSubmit = computed(
    () =>
        paired.value ||
        (skills.value.length > 0 &&
            Number(count.value) >= 1 &&
            Number(count.value) <= 20),
);

function toggleSkill(skill: TestQuestionSkillKey, checked: boolean): void {
    skills.value = checked
        ? [...new Set([...skills.value, skill])]
        : skills.value.filter((item) => item !== skill);
}

function submit(): void {
    if (!canSubmit.value) return;

    emit('generate', {
        prompt: prompt.value.trim(),
        count: paired.value ? null : Number(count.value),
        skills: paired.value ? [] : skills.value,
        level: level.value,
        paired: paired.value,
    });
}
</script>

<template>
    <LessonsModal
        :open="open"
        :title="$t('Generate questions with AI')"
        :description="
            $t(
                'New questions for :test arrive as drafts. Review them, then publish the test.',
                { test: testTitle },
            )
        "
        size="md"
        @update:open="emit('update:open', $event)"
    >
        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label
                v-if="ai.pairedSource"
                class="border-line bg-brand-50/35 flex items-start gap-3 rounded-md border p-3"
            >
                <Checkbox
                    :model-value="paired"
                    class="mt-0.5 size-4 shrink-0"
                    data-test="ai-paired-checkbox"
                    @update:model-value="paired = Boolean($event)"
                />
                <span class="text-brand-900 text-xs leading-5">
                    <span class="font-semibold">
                        {{ $t('Generate paired questions from the Pre-test') }}
                    </span>
                    <span class="text-ink-slate block">
                        {{ ai.pairedSource.title }} ·
                        {{
                            $t(
                                ':count questions. Same skills and difficulty, different items.',
                                { count: ai.pairedSource.questionCount },
                            )
                        }}
                    </span>
                </span>
            </label>

            <div class="space-y-1.5">
                <Label
                    for="ai-test-prompt"
                    class="text-brand-900 text-xs font-semibold"
                >
                    {{ $t('Topic or instructions') }}
                    <span class="text-ink-muted font-normal">{{
                        $t('(optional)')
                    }}</span>
                </Label>
                <textarea
                    id="ai-test-prompt"
                    v-model="prompt"
                    rows="3"
                    maxlength="1000"
                    :placeholder="
                        $t(
                            'e.g. Check-in, room problems and polite requests at reception',
                        )
                    "
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full resize-none rounded-md border px-3 py-2 text-sm leading-5 outline-none focus-visible:ring-3"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label
                        for="ai-test-count"
                        class="text-brand-900 text-xs font-semibold"
                    >
                        {{ $t('Number of questions') }}
                    </Label>
                    <Input
                        id="ai-test-count"
                        v-model="count"
                        type="number"
                        min="1"
                        max="20"
                        :disabled="paired"
                        class="border-line h-11 text-sm"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-brand-900 text-xs font-semibold">
                        {{ $t('Level') }}
                    </Label>
                    <Select v-model="level">
                        <SelectTrigger class="border-line h-11 w-full text-sm">
                            <SelectValue :placeholder="$t('Choose level')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in ai.levels"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <fieldset class="space-y-2" :disabled="paired">
                <legend class="text-brand-900 text-xs font-semibold">
                    {{ $t('Skill mix') }}
                </legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <label
                        v-for="skill in ai.skills"
                        :key="skill.value"
                        class="border-line text-brand-900 flex min-h-11 items-center gap-2 rounded-md border px-3 text-xs"
                        :class="paired ? 'opacity-50' : ''"
                    >
                        <Checkbox
                            :model-value="skills.includes(skill.value)"
                            :disabled="paired"
                            class="size-4 shrink-0"
                            @update:model-value="
                                toggleSkill(skill.value, Boolean($event))
                            "
                        />
                        {{ skill.label }}
                    </label>
                </div>
            </fieldset>

            <div
                class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-xs font-semibold sm:h-10"
                    @click="emit('update:open', false)"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 gap-1.5 rounded-md px-4 text-xs font-semibold text-white sm:h-10"
                    :disabled="!canSubmit"
                    data-test="generate-test-questions-button"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    {{
                        paired
                            ? $t('Generate paired questions')
                            : $t('Generate drafts')
                    }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
