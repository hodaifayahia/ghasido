<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CircleAlert, Mail } from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import StatusPill from '@/components/common/StatusPill.vue';
import { employees, messagesReminders } from '@/routes';
import type { AttentionGroup, AttentionGroupKey } from '@/types';

type Props = {
    groups: AttentionGroup[];
};

const props = defineProps<Props>();

type PillStatus = 'inactive' | 'not_started' | 'pretest_done';

const statusByGroup: Record<AttentionGroupKey, PillStatus> = {
    inactive: 'inactive',
    notStarted: 'not_started',
    pretestFinished: 'pretest_done',
};

const columns = ['Name', 'Department', 'Last Login', 'Status', 'Action'];

const active = ref<string>(props.groups[0]?.key ?? 'inactive');

function tabLabel(group: AttentionGroup): string {
    return `${group.label} (${group.total})`;
}

// Reminders are composed and sent from Messages & Reminders (REM-01), so
// the row action opens that screen; the full lists live on Employees.
const remindHref = messagesReminders();
const viewAllHref = employees();
</script>

<template>
    <PanelCard
        title="Needs Attention"
        title-id="needs-attention"
        class="px-2.5 pt-1 pb-5"
        title-class="text-[17px]"
        body-class="mt-[5px]"
    >
        <template #icon>
            <!-- Filled red disc with a white "!": the fill paints the circle,
                 the white stroke draws the mark. Negative margins keep a 24px
                 layout box around the 32px glyph. -->
            <CircleAlert
                class="fill-danger text-surface -my-1 -ms-0.5 -me-1 size-8 shrink-0"
                :stroke-width="2.25"
                aria-hidden="true"
            />
        </template>

        <template #actions>
            <Link
                :href="viewAllHref"
                class="text-brand-800 hover:text-brand-600 focus-visible:ring-brand-600/40 -me-0.5 inline-flex min-h-11 shrink-0 items-center gap-2 rounded-sm px-0.5 text-[13px] font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none md:min-h-0"
            >
                View All
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </template>

        <TabsRoot v-model="active">
            <TabsList
                aria-label="Employees needing attention"
                class="grid grid-cols-3 gap-1.5"
            >
                <TabsTrigger
                    v-for="group in groups"
                    :key="group.key"
                    :value="group.key"
                    class="bg-app-alt text-ink/75 data-[state=inactive]:hover:text-ink focus-visible:ring-brand-600/40 data-[state=active]:bg-danger-tint data-[state=active]:text-danger-text flex min-h-11 items-center justify-center rounded-sm px-1.5 py-1 text-center text-[12px] leading-tight transition-colors focus-visible:ring-2 focus-visible:outline-none data-[state=active]:font-medium md:h-[30px] md:min-h-0 md:py-0"
                >
                    {{ tabLabel(group) }}
                </TabsTrigger>
            </TabsList>

            <TabsContent
                v-for="group in groups"
                :key="group.key"
                :value="group.key"
                class="focus-visible:ring-brand-600/40 mt-2.5 rounded-[8px] focus-visible:ring-2 focus-visible:outline-none"
            >
                <!-- md and up: table -->
                <div
                    class="border-line hidden overflow-hidden rounded-[8px] border md:block"
                >
                    <table class="w-full table-fixed border-collapse">
                        <caption class="sr-only">
                            {{
                                tabLabel(group)
                            }}
                        </caption>
                        <colgroup>
                            <col class="w-[20.3%]" />
                            <col class="w-[19.3%]" />
                            <col class="w-[18.1%]" />
                            <col class="w-[18.1%]" />
                            <col />
                        </colgroup>
                        <thead class="bg-app-alt">
                            <tr class="text-ink/90 h-[26px] text-[12px]">
                                <th
                                    v-for="column in columns"
                                    :key="column"
                                    scope="col"
                                    class="ps-2 text-start font-medium"
                                >
                                    {{ column }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="text-[12px]">
                            <tr
                                v-for="employee in group.employees"
                                :key="employee.id"
                                class="border-line h-[30.6px] border-t first:border-t-0"
                            >
                                <td class="text-ink/90 truncate ps-2">
                                    {{ employee.name }}
                                </td>
                                <td class="text-ink/80 truncate ps-2">
                                    {{ employee.department }}
                                </td>
                                <td class="text-ink/80 truncate ps-2">
                                    {{ employee.lastLogin }}
                                </td>
                                <td class="ps-2">
                                    <StatusPill
                                        :status="statusByGroup[group.key]"
                                    />
                                </td>
                                <td class="ps-2">
                                    <Link
                                        :href="remindHref"
                                        class="border-brand-800/50 bg-surface text-brand-800 hover:border-brand-600 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/40 inline-flex h-[22px] w-[104px] items-center justify-center gap-1 rounded-sm border px-1 text-[11px] font-medium tracking-tight whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                        :aria-label="`Send reminder to ${employee.name}`"
                                    >
                                        <Mail
                                            class="size-3.5 shrink-0"
                                            :stroke-width="1.75"
                                            aria-hidden="true"
                                        />
                                        Send Reminder
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- below md: stacked cards -->
                <ul class="flex flex-col gap-2 md:hidden">
                    <li
                        v-for="employee in group.employees"
                        :key="employee.id"
                        class="border-line rounded-[8px] border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p
                                class="text-ink min-w-0 truncate text-sm font-medium"
                            >
                                {{ employee.name }}
                            </p>
                            <StatusPill :status="statusByGroup[group.key]" />
                        </div>
                        <p class="text-ink/75 mt-1 text-[13px]">
                            {{ employee.department }}
                            <span aria-hidden="true">&middot;</span>
                            <span class="sr-only">, last login</span>
                            {{ employee.lastLogin }}
                        </p>
                        <Link
                            :href="remindHref"
                            class="border-brand-800/50 bg-surface text-brand-800 hover:border-brand-600 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/40 mt-2.5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-sm border text-[13px] font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
                            :aria-label="`Send reminder to ${employee.name}`"
                        >
                            <Mail class="size-4 shrink-0" aria-hidden="true" />
                            Send Reminder
                        </Link>
                    </li>
                </ul>
            </TabsContent>
        </TabsRoot>
    </PanelCard>
</template>
