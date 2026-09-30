<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import MessagesDeleteDialog from '@/components/messages/MessagesDeleteDialog.vue';
import MessagesModal from '@/components/messages/MessagesModal.vue';
import MessagesPager from '@/components/messages/MessagesPager.vue';
import { messagesListDeleteButton as deleteButton } from '@/components/messages/messagesListStyles';
import { useI18n } from '@/composables/useI18n';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/messages-reminders/log';
import type { MessageLog, MessageLogStatus, MessagePagination } from '@/types';

type Props = {
    logs: MessageLog[];
    pagination: MessagePagination;
    loading?: boolean;
    /** May delete log rows (ReminderPolicy::delete). */
    canDelete?: boolean;
};

withDefaults(defineProps<Props>(), { loading: false, canDelete: false });

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

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
    sent: tk('Sent'),
    scheduled: tk('Scheduled'),
    queued: tk('Queued'),
    blocked: tk('Blocked'),
    failed: tk('Failed'),
};

const reasons: Record<string, string> = {
    no_consent: tk('no consent'),
    no_email: tk('no email address'),
    recipient_missing: tk('recipient missing'),
};

function reasonText(log: MessageLog): string {
    if (log.reason === null) {
        return '';
    }

    const reason = reasons[log.reason];

    return reason === undefined ? log.reason : t(reason);
}

function channelText(log: MessageLog): string {
    return log.channel === 'in_app' ? t('In-app') : t('Email');
}

// ---------------------------------------------------------------- delete
//
// Deletes only this row of the log: the template, the rule and the
// employee stay as they are (REM-06).

const deleting = ref<MessageLog | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);

function askDelete(log: MessageLog): void {
    deleting.value = log;
    confirmOpen.value = true;
}

function confirmDelete(): void {
    const log = deleting.value;

    if (log === null) {
        return;
    }

    router.delete(destroy.url(log.id), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <MessagesModal
        v-model:open="open"
        list
        :title="$t('Reminder Log')"
        :description="
            $t(
                'Every reminder written for the employees you can see, newest first. A blocked row is a reminder that was not sent because consent or an address was missing.',
            )
        "
        class="sm:max-w-[820px]"
    >
        <div :aria-busy="loading || undefined" data-test="reminder-log-list">
            <!-- Phone: one card per reminder, never a sideways table (RESP-01). -->
            <ul class="flex flex-col gap-2 md:hidden">
                <li
                    v-if="logs.length === 0"
                    class="text-ink-muted rounded-md border border-dashed px-3 py-8 text-center text-[12.5px]"
                >
                    {{ $t('No reminders yet.') }}
                </li>
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="border-line/75 bg-surface rounded-md border px-3 py-2.5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p
                                class="text-brand-900 truncate text-[13px] font-semibold"
                            >
                                {{ log.recipient }}
                            </p>
                            <p class="text-ink-slate text-[12px] leading-4.5">
                                {{ log.template }}
                                <template v-if="log.automatic">
                                    · {{ $t('automatic') }}
                                </template>
                            </p>
                        </div>
                        <button
                            v-if="canDelete"
                            type="button"
                            :class="deleteButton"
                            :aria-label="
                                $t('Delete the reminder to :name', {
                                    name: log.recipient,
                                })
                            "
                            :data-test="`delete-log-${log.id}-button`"
                            @click="askDelete(log)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                    <div
                        class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11.5px]"
                    >
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                    tone[log.status],
                                )
                            "
                        >
                            {{ $t(text[log.status]) }}
                        </span>
                        <span class="text-ink-muted">{{
                            channelText(log)
                        }}</span>
                        <span class="text-ink-faint">{{ log.sentAt }}</span>
                    </div>
                    <p
                        v-if="reasonText(log)"
                        class="text-ink-faint mt-0.5 text-[11px]"
                    >
                        {{ reasonText(log) }}
                    </p>
                </li>
            </ul>

            <div class="border-line/80 hidden rounded-lg border md:block">
                <table class="min-w-full border-collapse text-start">
                    <thead class="bg-tint-header sticky top-0 z-10">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="px-3 py-2 text-start">
                                {{ $t('Recipient') }}
                            </th>
                            <th class="px-2 py-2 text-start">
                                {{ $t('Template') }}
                            </th>
                            <th class="px-2 py-2 text-start">
                                {{ $t('Channel') }}
                            </th>
                            <th class="px-2 py-2 text-start">
                                {{ $t('When') }}
                            </th>
                            <th class="px-2 py-2 text-start">
                                {{ $t('Status') }}
                            </th>
                            <th v-if="canDelete" class="w-12 px-2 py-2">
                                <span class="sr-only">{{ $t('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <tr
                            v-if="logs.length === 0"
                            class="border-line/80 border-t"
                        >
                            <td
                                :colspan="canDelete ? 6 : 5"
                                class="text-ink-slate px-3 py-8 text-center"
                            >
                                {{ $t('No reminders yet.') }}
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
                                    {{ $t('automatic') }}
                                </span>
                            </td>
                            <td class="text-ink-muted px-2 py-2">
                                {{ channelText(log) }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-2 whitespace-nowrap"
                            >
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
                                    {{ $t(text[log.status]) }}
                                </span>
                                <span
                                    v-if="reasonText(log)"
                                    class="text-ink-faint block text-[11px]"
                                >
                                    {{ reasonText(log) }}
                                </span>
                            </td>
                            <td v-if="canDelete" class="px-2 py-2 text-end">
                                <button
                                    type="button"
                                    :class="deleteButton"
                                    :aria-label="
                                        $t('Delete the reminder to :name', {
                                            name: log.recipient,
                                        })
                                    "
                                    :data-test="`delete-log-${log.id}-button`"
                                    @click="askDelete(log)"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <template #footer>
            <MessagesPager
                v-if="pagination.total > 0"
                :pagination="pagination"
                noun="reminders"
                :label="$t('Full reminder log pagination')"
                class="shrink-0"
                @page="emit('page', $event)"
            />
        </template>
    </MessagesModal>

    <MessagesDeleteDialog
        v-model:open="confirmOpen"
        :title="
            $t('Delete the reminder to :name?', {
                name: deleting?.recipient ?? '',
            })
        "
        :description="
            $t(
                'Only this log entry is deleted. The template, the rule and the employee are not changed. A reminder still waiting to be sent is cancelled.',
            )
        "
        :processing="processing"
        confirm-test="confirm-delete-log-button"
        @confirm="confirmDelete"
    />
</template>
