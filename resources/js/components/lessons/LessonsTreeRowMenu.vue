<script setup lang="ts">
import {
    Copy,
    EllipsisVertical,
    Eye,
    EyeOff,
    Pencil,
    Trash2,
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
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { LessonsTreeAction } from '@/types';

/**
 * The "⋮" menu on a Course Structure row: Rename, Copy and Delete for a
 * course, a unit or a lesson (client request 2026-10-05, CMS-01). A bottom
 * sheet on a phone, a dropdown on desktop. Only the actions the row allows
 * are listed; the server checks each one again (ROLE-01, SEC-01).
 */
type Props = {
    /** The row's title, for the menu's label and the sheet heading. */
    name: string;
    testId: string;
    canEdit?: boolean;
    canCopy?: boolean;
    canDelete?: boolean;
    /** Always shown (the open lesson); otherwise see `slotClass`. */
    pinned?: boolean;
    /**
     * A course's visibility to employees: offers "Show to employees" when
     * hidden, "Hide from employees" when visible (client request
     * 2026-11-05). Omitted for units and lessons.
     */
    visibility?: 'published' | 'draft' | null;
};

const props = withDefaults(defineProps<Props>(), {
    canEdit: true,
    canCopy: true,
    canDelete: true,
    pinned: false,
    visibility: null,
});

const emit = defineEmits<{
    select: [action: LessonsTreeAction];
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
    action: LessonsTreeAction;
    label: string;
    icon: Component;
    destructive?: boolean;
};

const items = computed<Item[]>(() => {
    const list: Item[] = [];

    if (props.canEdit) {
        list.push({ action: 'rename', label: tk('Rename'), icon: Pencil });
    }

    if (props.canEdit && props.visibility === 'draft') {
        list.push({
            action: 'publish',
            label: tk('Show to employees'),
            icon: Eye,
        });
    }

    if (props.canEdit && props.visibility === 'published') {
        list.push({
            action: 'unpublish',
            label: tk('Hide from employees'),
            icon: EyeOff,
        });
    }

    if (props.canCopy) {
        list.push({
            action: 'duplicate',
            label: tk('Make a copy'),
            icon: Copy,
        });
    }

    if (props.canDelete) {
        list.push({
            action: 'delete',
            label: tk('Delete'),
            icon: Trash2,
            destructive: true,
        });
    }

    return list;
});

function choose(action: LessonsTreeAction): void {
    sheetOpen.value = false;
    emit('select', action);
}

/**
 * The approved tree keeps every title at full width. With a mouse the
 * button floats over the end of its row (`group/row` in the panel) and
 * shows on hover, on keyboard focus, while its menu is open, or always for
 * the open lesson; nothing is hover-only (AGENTS.md §7). On touch screens
 * there is no hover, so it always sits beside the title.
 */
const slotClass = computed(() =>
    cn(
        'flex shrink-0 items-center pointer-fine:absolute pointer-fine:inset-y-0 pointer-fine:end-0',
        !props.pinned &&
            'pointer-fine:opacity-0 pointer-fine:group-focus-within/row:opacity-100 pointer-fine:group-hover/row:opacity-100 pointer-fine:has-[[data-state=open]]:opacity-100',
    ),
);

const triggerClass =
    'text-ink-slate hover:bg-brand-50 hover:text-brand-800 focus-visible:ring-brand-600/15 pointer-fine:bg-surface pointer-fine:border-line pointer-fine:shadow-card inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:ring-3 focus-visible:outline-none pointer-fine:border md:size-6';
</script>

<template>
    <span v-if="items.length > 0" :class="slotClass">
        <template v-if="isPhone">
            <button
                type="button"
                :class="triggerClass"
                :aria-label="$t('More actions for :name', { name })"
                :data-test="`${testId}-actions`"
                @click="sheetOpen = true"
            >
                <EllipsisVertical class="size-4" aria-hidden="true" />
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
                            class="font-heading text-brand-900 truncate text-[16px] font-semibold"
                        >
                            {{ name }}
                        </SheetTitle>
                        <SheetDescription class="text-ink-slate text-[12.5px]">
                            {{ $t('Rename, copy or delete') }}
                        </SheetDescription>
                    </SheetHeader>
                    <ul class="mt-2 grid gap-1">
                        <li v-for="item in items" :key="item.action">
                            <button
                                type="button"
                                :class="
                                    cn(
                                        'hover:bg-brand-50 flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-start text-[14px] font-medium',
                                        item.destructive
                                            ? 'text-danger-text'
                                            : 'text-brand-900',
                                    )
                                "
                                :data-test="`${testId}-${item.action}`"
                                @click="choose(item.action)"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4.5 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ $t(item.label) }}
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
                    :aria-label="$t('More actions for :name', { name })"
                    :data-test="`${testId}-actions`"
                >
                    <EllipsisVertical class="size-3.5" aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                class="border-line bg-surface shadow-pop min-w-[170px] rounded-md p-1"
            >
                <template v-for="(item, index) in items" :key="item.action">
                    <DropdownMenuSeparator
                        v-if="item.destructive && index > 0"
                        class="bg-line"
                    />
                    <DropdownMenuItem
                        :class="
                            cn(
                                'focus:bg-brand-50 cursor-pointer gap-2.5 rounded-sm px-2.5 py-2 text-[13px] font-medium',
                                item.destructive
                                    ? 'text-danger-text focus:text-danger-text [&_svg]:text-danger'
                                    : 'text-brand-900 focus:text-brand-900 [&_svg]:text-brand-700',
                            )
                        "
                        :data-test="`${testId}-${item.action}`"
                        @select="choose(item.action)"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ $t(item.label) }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </span>
</template>
