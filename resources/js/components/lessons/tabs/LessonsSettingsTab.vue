<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import { update } from '@/routes/lessons';
import type {
    LessonCompletionRule,
    LessonContentStatus,
    LessonEditor,
    LessonFilterOption,
} from '@/types';

/**
 * Settings tab (CMS-05, JOURNEY-04): estimated minutes, the completion
 * condition, visibility (draft / published) and the scope the lesson
 * inherits from its course. Saved as one PATCH with a toast.
 */
type Props = {
    editor: LessonEditor;
};

const props = defineProps<Props>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const minutes = ref<number | null>(props.editor.estimatedMinutes);
const rule = ref<LessonCompletionRule>(
    props.editor.completionCondition?.rule ?? 'all_steps',
);
const minScore = ref<number | null>(
    props.editor.completionCondition?.min_score ?? null,
);
const status = ref<LessonContentStatus>(props.editor.status);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

watch(
    () => props.editor,
    (editor) => {
        minutes.value = editor.estimatedMinutes;
        rule.value = editor.completionCondition?.rule ?? 'all_steps';
        minScore.value = editor.completionCondition?.min_score ?? null;
        status.value = editor.status;
    },
);

const rules: LessonFilterOption[] = [
    { value: 'all_steps', label: 'Every visible step completed' },
    { value: 'last_step', label: 'The last step reached' },
    { value: 'practice_passed', label: 'Practice passed with a minimum score' },
];

const statuses: LessonFilterOption[] = [
    { value: 'draft', label: 'Draft — hidden from learners' },
    { value: 'published', label: 'Published — visible to learners' },
];

function onRule(value: AcceptableValue): void {
    if (
        value === 'all_steps' ||
        value === 'last_step' ||
        value === 'practice_passed'
    ) {
        rule.value = value;
    }
}

function onStatus(value: AcceptableValue): void {
    if (value === 'draft' || value === 'published') {
        status.value = value;
    }
}

function save(): void {
    if (props.editor.id === null) {
        return;
    }

    saving.value = true;
    errors.value = {};

    router.patch(
        update.url(props.editor.id),
        {
            estimated_minutes: minutes.value,
            completion_condition: {
                rule: rule.value,
                min_score:
                    rule.value === 'practice_passed' ? minScore.value : null,
            },
            status: status.value,
            _notify: true,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (bag) => {
                errors.value = bag;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

const selectTrigger =
    'border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm text-[13px] shadow-none focus-visible:ring-3';
const labelClass = 'text-brand-900 text-[12px] font-semibold tracking-[0.02em]';
</script>

<template>
    <form class="grid gap-4" @submit.prevent="save">
        <div class="grid gap-4 md:grid-cols-2">
            <LessonsField
                v-model="minutes"
                label="Estimated minutes"
                type="number"
                :min="1"
                :max="600"
                hint="Shown on the lesson card."
                :error="errors.estimated_minutes"
            />

            <div class="grid gap-1.5">
                <span :class="labelClass">Visibility</span>
                <Select :model-value="status" @update:model-value="onStatus">
                    <SelectTrigger
                        :class="selectTrigger"
                        data-test="settings-status-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="editor.publishedAt"
                    class="text-ink-faint text-[11.5px]"
                >
                    First published
                    {{ new Date(editor.publishedAt).toLocaleDateString() }}.
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1.5">
                <span :class="labelClass">Completion condition</span>
                <Select :model-value="rule" @update:model-value="onRule">
                    <SelectTrigger
                        :class="selectTrigger"
                        data-test="settings-rule-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in rules"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-ink-faint text-[11.5px]">
                    Decides when the lesson counts as done for the journey
                    (JOURNEY-04).
                </p>
            </div>

            <LessonsField
                v-if="rule === 'practice_passed'"
                v-model="minScore"
                label="Minimum practice score (%)"
                type="number"
                :min="0"
                :max="100"
                :error="errors['completion_condition.min_score']"
            />
        </div>

        <dl
            class="border-line bg-brand-50/40 grid gap-2 rounded-md border px-4 py-3 text-[12.5px] md:grid-cols-2"
        >
            <div>
                <dt class="text-ink-slate">Department</dt>
                <dd class="text-ink font-medium">
                    {{ editor.departmentLabel ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-ink-slate">Hotel scope</dt>
                <dd class="text-ink font-medium">
                    {{ editor.hotelLabel ?? '—' }}
                </dd>
            </div>
            <p class="text-ink-faint md:col-span-2">
                Scope follows the course: move the course to change it (CMS-04).
            </p>
        </dl>

        <div v-if="manage" class="flex justify-end">
            <Button
                type="submit"
                :disabled="saving"
                data-test="settings-save-button"
                :class="
                    cn(
                        'bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]',
                    )
                "
            >
                {{ saving ? 'Saving…' : 'Save settings' }}
            </Button>
        </div>
    </form>
</template>
