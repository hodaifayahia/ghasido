<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CheckoutShell from '@/components/checkout/CheckoutShell.vue';
import PaymentSubmittedCard from '@/components/checkout/PaymentSubmittedCard.vue';
import { home } from '@/routes';
import type { CheckoutSubmittedPayment, LandingPageContent } from '@/types';

/*
 * After checkout: the payment waits for the Super Admin to confirm it
 * (client payment-flow reference, 2026-09-27). The link is unguessable, so
 * the customer can bookmark it and come back for the outcome.
 */
type Props = {
    content: LandingPageContent;
    payment: CheckoutSubmittedPayment;
};

defineProps<Props>();
</script>

<template>
    <Head :title="$t('Payment submitted')">
        <meta name="robots" content="noindex" />
    </Head>

    <CheckoutShell
        :content="content"
        :back-href="home().url"
        :back-label="$t('Home')"
    >
        <div class="px-4 py-10 sm:px-8 sm:py-16">
            <PaymentSubmittedCard :payment="payment" />
        </div>
    </CheckoutShell>
</template>
