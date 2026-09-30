<script setup lang="ts">
import { computed } from 'vue';
import { tk } from '@/lib/i18n';
import type { LandingPaymentMethod } from '@/types';
import CopyButton from './CopyButton.vue';
import { methodIcon, methodTone } from './methodIcon';

/*
 * "View payment details" (client payment-flow reference, step 3): where to
 * send the money, how much, what to write in the note, and the method's own
 * instructions as numbered steps. Every value the customer types into their
 * banking app has a copy button.
 */
type Props = {
    method: LandingPaymentMethod;
    methodIndex: number;
    /** "12 000 DZD" */
    amount: string;
    /** The bare number to paste into a banking app: "12000". */
    amountValue: string;
    suggestedReference: string;
};

const props = defineProps<Props>();

const fallbackSteps = [
    tk('Send the amount shown above to this account.'),
    tk('Add the suggested reference to the payment note.'),
    tk('Keep the receipt or take a screenshot of the payment.'),
    tk('Upload it below, or type the transaction number.'),
];

// The admin writes instructions one per line, sometimes numbered already.
const steps = computed((): { text: string; translate: boolean }[] => {
    const lines = (props.method.instructions ?? '')
        .split(/\r?\n/)
        .map((line) => line.replace(/^\s*(\d+[.)-]|[-•*])\s*/, '').trim())
        .filter((line) => line !== '');

    return lines.length
        ? lines.map((text) => ({ text, translate: false }))
        : fallbackSteps.map((text) => ({ text, translate: true }));
});
</script>

<template>
    <div class="border-line bg-app-alt overflow-hidden rounded-lg border">
        <div
            class="border-line bg-surface flex items-center gap-3 border-b px-4 py-3.5 sm:px-5"
        >
            <span
                :class="methodTone(methodIndex)"
                class="grid size-10 shrink-0 place-items-center rounded-md"
                aria-hidden="true"
            >
                <component :is="methodIcon(method.name)" class="size-5" />
            </span>
            <h3
                class="font-heading text-brand-900 min-w-0 text-[16px] font-semibold break-words"
            >
                {{ $t('Pay with :method', { method: method.name }) }}
            </h3>
        </div>

        <div class="grid gap-4 p-4 sm:p-5">
            <div
                class="bg-brand-600 text-surface shadow-btn flex flex-wrap items-center justify-between gap-3 rounded-lg px-4 py-3.5"
            >
                <div class="min-w-0">
                    <p class="text-brand-100 text-[12px] font-semibold">
                        {{ $t('Amount to pay') }}
                    </p>
                    <p
                        class="font-heading mt-0.5 text-[24px] leading-tight font-bold tracking-[-0.02em] sm:text-[26px]"
                        dir="ltr"
                    >
                        {{ amount }}
                    </p>
                </div>
                <CopyButton
                    :value="amountValue"
                    :label="$t('Copy the amount')"
                />
            </div>

            <dl class="grid gap-3">
                <div
                    v-if="method.recipientName"
                    class="border-line bg-surface rounded-md border px-4 py-3"
                >
                    <dt class="text-ink-muted text-[12px] font-medium">
                        {{ $t('Recipient name') }}
                    </dt>
                    <dd
                        class="text-ink mt-0.5 text-[15px] font-semibold break-words"
                    >
                        {{ method.recipientName }}
                    </dd>
                </div>
                <div class="border-line bg-surface rounded-md border px-4 py-3">
                    <dt class="text-ink-muted text-[12px] font-medium">
                        {{ $t('Account / reference') }}
                    </dt>
                    <dd class="mt-0.5 flex items-center justify-between gap-3">
                        <span
                            class="text-ink min-w-0 text-[16px] font-semibold tracking-[0.04em] break-all tabular-nums"
                            dir="ltr"
                        >
                            {{ method.accountReference }}
                        </span>
                        <CopyButton
                            :value="method.accountReference"
                            :label="$t('Copy the account number')"
                        />
                    </dd>
                </div>
                <div
                    v-if="suggestedReference"
                    class="border-line bg-surface rounded-md border px-4 py-3"
                >
                    <dt class="text-ink-muted text-[12px] font-medium">
                        {{ $t('Payment note / reference to add') }}
                    </dt>
                    <dd class="mt-0.5 flex items-center justify-between gap-3">
                        <span
                            class="text-brand-700 min-w-0 text-[15px] font-semibold break-all"
                            dir="ltr"
                        >
                            {{ suggestedReference }}
                        </span>
                        <CopyButton
                            :value="suggestedReference"
                            :label="$t('Copy the payment note')"
                        />
                    </dd>
                </div>
            </dl>

            <div>
                <h4 class="text-ink-indigo text-[13px] font-semibold">
                    {{ $t('Instructions') }}
                </h4>
                <ol class="mt-3 grid gap-2.5">
                    <li
                        v-for="(step, index) in steps"
                        :key="index"
                        class="text-ink flex items-start gap-3 text-[14px] leading-6"
                    >
                        <span
                            class="bg-brand-100 text-brand-700 font-heading mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-[12px] font-bold"
                            aria-hidden="true"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="min-w-0 break-words">
                            {{ step.translate ? $t(step.text) : step.text }}
                        </span>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>
