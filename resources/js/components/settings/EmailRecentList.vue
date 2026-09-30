<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    FileText,
    Hourglass,
    Send,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { intlLocale } from '@/lib/i18n';
import { flush } from '@/routes/mail-settings';
import type { MailLogRow, MailQueueCounts } from '@/types';

/*
 * Settings → Email's "Recent emails" (client report 2026-09-30: "sending
 * email does not work"): what happened to each email, in words and an icon
 * (ACC-02), and the emails still waiting in the queue with a button that
 * sends them now.
 */
type Props = {
    rows: MailLogRow[];
    queue: MailQueueCounts;
};

const props = defineProps<Props>();

const sending = ref(false);

function sendWaiting(): void {
    sending.value = true;
    router.post(
        flush.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function when(at: string | null): string {
    return at ? new Date(at).toLocaleString(intlLocale()) : '';
}

const waiting = (): number => props.queue.waiting + props.queue.failed;
</script>

<template>
    <div class="grid min-w-0 gap-4 text-sm">
        <div
            v-if="waiting() > 0"
            class="border-warning/40 bg-warning-tint flex min-w-0 flex-col gap-3 rounded-md border p-3 md:flex-row md:items-center md:justify-between"
        >
            <p class="text-warning-text flex min-w-0 items-start gap-2">
                <Hourglass class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span class="min-w-0">
                    {{
                        $t(
                            ':count emails have not left the server yet (waiting in the queue, or failed before). Send them now with the saved settings.',
                            { count: waiting() },
                        )
                    }}
                </span>
            </p>
            <Button
                type="button"
                class="min-h-11 shrink-0"
                :disabled="sending"
                data-test="send-waiting-emails-button"
                @click="sendWaiting"
            >
                <Send class="size-4" aria-hidden="true" />
                {{ sending ? $t('Sending…') : $t('Send waiting emails now') }}
            </Button>
        </div>

        <p v-if="!rows.length" class="text-ink-muted flex items-center gap-2">
            <CircleDashed class="size-4 shrink-0" aria-hidden="true" />
            {{ $t('No email sent yet') }}
        </p>

        <ul v-else class="divide-line grid min-w-0 divide-y">
            <li
                v-for="row in rows"
                :key="row.id"
                class="flex min-w-0 items-start gap-3 py-2.5 first:pt-0 last:pb-0"
            >
                <CircleCheck
                    v-if="row.status === 'sent'"
                    class="text-success mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <FileText
                    v-else-if="row.status === 'logged'"
                    class="text-ink-faint mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <CircleAlert
                    v-else
                    class="text-danger mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <div class="grid min-w-0 flex-1 gap-0.5">
                    <p class="text-ink min-w-0 font-semibold wrap-anywhere">
                        {{ row.subject || $t('(no subject)') }}
                    </p>
                    <p class="text-ink-muted min-w-0 text-xs wrap-anywhere">
                        <span
                            :class="
                                row.status === 'failed'
                                    ? 'text-danger-text font-semibold'
                                    : row.status === 'sent'
                                      ? 'text-success-text font-semibold'
                                      : 'font-semibold'
                            "
                        >
                            {{
                                row.status === 'sent'
                                    ? $t('Sent')
                                    : row.status === 'logged'
                                      ? $t('Written to the log only')
                                      : $t('Failed')
                            }}
                        </span>
                        <template v-if="row.to">
                            · <span dir="ltr">{{ row.to }}</span>
                        </template>
                        · {{ when(row.at) }}
                    </p>
                    <p
                        v-if="row.error"
                        class="text-danger-text min-w-0 text-xs wrap-anywhere"
                    >
                        {{ row.error }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
