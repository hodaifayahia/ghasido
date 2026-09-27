<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    CircleCheck,
    CircleX,
    Copy,
    Mail,
    MessageCircle,
    Phone,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    formatMoney,
    formatSubmitted,
    telUrl,
    whatsappUrl,
} from '@/components/hotels/paymentFormat';
import PaymentEmailDialog from '@/components/payments/PaymentEmailDialog.vue';
import PaymentReceiptViewer from '@/components/payments/PaymentReceiptViewer.vue';
import PaymentStatusPill from '@/components/payments/PaymentStatusPill.vue';
import PaymentTypeChip from '@/components/payments/PaymentTypeChip.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import type { PaymentRow } from '@/types';

/**
 * The review of one payment (client request 2026-09-27): its details and
 * receipt side by side, ways to reach the customer, and the next step —
 * opening the hotel or individual, where the account is approved. The
 * payment follows that decision, so there is no approve button here.
 * A side sheet on desktop, a bottom sheet on a phone (AGENTS.md §7).
 */
type Props = {
    payment: PaymentRow | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const { t, isRtl } = useI18n();

const phone = useMediaQuery('(max-width: 767px)');
const side = computed((): 'bottom' | 'left' | 'right' => {
    if (phone.value) {
        return 'bottom';
    }

    return isRtl.value ? 'left' : 'right';
});

const emailOpen = ref(false);
const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.payment?.id,
    () => {
        copied.value = false;
        emailOpen.value = false;
    },
);

onBeforeUnmount(() => {
    if (copiedTimer !== null) {
        clearTimeout(copiedTimer);
    }
});

async function copyReference(): Promise<void> {
    const reference = props.payment?.reference;

    if (!reference) {
        return;
    }

    try {
        await navigator.clipboard.writeText(reference);
        copied.value = true;
        toast.success(t('Reference copied.'));

        if (copiedTimer !== null) {
            clearTimeout(copiedTimer);
        }

        copiedTimer = setTimeout(() => {
            copied.value = false;
        }, 1800);
    } catch {
        toast.error(t('Could not copy. Select the reference and copy it.'));
    }
}

const whatsapp = computed((): string | null =>
    props.payment?.payerPhone ? whatsappUrl(props.payment.payerPhone) : null,
);

const isHotel = computed(() => props.payment?.type === 'hotel');

type Detail = { label: string; value: string; mono?: boolean };

const details = computed((): Detail[] => {
    const payment = props.payment;

    if (payment === null) {
        return [];
    }

    const rows: Detail[] = [
        {
            label: isHotel.value ? t('Hotel') : t('Subscriber'),
            value: payment.customer,
        },
    ];

    if (payment.city) {
        rows.push({ label: t('City'), value: payment.city });
    }

    rows.push(
        { label: t('Contact'), value: payment.payerName },
        { label: t('Email'), value: payment.payerEmail },
    );

    if (payment.payerPhone) {
        rows.push({ label: t('Phone'), value: payment.payerPhone, mono: true });
    }

    rows.push(
        { label: t('Plan'), value: payment.planName },
        {
            label: t('Amount'),
            value: formatMoney(payment.amount, payment.currency),
        },
        { label: t('Payment method'), value: payment.method },
        {
            label: t('Submitted'),
            value: formatSubmitted(payment.submittedAt),
        },
    );

    if (payment.reviewedAt) {
        rows.push({
            label: t('Reviewed'),
            value: formatSubmitted(payment.reviewedAt),
        });
    }

    return rows;
});

const accountLabel = computed((): string => {
    const payment = props.payment;

    if (payment === null) {
        return '';
    }

    if (payment.status === 'pending') {
        return isHotel.value
            ? t('Open hotel to approve')
            : t('Open individual to approve');
    }

    return isHotel.value ? t('Open hotel') : t('Open individual');
});

const contactClass =
    'border-line bg-surface text-ink hover:border-brand-300 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex min-h-11 flex-1 basis-28 items-center justify-center gap-2 rounded-md border px-3 text-[13px] font-semibold transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none';
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            :side="side"
            :class="
                cn(
                    'bg-app gap-0 overflow-y-auto border-0 p-0',
                    side === 'bottom'
                        ? 'max-h-[92svh] rounded-t-xl'
                        : 'w-full sm:max-w-[min(780px,calc(100vw-72px))]',
                )
            "
            data-test="payment-review-sheet"
        >
            <template v-if="payment">
                <!-- Drag handle on the phone sheet -->
                <div
                    v-if="side === 'bottom'"
                    class="bg-line-strong mx-auto mt-2 h-1.5 w-10 shrink-0 rounded-full"
                    aria-hidden="true"
                />

                <header
                    class="border-line bg-surface sticky top-0 z-10 flex flex-col gap-2 border-b px-5 pt-4 pb-4 ltr:pe-12 rtl:ps-12"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <PaymentTypeChip :type="payment.type" />
                        <PaymentStatusPill
                            :status="payment.status"
                            :label="payment.statusLabel"
                        />
                    </div>
                    <SheetTitle
                        class="font-heading text-ink-royal text-[20px] leading-7 font-bold tracking-[-0.02em] break-words"
                    >
                        {{ payment.customer }}
                    </SheetTitle>
                    <SheetDescription class="text-ink-slate text-[13px]">
                        {{
                            $t(':plan plan · :amount · sent :date', {
                                plan: payment.planName,
                                amount: formatMoney(
                                    payment.amount,
                                    payment.currency,
                                ),
                                date: formatSubmitted(payment.submittedAt),
                            })
                        }}
                    </SheetDescription>
                </header>

                <div class="@container grid gap-4 p-4 md:p-6">
                    <div
                        v-if="
                            payment.status === 'rejected' &&
                            payment.rejectionReason
                        "
                        class="border-danger/30 bg-danger-tint text-danger-text flex gap-3 rounded-lg border p-3.5"
                    >
                        <CircleX
                            class="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold">
                                {{ $t('Rejection reason') }}
                            </p>
                            <p class="mt-0.5 text-[13px] leading-5 break-words">
                                {{ payment.rejectionReason }}
                            </p>
                        </div>
                    </div>

                    <div class="grid min-w-0 gap-4 @[600px]:grid-cols-2">
                        <!-- Details -->
                        <section
                            aria-labelledby="payment-details-title"
                            class="border-line bg-surface shadow-card min-w-0 rounded-lg border"
                        >
                            <h3
                                id="payment-details-title"
                                class="font-heading text-brand-800 border-line flex min-h-11 items-center border-b px-4 py-2 text-[14px] font-semibold"
                            >
                                {{ $t('Payment details') }}
                            </h3>
                            <dl class="divide-line divide-y px-4">
                                <div
                                    v-for="detail in details"
                                    :key="detail.label"
                                    class="grid grid-cols-[minmax(92px,38%)_1fr] items-baseline gap-3 py-2.5"
                                >
                                    <dt class="text-ink-slate text-[12px]">
                                        {{ detail.label }}
                                    </dt>
                                    <dd
                                        :class="
                                            cn(
                                                'text-ink min-w-0 text-[13px] font-medium break-words',
                                                detail.mono && 'tabular-nums',
                                            )
                                        "
                                        :dir="detail.mono ? 'ltr' : undefined"
                                    >
                                        {{ detail.value }}
                                    </dd>
                                </div>
                                <div
                                    class="grid grid-cols-[minmax(92px,38%)_1fr] items-center gap-3 py-2"
                                >
                                    <dt class="text-ink-slate text-[12px]">
                                        {{ $t('Transaction reference') }}
                                    </dt>
                                    <dd
                                        class="flex min-w-0 items-center gap-1.5"
                                    >
                                        <template v-if="payment.reference">
                                            <span
                                                class="bg-app text-ink min-w-0 truncate rounded-sm px-2 py-1 font-mono text-[12.5px] font-semibold"
                                                dir="ltr"
                                                :title="payment.reference"
                                                >{{ payment.reference }}</span
                                            >
                                            <button
                                                type="button"
                                                class="text-brand-600 hover:bg-brand-50 focus-visible:ring-brand-600/40 grid size-9 shrink-0 place-items-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                                                :aria-label="
                                                    $t('Copy the reference')
                                                "
                                                :title="$t('Copy')"
                                                data-test="copy-payment-reference"
                                                @click="copyReference"
                                            >
                                                <Check
                                                    v-if="copied"
                                                    class="text-success size-4"
                                                    aria-hidden="true"
                                                />
                                                <Copy
                                                    v-else
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </template>
                                        <span
                                            v-else
                                            class="text-ink-faint text-[13px]"
                                            >{{ $t('None given') }}</span
                                        >
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <PaymentReceiptViewer
                            :payment="payment"
                            class="shadow-card"
                        />
                    </div>

                    <!-- Reach the customer -->
                    <section
                        aria-labelledby="payment-contact-title"
                        class="border-line bg-surface shadow-card rounded-lg border p-4"
                    >
                        <h3
                            id="payment-contact-title"
                            class="font-heading text-brand-800 text-[14px] font-semibold"
                        >
                            {{ $t('Contact the customer') }}
                        </h3>
                        <p class="text-ink-slate mt-0.5 text-[12.5px]">
                            {{
                                $t(
                                    'Ask for a clearer receipt or confirm a detail before you decide.',
                                )
                            }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                :class="contactClass"
                                data-test="email-payment-customer"
                                @click="emailOpen = true"
                            >
                                <Mail
                                    class="text-brand-600 size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Email') }}
                            </button>
                            <a
                                v-if="payment.payerPhone"
                                :href="telUrl(payment.payerPhone)"
                                :class="contactClass"
                            >
                                <Phone
                                    class="text-brand-600 size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Call') }}
                                <span class="sr-only">{{
                                    payment.payerPhone
                                }}</span>
                            </a>
                            <a
                                v-if="whatsapp"
                                :href="whatsapp"
                                target="_blank"
                                rel="noopener"
                                :class="contactClass"
                            >
                                <MessageCircle
                                    class="text-excel size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('WhatsApp') }}
                                <span class="sr-only">{{
                                    $t('(opens in a new tab)')
                                }}</span>
                            </a>
                        </div>
                    </section>

                    <!-- Next step: the approval happens on the account -->
                    <section
                        aria-labelledby="payment-next-title"
                        :class="
                            cn(
                                'rounded-lg border p-4',
                                payment.status === 'pending'
                                    ? 'border-brand-200 bg-brand-50'
                                    : 'border-line bg-surface shadow-card',
                            )
                        "
                    >
                        <h3
                            id="payment-next-title"
                            class="font-heading text-brand-800 text-[14px] font-semibold"
                        >
                            {{
                                payment.status === 'pending'
                                    ? $t(
                                          'Next step: approve or reject the account',
                                      )
                                    : $t('Account')
                            }}
                        </h3>

                        <template v-if="payment.status === 'pending'">
                            <p
                                class="text-ink-slate mt-1 text-[12.5px] leading-5"
                            >
                                {{
                                    isHotel
                                        ? $t(
                                              'Open the hotel and approve or reject it there. This payment then becomes Confirmed or Rejected automatically, and the customer is emailed.',
                                          )
                                        : $t(
                                              'Open the individual subscriber and approve or reject them there. This payment then becomes Confirmed or Rejected automatically, and the customer is emailed.',
                                          )
                                }}
                            </p>
                            <ul class="mt-3 grid gap-2 @[520px]:grid-cols-2">
                                <li
                                    class="border-success/30 bg-success-tint flex gap-2.5 rounded-md border p-3"
                                >
                                    <CircleCheck
                                        class="text-success mt-0.5 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="text-success-text block text-[13px] font-semibold"
                                            >{{ $t('Approve') }}</span
                                        >
                                        <span
                                            class="text-success-text/90 block text-[12px] leading-5"
                                            >{{
                                                $t(
                                                    'The subscription is activated and the payment confirmed.',
                                                )
                                            }}</span
                                        >
                                    </span>
                                </li>
                                <li
                                    class="border-danger/30 bg-danger-tint flex gap-2.5 rounded-md border p-3"
                                >
                                    <CircleX
                                        class="text-danger mt-0.5 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="text-danger-text block text-[13px] font-semibold"
                                            >{{ $t('Reject') }}</span
                                        >
                                        <span
                                            class="text-danger-text/90 block text-[12px] leading-5"
                                            >{{
                                                $t(
                                                    'The customer is emailed your reason and can send a valid payment.',
                                                )
                                            }}</span
                                        >
                                    </span>
                                </li>
                            </ul>
                        </template>
                        <p
                            v-else
                            class="text-ink-slate mt-1 text-[12.5px] leading-5"
                        >
                            {{
                                payment.status === 'confirmed'
                                    ? $t(
                                          'This payment was confirmed when the account was approved.',
                                      )
                                    : $t(
                                          'This payment was rejected together with the account.',
                                      )
                            }}
                        </p>

                        <Link
                            v-if="payment.accountUrl"
                            :href="payment.accountUrl"
                            :class="
                                cn(
                                    'font-heading focus-visible:ring-brand-600/30 mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md px-5 text-[14px] font-semibold transition-[background-color,transform] duration-100 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] motion-reduce:active:scale-100 sm:w-auto',
                                    payment.status === 'pending'
                                        ? 'bg-brand-600 shadow-btn hover:bg-brand-700 text-white'
                                        : 'border-line text-brand-700 hover:border-brand-300 bg-surface border',
                                )
                            "
                            data-test="open-payment-account"
                        >
                            {{ accountLabel }}
                            <ArrowUpRight
                                class="size-4 rtl:-scale-x-100"
                                aria-hidden="true"
                            />
                        </Link>
                        <p
                            v-else
                            class="text-ink-slate mt-3 text-[12.5px] italic"
                        >
                            {{
                                $t(
                                    'The account for this payment no longer exists.',
                                )
                            }}
                        </p>
                    </section>
                </div>

                <PaymentEmailDialog
                    v-model:open="emailOpen"
                    :payment="payment"
                />
            </template>
        </SheetContent>
    </Sheet>
</template>
