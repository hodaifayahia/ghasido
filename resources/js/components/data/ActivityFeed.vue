<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    CircleCheck,
    Clock,
    FileText,
    Mic,
    Play,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { cn } from '@/lib/utils';
import { reportsExport } from '@/routes';
import type { ActivityType, RecentActivity } from '@/types';

type Props = {
    items: RecentActivity[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

type ActivityIcon = {
    icon: Component;
    /** Colour plus the fills that give Lucide's outline icons a solid look. */
    class: string;
};

const activityIcons: Record<ActivityType, ActivityIcon> = {
    lessonCompleted: {
        icon: BookOpen,
        class: 'size-[19px] fill-current text-ai',
    },
    pretestFinished: {
        icon: FileText,
        class: 'size-[22px] fill-current text-brand-600 [&>path:not(:first-child)]:stroke-surface',
    },
    roleplayUsed: {
        icon: Mic,
        class: 'size-6 text-brand-600 [&>rect]:fill-current',
    },
    trainingCompleted: {
        icon: CircleCheck,
        class: 'size-[23px] text-success [&>circle]:fill-current [&>path]:stroke-surface',
    },
    trainingStarted: {
        icon: Play,
        class: 'size-5 fill-current text-brand-500',
    },
};

/**
 * "Lesson 3 – At the Restaurant" → ['Lesson 3 –', 'At the Restaurant'], so
 * the part after the dash can be kept together when the cell wraps.
 */
function detailParts(details: string): string[] {
    const match = details.match(/^(.+?\s[–-])\s+(.+)$/);

    return match ? [match[1], match[2]] : [details];
}
</script>

<template>
    <PanelCard
        :title="$t('Recent Activity')"
        title-id="recent-activity"
        :class="
            cn('px-4 pt-3 pb-4 md:px-2.5 md:pt-1 md:pb-[13px]', props.class)
        "
        title-class="text-[17px] leading-6"
        body-class="mt-2 md:mt-[3px]"
    >
        <template #icon>
            <span
                class="bg-brand-600 flex size-[26px] shrink-0 items-center justify-center rounded-full md:ms-1 md:me-1"
                aria-hidden="true"
            >
                <Clock
                    class="fill-surface text-brand-600 size-4"
                    :stroke-width="2.75"
                    aria-hidden="true"
                />
            </span>
        </template>

        <template #actions>
            <!-- The full activity log lives on Reports & Export (REP-01). -->
            <Link
                :href="reportsExport()"
                class="text-brand-800 ease-brand hover:text-brand-600 focus-visible:ring-brand-600/40 -me-1 inline-flex min-h-11 shrink-0 items-center gap-2 rounded-sm px-1 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none md:min-h-7"
            >
                {{ $t('View All') }}
                <ArrowRight
                    class="size-3.5 rtl:-scale-x-100"
                    aria-hidden="true"
                />
            </Link>
        </template>

        <!-- md and up: the mockup's four-column table -->
        <div
            class="border-line hidden overflow-hidden rounded-sm border md:block"
        >
            <table
                class="w-full table-fixed border-collapse text-xs tracking-[-0.01em]"
            >
                <caption class="sr-only">
                    {{
                        $t('Recent employee activity')
                    }}
                </caption>
                <colgroup>
                    <col class="w-[19.1%]" />
                    <col class="w-[21.7%]" />
                    <col class="w-[33.2%]" />
                    <col />
                </colgroup>
                <thead class="bg-app-alt">
                    <tr class="text-ink/80 h-7">
                        <th scope="col" class="ps-[7px] text-start font-medium">
                            {{ $t('Date & Time') }}
                        </th>
                        <th scope="col" class="ps-[7px] text-start font-medium">
                            {{ $t('Employee') }}
                        </th>
                        <th scope="col" class="ps-[7px] text-start font-medium">
                            {{ $t('Activity') }}
                        </th>
                        <th
                            scope="col"
                            class="border-line border-s ps-2 text-start font-medium"
                        >
                            {{ $t('Details') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-line/70 h-10 border-t"
                    >
                        <td class="ps-[7px] leading-[15px]">
                            <span class="text-ink/80 block">{{
                                item.date
                            }}</span>
                            <span class="text-ink-muted block">{{
                                item.time
                            }}</span>
                        </td>
                        <td class="text-ink/75 truncate ps-[7px]">
                            {{ item.employee }}
                        </td>
                        <td class="text-ink/80 ps-[7px]">
                            <span class="flex items-center gap-2.5">
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center"
                                >
                                    <component
                                        :is="activityIcons[item.type].icon"
                                        :class="activityIcons[item.type].class"
                                        aria-hidden="true"
                                    >
                                        <path
                                            v-if="
                                                item.type === 'lessonCompleted'
                                            "
                                            d="M12 5v16"
                                            class="stroke-surface"
                                        />
                                    </component>
                                </span>
                                <span class="min-w-0">{{ item.activity }}</span>
                            </span>
                        </td>
                        <td
                            class="border-line text-ink/70 border-s ps-2 pe-0.5 leading-[14px]"
                        >
                            <template
                                v-for="(part, index) in detailParts(
                                    item.details,
                                )"
                                :key="index"
                            >
                                {{ index > 0 ? ' ' : '' }}
                                <span :class="index > 0 && 'inline-block'">{{
                                    part
                                }}</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- below md: stacked list, nothing wider than the card -->
        <ul class="divide-line flex flex-col divide-y md:hidden">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex gap-3 py-3 first:pt-1 last:pb-0"
            >
                <span class="flex size-6 shrink-0 items-center justify-center">
                    <component
                        :is="activityIcons[item.type].icon"
                        :class="activityIcons[item.type].class"
                        aria-hidden="true"
                    >
                        <path
                            v-if="item.type === 'lessonCompleted'"
                            d="M12 5v16"
                            class="stroke-surface"
                        />
                    </component>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-ink text-[13px] leading-5">
                        <span class="font-medium">{{ item.activity }}</span>
                        <span class="text-ink-muted" aria-hidden="true">
                            ·
                        </span>
                        <span class="text-ink/80">{{ item.employee }}</span>
                    </p>
                    <p class="text-ink/70 mt-0.5 text-xs leading-4">
                        {{ item.details }}
                    </p>
                    <p class="text-ink-muted mt-1 text-xs leading-4">
                        {{ item.date }}
                        <span aria-hidden="true">·</span>
                        {{ item.time }}
                    </p>
                </div>
            </li>
        </ul>
    </PanelCard>
</template>
