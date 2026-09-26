<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Mail, ShieldAlert } from '@lucide/vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { messagesReminders } from '@/routes';
import type { AtRiskSummary } from '@/types';

/*
 * At-risk Learners (spec 0005 §4.1): who may drop out or fall behind, and
 * why, from rules anyone can check (inactivity, not started, slow progress,
 * a low Pre-test, low role-play scores). No AI decides who is listed. Same
 * card, table and stacked-card recipe as Needs Attention; the level is a
 * word as well as a colour (ACC-02).
 */
type Props = {
    atRisk: AtRiskSummary;
};

defineProps<Props>();

const levelPill: Record<'high' | 'medium', string> = {
    high: 'bg-danger-tint text-danger-text',
    medium: 'bg-warning-tint text-warning-text',
};

const levelLabel: Record<'high' | 'medium', string> = {
    high: tk('High risk'),
    medium: tk('Medium risk'),
};

const remindHref = messagesReminders();
</script>

<template>
    <PanelCard
        :title="$t('At-risk Learners')"
        title-id="at-risk-learners"
        class="px-2.5 pt-1 pb-5"
        title-class="text-[17px]"
        body-class="mt-[5px]"
        data-test="at-risk-panel"
    >
        <template #icon>
            <ShieldAlert
                class="text-danger -my-1 -ms-0.5 -me-1 size-7 shrink-0"
                :stroke-width="2"
                aria-hidden="true"
            />
        </template>

        <template #actions>
            <p class="text-ink/75 text-[12px]">
                {{
                    $t(':high high · :medium medium', {
                        high: atRisk.high,
                        medium: atRisk.medium,
                    })
                }}
                <span v-if="atRisk.total > atRisk.rows.length">
                    ·
                    {{
                        $t('top :count shown', { count: atRisk.rows.length })
                    }}</span
                >
            </p>
        </template>

        <p
            v-if="atRisk.rows.length === 0"
            class="bg-app-alt text-ink/80 rounded-[8px] px-3 py-4 text-[13px]"
        >
            {{
                $t(
                    'No learner is at risk right now. Learners appear here when they stop practising, never start, or struggle with their tests or role-plays.',
                )
            }}
        </p>

        <template v-else>
            <!-- md and up: table -->
            <div
                class="border-line hidden overflow-hidden rounded-[8px] border md:block"
            >
                <table class="w-full table-fixed border-collapse">
                    <caption class="sr-only">
                        {{
                            $t('At-risk learners and the reasons')
                        }}
                    </caption>
                    <colgroup>
                        <col class="w-[22%]" />
                        <col class="w-[18%]" />
                        <col />
                        <col class="w-[16%]" />
                        <col class="w-[64px]" />
                    </colgroup>
                    <thead class="bg-app-alt">
                        <tr class="text-ink/90 h-[26px] text-[12px]">
                            <th scope="col" class="ps-2 text-start font-medium">
                                {{ $t('Name') }}
                            </th>
                            <th scope="col" class="ps-2 text-start font-medium">
                                {{ $t('Department') }}
                            </th>
                            <th scope="col" class="ps-2 text-start font-medium">
                                {{ $t('Why') }}
                            </th>
                            <th scope="col" class="ps-2 text-start font-medium">
                                {{ $t('Level') }}
                            </th>
                            <th scope="col" class="sr-only">
                                {{ $t('Action') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-[12px]">
                        <tr
                            v-for="learner in atRisk.rows"
                            :key="learner.id"
                            class="border-line border-t align-top first:border-t-0"
                        >
                            <td class="text-ink/90 truncate py-1.5 ps-2">
                                {{ learner.name }}
                            </td>
                            <td class="text-ink/80 truncate py-1.5 ps-2">
                                {{ learner.department }}
                            </td>
                            <td class="text-ink/80 py-1.5 ps-2 leading-4">
                                <span
                                    class="line-clamp-2"
                                    :title="learner.reasons.join(' · ')"
                                >
                                    {{ learner.reasons.join(' · ') }}
                                </span>
                            </td>
                            <td class="py-1.5 ps-2">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex h-[22px] items-center px-2 text-[11px] font-semibold whitespace-nowrap',
                                            levelPill[learner.level],
                                        )
                                    "
                                >
                                    {{ $t(levelLabel[learner.level]) }}
                                </span>
                            </td>
                            <td class="py-1.5 ps-2">
                                <Link
                                    :href="remindHref"
                                    class="border-brand-800/50 bg-surface text-brand-800 hover:border-brand-600 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/40 inline-flex size-[26px] items-center justify-center rounded-sm border transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    :aria-label="
                                        $t('Send a reminder to :name', {
                                            name: learner.name,
                                        })
                                    "
                                    :title="
                                        $t('Send a reminder to :name', {
                                            name: learner.name,
                                        })
                                    "
                                >
                                    <Mail
                                        class="size-3.5"
                                        :stroke-width="1.75"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- below md: stacked cards -->
            <ul class="flex flex-col gap-2 md:hidden">
                <li
                    v-for="learner in atRisk.rows"
                    :key="learner.id"
                    class="border-line rounded-[8px] border p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p
                            class="text-ink min-w-0 truncate text-sm font-medium"
                        >
                            {{ learner.name }}
                        </p>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex h-6 items-center px-2 text-[11px] font-semibold whitespace-nowrap',
                                    levelPill[learner.level],
                                )
                            "
                        >
                            {{ $t(levelLabel[learner.level]) }}
                        </span>
                    </div>
                    <p class="text-ink/75 mt-1 text-[13px]">
                        {{ learner.department }}
                    </p>
                    <ul class="text-ink/80 mt-1 list-disc ps-4 text-[13px]">
                        <li v-for="reason in learner.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
                    <Link
                        :href="remindHref"
                        class="border-brand-800/50 bg-surface text-brand-800 hover:border-brand-600 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/40 mt-2.5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-sm border text-[13px] font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        :aria-label="
                            $t('Send a reminder to :name', {
                                name: learner.name,
                            })
                        "
                    >
                        <Mail class="size-4 shrink-0" aria-hidden="true" />
                        {{ $t('Send Reminder') }}
                    </Link>
                </li>
            </ul>
        </template>
    </PanelCard>
</template>
