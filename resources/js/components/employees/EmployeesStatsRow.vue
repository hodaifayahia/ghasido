<script setup lang="ts">
import { Check, Play, User, Users } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { EmployeeMetric, EmployeeMetricKey } from '@/types';

type Props = {
    stats: EmployeeMetric[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

type MetricLook = {
    icon: Component;
    chipClass: string;
    iconClass: string;
    valueClass: string;
};

const looks: Record<EmployeeMetricKey, MetricLook> = {
    totalEmployees: {
        icon: Users,
        chipClass: 'bg-ai/14 text-ai',
        iconClass: 'fill-current stroke-[1.8]',
        valueClass: 'text-brand-800',
    },
    activeAccounts: {
        icon: User,
        chipClass: 'bg-success/14 text-success',
        iconClass: 'fill-current stroke-[1.8]',
        valueClass: 'text-brand-800',
    },
    inactiveAccounts: {
        icon: Users,
        chipClass: 'bg-danger/14 text-danger',
        iconClass: 'fill-current stroke-[1.8]',
        valueClass: 'text-danger',
    },
    startedTraining: {
        icon: Play,
        chipClass: 'bg-brand-100 text-brand-600',
        iconClass: 'fill-current stroke-[2.3]',
        valueClass: 'text-brand-700',
    },
    completedTraining: {
        icon: Check,
        chipClass: 'bg-success-tint text-success',
        iconClass: 'stroke-[3]',
        valueClass: 'text-success-text',
    },
    notStarted: {
        icon: User,
        chipClass: 'bg-warning-tint text-warning',
        iconClass: 'fill-current stroke-[1.8]',
        valueClass: 'text-sunset',
    },
};
</script>

<template>
    <ul
        role="list"
        aria-label="Employee summary"
        tabindex="0"
        :class="
            cn(
                '-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-2 overflow-x-auto px-4 pb-1',
                'focus-visible:ring-brand-600/40 focus-visible:ring-2 focus-visible:outline-none',
                'md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0',
                'xl:grid-cols-6',
                props.class,
            )
        "
    >
        <li
            v-for="stat in stats"
            :key="stat.key"
            class="min-w-[166px] shrink-0 snap-start md:min-w-0"
        >
            <article
                class="border-line bg-surface shadow-card flex h-full items-start gap-3 rounded-lg border px-3 pt-[11px] pb-[10px]"
            >
                <div
                    :class="
                        cn(
                            'mt-px grid size-12 shrink-0 place-items-center rounded-full',
                            looks[stat.key].chipClass,
                        )
                    "
                >
                    <component
                        :is="looks[stat.key].icon"
                        aria-hidden="true"
                        :class="cn('size-[23px]', looks[stat.key].iconClass)"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        :class="
                            cn(
                                'font-heading text-[24px] leading-[1.05] font-bold tracking-[-0.02em]',
                                looks[stat.key].valueClass,
                            )
                        "
                    >
                        {{ stat.value }}
                    </p>
                    <p
                        class="text-brand-900 mt-[5px] text-[11.5px] leading-[1.15rem] font-medium"
                    >
                        {{ stat.label }}
                    </p>
                    <p
                        v-if="stat.detail"
                        class="text-ink-slate mt-[1px] text-[11.5px] leading-[1.05rem]"
                    >
                        {{ stat.detail }}
                    </p>
                </div>
            </article>
        </li>
    </ul>
</template>
