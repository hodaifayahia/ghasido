<script setup lang="ts">
import MessagesModal from '@/components/messages/MessagesModal.vue';
import MessagesPager from '@/components/messages/MessagesPager.vue';
import { cn } from '@/lib/utils';
import type { MessageLog, MessageLogStatus, MessagePagination } from '@/types';

type Props = {
    logs: MessageLog[];
    pagination: MessagePagination;
    loading?: boolean;
};

withDefaults(defineProps<Props>(), { loading: false });

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    page: [page: number];
}>();

const tone: Record<MessageLogStatus, string> = {
    sent: 'bg-success-tint text-success-text',
    scheduled: 'bg-brand-100/70 text-brand-700',
    queued: 'bg-brand-100/70 text-brand-700',
    blocked: 'bg-danger-tint text-danger-text',
    failed: 'bg-danger-tint text-danger-text',
};

const text: Record<MessageLogStatus, string> = {
    sent: 'Sent',
    scheduled: 'Scheduled',
    queued: 'Queued',
    blocked: 'Blocked',
    failed: 'Failed',
};

const reasons: Record<string, string> = {
    no_consent: 'no consent',
    no_email: 'no email address',
    recipient_missing: 'recipient missing',
};

function reasonText(log: MessageLog): string {
    if (log.reason === null) {
        return '';
    }

    return reasons[log.reason] ?? log.reason;
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        title="Reminder Log"
        description="Every reminder written for the employees you can see, newest first. A blocked row is a reminder that was not sent because consent or an address was missing."
        class="sm:max-w-[760px]"
    >
        <div
            class="border-line/80 mt-3 overflow-x-auto rounded-lg border"
            :aria-busy="loading || undefined"
        >
            <table class="min-w-full border-collapse text-start">
                <thead class="bg-tint-header">
                    <tr
                        class="text-brand-900 text-[12px] leading-4 font-semibold"
                    >
                        <th class="px-3 py-2 text-start">Recipient</th>
                        <th class="px-2 py-2 text-start">Template</th>
                        <th class="px-2 py-2 text-start">Channel</th>
                        <th class="px-2 py-2 text-start">When</th>
                        <th class="px-2 py-2 text-start">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-surface text-ink text-[12.5px]">
                    <tr
                        v-if="logs.length === 0"
                        class="border-line/80 border-t"
                    >
                        <td
                            colspan="5"
                            class="text-ink-slate px-3 py-8 text-center"
                        >
                            No reminders yet.
                        </td>
                    </tr>
                    <tr
                        v-for="log in logs"
                        :key="log.id"
                        class="border-line/80 border-t"
                    >
                        <td class="text-brand-900 px-3 py-2 font-medium">
                            {{ log.recipient }}
                        </td>
                        <td class="text-ink-muted px-2 py-2">
                            {{ log.template }}
                            <span
                                v-if="log.automatic"
                                class="text-ink-faint block text-[11px]"
                            >
                                automatic
                            </span>
                        </td>
                        <td class="text-ink-muted px-2 py-2">
                            {{ log.channel === 'in_app' ? 'In-app' : 'Email' }}
                        </td>
                        <td class="text-ink-muted px-2 py-2 whitespace-nowrap">
                            {{ log.sentAt }}
                        </td>
                        <td class="px-2 py-2">
                            <span
                                :class="
                                    cn(
                                        'rounded-pill inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                        tone[log.status],
                                    )
                                "
                            >
                                {{ text[log.status] }}
                            </span>
                            <span
                                v-if="reasonText(log)"
                                class="text-ink-faint block text-[11px]"
                            >
                                {{ reasonText(log) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <MessagesPager
            :pagination="pagination"
            noun="reminders"
            label="Full reminder log pagination"
            class="mt-3"
            @page="emit('page', $event)"
        />
    </MessagesModal>
</template>
