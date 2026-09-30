<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Hourglass, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import ApprovalDecision from '@/components/hotels/ApprovalDecision.vue';
import PaymentDetailsList from '@/components/hotels/PaymentDetailsList.vue';
import PaymentReceiptPreview from '@/components/hotels/PaymentReceiptPreview.vue';
import RequesterContact from '@/components/hotels/RequesterContact.vue';
import {
    formatMoney,
    formatSubmitted,
    paymentStatusTone,
} from '@/components/hotels/paymentFormat';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import { approve, reject } from '@/routes/hotels';
import type { HotelApproval } from '@/types';

/**
 * A hotel that bought a plan online waits here for approval (client
 * request 2026-09-27, reference "B. Admin flow"): who asked, the payment
 * they sent with its receipt, and the Approve and Reject actions.
 */
type Props = {
    hotelId: number;
    hotelName: string;
    approval: HotelApproval;
};

const props = defineProps<Props>();

const { t } = useI18n();

const latest = computed(() => props.approval.payments[0] ?? null);
const earlier = computed(() => props.approval.payments.slice(1));

const approveAction = computed(() => approve.form(props.hotelId));
const rejectAction = computed(() => reject.form(props.hotelId));

const label =
    'text-ink-muted text-[11px] font-semibold tracking-[0.08em] uppercase';
</script>

<template>
    <PanelCard
        :title="$t('Awaiting approval')"
        title-id="hotel-approval-heading"
        class="border-warning/45 motion-safe:animate-fade-up"
        data-test="hotel-approval-panel"
    >
        <template #icon>
            <span
                class="bg-warning-tint text-warning grid size-8 shrink-0 place-items-center rounded-full"
            >
                <Hourglass class="size-4.5" aria-hidden="true" />
            </span>
        </template>
        <template #actions>
            <Link
                v-if="latest"
                :href="latest.reviewUrl"
                class="text-brand-600 hover:text-brand-700 focus-visible:ring-brand-600/15 inline-flex min-h-9 shrink-0 items-center gap-1 rounded-md px-1.5 text-[12.5px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                data-test="hotel-approval-review-link"
            >
                <span class="hidden sm:inline">{{
                    $t('Review in Payments')
                }}</span>
                <span class="sm:hidden">{{ $t('Payments') }}</span>
                <ArrowUpRight
                    class="size-4 rtl:-scale-x-100"
                    aria-hidden="true"
                />
            </Link>
        </template>

        <p class="text-ink-slate -mt-1 mb-4 text-[13px] leading-5">
            {{
                $t(
                    'This hotel bought a plan online. Check the payment and the receipt, then approve or reject the request.',
                )
            }}
        </p>

        <section
            class="border-line bg-app-alt/60 mb-5 rounded-md border px-3 py-3"
            aria-labelledby="hotel-approval-requester"
        >
            <h3 id="hotel-approval-requester" :class="cn(label, 'mb-2.5')">
                {{ $t('Requested by') }}
            </h3>
            <RequesterContact
                v-if="approval.requester"
                :name="approval.requester.name"
                :email="approval.requester.email"
                :phone="approval.requester.phone"
                :role="$t('Hotel manager')"
                layout="inline"
            />
            <p v-else class="text-ink-muted text-[12.5px]">
                {{ $t('The person who asked is not known.') }}
            </p>
        </section>

        <div
            class="grid min-w-0 gap-5 md:grid-cols-2 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.85fr)_minmax(0,1fr)] xl:gap-6"
        >
            <section class="min-w-0" aria-labelledby="hotel-approval-payment">
                <h3 id="hotel-approval-payment" :class="cn(label, 'mb-2.5')">
                    {{ $t('Payment details') }}
                </h3>
                <PaymentDetailsList
                    v-if="latest"
                    :plan-name="latest.planName"
                    :amount="latest.amount"
                    :currency="latest.currency"
                    :method="latest.method"
                    :reference="latest.reference"
                    :status="latest.status"
                    :status-label="latest.statusLabel"
                    :submitted-at="latest.submittedAt"
                />
                <div
                    v-else
                    class="border-line text-ink-slate rounded-md border border-dashed px-4 py-6 text-center text-[12.5px]"
                >
                    {{ $t('No payment was sent with this request.') }}
                </div>
            </section>

            <section
                v-if="latest"
                class="min-w-0"
                aria-labelledby="hotel-approval-receipt"
            >
                <h3 id="hotel-approval-receipt" :class="cn(label, 'mb-2.5')">
                    {{ $t('Payment receipt') }}
                </h3>
                <PaymentReceiptPreview
                    :url="latest.receiptUrl"
                    :is-image="latest.isImage"
                    :customer="hotelName"
                />
            </section>

            <section
                :class="
                    cn(
                        'min-w-0',
                        latest
                            ? 'md:col-span-2 xl:col-span-1'
                            : 'xl:col-span-2',
                    )
                "
                aria-labelledby="hotel-approval-decision"
            >
                <h3 id="hotel-approval-decision" :class="cn(label, 'mb-2.5')">
                    {{ $t('Your decision') }}
                </h3>
                <ApprovalDecision
                    v-if="approval.canApprove"
                    :approve-action="approveAction"
                    :reject-action="rejectAction"
                    :subject="hotelName"
                    :approve-hint="
                        t(
                            'The hotel is activated and its manager is emailed right away.',
                        )
                    "
                    :reject-hint="
                        t(
                            'The hotel is archived and the customer is emailed your reason.',
                        )
                    "
                    :approve-confirm="
                        t(
                            'The hotel is activated and its manager is emailed right away. The payment is marked as confirmed.',
                        )
                    "
                    :reject-confirm="
                        t(
                            'The customer is emailed the reason you write here. The hotel is archived and nothing is deleted.',
                        )
                    "
                    test-id="hotel"
                />
                <div
                    v-else
                    class="border-line bg-app-alt text-ink-slate flex items-start gap-2.5 rounded-md border px-3 py-3 text-[12.5px] leading-5"
                >
                    <ShieldCheck
                        class="text-brand-600 mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{
                        $t(
                            'A Super Admin checks the payment and approves or rejects this hotel.',
                        )
                    }}
                </div>
            </section>
        </div>

        <div v-if="earlier.length > 0" class="border-line mt-5 border-t pt-3">
            <h3 :class="cn(label, 'mb-2')">{{ $t('Earlier payments') }}</h3>
            <ul class="grid gap-1.5">
                <li
                    v-for="payment in earlier"
                    :key="payment.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px]"
                >
                    <span class="text-brand-900 font-semibold">
                        <bdi>{{
                            formatMoney(payment.amount, payment.currency)
                        }}</bdi>
                    </span>
                    <span class="text-ink-slate">{{ payment.method }}</span>
                    <span class="text-ink-muted">{{
                        formatSubmitted(payment.submittedAt)
                    }}</span>
                    <span
                        :class="
                            cn(
                                'rounded-pill text-pill inline-flex h-6 items-center px-2.5',
                                paymentStatusTone[payment.status],
                            )
                        "
                    >
                        {{ payment.statusLabel }}
                    </span>
                </li>
            </ul>
        </div>
    </PanelCard>
</template>
