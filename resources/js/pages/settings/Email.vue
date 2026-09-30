<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FileText,
    KeyRound,
    Mail,
    Send,
    ServerCog,
    UserRound,
    Zap,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import EmailField from '@/components/settings/EmailField.vue';
import EmailModeOption from '@/components/settings/EmailModeOption.vue';
import EmailSettingsCard from '@/components/settings/EmailSettingsCard.vue';
import EmailTestResult from '@/components/settings/EmailTestResult.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import { edit, test, update } from '@/routes/mail-settings';
import type {
    MailEncryption,
    MailSettingsPayload,
    MailSettingsValues,
} from '@/types';

/*
 * Settings → Email (client request 2026-09-29; Super Admin only): the
 * mailbox every GHASIDO email is sent from (contact@ghasido.com on
 * Hostinger), real sending or log only, "send immediately" for a host with
 * no queue worker, and a test email that reports the server's answer.
 * The password is write-only: blank keeps the saved one (SEC-03).
 */
type Props = {
    settings: MailSettingsPayload;
    defaultTestTo: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('Email'), href: edit() }],
    },
});

type Form = MailSettingsValues & { password: string };

const form = reactive<Form>({ ...props.settings.values, password: '' });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const testing = ref(false);
const testTo = ref(props.defaultTestTo);
const testError = ref('');

watch(
    () => props.settings.values,
    (values) => Object.assign(form, values, { password: '' }),
);

const smtp = computed(() => form.mailer === 'smtp');

// The usual port for each encryption; changing one suggests the other.
const PORTS: Record<MailEncryption, number> = { ssl: 465, tls: 587, none: 25 };

const encryption = computed({
    get: (): MailEncryption => form.encryption,
    set: (value: MailEncryption) => {
        if (Number(form.port) === PORTS[form.encryption]) {
            form.port = PORTS[value];
        }

        form.encryption = value;
    },
});

const encryptionOptions: { value: MailEncryption; label: string }[] = [
    { value: 'ssl', label: tk('SSL (port 465)') },
    { value: 'tls', label: tk('TLS / STARTTLS (port 587)') },
    { value: 'none', label: tk('None (not recommended)') },
];

const portText = computed({
    get: (): string => String(form.port ?? ''),
    set: (value: string) => {
        form.port = Number(value.replace(/\D/g, '')) || 0;
    },
});

const sendImmediately = computed({
    get: (): boolean => form.sendImmediately,
    set: (value: boolean | 'indeterminate') => {
        form.sendImmediately = value === true;
    },
});

function save(): void {
    saving.value = true;
    router.patch(update.url(), form, {
        preserveScroll: true,
        onError: (bag) => {
            errors.value = bag;
        },
        onSuccess: () => {
            errors.value = {};
            form.password = '';
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function sendTest(): void {
    testing.value = true;
    router.post(
        test.url(),
        { to: testTo.value },
        {
            preserveScroll: true,
            onError: (bag) => {
                testError.value = bag.to ?? '';
            },
            onSuccess: () => {
                testError.value = '';
            },
            onFinish: () => {
                testing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Email')" />

    <h1 class="sr-only">{{ $t('Email') }}</h1>

    <div class="flex min-w-0 flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Email')"
            :description="
                $t(
                    'The mailbox every GHASIDO email is sent from: approvals, payments, reminders and password resets.',
                )
            "
        />

        <p
            v-if="!settings.stored"
            class="border-line bg-brand-50 text-brand-800 rounded-md border p-3 text-sm"
            role="note"
        >
            {{
                $t(
                    'Nothing is saved yet, so the server file (.env) decides: :mailer. Save this page to send from your own mailbox.',
                    {
                        mailer:
                            settings.env.mailer === 'smtp'
                                ? $t('SMTP (:host)', {
                                      host: settings.env.host,
                                  })
                                : $t('log only, nothing is sent'),
                    },
                )
            }}
        </p>

        <form class="grid min-w-0 gap-4" novalidate @submit.prevent="save">
            <EmailSettingsCard
                :icon="Send"
                :title="$t('How emails are sent')"
                :description="
                    $t(
                        'Log only writes emails to the server log instead of sending them, for testing.',
                    )
                "
            >
                <div
                    class="grid min-w-0 gap-3 md:grid-cols-2"
                    role="radiogroup"
                    :aria-label="$t('How emails are sent')"
                >
                    <EmailModeOption
                        v-model="form.mailer"
                        name="mailer"
                        value="smtp"
                        :icon="Mail"
                        :title="$t('Send real emails (SMTP)')"
                        :description="
                            $t(
                                'Delivered to Gmail, Outlook, Hotmail and others.',
                            )
                        "
                    />
                    <EmailModeOption
                        v-model="form.mailer"
                        name="mailer"
                        value="log"
                        :icon="FileText"
                        :title="$t('Log only')"
                        :description="
                            $t('Nothing leaves the server. For testing.')
                        "
                    />
                </div>

                <div
                    class="border-line flex min-w-0 items-start gap-3 border-t pt-4"
                >
                    <Checkbox
                        id="mail-send-immediately"
                        v-model="sendImmediately"
                        class="mt-0.5 size-5"
                        data-test="mail-send-immediately-checkbox"
                    />
                    <div class="grid min-w-0 gap-0.5">
                        <Label
                            for="mail-send-immediately"
                            class="text-ink flex items-center gap-1.5 text-sm font-semibold"
                        >
                            <Zap
                                class="text-brand-600 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {{
                                $t(
                                    'Send emails immediately (no background worker needed)',
                                )
                            }}
                        </Label>
                        <p class="text-ink-muted text-xs">
                            {{
                                $t(
                                    'Keep this on unless a cron job runs the queue worker on the server. AI and audio jobs stay in the background either way.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </EmailSettingsCard>

            <EmailSettingsCard
                v-if="smtp"
                :icon="ServerCog"
                :title="$t('Mail server (SMTP)')"
                :description="
                    $t(
                        'For Hostinger: smtp.hostinger.com, SSL on port 465, and your full email address as the username.',
                    )
                "
            >
                <div class="grid min-w-0 gap-4 md:grid-cols-[1fr_8rem]">
                    <EmailField
                        v-model="form.host"
                        :label="$t('SMTP host')"
                        placeholder="smtp.hostinger.com"
                        :error="errors.host"
                        data-test="mail-host-input"
                    />
                    <EmailField
                        v-model="portText"
                        :label="$t('Port')"
                        inputmode="numeric"
                        :error="errors.port"
                        data-test="mail-port-input"
                    />
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label
                        for="mail-encryption"
                        class="text-brand-900 text-xs font-semibold"
                    >
                        {{ $t('Encryption') }}
                    </Label>
                    <Select v-model="encryption">
                        <SelectTrigger
                            id="mail-encryption"
                            class="border-line h-11 w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in encryptionOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ $t(option.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="errors.encryption"
                        class="text-danger-text text-xs"
                        role="alert"
                    >
                        {{ errors.encryption }}
                    </p>
                </div>

                <div class="grid min-w-0 gap-4 md:grid-cols-2">
                    <EmailField
                        v-model="form.username"
                        :label="$t('Username')"
                        inputmode="email"
                        autocomplete="off"
                        placeholder="contact@ghasido.com"
                        :error="errors.username"
                        data-test="mail-username-input"
                    />
                    <EmailField
                        v-model="form.password"
                        type="password"
                        :label="$t('Password')"
                        autocomplete="new-password"
                        :placeholder="
                            settings.hasPassword
                                ? $t('Leave blank to keep the current password')
                                : $t('The mailbox password')
                        "
                        :hint="
                            settings.hasPassword
                                ? $t('A password is saved (encrypted).')
                                : undefined
                        "
                        :error="errors.password"
                        data-test="mail-password-input"
                    />
                </div>
                <p
                    v-if="settings.hasPassword"
                    class="text-success-text flex items-center gap-1.5 text-xs font-semibold"
                >
                    <KeyRound class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ $t('Saved password in use') }}
                </p>
            </EmailSettingsCard>

            <EmailSettingsCard
                :icon="UserRound"
                :title="$t('Sender')"
                :description="
                    $t(
                        'Who the emails come from. Use the same address as the SMTP username so Gmail and Outlook trust them.',
                    )
                "
            >
                <div class="grid min-w-0 gap-4 md:grid-cols-2">
                    <EmailField
                        v-model="form.fromAddress"
                        :label="$t('From address')"
                        inputmode="email"
                        placeholder="contact@ghasido.com"
                        :error="errors.fromAddress"
                        data-test="mail-from-address-input"
                    />
                    <EmailField
                        v-model="form.fromName"
                        :label="$t('From name')"
                        dir="auto"
                        placeholder="GHASIDO"
                        :error="errors.fromName"
                        data-test="mail-from-name-input"
                    />
                </div>
                <EmailField
                    v-model="form.replyTo"
                    :label="$t('Reply-To address (optional)')"
                    inputmode="email"
                    :hint="
                        $t(
                            'Where replies go when it is not the From address. Also shown in the footer of every email.',
                        )
                    "
                    :error="errors.replyTo"
                    data-test="mail-reply-to-input"
                />
            </EmailSettingsCard>

            <div class="flex flex-wrap items-center gap-4">
                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="saving"
                    data-test="update-mail-settings-button"
                >
                    {{ saving ? $t('Saving…') : $t('Save') }}
                </Button>
                <p class="text-ink-muted text-xs">
                    {{ $t('New emails use the saved settings at once.') }}
                </p>
            </div>
        </form>

        <EmailSettingsCard
            :icon="Mail"
            :title="$t('Send a test email')"
            :description="
                $t(
                    'Sends one email with the saved settings and shows the mail server\'s answer. Save your changes first.',
                )
            "
        >
            <form
                class="flex min-w-0 flex-col gap-3 md:flex-row md:items-start"
                novalidate
                @submit.prevent="sendTest"
            >
                <div class="min-w-0 flex-1">
                    <EmailField
                        v-model="testTo"
                        :label="$t('Send to')"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="name@gmail.com"
                        :error="testError"
                        data-test="mail-test-to-input"
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    class="min-h-11 shrink-0 md:mt-5.5"
                    :disabled="testing || testTo.trim() === ''"
                    data-test="send-test-email-button"
                >
                    <Send class="size-4" aria-hidden="true" />
                    {{ testing ? $t('Sending…') : $t('Send test email') }}
                </Button>
            </form>

            <EmailTestResult :result="settings.lastTest" />
        </EmailSettingsCard>
    </div>
</template>
