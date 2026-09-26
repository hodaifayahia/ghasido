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
import { intlLocale, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/lessons';
import type {
    Accent,
    LessonCompletionRule,
    LessonContentStatus,
    LessonEditor,
    LessonFilterOption,
} from '@/types';

/**
 * Settings tab (CMS-05, JOURNEY-04): estimated minutes, the completion
 * condition, visibility (draft / published), the accent the lesson is
 * taught and judged in (spec 0006 §3) and the scope the lesson inherits
 * from its course. Saved as one PATCH with a toast.
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
const accent = ref<Accent>(props.editor.effectiveAccent);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

watch(
    () => props.editor,
    (editor) => {
        minutes.value = editor.estimatedMinutes;
        rule.value = editor.completionCondition?.rule ?? 'all_steps';
        minScore.value = editor.completionCondition?.min_score ?? null;
        status.value = editor.status;
        accent.value = editor.effectiveAccent;
    },
);

const accents: LessonFilterOption[] = [
    { value: 'en-GB', label: tk('British English') },
    { value: 'en-US', label: tk('American English') },
];

function onAccent(value: AcceptableValue): void {
    if (value === 'en-GB' || value === 'en-US') {
        accent.value = value;
    }
}

const rules: LessonFilterOption[] = [
    { value: 'all_steps', label: tk('Every visible step completed') },
    { value: 'last_step', label: tk('The last step reached') },
    {
        value: 'practice_passed',
        label: tk('Practice passed with a minimum score'),
    },
];

const statuses: LessonFilterOption[] = [
    { value: 'draft', label: tk('Draft — hidden from learners') },
    { value: 'published', label: tk('Published — visible to learners') },
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
            // Saved only when it differs from what is stored, so a lesson
            // on the platform default keeps following it.
            ...(props.editor.accent !== null ||
            accent.value !== props.editor.effectiveAccent
                ? { accent: accent.value }
                : {}),
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
                :label="$t('Estimated minutes')"
                type="number"
                :min="1"
                :max="600"
                :hint="$t('Shown on the lesson card.')"
                :error="errors.estimated_minutes"
            />

            <div class="grid gap-1.5">
                <span :class="labelClass">{{ $t('Visibility') }}</span>
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
                            {{ $t(option.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="editor.publishedAt"
                    class="text-ink-faint text-[11.5px]"
                >
                    {{
                        $t('First published :date.', {
                            date: new Date(
                                editor.publishedAt,
                            ).toLocaleDateString(intlLocale()),
                        })
                    }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1.5">
                <span :class="labelClass">{{
                    $t('Completion condition')
                }}</span>
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
                            {{ $t(option.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-ink-faint text-[11.5px]">
                    {{
                        $t(
                            'Decides when the lesson counts as done for the journey (JOURNEY-04).',
                        )
                    }}
                </p>
            </div>

            <LessonsField
                v-if="rule === 'practice_passed'"
                v-model="minScore"
                :label="$t('Minimum practice score (%)')"
                type="number"
                :min="0"
                :max="100"
                :error="errors['completion_condition.min_score']"
            />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1.5">
                <span :class="labelClass">{{ $t('Accent') }}</span>
                <Select :model-value="accent" @update:model-value="onAccent">
                    <SelectTrigger
                        :class="selectTrigger"
                        data-test="settings-accent-select"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in accents"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ $t(option.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="errors.accent" class="text-danger-text text-[11.5px]">
                    {{ errors.accent }}
                </p>
                <p v-else class="text-ink-faint text-[11.5px]">
                    {{
                        $t(
                            "The voice of the lesson audio and the accent a learner's pronunciation is checked against. Changing it queues new Normal and Slow audio.",
                        )
                    }}
                </p>
            </div>
        </div>

        <dl
            class="border-line bg-brand-50/40 grid gap-2 rounded-md border px-4 py-3 text-[12.5px] md:grid-cols-2"
        >
            <div>
                <dt class="text-ink-slate">{{ $t('Department') }}</dt>
                <dd class="text-ink font-medium">
                    {{ editor.departmentLabel ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-ink-slate">{{ $t('Hotel scope') }}</dt>
                <dd class="text-ink font-medium">
                    {{ editor.hotelLabel ?? '—' }}
                </dd>
            </div>
            <p class="text-ink-faint md:col-span-2">
                {{
                    $t(
                        'Scope follows the course: move the course to change it (CMS-04).',
                    )
                }}
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
                {{ saving ? $t('Saving…') : $t('Save settings') }}
            </Button>
        </div>
    </form>
</template>
