<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import InboxPanel from '@/components/inbox/InboxPanel.vue';
import LevelRequestsPanel from '@/components/inbox/LevelRequestsPanel.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { tk } from '@/lib/i18n';
import { dashboard, inbox } from '@/routes';
import { cn } from '@/lib/utils';
import type { InboxMessage, LevelRequestRow } from '@/types';

/**
 * The Super Admin's inbox (client request 2026-10-03): every message from
 * the website contact form and from Help, read in full and answered here.
 * The open message follows `?message=<id>`, the bell's link.
 */
type Props = {
    messages: InboxMessage[];
    selectedId: number | null;
    unread: number;
    /** Messages, or learners' level change requests (2026-10-04). */
    tab: 'messages' | 'levels';
    levelRequests: LevelRequestRow[];
    pendingLevelRequests: number;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('Inbox'), href: inbox() },
        ],
    },
});

function showTab(tab: 'messages' | 'levels'): void {
    router.get(
        inbox.url({ query: tab === 'levels' ? { tab } : {} }),
        {},
        { preserveScroll: true, replace: true },
    );
}

function open(id: number | null): void {
    router.get(
        inbox.url({ query: id === null ? {} : { message: id } }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="$t('Inbox')" />

    <div class="flex min-w-0 flex-col gap-3 px-4 pt-5 pb-5 md:px-6">
        <PageHeader
            :title="$t('Inbox')"
            :description="
                unread > 0
                    ? $t(
                          'Messages from your customers and users. :count unread.',
                          { count: unread },
                      )
                    : $t('Messages from your customers and users.')
            "
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <div
            class="border-line bg-surface flex w-fit gap-1 rounded-md border p-1"
            role="tablist"
        >
            <button
                v-for="item in [
                    {
                        key: 'messages',
                        label: tk('Messages'),
                        count: unread,
                    },
                    {
                        key: 'levels',
                        label: tk('Level requests'),
                        count: pendingLevelRequests,
                    },
                ] as const"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="tab === item.key"
                :class="
                    cn(
                        'inline-flex min-h-9 items-center gap-2 rounded-sm px-3 text-[13px] font-semibold',
                        tab === item.key
                            ? 'bg-brand-600 text-white'
                            : 'text-ink-slate hover:bg-brand-50',
                    )
                "
                :data-test="`inbox-tab-${item.key}`"
                @click="showTab(item.key)"
            >
                {{ $t(item.label) }}
                <span
                    v-if="item.count > 0"
                    class="rounded-pill bg-danger min-w-[18px] px-1 text-[10.5px] leading-[18px] text-white"
                    >{{ item.count }}</span
                >
            </button>
        </div>

        <LevelRequestsPanel v-if="tab === 'levels'" :requests="levelRequests" />
        <InboxPanel
            v-else
            :messages="messages"
            :selected-id="selectedId"
            @open="open"
        />
    </div>
</template>
