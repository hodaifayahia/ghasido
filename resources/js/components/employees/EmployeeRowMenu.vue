<script setup lang="ts">
import { EllipsisVertical, KeyRound, UserCheck, UserX } from '@lucide/vue';
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
import type { EmployeeRecord, EmployeeRowAction } from '@/types';

type Props = {
    employee: EmployeeRecord;
    /** Trigger size: the 26px table button or the 36px mobile card button. */
    size?: 'table' | 'card';
};

const props = withDefaults(defineProps<Props>(), { size: 'table' });

const emit = defineEmits<{
    select: [action: EmployeeRowAction];
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
    action: EmployeeRowAction;
    label: string;
    icon: Component;
    destructive?: boolean;
};

/**
 * The "More" menu: what the three icon buttons do not carry (spec 0003
 * Part D). The server checks every action again regardless (ROLE-01).
 */
const items = computed<Item[]>(() => [
    { action: 'reset-password', label: 'Reset password', icon: KeyRound },
    props.employee.accountStatus === 'active'
        ? {
              action: 'deactivate',
              label: 'Deactivate',
              icon: UserX,
              destructive: true,
          }
        : { action: 'activate', label: 'Activate', icon: UserCheck },
]);

function choose(action: EmployeeRowAction): void {
    sheetOpen.value = false;
    emit('select', action);
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
            :aria-label="`More actions for ${employee.name}`"
            :data-test="`employee-${employee.id}-actions-button`"
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
                        {{ employee.name }}
                    </SheetTitle>
                    <SheetDescription class="text-ink-slate text-[12.5px]">
                        More actions for this employee
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
                            :data-test="`employee-${item.action}-action`"
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
                :aria-label="`More actions for ${employee.name}`"
                :data-test="`employee-${employee.id}-actions-button`"
            >
                <EllipsisVertical
                    :class="size === 'table' ? 'size-3' : 'size-4'"
                    aria-hidden="true"
                />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="border-line bg-surface shadow-pop min-w-[180px] rounded-md p-1"
        >
            <template v-for="(item, index) in items" :key="item.action">
                <DropdownMenuSeparator v-if="index > 0" class="bg-line" />
                <DropdownMenuItem
                    :class="
                        cn(
                            'focus:bg-brand-50 cursor-pointer gap-2.5 rounded-sm px-2.5 py-2 text-[13px] font-medium',
                            item.destructive
                                ? 'text-danger-text focus:text-danger-text [&_svg]:text-danger'
                                : 'text-brand-900 focus:text-brand-900 [&_svg]:text-brand-700',
                        )
                    "
                    :data-test="`employee-${item.action}-action`"
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
