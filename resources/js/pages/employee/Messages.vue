<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import MessageCard from '@/components/learning/MessageCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import type { LearnerMessage } from '@/types';

/*
 * Messages (REM-01, REM-04; spec 0003 Part E): the employee's in-app
 * reminders, newest first. No client mockup covers this page yet.
 */
type Props = {
    messages: LearnerMessage[];
    unread: number;
};

defineProps<Props>();
</script>

<template>
    <Head title="Messages" />
    <h1 class="sr-only">Messages</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            title="Messages"
            :description="
                unread > 0
                    ? `${unread} unread ${unread === 1 ? 'message' : 'messages'}`
                    : 'You are up to date.'
            "
        />

        <LearnerEmptyState
            v-if="messages.length === 0"
            :icon="Inbox"
            text="Reminders and messages from your trainer will appear here."
        />

        <MessageCard
            v-for="message in messages"
            :key="message.id"
            :message="message"
        />
    </div>
</template>
