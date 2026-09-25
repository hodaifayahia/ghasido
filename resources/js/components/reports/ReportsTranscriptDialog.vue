<script setup lang="ts">
import ReportsModal from '@/components/reports/ReportsModal.vue';
import { cn } from '@/lib/utils';
import type { ReportRoleplayRow } from '@/types';

type Props = {
    /** The attempt whose transcript is shown; only Super Admin payloads carry one (ROLE-04). */
    attempt: ReportRoleplayRow | null;
};

defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <ReportsModal
        v-model:open="open"
        :title="
            attempt === null
                ? 'Transcript'
                : `${attempt.scenario} · ${attempt.employee}`
        "
        :description="
            attempt === null
                ? ''
                : `Attempt ${attempt.attemptNo} · ${attempt.startedAt} · ${attempt.statusLabel}`
        "
        size="sm:max-w-[720px]"
    >
        <div
            v-if="attempt !== null"
            class="mt-2 grid gap-5"
            data-test="report-transcript"
        >
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="criterion in attempt.criteria"
                    :key="criterion.key"
                    class="bg-ai-tint text-ai rounded-pill inline-flex items-center gap-1.5 px-2.5 py-1 text-[11.5px] font-semibold"
                >
                    {{ criterion.label }}
                    <span>{{ criterion.score ?? '—' }}</span>
                </li>
                <li
                    class="bg-brand-100/70 text-brand-700 rounded-pill inline-flex items-center gap-1.5 px-2.5 py-1 text-[11.5px] font-semibold"
                >
                    Overall
                    <span>{{ attempt.overallScore ?? '—' }}</span>
                </li>
            </ul>

            <section>
                <h3
                    class="font-heading text-brand-900 text-[13px] font-semibold"
                >
                    Conversation
                </h3>
                <ol
                    v-if="
                        attempt.transcript !== null &&
                        attempt.transcript.length > 0
                    "
                    class="mt-2 grid gap-2"
                >
                    <li
                        v-for="(turn, index) in attempt.transcript"
                        :key="index"
                        :class="
                            cn(
                                'flex',
                                turn.role === 'employee'
                                    ? 'justify-end'
                                    : 'justify-start',
                            )
                        "
                    >
                        <div
                            :class="
                                cn(
                                    'max-w-[80%] rounded-lg px-3 py-2 text-[12.5px] leading-5',
                                    turn.role === 'employee'
                                        ? 'bg-brand-50 text-brand-900'
                                        : 'bg-tint-grid text-ink',
                                )
                            "
                        >
                            <p
                                class="text-ink-slate text-[10.5px] font-semibold"
                            >
                                {{
                                    turn.role === 'employee'
                                        ? 'Employee'
                                        : 'Guest'
                                }}
                            </p>
                            <p>{{ turn.text }}</p>
                        </div>
                    </li>
                </ol>
                <p v-else class="text-ink-slate mt-2 text-[12.5px]">
                    No turns were recorded for this attempt.
                </p>
            </section>

            <section v-if="attempt.feedback !== null">
                <h3
                    class="font-heading text-brand-900 text-[13px] font-semibold"
                >
                    Feedback
                    <span
                        v-if="attempt.feedback.summary_label"
                        class="text-ink-slate font-normal"
                    >
                        · {{ attempt.feedback.summary_label }}
                    </span>
                </h3>
                <p
                    v-if="attempt.feedback.summary_text"
                    class="text-ink mt-1 text-[12.5px]"
                >
                    {{ attempt.feedback.summary_text }}
                </p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <div v-if="attempt.feedback.did_well?.length">
                        <p
                            class="text-success-text text-[11.5px] font-semibold"
                        >
                            Did well
                        </p>
                        <ul
                            class="text-ink mt-1 list-disc ps-4 text-[12px] leading-5"
                        >
                            <li
                                v-for="line in attempt.feedback.did_well"
                                :key="line"
                            >
                                {{ line }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="attempt.feedback.improve?.length">
                        <p
                            class="text-warning-text text-[11.5px] font-semibold"
                        >
                            To improve
                        </p>
                        <ul
                            class="text-ink mt-1 list-disc ps-4 text-[12px] leading-5"
                        >
                            <li
                                v-for="entry in attempt.feedback.improve"
                                :key="entry.title"
                            >
                                <span class="font-semibold"
                                    >{{ entry.title }}:</span
                                >
                                {{ entry.text }}
                            </li>
                        </ul>
                    </div>
                </div>
                <p
                    v-if="attempt.feedback.better_expression"
                    class="border-line bg-app mt-3 rounded-md border px-3 py-2 text-[12px] leading-5"
                >
                    <span class="text-ink-slate">Yours:</span>
                    {{ attempt.feedback.better_expression.yours }}
                    <br />
                    <span class="text-ink-slate">Better:</span>
                    <span class="text-brand-900 font-semibold">
                        {{ attempt.feedback.better_expression.better }}
                    </span>
                </p>
            </section>
        </div>
    </ReportsModal>
</template>
