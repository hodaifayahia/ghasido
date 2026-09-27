<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Mail, Send } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { formatMoney } from '@/components/hotels/paymentFormat';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { message } from '@/routes/payments';
import type { PaymentRow } from '@/types';

/**
 * Write to the customer about their payment. Subject and body start as a
 * polite draft the admin edits; the server queues the email and flashes a
 * toast (POST /payments/{id}/message).
 */
type Props = {
    payment: PaymentRow;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const form = useForm({ subject: '', body: '' });

function draft(): void {
    const payment = props.payment;
    const amount = formatMoney(payment.amount, payment.currency);

    const middle: Record<PaymentRow['status'], string> = {
        pending: t(
            'We have received your payment of :amount for the :plan plan and we are checking it now.',
            { amount, plan: payment.planName },
        ),
        confirmed: t(
            'Your payment of :amount for the :plan plan is confirmed. Thank you!',
            { amount, plan: payment.planName },
        ),
        rejected: t(
            'We could not confirm your payment of :amount for the :plan plan.',
            { amount, plan: payment.planName },
        ),
    };

    form.subject = t('Your GHASIDO payment for the :plan plan', {
        plan: payment.planName,
    });
    form.body = [
        t('Hello :name,', { name: payment.payerName }),
        '',
        middle[payment.status],
        '',
        t('Best regards,'),
        t('The GHASIDO team'),
    ].join('\n');
    form.clearErrors();
}

watch(open, (value) => {
    if (value) {
        draft();
    }
});

function submit(): void {
    form.post(message.url(props.payment.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="bg-surface gap-5 sm:max-w-xl">
            <DialogHeader class="text-start">
                <DialogTitle
                    class="font-heading text-brand-800 flex items-center gap-2 text-lg"
                >
                    <span
                        class="bg-brand-50 text-brand-600 grid size-9 place-items-center rounded-xl"
                    >
                        <Mail class="size-[18px]" aria-hidden="true" />
                    </span>
                    {{ $t('Email the customer') }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate text-[13px]">
                    {{
                        $t('To :name <:email>', {
                            name: payment.payerName,
                            email: payment.payerEmail,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="payment-email-subject">{{
                        $t('Subject')
                    }}</Label>
                    <input
                        id="payment-email-subject"
                        v-model="form.subject"
                        type="text"
                        maxlength="150"
                        required
                        class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm border px-3 text-[14px] focus-visible:ring-3 focus-visible:outline-none"
                    />
                    <InputError :message="form.errors.subject" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="payment-email-body">{{ $t('Message') }}</Label>
                    <textarea
                        id="payment-email-body"
                        v-model="form.body"
                        rows="8"
                        maxlength="5000"
                        required
                        class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-40 w-full resize-y rounded-sm border px-3 py-2 text-[14px] leading-6 focus-visible:ring-3 focus-visible:outline-none"
                    />
                    <InputError :message="form.errors.body" />
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-ink h-10 rounded-md px-4"
                        @click="open = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 font-heading h-10 gap-2 rounded-md px-4 font-semibold text-white active:scale-[.97]"
                        :disabled="form.processing"
                        data-test="send-payment-email-button"
                    >
                        <Send class="size-4" aria-hidden="true" />
                        {{
                            form.processing ? $t('Sending…') : $t('Send email')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
