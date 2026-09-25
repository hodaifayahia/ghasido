<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { subscriptions } from '@/routes';
import { messages } from '@/routes/learn';
import type { AppNotification } from '@/types';

const page = usePage();
const sourceItems = computed(() => page.props.notifications.items);
const now = ref(Date.now());
const items = computed(() =>
    sourceItems.value.filter(
        (item) =>
            item.expiresAt === null || Date.parse(item.expiresAt) > now.value,
    ),
);
const unread = computed(() => items.value.filter((item) => !item.read).length);
const isSuperAdmin = computed(
    () => page.props.auth.user?.role === 'super_admin',
);

let pollTimer: number | undefined;
let expiryTimer: number | undefined;

function scheduleExpiry(): void {
    now.value = Date.now();

    if (expiryTimer !== undefined) {
        window.clearTimeout(expiryTimer);
    }

    const nextExpiry = sourceItems.value
        .filter((item) => item.expiresAt !== null)
        .map((item) => Date.parse(item.expiresAt!))
        .filter(
            (timestamp) => Number.isFinite(timestamp) && timestamp > now.value,
        )
        .sort((left, right) => left - right)[0];

    if (nextExpiry !== undefined) {
        expiryTimer = window.setTimeout(
            scheduleExpiry,
            Math.max(0, nextExpiry - now.value + 1),
        );
    }
}

watch(sourceItems, scheduleExpiry, { deep: true });

onMounted(() => {
    scheduleExpiry();
    pollTimer = window.setInterval(() => {
        router.reload({
            // Refresh the navbar point balance while an AI session is open too (AIL-03).
            only: ['notifications', 'aiPointBalance'],
        });
    }, 60_000);
});

onUnmounted(() => {
    if (pollTimer !== undefined) {
        window.clearInterval(pollTimer);
    }
    if (expiryTimer !== undefined) {
        window.clearTimeout(expiryTimer);
    }
});

function markRead(notification: AppNotification): void {
    if (notification.read) {
        return;
    }

    router.post(
        notification.readUrl,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function sentTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :aria-label="
                    unread > 0
                        ? `Notifications, ${unread} unread`
                        : 'Notifications, none unread'
                "
                class="text-brand-800/85 hover:bg-brand-50 focus-visible:ring-brand-600/40 data-[state=open]:bg-brand-50 flex size-11 shrink-0 items-center justify-center rounded-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                data-test="notification-menu-trigger"
            >
                <span class="relative flex">
                    <Bell class="size-6 fill-current" aria-hidden="true" />
                    <span
                        v-if="unread > 0"
                        aria-hidden="true"
                        class="rounded-pill bg-danger ring-surface absolute -end-[4px] -top-[4px] flex h-[11px] min-w-[11px] items-center justify-center px-[2px] text-[7.5px] leading-none font-bold text-white ring-1"
                    >
                        {{ unread > 9 ? '9+' : unread }}
                    </span>
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :side-offset="8"
            class="shadow-pop w-80 rounded-lg p-0"
            data-test="notification-menu-content"
        >
            <DropdownMenuLabel
                class="flex items-center justify-between gap-2 px-4 py-3"
            >
                <span class="font-heading text-brand-800 text-sm font-semibold">
                    Notifications
                </span>
                <span
                    v-if="unread > 0"
                    class="rounded-pill bg-danger-tint text-danger-text px-2 py-0.5 text-xs font-semibold"
                >
                    {{ unread }} unread
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator class="mx-0 my-0" />

            <div v-if="items.length > 0" class="max-h-80 overflow-y-auto">
                <DropdownMenuItem
                    v-for="item in items"
                    :key="item.id"
                    class="h-auto items-start px-4 py-3 whitespace-normal"
                    :data-test="`notification-${item.id}`"
                    @select="markRead(item)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                :class="[
                                    'text-ink-indigo text-sm leading-5',
                                    item.read ? 'font-medium' : 'font-semibold',
                                ]"
                            >
                                {{ item.subject }}
                            </span>
                            <time
                                class="text-ink-slate shrink-0 pt-0.5 text-[11px]"
                                :datetime="item.sentAt"
                            >
                                {{ sentTime(item.sentAt) }}
                            </time>
                        </div>
                        <p
                            class="text-ink-slate mt-1 line-clamp-3 text-xs leading-5 whitespace-pre-line"
                        >
                            {{ item.body }}
                        </p>
                        <span
                            v-if="!item.read"
                            class="text-brand-600 mt-1 inline-block text-[11px] font-semibold"
                        >
                            New
                        </span>
                    </div>
                </DropdownMenuItem>
            </div>
            <p v-else class="text-ink-slate px-4 py-5 text-center text-sm">
                You’re all caught up.
            </p>

            <DropdownMenuSeparator class="mx-0 my-0" />
            <DropdownMenuItem as-child class="justify-center px-4 py-2.5">
                <Link
                    :href="isSuperAdmin ? subscriptions() : messages()"
                    class="text-brand-700 cursor-pointer text-sm font-semibold"
                >
                    {{
                        isSuperAdmin
                            ? 'Review point requests'
                            : 'View all notifications'
                    }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
