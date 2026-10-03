<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import InboxPanel from '@/components/inbox/InboxPanel.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import { tk } from '@/lib/i18n';
import { dashboard, inbox } from '@/routes';
import type { InboxMessage } from '@/types';

/**
 * The Super Admin's inbox (client request 2026-10-03): every message from
 * the website contact form and from Help, read in full and answered here.
 * The open message follows `?message=<id>`, the bell's link.
 */
type Props = {
    messages: InboxMessage[];
    selectedId: number | null;
    unread: number;
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

        <InboxPanel
            :messages="messages"
            :selected-id="selectedId"
            @open="open"
        />
    </div>
</template>
