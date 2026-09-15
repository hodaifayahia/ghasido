<script setup lang="ts">
import {
    BookOpen,
    Bot,
    Check,
    ClipboardCheck,
    Plus,
    Settings,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ProgressBar from '@/components/data/ProgressBar.vue';
import SolidBuildingIcon from '@/components/icons/SolidBuildingIcon.vue';
import SolidUsersGroupIcon from '@/components/icons/SolidUsersGroupIcon.vue';
import { Button } from '@/components/ui/button';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type {
    DepartmentOverview,
    DepartmentQuotaState,
    DepartmentStatus,
} from '@/types';

type Props = {
    overview: DepartmentOverview;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const statusText: Record<DepartmentStatus, string> = {
    active: 'Active',
    review: 'In Review',
    draft: 'Draft',
};

const statusTone: Record<DepartmentStatus, string> = {
    active: 'bg-success-tint text-success-text',
    review: 'bg-warning-tint text-warning-text',
    draft: 'bg-brand-100/70 text-brand-700',
};

const quotaTone: Record<DepartmentQuotaState, 'brand' | 'warning'> = {
    available: 'brand',
    full: 'warning',
    over: 'warning',
};

const quotaText: Record<DepartmentQuotaState, string> = {
    available: 'Available',
    full: 'Full',
    over: 'Over quota',
};

const quotaPillTone: Record<DepartmentQuotaState, string> = {
    available: 'bg-brand-100/65 text-brand-700',
    full: 'bg-warning-tint text-warning-text',
    over: 'bg-danger-tint text-danger-text',
};

const usedSeats = computed(() =>
    props.overview.assignments.reduce(
        (sum, assignment) => sum + assignment.usedSeats,
        0,
    ),
);

const totalSeats = computed(() =>
    props.overview.assignments.reduce(
        (sum, assignment) => sum + assignment.totalSeats,
        0,
    ),
);

const occupancy = computed(() => {
    if (totalSeats.value === 0) {
        return 0;
    }

    return Math.round((usedSeats.value / totalSeats.value) * 100);
});
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <PanelCard
            title="Department Overview"
            title-id="departments-overview-title"
            body-class="flex flex-col gap-4"
        >
            <template #icon>
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <SolidUsersGroupIcon
                        class="size-4.5 fill-current"
                        aria-hidden="true"
                    />
                </div>
            </template>

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p
                        class="font-heading text-brand-900 text-[17px] leading-6 font-semibold"
                    >
                        {{ overview.name }}
                    </p>
                    <p class="text-ink-slate mt-0.5 text-[12.5px] leading-5">
                        {{ overview.scopeLabel }}
                    </p>
                </div>

                <span
                    :class="
                        cn(
                            'rounded-pill inline-flex min-h-6 min-w-[90px] items-center justify-center px-2.5 text-[11px] font-semibold whitespace-nowrap',
                            statusTone[overview.status],
                        )
                    "
                >
                    {{ statusText[overview.status] }}
                </span>
            </div>

            <div
                class="border-brand-100 bg-brand-50/60 text-ink-muted rounded-lg border p-3 text-[12.5px] leading-5"
            >
                {{ overview.focus }}
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.hotelCount }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">Hotels</p>
                </div>
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.employeeCount }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">
                        Employees
                    </p>
                </div>
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.lessonCount }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">Lessons</p>
                </div>
                <div class="bg-tint-header rounded-md px-3 py-2.5 text-center">
                    <p
                        class="font-heading text-brand-800 text-[18px] font-semibold"
                    >
                        {{ overview.scenarioCount }}
                    </p>
                    <p class="text-ink-slate text-[11px] leading-4">
                        AI Scenarios
                    </p>
                </div>
            </div>

            <div class="border-line/80 bg-surface rounded-lg border px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p
                            class="font-heading text-brand-800 text-[13px] font-semibold"
                        >
                            Seat Coverage
                        </p>
                        <p
                            class="text-ink-slate mt-0.5 text-[11.5px] leading-4"
                        >
                            {{ usedSeats }} / {{ totalSeats }} seats assigned
                        </p>
                    </div>
                    <span class="text-brand-700 text-[11px] font-semibold">
                        {{ occupancy }}%
                    </span>
                </div>

                <ProgressBar
                    :value="occupancy"
                    tone="brand"
                    :label="`${overview.name} seat coverage`"
                    class="mt-3 h-[7px]"
                />

                <div
                    class="text-ink-muted mt-2 flex items-center justify-between gap-2 text-[11.5px] leading-4"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <ClipboardCheck
                            class="text-brand-700 size-3.5"
                            aria-hidden="true"
                        />
                        {{ overview.testCount }} tests
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Bot class="text-ai size-3.5" aria-hidden="true" />
                        {{ overview.scenarioCount }} AI scenarios
                    </span>
                </div>
            </div>

            <div class="border-brand-100 bg-brand-50/60 rounded-lg border p-3">
                <p
                    class="font-heading text-brand-800 text-[13px] font-semibold"
                >
                    Key Notes
                </p>
                <ul
                    class="text-ink-muted mt-2 grid gap-1.5 text-[12px] leading-4.5"
                >
                    <li
                        v-for="note in overview.notes"
                        :key="note"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="bg-brand-600 mt-[5px] size-1.5 shrink-0 rounded-full"
                        />
                        <span>{{ note }}</span>
                    </li>
                </ul>
            </div>
        </PanelCard>

        <PanelCard
            title="Hotel Coverage"
            title-id="department-hotels-title"
            body-class="flex flex-col gap-3"
        >
            <template #icon>
                <div
                    class="bg-ai/12 text-ai grid size-8 place-items-center rounded-full"
                >
                    <SolidBuildingIcon
                        class="size-4.5 fill-current"
                        aria-hidden="true"
                    />
                </div>
            </template>

            <article
                v-for="assignment in overview.assignments"
                :key="assignment.hotel"
                class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="text-brand-900 truncate text-[12.5px] font-semibold"
                        >
                            {{ assignment.hotel }}
                        </p>
                        <p
                            class="text-ink-slate truncate text-[11px] leading-4"
                        >
                            {{ assignment.manager }}
                        </p>
                    </div>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 min-w-[78px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                quotaPillTone[assignment.state],
                            )
                        "
                    >
                        {{ quotaText[assignment.state] }}
                    </span>
                </div>

                <div class="mt-2 flex items-center gap-3">
                    <ProgressBar
                        :value="
                            Math.min(
                                100,
                                Math.round(
                                    (assignment.usedSeats /
                                        assignment.totalSeats) *
                                        100,
                                ),
                            )
                        "
                        :tone="quotaTone[assignment.state]"
                        :label="`${assignment.hotel} seats assigned`"
                        class="h-[7px] flex-1"
                    />
                    <span class="text-brand-900 text-[11.5px] font-medium">
                        {{ assignment.usedSeats }}/{{ assignment.totalSeats }}
                    </span>
                </div>
            </article>
        </PanelCard>

        <section
            class="border-line bg-surface shadow-card rounded-lg border px-4 pt-3 pb-3.5"
        >
            <header class="flex items-center gap-2.5">
                <div
                    class="bg-brand-100 text-brand-600 grid size-8 place-items-center rounded-full"
                >
                    <Plus class="size-4.5 stroke-[2.3]" aria-hidden="true" />
                </div>
                <div>
                    <h2
                        class="font-heading text-brand-800 text-[15px] font-semibold"
                    >
                        Quick Actions
                    </h2>
                    <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                        Department setup, alignment and content tasks.
                    </p>
                </div>
            </header>

            <div class="mt-3.5 grid gap-2">
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold text-white"
                    @click="notifyComingSoon('Create department')"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Create Department
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="notifyComingSoon('Assign department hotels')"
                >
                    <Users class="size-4" aria-hidden="true" />
                    Assign Hotels
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="notifyComingSoon('Configure department content')"
                >
                    <BookOpen class="size-4" aria-hidden="true" />
                    Configure Content
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 justify-start gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    @click="notifyComingSoon('Review department settings')"
                >
                    <Settings class="size-4" aria-hidden="true" />
                    Review Settings
                </Button>
            </div>
        </section>
    </div>
</template>
