<script setup lang="ts">
import { Bell, Check, Clock, FileText, Mail } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { MessageMetric, MessageMetricKey } from '@/types';

type Props = {
    stats: MessageMetric[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

type MetricLook = {
    icon: Component;
    chipClass: string;
    iconClass: string;
    valueClass: string;
};

const looks: Record<MessageMetricKey, MetricLook> = {
    consentedEmployees: {
        icon: Check,
        chipClass: 'bg-success-tint text-success',
        iconClass: 'stroke-[3]',
        valueClass: 'text-success-text',
    },
    scheduledReminders: {
        icon: Clock,
        chipClass: 'bg-brand-100 text-brand-600',
        iconClass: 'stroke-[2.25]',
        valueClass: 'text-brand-800',
    },
    followUpNeeded: {
        icon: Bell,
        chipClass: 'bg-warning-tint text-warning',
        iconClass: 'fill-current',
        valueClass: 'text-warning-text',
    },
    sentToday: {
        icon: Mail,
        chipClass: 'bg-ai/14 text-ai',
        iconClass: 'stroke-[2.2]',
        valueClass: 'text-brand-800',
    },
    templates: {
        icon: FileText,
        chipClass: 'bg-brand-50 text-brand-700',
        iconClass: 'fill-current [&>path:not(:first-child)]:stroke-surface',
        valueClass: 'text-brand-700',
    },
};
</script>

<template>
    <ul
        role="list"
        :aria-label="$t('Reminder summary')"
        tabindex="0"
        :class="
            cn(
                '-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-2 overflow-x-auto px-4 pb-1',
                'focus-visible:ring-brand-600/40 focus-visible:ring-2 focus-visible:outline-none',
                'md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0',
                'xl:grid-cols-5',
                props.class,
            )
        "
    >
        <li
            v-for="stat in stats"
            :key="stat.key"
            class="min-w-[170px] shrink-0 snap-start md:min-w-0"
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
