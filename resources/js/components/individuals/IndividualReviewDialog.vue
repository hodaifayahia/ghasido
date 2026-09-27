<script setup lang="ts">
import { CircleX, Receipt } from '@lucide/vue';
import { computed } from 'vue';
import ApprovalDecision from '@/components/hotels/ApprovalDecision.vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import PaymentDetailsList from '@/components/hotels/PaymentDetailsList.vue';
import PaymentReceiptPreview from '@/components/hotels/PaymentReceiptPreview.vue';
import RequesterContact from '@/components/hotels/RequesterContact.vue';
import { useI18n } from '@/composables/useI18n';
import { approve, reject } from '@/routes/individuals';
import type { IndividualRow } from '@/types';

/**
 * Review an individual who bought a plan online (client request
 * 2026-09-27): their contact, the payment and its receipt, and the
 * Approve / Reject decision while it is pending. A rejected request shows
 * the reason the person was emailed.
 */
type Props = {
    individual: IndividualRow | null;
    canManage: boolean;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const row = computed(() => props.individual);

const title = computed(() =>
    t('Review :name', { name: row.value?.name ?? t('subscriber') }),
);

const description = computed(() => {
    const current = row.value;

    if (current === null) {
        return '';
    }

    if (current.approvalState === 'rejected') {
        return t('This request was rejected. The person was emailed why.');
    }

    if (current.approvalState === 'approved') {
        return t('This subscription is approved and open.');
    }

    return current.planName
        ? t(
              'Bought the :plan plan online. Check the payment, then approve or reject it.',
              { plan: current.planName },
          )
        : t('Check the payment, then approve or reject it.');
});

const approveAction = computed(() => approve.form(row.value?.id ?? 0));
const rejectAction = computed(() => reject.form(row.value?.id ?? 0));

const label =
    'text-ink-muted mb-2 text-[11px] font-semibold tracking-[0.08em] uppercase';
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="title"
        :description="description"
        class="sm:max-w-[720px]"
    >
        <div
            v-if="row"
            class="mt-2 grid gap-4"
            :data-test="`individual-review-${row.id}`"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <section aria-labelledby="individual-review-contact">
                    <h3 id="individual-review-contact" :class="label">
                        {{ $t('Contact') }}
                    </h3>
                    <RequesterContact
                        :name="row.name"
                        :email="row.email"
                        :phone="row.phone"
                        :role="row.department"
                    />
                </section>
                <section
                    v-if="row.payment"
                    aria-labelledby="individual-review-receipt"
                >
                    <h3 id="individual-review-receipt" :class="label">
                        {{ $t('Payment receipt') }}
                    </h3>
                    <PaymentReceiptPreview
                        :url="row.payment.receiptUrl"
                        :is-image="row.payment.isImage"
                        :customer="row.name"
                    />
                </section>
            </div>

            <section
                v-if="row.payment"
                aria-labelledby="individual-review-payment"
            >
                <h3 id="individual-review-payment" :class="label">
                    {{ $t('Payment details') }}
                </h3>
                <PaymentDetailsList
                    :plan-name="row.planName"
                    :amount="row.payment.amount"
                    :currency="row.payment.currency"
                    :method="row.payment.method"
                    :reference="row.payment.reference"
                    :status="row.payment.status"
                    :submitted-at="row.payment.submittedAt"
                />
            </section>
            <div
                v-else
                class="border-line text-ink-slate flex items-center justify-center gap-2 rounded-md border border-dashed px-4 py-5 text-[12.5px]"
            >
                <Receipt class="size-4" aria-hidden="true" />
                {{ $t('No payment was sent with this request.') }}
            </div>

            <div
                v-if="row.approvalState === 'rejected'"
                class="border-danger/25 bg-danger-tint flex items-start gap-3 rounded-md border px-3 py-3"
            >
                <CircleX
                    class="text-danger mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-danger-text text-[13px] font-semibold">
                        {{ $t('Reason for rejecting') }}
                    </p>
                    <p
                        class="text-danger-text/90 mt-0.5 text-[12.5px] leading-5 break-words whitespace-pre-line"
                    >
                        {{
                            row.rejectionReason ?? $t('No reason was recorded.')
                        }}
                    </p>
                </div>
            </div>

            <ApprovalDecision
                v-if="row.approvalState === 'pending' && canManage"
                :approve-action="approveAction"
                :reject-action="rejectAction"
                :subject="row.name"
                :approve-hint="
                    t(
                        'The account opens today for one month and the person is emailed right away.',
                    )
                "
                :reject-hint="
                    t(
                        'The account stays closed and the person is emailed your reason.',
                    )
                "
                :approve-confirm="
                    t(
                        'The account opens today and the person is emailed right away. The payment is marked as confirmed.',
                    )
                "
                :reject-confirm="
                    t(
                        'The person is emailed the reason you write here. Their account stays closed and nothing is deleted.',
                    )
                "
                test-id="individual"
                class="border-line border-t pt-4"
                @done="open = false"
            />
        </div>
    </HotelsModal>
</template>
