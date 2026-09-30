<script setup lang="ts">
import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleMinus,
    Wand2,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { intlLocale, tk } from '@/lib/i18n';
import type { MailEncryption, MailTestResult } from '@/types';

/*
 * The last "Send test email" on Settings → Email, as icon + words (ACC-02):
 * each step of the check (server, port, login, send) with its own answer,
 * and, when another port works, a button that puts it in the form (client
 * report 2026-09-30: "sending email does not work").
 */
type Props = {
    result: MailTestResult | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    apply: [suggestion: { port: number; encryption: MailEncryption }];
}>();

const when = computed((): string => {
    const at = props.result?.at;

    return at ? new Date(at).toLocaleString(intlLocale()) : '';
});

const STEP_LABELS: Record<string, string> = {
    server: tk('Mail server'),
    port: tk('Port'),
    login: tk('Secure connection and login'),
    password: tk('Password'),
    send: tk('Send'),
};

const steps = computed(() => props.result?.steps ?? []);
</script>

<template>
    <div role="status" aria-live="polite" class="grid min-w-0 gap-3 text-sm">
        <p v-if="!result" class="text-ink-muted flex items-center gap-2">
            <CircleDashed class="size-4 shrink-0" aria-hidden="true" />
            {{ $t('No test email sent yet') }}
        </p>

        <template v-else>
            <div
                v-if="result.status === 'ok'"
                class="bg-success-tint grid min-w-0 gap-1 rounded-md p-3"
            >
                <p
                    class="text-success-text flex items-start gap-2 font-semibold"
                >
                    <CircleCheck
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 wrap-anywhere">{{
                        result.message
                    }}</span>
                </p>
                <p class="text-ink-muted ps-6 text-xs">{{ when }}</p>
            </div>

            <div
                v-else
                class="bg-danger-tint grid min-w-0 gap-1 rounded-md p-3"
            >
                <p
                    class="text-danger-text flex items-start gap-2 font-semibold"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 wrap-anywhere">{{
                        result.message
                    }}</span>
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

            <div
                v-if="result.suggestion"
                class="border-line bg-brand-50 flex min-w-0 flex-col gap-2 rounded-md border p-3 md:flex-row md:items-center md:justify-between"
            >
                <p class="text-brand-800 min-w-0 text-sm">
                    {{
                        $t(
                            'Port :port with :encryption works with this mailbox.',
                            {
                                port: result.suggestion.port,
                                encryption:
                                    result.suggestion.encryption.toUpperCase(),
                            },
                        )
                    }}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 shrink-0"
                    data-test="apply-mail-suggestion-button"
                    @click="emit('apply', result.suggestion)"
                >
                    <Wand2 class="size-4" aria-hidden="true" />
                    {{ $t('Use it') }}
                </Button>
            </div>

            <ol v-if="steps.length" class="grid min-w-0 gap-2">
                <li
                    v-for="step in steps"
                    :key="step.key"
                    class="flex min-w-0 items-start gap-2"
                >
                    <CircleCheck
                        v-if="step.status === 'ok'"
                        class="text-success mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <CircleAlert
                        v-else-if="step.status === 'failed'"
                        class="text-danger mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <CircleMinus
                        v-else
                        class="text-ink-faint mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span class="text-ink font-semibold">
                            {{ $t(STEP_LABELS[step.key] ?? step.key) }}:
                        </span>
                        <span class="text-ink-muted wrap-anywhere">
                            {{ step.message }}
                        </span>
                    </span>
                </li>
            </ol>
        </template>
    </div>
</template>
