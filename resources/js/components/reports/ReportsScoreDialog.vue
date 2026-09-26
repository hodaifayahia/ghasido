<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { RotateCcw, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import ScoreOverrideController from '@/actions/App/Http/Controllers/Admin/Reports/ScoreOverrideController';
import InputError from '@/components/InputError.vue';
import ReportsModal from '@/components/reports/ReportsModal.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import type { ReportScoreTarget } from '@/types';

/*
 * Adjust an AI score (AIE-05; spec 0005 §2.5): replace it with a reason,
 * restore the AI's value, or ask the AI to grade again. The machine's value
 * is always kept beside an override; the server re-checks every action.
 */
type Props = {
    target: ReportScoreTarget | null;
};

const props = defineProps<Props>();

const { t } = useI18n();

const open = defineModel<boolean>('open', { required: true });

const busy = ref(false);

const title = computed(() =>
    props.target === null
        ? t('Adjust score')
        : t('Adjust score · :name', { name: props.target.row.employee }),
);

const description = computed(() => {
    if (props.target === null) {
        return '';
    }

    return props.target.kind === 'answer'
        ? `${props.target.row.context} · ${props.target.row.skill}`
        : `${props.target.row.scenario} · ${t('attempt :number', {
              number: props.target.row.attemptNo,
          })}`;
});

const current = computed((): number | null => {
    if (props.target === null) {
        return null;
    }

    return props.target.kind === 'answer'
        ? props.target.row.score
        : props.target.row.overallScore;
});

const max = computed((): number => {
    if (props.target?.kind === 'answer') {
        return props.target.row.maxScore ?? 100;
    }

    return 100;
});

const canRegrade = computed((): boolean => {
    if (props.target === null) {
        return false;
    }

    return props.target.kind === 'answer'
        ? props.target.row.aiGraded
        : props.target.row.status === 'completed';
});

const grading = computed(
    (): boolean =>
        props.target?.row.aiStatus === 'pending' ||
        props.target?.row.aiStatus === 'running',
);

const updateForm = computed(() => {
    if (props.target === null) {
        return null;
    }

    return props.target.kind === 'answer'
        ? ScoreOverrideController.updateAnswer.form(props.target.row.id)
        : ScoreOverrideController.updateRoleplay.form(props.target.row.id);
});

function close(): void {
    open.value = false;
}

function restore(): void {
    if (props.target === null) {
        return;
    }

    const route =
        props.target.kind === 'answer'
            ? ScoreOverrideController.restoreAnswer(props.target.row.id)
            : ScoreOverrideController.restoreRoleplay(props.target.row.id);

    busy.value = true;
    router.delete(route.url, {
        preserveScroll: true,
        onSuccess: close,
        onFinish: () => (busy.value = false),
    });
}

function regrade(): void {
    if (props.target === null) {
        return;
    }

    const route =
        props.target.kind === 'answer'
            ? ScoreOverrideController.reevaluateAnswer(props.target.row.id)
            : ScoreOverrideController.reevaluateRoleplay(props.target.row.id);

    busy.value = true;
    router.post(
        route.url,
        {},
        {
            preserveScroll: true,
            onSuccess: close,
            onFinish: () => (busy.value = false),
        },
    );
}

const outlineButton =
    'border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:ring-brand-600/15 inline-flex h-10 items-center justify-center gap-2 rounded-md border px-4 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none disabled:opacity-50';
</script>

<template>
    <ReportsModal
        v-model:open="open"
        :title="title"
        :description="description"
        size="sm:max-w-[560px]"
    >
        <div
            v-if="target !== null && updateForm !== null"
            class="mt-2 grid gap-5"
            data-test="report-score-dialog"
        >
            <ul class="flex flex-wrap gap-2">
                <li
                    class="bg-brand-100/70 text-brand-700 rounded-pill inline-flex items-center gap-1.5 px-2.5 py-1 text-[11.5px] font-semibold"
                >
                    {{ $t('Current') }}
                    <span>{{ current ?? '—' }} / {{ max }}</span>
                </li>
                <li
                    v-if="target.row.overridden"
                    class="bg-ai-tint text-ai rounded-pill inline-flex items-center gap-1.5 px-2.5 py-1 text-[11.5px] font-semibold"
                >
                    {{ $t('Original score') }}
                    <span>{{ target.row.originalScore ?? '—' }}</span>
                </li>
                <li
                    v-if="grading"
                    class="bg-warning-tint text-warning-text rounded-pill inline-flex items-center gap-1.5 px-2.5 py-1 text-[11.5px] font-semibold"
                >
                    {{ $t('AI grading in progress') }}
                </li>
            </ul>

            <p
                v-if="target.row.overridden && target.row.overrideReason"
                class="bg-app text-ink-slate rounded-md px-3 py-2 text-[12.5px]"
            >
                <span class="text-ink font-semibold">{{
                    $t('Why it changed:')
                }}</span>
                {{ target.row.overrideReason }}
            </p>

            <Form
                :key="`${target.kind}-${target.row.id}`"
                v-bind="updateForm"
                :options="{ preserveScroll: true }"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="close"
            >
                <div class="grid gap-2">
                    <Label for="override-score">{{
                        $t('New score (0–:max)', { max })
                    }}</Label>
                    <Input
                        id="override-score"
                        name="score"
                        type="number"
                        min="0"
                        :max="max"
                        :step="target.kind === 'roleplay' ? 1 : 0.5"
                        :default-value="current ?? undefined"
                        required
                        class="h-10 max-w-[160px]"
                    />
                    <InputError :message="errors.score" />
                </div>

                <div class="grid gap-2">
                    <Label for="override-reason">{{ $t('Reason') }}</Label>
                    <textarea
                        id="override-reason"
                        name="reason"
                        rows="3"
                        maxlength="500"
                        required
                        :placeholder="
                            $t(
                                'For example: the recording was clear and the guest\'s request was fully handled.',
                            )
                        "
                        class="border-line text-ink bg-surface placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full resize-none rounded-md border px-3 py-2 text-[13.5px] shadow-none focus-visible:ring-3 focus-visible:outline-none"
                    />
                    <InputError :message="errors.reason" />
                    <p class="text-ink-faint text-[11.5px]">
                        {{
                            $t(
                                'The original score is kept beside yours and both appear in the research export.',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="canRegrade"
                            type="button"
                            :disabled="busy || processing || grading"
                            :class="outlineButton"
                            data-test="report-score-regrade-button"
                            @click="regrade"
                        >
                            <Sparkles class="size-4" aria-hidden="true" />
                            {{ $t('Grade again with AI') }}
                        </button>
                        <button
                            v-if="target.row.overridden"
                            type="button"
                            :disabled="busy || processing"
                            :class="outlineButton"
                            data-test="report-score-restore-button"
                            @click="restore"
                        >
                            <RotateCcw class="size-4" aria-hidden="true" />
                            {{ $t('Restore original score') }}
                        </button>
                    </div>
                    <div class="flex gap-2 sm:justify-end">
                        <button
                            type="button"
                            :class="cn(outlineButton, 'flex-1 sm:flex-none')"
                            @click="close"
                        >
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="busy || processing"
                            class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-10 flex-1 items-center justify-center rounded-md px-5 text-[13px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-50 sm:flex-none"
                            data-test="report-score-save-button"
                        >
                            {{ $t('Save score') }}
                        </button>
                    </div>
                </div>
            </Form>
        </div>
    </ReportsModal>
</template>
