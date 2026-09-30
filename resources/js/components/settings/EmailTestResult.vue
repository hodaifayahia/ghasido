<script setup lang="ts">
import { CircleAlert, CircleCheck, CircleDashed } from '@lucide/vue';
import { computed } from 'vue';
import { intlLocale } from '@/lib/i18n';
import type { MailTestResult } from '@/types';

/*
 * The last "Send test email" on Settings → Email, as icon + words (ACC-02);
 * a failure shows the plain explanation and the mail server's own answer.
 */
type Props = {
    result: MailTestResult | null;
};

const props = defineProps<Props>();

const when = computed((): string => {
    const at = props.result?.at;

    return at ? new Date(at).toLocaleString(intlLocale()) : '';
});
</script>

<template>
    <div role="status" aria-live="polite" class="min-w-0 text-sm">
        <p v-if="!result" class="text-ink-muted flex items-center gap-2">
            <CircleDashed class="size-4 shrink-0" aria-hidden="true" />
            {{ $t('No test email sent yet') }}
        </p>

        <div
            v-else-if="result.status === 'ok'"
            class="bg-success-tint grid min-w-0 gap-1 rounded-md p-3"
        >
            <p class="text-success-text flex items-start gap-2 font-semibold">
                <CircleCheck
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 wrap-anywhere">{{ result.message }}</span>
            </p>
            <p class="text-ink-muted ps-6 text-xs">{{ when }}</p>
        </div>

        <div v-else class="bg-danger-tint grid min-w-0 gap-1 rounded-md p-3">
            <p class="text-danger-text flex items-start gap-2 font-semibold">
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 wrap-anywhere">{{ result.message }}</span>
            </p>
            <p
                v-if="result.detail"
                class="text-ink-muted ps-6 font-mono text-xs wrap-anywhere"
                dir="ltr"
            >
                {{ result.detail }}
            </p>
            <p class="text-ink-muted ps-6 text-xs">
                {{ $t('Sent to :to', { to: result.to }) }} · {{ when }}
            </p>
        </div>
    </div>
</template>
