<script setup lang="ts">
import {
    BookOpen,
    Bot,
    ChartColumn,
    Download,
    EllipsisVertical,
    Eye,
    ListChecks,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import type { Component } from 'vue';
import { computed, onMounted, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { ReportEmployeeRow, ReportRowAction } from '@/types';

type Props = {
    row: ReportEmployeeRow;
    /** Whether the CSV item is offered (reports.export). */
    canExport: boolean;
    /** Trigger size: the 28px table button or the 36px mobile card button. */
    size?: 'table' | 'card';
};

const props = withDefaults(defineProps<Props>(), { size: 'table' });

const emit = defineEmits<{
    select: [action: ReportRowAction];
}>();

// Trusted only after mount, so the server and the first client render agree.
const phoneQuery = useMediaQuery('(max-width: 767px)');
const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});
const isPhone = computed(() => mounted.value && phoneQuery.value);
const sheetOpen = ref(false);

type Item = {
    action: ReportRowAction;
    label: string;
    icon: Component;
    group: 'read' | 'tabs' | 'export';
};

const items = computed<Item[]>(() => {
    const list: Item[] = [
        { action: 'details', label: 'View details', icon: Eye, group: 'read' },
        {
            action: 'answers',
            label: 'Detailed answers',
            icon: ListChecks,
            group: 'tabs',
        },
        {
            action: 'roleplay',
            label: 'AI role-play logs',
            icon: Bot,
            group: 'tabs',
        },
        {
            action: 'lessons',
            label: 'Lesson progress',
            icon: BookOpen,
            group: 'tabs',
        },
        {
            action: 'comparison',
            label: 'Pre/Post comparison',
            icon: ChartColumn,
            group: 'tabs',
        },
    ];

    if (props.canExport) {
        list.push({
            action: 'export',
            label: 'Export answers (.csv)',
            icon: Download,
            group: 'export',
        });
    }

    return list;
});

function choose(action: ReportRowAction): void {
    sheetOpen.value = false;
    emit('select', action);
}

function showsSeparatorBefore(index: number): boolean {
    const current = items.value[index];
    const previous = items.value[index - 1];

    return (
        current !== undefined &&
        previous !== undefined &&
        current.group !== previous.group
    );
}

const triggerClass = computed(() =>
    cn(
        'text-ink-faint hover:bg-brand-50 inline-flex items-center justify-center rounded-md',
        'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
        props.size === 'table' ? 'size-7' : 'size-9',
    ),
);
</script>

<template>
    <template v-if="isPhone">
        <button
            type="button"
            :class="triggerClass"
            :aria-label="`More actions for ${row.name}`"
            :data-test="`report-${row.id}-actions-button`"
            @click="sheetOpen = true"
        >
            <EllipsisVertical
                :class="size === 'table' ? 'size-3.5' : 'size-4'"
                aria-hidden="true"
            />
        </button>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="bottom"
                class="bg-surface border-line rounded-t-xl px-4 pb-6"
            >
                <div
                    class="bg-line-strong mx-auto mt-3 mb-1 h-1.5 w-10 rounded-full"
                    aria-hidden="true"
                />
                <SheetHeader class="px-0 text-start">
                    <SheetTitle
                        class="font-heading text-brand-900 text-[16px] font-semibold"
                    >
                        {{ row.name }}
                    </SheetTitle>
                    <SheetDescription class="text-ink-slate text-[12.5px]">
                        Reports for this employee
                    </SheetDescription>
                </SheetHeader>
                <ul class="mt-2 grid gap-1">
                    <li v-for="item in items" :key="item.action">
                        <button
                            type="button"
                            class="hover:bg-brand-50 text-brand-900 flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-start text-[14px] font-medium"
                            :data-test="`report-${item.action}-action`"
                            @click="choose(item.action)"
                        >
                            <component
                                :is="item.icon"
                                class="size-4.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ item.label }}
                        </button>
                    </li>
                </ul>
            </SheetContent>
        </Sheet>
    </template>

    <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :class="triggerClass"
                :aria-label="`More actions for ${row.name}`"
                :data-test="`report-${row.id}-actions-button`"
            >
                <EllipsisVertical
                    :class="size === 'table' ? 'size-3.5' : 'size-4'"
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="border-line bg-surface shadow-pop min-w-[200px] rounded-md p-1"
        >
            <template v-for="(item, index) in items" :key="item.action">
                <DropdownMenuSeparator
                    v-if="showsSeparatorBefore(index)"
                    class="bg-line"
                />
                <DropdownMenuItem
                    class="focus:bg-brand-50 text-brand-900 focus:text-brand-900 [&_svg]:text-brand-700 cursor-pointer gap-2.5 rounded-sm px-2.5 py-2 text-[13px] font-medium"
                    :data-test="`report-${item.action}-action`"
                    @select="choose(item.action)"
                >
                    <component
                        :is="item.icon"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
