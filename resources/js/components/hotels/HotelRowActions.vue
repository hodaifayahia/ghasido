<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Archive,
    Trash2,
    CalendarPlus,
    Check,
    EllipsisVertical,
    Eye,
    Network,
    Pause,
    Pencil,
    Play,
    Users,
    X,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import type { Component } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { useCan } from '@/composables/useCan';
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
import type { HotelRecord, HotelRowAction } from '@/types';

type Props = {
    hotel: HotelRecord;
    /** Trigger size: the 26px table button or the 36px mobile card button. */
    size?: 'table' | 'card';
};

const props = withDefaults(defineProps<Props>(), { size: 'table' });

const emit = defineEmits<{
    select: [action: HotelRowAction];
}>();

const { can } = useCan();
// Trusted only after mount, so the server and the first client render agree.
const phoneQuery = useMediaQuery('(max-width: 767px)');
const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});
const isPhone = computed(() => mounted.value && phoneQuery.value);
const sheetOpen = ref(false);

type Item = {
    action: HotelRowAction;
    label: string;
    icon: Component;
    destructive?: boolean;
    group: 'read' | 'state' | 'end';
};

/**
 * Only the actions the user's permissions and the hotel's state allow are
 * rendered; the server checks every one again regardless (spec 0002,
 * contracts and seats child, step 6; ROLE-01).
 */
const page = usePage();
const isSuperAdmin = computed(
    () => page.props.auth.user?.role === 'super_admin',
);

const items = computed<Item[]>(() => {
    const manage = can('hotels.manage');
    const approve = can('hotels.approve');
    const state = props.hotel.accessState;
    const list: Item[] = [
        { action: 'view', label: tk('View'), icon: Eye, group: 'read' },
    ];

    if (manage && state !== 'archived') {
        list.push(
            { action: 'edit', label: tk('Edit'), icon: Pencil, group: 'read' },
            {
                action: 'seats',
                label: tk('Manage seats'),
                icon: Users,
                group: 'read',
            },
            {
                action: 'departments',
                label: tk('Departments'),
                icon: Network,
                group: 'read',
            },
        );
    }

    if (approve && state === 'pending') {
        list.push(
            {
                action: 'approve',
                label: tk('Approve'),
                icon: Check,
                group: 'state',
            },
            {
                action: 'reject',
                label: tk('Reject'),
                icon: X,
                destructive: true,
                group: 'state',
            },
        );
    }

    if (manage && (state === 'active' || state === 'paused')) {
        list.push({
            action: 'extend',
            label: tk('Extend Contract'),
            icon: CalendarPlus,
            group: 'state',
        });
        list.push(
            state === 'active'
                ? {
                      action: 'pause',
                      label: tk('Pause Access'),
                      icon: Pause,
                      group: 'state',
                  }
                : {
                      action: 'resume',
                      label: tk('Resume Access'),
                      icon: Play,
                      group: 'state',
                  },
        );
    }

    if (manage && state !== 'archived') {
        list.push({
            action: 'archive',
            label: tk('Archive'),
            icon: Archive,
            destructive: true,
            group: 'end',
        });
    }

    // An archived hotel can be deleted for good, by the Super Admin only
    // (client request 2026-10-02).
    if (state === 'archived' && isSuperAdmin.value) {
        list.push({
            action: 'delete',
            label: tk('Delete hotel'),
            icon: Trash2,
            destructive: true,
            group: 'end',
        });
    }

    return list;
});

function choose(action: HotelRowAction): void {
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
        'border-line text-brand-800 hover:bg-brand-50 bg-surface inline-flex items-center justify-center rounded-md border',
        'focus-visible:border-brand-600 focus-visible:ring-brand-600/15 focus-visible:ring-3 focus-visible:outline-none',
        props.size === 'table' ? 'size-6.5' : 'size-9',
    ),
);
</script>

<template>
    <template v-if="isPhone">
        <button
            type="button"
            :class="triggerClass"
            :aria-label="$t('More actions for :name', { name: hotel.name })"
            :data-test="`hotel-${hotel.id}-actions-button`"
            @click="sheetOpen = true"
        >
            <EllipsisVertical
                :class="size === 'table' ? 'size-3' : 'size-4'"
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
                        {{ hotel.name }}
                    </SheetTitle>
                    <SheetDescription class="text-ink-slate text-[12.5px]">
                        {{ $t('Actions for this hotel') }}
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
                            :data-test="`hotel-${item.action}-action`"
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
                :aria-label="$t('More actions for :name', { name: hotel.name })"
                :data-test="`hotel-${hotel.id}-actions-button`"
            >
                <EllipsisVertical
                    :class="size === 'table' ? 'size-3' : 'size-4'"
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="border-line bg-surface shadow-pop min-w-[190px] rounded-md p-1"
        >
            <template v-for="(item, index) in items" :key="item.action">
                <DropdownMenuSeparator
                    v-if="showsSeparatorBefore(index)"
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
                    :data-test="`hotel-${item.action}-action`"
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
</template>
