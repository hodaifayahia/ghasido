<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MessagesRecipientsPanel from '@/components/messages/MessagesRecipientsPanel.vue';
import MessagesSidebarPanel from '@/components/messages/MessagesSidebarPanel.vue';
import MessagesStatsRow from '@/components/messages/MessagesStatsRow.vue';
import MessagesToolbar from '@/components/messages/MessagesToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import ScriptAccent from '@/components/shell/ScriptAccent.vue';
import {
    dashboard,
    messagesReminders as messagesRemindersRoute,
} from '@/routes';
import type {
    MessageAutomationRule,
    MessageFilters,
    MessageLog,
    MessageMetric,
    MessageRecipient,
    MessageTemplate,
} from '@/types';

type Props = {
    stats: MessageMetric[];
    filters: MessageFilters;
    recipients: MessageRecipient[];
    templates: MessageTemplate[];
    automations: MessageAutomationRule[];
    logs: MessageLog[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Messages & Reminders',
                href: messagesRemindersRoute(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Messages & Reminders" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Messages & Reminders</h1>

        <PageHeader
            title="Messages & Reminders"
            description="Send training reminders, manage templates and review consent-aware delivery history."
            class="mb-1"
        >
            <template #accent>
                <ScriptAccent />
            </template>
        </PageHeader>

        <MessagesToolbar :filters="filters" />

        <MessagesStatsRow :stats="stats" />

        <div class="messages-layout grid min-w-0 gap-3">
            <MessagesRecipientsPanel :recipients="recipients" />
            <MessagesSidebarPanel
                :templates="templates"
                :automations="automations"
                :logs="logs"
            />
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1280px) {
    .messages-layout {
        align-items: start;
        grid-template-columns: minmax(0, 1fr) 320px;
    }
}
</style>
