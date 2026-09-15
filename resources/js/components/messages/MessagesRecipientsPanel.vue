<script setup lang="ts">
import { Mail, Search } from '@lucide/vue';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { notifyComingSoon } from '@/lib/comingSoon';
import { cn } from '@/lib/utils';
import type {
    MessageConsentStatus,
    MessageRecipient,
    MessageRecipientStatus,
} from '@/types';

type Props = {
    recipients: MessageRecipient[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const search = ref('');

const consentTone: Record<MessageConsentStatus, string> = {
    granted: 'bg-success-tint text-success-text',
    not_granted: 'bg-danger-tint text-danger-text',
};

const consentText: Record<MessageConsentStatus, string> = {
    granted: 'Granted',
    not_granted: 'Missing',
};

const statusTone: Record<MessageRecipientStatus, string> = {
    active: 'bg-success-tint text-success-text',
    in_progress: 'bg-warning-tint text-warning-text',
    inactive: 'bg-danger-tint text-danger-text',
    consent_pending: 'bg-brand-100/70 text-brand-700',
};

const statusText: Record<MessageRecipientStatus, string> = {
    active: 'Active',
    in_progress: 'In progress',
    inactive: 'Inactive',
    consent_pending: 'Consent pending',
};
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card rounded-lg border p-3',
                props.class,
            )
        "
    >
        <div class="flex min-h-8 items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    Reminder Recipients
                </h2>
                <p class="text-ink-slate mt-0.5 text-[12px] leading-4">
                    Review consent, inactivity and send status before messaging.
                </p>
            </div>
        </div>

        <div class="relative mt-3 min-w-0">
            <Search
                aria-hidden="true"
                class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search by employee, hotel or department..."
                class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
            />
        </div>

        <div class="border-line/80 mt-3 overflow-hidden rounded-lg border">
            <div class="hidden overflow-x-auto md:block">
                <table
                    class="min-w-full table-fixed border-collapse text-start"
                >
                    <thead class="bg-tint-header">
                        <tr
                            class="text-brand-900 text-[12px] leading-4 font-semibold"
                        >
                            <th class="w-9 py-2 ps-3 pe-2 text-start">
                                <Checkbox :model-value="false" />
                            </th>
                            <th class="w-[168px] px-2 py-2 text-start">
                                Employee
                            </th>
                            <th class="w-[126px] px-2 py-2 text-start">
                                Hotel
                            </th>
                            <th class="w-[100px] px-2 py-2 text-start">
                                Department
                            </th>
                            <th class="w-[104px] px-2 py-2 text-start">
                                Last Activity
                            </th>
                            <th class="w-[94px] px-2 py-2 text-start">
                                Consent
                            </th>
                            <th class="w-[112px] px-2 py-2 text-start">
                                Status
                            </th>
                            <th class="w-[112px] px-2 py-2 text-start">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface text-ink text-[12.5px]">
                        <tr
                            v-for="recipient in recipients"
                            :key="recipient.id"
                            class="border-line/80 hover:bg-brand-50/35 border-t"
                        >
                            <td class="py-[7px] ps-3 pe-2 align-middle">
                                <Checkbox :model-value="false" />
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <div class="min-w-0">
                                    <span
                                        class="text-brand-900 block truncate font-medium"
                                    >
                                        {{ recipient.name }}
                                    </span>
                                    <span
                                        class="text-ink-slate block truncate text-[11.5px]"
                                    >
                                        {{ recipient.inactivityLabel }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.hotel }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.department }}
                            </td>
                            <td
                                class="text-ink-muted px-2 py-[7px] align-middle"
                            >
                                {{ recipient.lastActivity }}
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[72px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            consentTone[
                                                recipient.consentStatus
                                            ],
                                        )
                                    "
                                >
                                    {{ consentText[recipient.consentStatus] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <span
                                    :class="
                                        cn(
                                            'rounded-pill inline-flex min-h-5 min-w-[90px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                            statusTone[recipient.status],
                                        )
                                    "
                                >
                                    {{ statusText[recipient.status] }}
                                </span>
                            </td>
                            <td class="px-2 py-[7px] align-middle">
                                <button
                                    type="button"
                                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface inline-flex h-8 min-w-[96px] items-center justify-center gap-1.5 rounded-md border px-3 text-[11.5px] font-semibold"
                                    :aria-label="`Send reminder to ${recipient.name}`"
                                    @click="
                                        notifyComingSoon(
                                            `${recipient.name} reminder`,
                                        )
                                    "
                                >
                                    <Mail class="size-3.5" aria-hidden="true" />
                                    Send
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-line divide-y md:hidden">
                <li
                    v-for="recipient in recipients"
                    :key="recipient.id"
                    class="bg-surface p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="font-heading text-brand-800 text-[15px] leading-5 font-semibold"
                            >
                                {{ recipient.name }}
                            </p>
                            <p
                                class="text-ink-muted mt-0.5 text-[13px] leading-5"
                            >
                                {{ recipient.hotel }}
                            </p>
                        </div>
                        <Checkbox :model-value="false" />
                    </div>

                    <div
                        class="text-ink-muted mt-3 grid gap-2 text-[13px] leading-5"
                    >
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Department:</span
                            >
                            {{ recipient.department }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Last Activity:</span
                            >
                            {{ recipient.lastActivity }}
                        </p>
                        <p>
                            <span class="text-brand-900 font-medium"
                                >Inactivity:</span
                            >
                            {{ recipient.inactivityLabel }}
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                    consentTone[recipient.consentStatus],
                                )
                            "
                        >
                            Consent {{ consentText[recipient.consentStatus] }}
                        </span>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-6 items-center px-2.5 text-[11px] font-semibold',
                                    statusTone[recipient.status],
                                )
                            "
                        >
                            {{ statusText[recipient.status] }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border text-[12.5px] font-semibold"
                        :aria-label="`Send reminder to ${recipient.name}`"
                        @click="notifyComingSoon(`${recipient.name} reminder`)"
                    >
                        <Mail class="size-4" aria-hidden="true" />
                        Send Reminder
                    </button>
                </li>
            </ul>
        </div>
    </section>
</template>
