<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, CircleX, Hourglass, LogIn } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { intlLocale, t } from '@/lib/i18n';
import { home, login } from '@/routes';
import type {
    CheckoutSubmittedPayment,
    PaymentSubmissionStatus,
} from '@/types';
import { formatPrice } from './money';

/*
 * "Payment submitted" (client payment-flow reference, step 5): what was
 * sent and where it stands. Pending waits for the Super Admin; confirmed
 * and rejected show the outcome when the customer comes back to the link.
 */
type Props = {
    payment: CheckoutSubmittedPayment;
};

const props = defineProps<Props>();

type Tone = {
    icon: Component;
    chip: string;
    ring: string;
    pill: string;
};

const tones: Record<PaymentSubmissionStatus, Tone> = {
    pending: {
        icon: Hourglass,
        chip: 'bg-warning-tint text-warning',
        ring: 'ring-warning-tint/60',
        pill: 'bg-warning-tint text-warning-text',
    },
    confirmed: {
        icon: CircleCheck,
        chip: 'bg-success-tint text-success',
        ring: 'ring-success-tint/60',
        pill: 'bg-success-tint text-success-text',
    },
    rejected: {
        icon: CircleX,
        chip: 'bg-danger-tint text-danger',
        ring: 'ring-danger-tint/60',
        pill: 'bg-danger-tint text-danger-text',
    },
};

const tone = computed(() => tones[props.payment.status]);

const title = computed(() => {
    switch (props.payment.status) {
        case 'confirmed':
            return t('Payment confirmed');
        case 'rejected':
            return t('Payment not accepted');
        default:
            return t('Payment submitted');
    }
});

const message = computed(() => {
    const individual = props.payment.isIndividual;

    switch (props.payment.status) {
        case 'confirmed':
            return individual
                ? t(
                      'Your account is active. Sign in with the username and password you chose.',
                  )
                : t(
                      'The hotel is active. The manager can sign in with the username and password chosen at checkout.',
                  );
        case 'rejected':
            return t(
                'We could not confirm this payment. We have emailed the reason and what to do next.',
            );
        default:
            return individual
                ? t(
                      'Thank you! We will review your payment and activate your account soon. You will get an email when it is ready.',
                  )
                : t(
                      'Thank you! We will review your payment and activate the hotel’s subscription soon. The manager will get an email when it is ready.',
                  );
    }
});

const submittedAt = computed(() => {
    if (!props.payment.submittedAt) {
        return '—';
    }

    return new Intl.DateTimeFormat(intlLocale(), {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.payment.submittedAt));
});

const rows = computed((): { label: string; value: string; ltr?: boolean }[] => [
    { label: t('Plan'), value: props.payment.planName },
    {
        label: t('Amount'),
        value: formatPrice(props.payment.amount, props.payment.currency),
        ltr: true,
    },
    { label: t('Payment method'), value: props.payment.method },
    {
        label: t('Transaction reference'),
        value: props.payment.reference ?? '—',
        ltr: props.payment.reference !== null,
    },
    {
        label: t('Receipt'),
        value: props.payment.hasReceipt ? t('Uploaded') : t('Not uploaded'),
    },
    { label: t('Submitted'), value: submittedAt.value },
]);
</script>

<template>
    <section
        class="border-line bg-surface shadow-card motion-safe:animate-fade-up mx-auto w-full max-w-xl rounded-xl border px-5 py-8 sm:px-10 sm:py-10"
        aria-labelledby="payment-status-title"
    >
        <div class="flex flex-col items-center text-center">
            <span
                :class="[tone.chip, tone.ring]"
                class="grid size-20 place-items-center rounded-full ring-8"
                aria-hidden="true"
            >
                <component :is="tone.icon" class="size-9" />
            </span>
            <h1
                id="payment-status-title"
                class="font-heading text-ink-royal mt-6 text-[26px] leading-tight font-bold tracking-[-0.02em] sm:text-[30px]"
            >
                {{ title }}
            </h1>
            <p class="text-ink-slate mt-3 max-w-md text-[15px] leading-6">
                {{ message }}
            </p>
        </div>

        <div class="border-line bg-app-alt mt-8 rounded-lg border">
            <dl class="divide-line divide-y">
                <div
                    v-for="row in rows"
                    :key="row.label"
                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-0.5 px-4 py-3"
                >
                    <dt class="text-ink-muted text-[13px]">{{ row.label }}</dt>
                    <dd
                        class="text-ink min-w-0 text-[14px] font-semibold break-all"
                        :dir="row.ltr ? 'ltr' : undefined"
                    >
                        {{ row.value }}
                    </dd>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3"
                >
                    <dt class="text-ink-muted text-[13px]">
                        {{ $t('Status') }}
                    </dt>
                    <dd>
                        <span
                            :class="tone.pill"
                            class="rounded-pill inline-flex items-center gap-1.5 px-3 py-1 text-[12px] font-semibold"
                        >
                            <component
                                :is="tone.icon"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ payment.statusLabel }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <p
            v-if="payment.status === 'pending'"
            class="text-ink-muted mt-5 text-center text-[12.5px] leading-5"
        >
            {{
                $t(
                    'Keep this page’s link to check the status later. You cannot sign in until the payment is approved.',
                )
            }}
        </p>

        <div class="mt-7 grid gap-3 sm:grid-cols-2">
            <Link
                :href="home()"
                class="border-line bg-surface text-brand-700 hover:border-brand-300 hover:bg-brand-50 focus-visible:ring-brand-600/30 font-heading inline-flex h-11 items-center justify-center gap-2 rounded-md border px-4 text-[14px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] motion-reduce:transition-none"
            >
                <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
                {{ $t('Back to the home page') }}
            </Link>
            <Link
                :href="login()"
                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/30 font-heading inline-flex h-11 items-center justify-center gap-2 rounded-md px-4 text-[14px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] motion-reduce:transition-none"
            >
                <LogIn class="size-4 rtl:rotate-180" aria-hidden="true" />
                {{ $t('Sign in') }}
            </Link>
        </div>
    </section>
</template>
