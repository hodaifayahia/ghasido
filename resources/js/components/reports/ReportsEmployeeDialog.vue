<script setup lang="ts">
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';
import ReportsModal from '@/components/reports/ReportsModal.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import type { ReportEmployeeDetail, ReportRowStatus } from '@/types';

type Props = {
    /** The drill-down payload, or null while it loads (REP-02). */
    detail: ReportEmployeeDetail | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const statusTone: Record<ReportRowStatus, string> = {
    active: 'bg-success-tint text-success-text',
    in_progress: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
    completed: 'bg-brand-100/70 text-brand-700',
};

const figures = computed(() => {
    const detail = props.detail;

    if (detail === null) {
        return [];
    }

    return [
        { label: 'Pre-test', value: score(detail.preScore) },
        { label: 'Post-test', value: score(detail.postScore) },
        {
            label: 'Lessons',
            value: `${detail.lessonsCompleted} / ${detail.lessonsTotal}`,
        },
        {
            label: 'AI scenarios',
            value: `${detail.scenariosCompleted} / ${detail.scenariosTotal}`,
        },
        { label: 'Last activity', value: detail.lastActivity },
        { label: 'Training started', value: detail.trainingStarted },
        { label: 'Training completed', value: detail.trainingCompleted },
        { label: 'Participant code', value: detail.participantCode ?? '—' },
    ];
});

function score(value: number | null): string {
    return value === null ? '—' : `${value}%`;
}

const skeletonRows = [0, 1, 2, 3];
</script>

<template>
    <ReportsModal
        v-model:open="open"
        :title="detail?.name ?? 'Employee details'"
        :description="
            detail === null
                ? 'Loading this employee\'s results…'
                : `${detail.department} · ${detail.hotel}`
        "
        size="sm:max-w-[720px]"
    >
        <div v-if="detail === null" class="mt-2 grid gap-2" aria-busy="true">
            <div
                v-for="row in skeletonRows"
                :key="row"
                class="bg-tint-track h-4 animate-pulse rounded-sm motion-reduce:animate-none"
            />
        </div>

        <div v-else class="mt-2 grid gap-5" data-test="report-employee-detail">
            <div class="flex flex-wrap items-center gap-3">
                <Avatar class="size-11">
                    <AvatarFallback
                        class="bg-brand-100 font-heading text-brand-700 text-[14px] font-semibold"
                    >
                        {{ detail.initials }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1">
                    <p
                        class="font-heading text-brand-900 truncate text-[15px] font-semibold"
                    >
                        {{ detail.name }}
                    </p>
                    <p class="text-ink-slate text-[12.5px]">
                        {{ detail.username ?? '—' }}
                    </p>
                </div>
                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                            statusTone[detail.status],
                        )
                    "
                >
                    {{ detail.statusLabel }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div
                    v-for="figure in figures"
                    :key="figure.label"
                    class="border-line bg-app rounded-md border px-3 py-2"
                >
                    <dt class="text-ink-slate text-[10.5px] font-semibold">
                        {{ figure.label }}
                    </dt>
                    <dd class="text-brand-900 text-[13px] font-semibold">
                        {{ figure.value }}
                    </dd>
                </div>
            </dl>

            <section>
                <h3
                    class="font-heading text-brand-900 text-[13px] font-semibold"
                >
                    Lessons
                </h3>
                <ul
                    v-if="detail.lessons.length > 0"
                    class="divide-line border-line mt-2 divide-y rounded-md border"
                >
                    <li
                        v-for="lesson in detail.lessons"
                        :key="lesson.id"
                        class="flex items-center gap-3 px-3 py-2 text-[12.5px]"
                    >
                        <component
                            :is="lesson.completedAt === null ? Circle : Check"
                            :class="
                                cn(
                                    'size-4 shrink-0',
                                    lesson.completedAt === null
                                        ? 'text-ink-faint'
                                        : 'text-success',
                                )
                            "
                            aria-hidden="true"
                        />
                        <span class="text-brand-900 min-w-0 flex-1 truncate">
                            {{ lesson.title }}
                            <span class="text-ink-slate">
                                · {{ lesson.course }}
                            </span>
                        </span>
                        <span class="text-ink-muted shrink-0 text-[11.5px]">
                            {{ lesson.completedAt ?? 'Not started' }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-ink-slate mt-2 text-[12.5px]">
                    No published lessons for this department yet.
                </p>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                <section>
                    <h3
                        class="font-heading text-brand-900 text-[13px] font-semibold"
                    >
                        Tests
                    </h3>
                    <ul
                        v-if="detail.tests.length > 0"
                        class="divide-line border-line mt-2 divide-y rounded-md border"
                    >
                        <li
                            v-for="test in detail.tests"
                            :key="test.id"
                            class="flex items-center gap-3 px-3 py-2 text-[12.5px]"
                        >
                            <span
                                class="text-brand-900 min-w-0 flex-1 truncate"
                            >
                                {{ test.title }}
                                <span class="text-ink-slate">
                                    · attempt {{ test.attemptNo }}
                                </span>
                            </span>
                            <span class="text-brand-700 shrink-0 font-semibold">
                                {{ score(test.percent) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-ink-slate mt-2 text-[12.5px]">
                        No test submitted yet.
                    </p>
                </section>

                <section>
                    <h3
                        class="font-heading text-brand-900 text-[13px] font-semibold"
                    >
                        AI role-play
                    </h3>
                    <ul
                        v-if="detail.roleplays.length > 0"
                        class="divide-line border-line mt-2 divide-y rounded-md border"
                    >
                        <li
                            v-for="attempt in detail.roleplays"
                            :key="attempt.id"
                            class="flex items-center gap-3 px-3 py-2 text-[12.5px]"
                        >
                            <span
                                class="text-brand-900 min-w-0 flex-1 truncate"
                            >
                                {{ attempt.scenario }}
                                <span class="text-ink-slate">
                                    · attempt {{ attempt.attemptNo }}
                                </span>
                            </span>
                            <span class="text-ai shrink-0 font-semibold">
                                {{ score(attempt.overallScore) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-ink-slate mt-2 text-[12.5px]">
                        No role-play attempt yet.
                    </p>
                </section>
            </div>
        </div>
    </ReportsModal>
</template>
