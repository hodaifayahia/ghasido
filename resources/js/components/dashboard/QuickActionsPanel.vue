<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    ChartNoAxesColumnIncreasing,
    ClipboardList,
    Hotel,
    Mail,
    Plus,
    Settings,
    UserPlus,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import {
    aiScenarios,
    departments,
    employees,
    hotels,
    lessonsContent,
    messagesReminders,
    reportsExport,
    tests,
} from '@/routes';
import { edit } from '@/routes/profile';

type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
const { can } = useCan();

type QuickAction = {
    label: string;
    icon: Component;
    /** Rendered px size — the mockup's glyphs differ in optical size. */
    size?: number;
    strokeWidth?: number;
    /**
     * The mockup's icons are solid while Lucide's are outline: these fill the
     * glyph's body and knock its inner details out in the tile colour.
     */
    glyphClass?: string;
    /** Draws the glyph again on top, for details Lucide paints beneath the body. */
    overlayClass?: string;
    /** Size and position of the small "+" on the "add" actions. */
    plusClass?: string;
    href: NonNullable<InertiaLinkProps['href']>;
    permission?: string;
};

const actions: QuickAction[] = [
    {
        label: 'Add Hotel',
        icon: Hotel,
        size: 23,
        glyphClass: '[&>path]:hidden [&>rect]:fill-current',
        // Door plus a 2 × 2 grid of windows (the middle column is dropped).
        overlayClass:
            '[&>path]:stroke-surface [&>path:nth-child(n+2):nth-child(-n+3)]:hidden [&>path:nth-child(n+6)]:stroke-[2.8] [&>rect]:hidden',
        plusClass: 'start-[calc(100%-1px)] top-1/2 size-2.5 -translate-y-1/2',
        href: hotels(),
    },
    {
        label: 'Add Department',
        icon: UserPlus,
        size: 26,
        glyphClass: '[&>circle]:fill-current [&>path]:fill-current',
        href: departments(),
    },
    {
        label: 'Add Employee',
        icon: UserPlus,
        size: 26,
        glyphClass: '[&>circle]:fill-current [&>path]:fill-current',
        href: employees(),
    },
    {
        label: 'Create Lesson',
        icon: BookOpen,
        glyphClass: '[&>path]:fill-current [&>path:first-child]:hidden',
        overlayClass: '[&>path]:stroke-surface [&>path:last-child]:hidden',
        plusClass: 'start-[calc(100%-3px)] -top-1 size-3.5',
        href: lessonsContent(),
    },
    {
        label: 'Add AI Scenario',
        icon: Bot,
        size: 28,
        glyphClass:
            '[&>rect]:fill-current [&>path:nth-child(n+5)]:stroke-surface',
        plusClass: 'start-[calc(100%+1px)] top-[11px] size-2.5',
        href: aiScenarios(),
    },
    {
        label: 'Manage Tests',
        icon: ClipboardList,
        size: 25,
        glyphClass:
            '[&>path:nth-child(2)]:fill-current [&>path:nth-child(n+3)]:stroke-surface [&>rect]:fill-current',
        href: tests(),
        permission: 'tests.view',
    },
    {
        label: 'Send Reminder',
        icon: Mail,
        glyphClass: '[&>path]:hidden [&>rect]:fill-current',
        overlayClass: '[&>path]:stroke-surface [&>rect]:hidden',
        href: messagesReminders(),
    },
    {
        label: 'View Reports',
        icon: ChartNoAxesColumnIncreasing,
        size: 22,
        strokeWidth: 5.8,
        href: reportsExport(),
    },
    {
        label: 'System Settings',
        icon: Settings,
        glyphClass:
            '[&>circle]:fill-surface [&>circle]:stroke-none [&>path]:fill-current',
        href: edit(),
    },
];

const visibleActions = computed(() =>
    actions.filter((action) => !action.permission || can(action.permission)),
);
</script>

<template>
    <PanelCard
        title="Quick Actions"
        title-id="quick-actions"
        :class="cn('px-2.5 pt-1.5 pb-2', props.class)"
        title-class="ps-1 text-[15px] tracking-tight"
        body-class="mt-0.5 grid"
    >
        <ul class="grid grid-cols-3 grid-rows-3 gap-x-[9px] gap-y-2">
            <li
                v-for="action in visibleActions"
                :key="action.label"
                class="flex"
            >
                <component
                    :is="Link"
                    :href="action.href"
                    class="border-line/60 bg-surface text-brand-900 ease-brand hover:border-brand-300 hover:bg-brand-50 hover:shadow-hover focus-visible:border-brand-600 focus-visible:ring-brand-600/15 flex min-h-[59px] w-full min-w-0 flex-col items-center justify-center gap-1 rounded-[8px] border px-0.5 pt-1 text-center transition duration-200 focus-visible:ring-3 focus-visible:outline-none motion-safe:hover:-translate-y-0.5 motion-reduce:transition-none"
                >
                    <span
                        class="relative flex h-6 shrink-0 items-center justify-center"
                        aria-hidden="true"
                    >
                        <component
                            :is="action.icon"
                            :size="action.size ?? 24"
                            :stroke-width="action.strokeWidth ?? 2"
                            :class="cn('shrink-0', action.glyphClass)"
                        />
                        <component
                            :is="action.icon"
                            v-if="action.overlayClass"
                            :size="action.size ?? 24"
                            :stroke-width="action.strokeWidth ?? 2"
                            :class="
                                cn(
                                    'absolute inset-0 m-auto',
                                    action.overlayClass,
                                )
                            "
                        />
                        <Plus
                            v-if="action.plusClass"
                            :stroke-width="4"
                            :class="cn('absolute', action.plusClass)"
                        />
                    </span>
                    <span
                        class="text-ink/80 text-[11px] leading-[14px] font-medium tracking-tight xl:text-[10px]"
                    >
                        {{ action.label }}
                    </span>
                </component>
            </li>
        </ul>
    </PanelCard>
</template>
