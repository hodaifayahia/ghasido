<script setup lang="ts">
import { BadgeCheck, ReceiptText, Send } from '@lucide/vue';
import { computed } from 'vue';
import { tk } from '@/lib/i18n';

/*
 * "What happens next" beside the checkout: the manual payment flow in
 * three short lines, so nobody wonders why the account is not live yet.
 */
type Props = {
    isIndividual: boolean;
    acceptsPayment: boolean;
};

const props = defineProps<Props>();

// The receipt step is always part of the checkout; only the first line
// depends on whether a payment method is set up to pick from.
const steps = computed(() => [
    {
        icon: Send,
        text: props.acceptsPayment
            ? tk('Send the payment with the method you pick.')
            : tk('Send the payment, then keep the receipt.'),
    },
    {
        icon: ReceiptText,
        text: tk('Upload the receipt or type the transaction number.'),
    },
    {
        icon: BadgeCheck,
        text: props.isIndividual
            ? tk(
                  'We check the payment and activate your account. You get an email when it is ready.',
              )
            : tk(
                  'We check the payment and activate the hotel. The manager gets an email when it is ready.',
              ),
    },
]);
</script>

<template>
    <section
        class="border-line bg-surface shadow-card rounded-xl border p-5"
        aria-labelledby="next-steps-title"
    >
        <h2
            id="next-steps-title"
            class="font-heading text-brand-900 text-[16px] font-semibold"
        >
            {{ $t('What happens next') }}
        </h2>
        <ol class="mt-4 grid gap-3.5">
            <li
                v-for="(step, index) in steps"
                :key="index"
                class="flex items-start gap-3"
            >
                <span
                    class="bg-brand-50 text-brand-600 grid size-9 shrink-0 place-items-center rounded-md"
                    aria-hidden="true"
                >
                    <component :is="step.icon" class="size-4.5" />
                </span>
                <p class="text-ink-slate pt-1.5 text-[13px] leading-5">
                    {{ $t(step.text) }}
                </p>
            </li>
        </ol>
    </section>
</template>
