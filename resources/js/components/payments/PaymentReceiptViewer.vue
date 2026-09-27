<script setup lang="ts">
import {
    ExternalLink,
    FileText,
    ImageOff,
    ReceiptText,
    ZoomIn,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PaymentRow } from '@/types';

/**
 * The proof of payment in the review panel: an image shown in place that
 * zooms to full size, a PDF embedded with an "Open" link, or a note that
 * the customer sent a transaction reference only. The URL streams the
 * private file to signed-in admins (same origin).
 */
type Props = {
    payment: PaymentRow;
};

const props = defineProps<Props>();

const failed = ref(false);
const zoomed = ref(false);

watch(
    () => props.payment.id,
    () => {
        failed.value = false;
        zoomed.value = false;
    },
);

const sizeText = computed((): string | null => {
    const bytes = props.payment.receiptSize;

    if (bytes === null || bytes <= 0) {
        return null;
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
});
</script>

<template>
    <section
        aria-labelledby="payment-receipt-title"
        class="border-line bg-surface flex min-w-0 flex-col overflow-hidden rounded-lg border"
    >
        <header
            class="border-line flex min-h-11 items-center justify-between gap-2 border-b px-4 py-2"
        >
            <h3
                id="payment-receipt-title"
                class="font-heading text-brand-800 inline-flex items-center gap-2 text-[14px] font-semibold"
            >
                <ReceiptText class="text-brand-600 size-4" aria-hidden="true" />
                {{ $t('Payment receipt') }}
            </h3>
            <a
                v-if="payment.receiptUrl !== null"
                :href="payment.receiptUrl"
                target="_blank"
                rel="noopener"
                class="text-brand-600 hover:text-brand-700 focus-visible:ring-brand-600/40 inline-flex min-h-9 items-center gap-1 rounded-md px-1.5 text-[12px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                data-test="payment-receipt-open"
            >
                {{ $t('Open') }}
                <ExternalLink class="size-3.5" aria-hidden="true" />
                <span class="sr-only">{{ $t('(opens in a new tab)') }}</span>
            </a>
        </header>

        <!-- Image: shown in place, click to zoom -->
        <button
            v-if="payment.receiptUrl !== null && payment.isImage && !failed"
            type="button"
            class="group bg-app-alt focus-visible:ring-brand-600/40 relative block w-full cursor-zoom-in overflow-hidden focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
            :aria-label="$t('Zoom the receipt')"
            data-test="payment-receipt-zoom"
            @click="zoomed = true"
        >
            <img
                :src="payment.receiptUrl"
                :alt="
                    $t('Proof of payment sent by :name', {
                        name: payment.customer,
                    })
                "
                decoding="async"
                class="ease-brand mx-auto h-[300px] w-full object-contain p-3 transition-transform duration-300 group-hover:scale-[1.02] motion-reduce:transition-none motion-reduce:group-hover:scale-100 md:h-[360px]"
                @error="failed = true"
            />
            <span
                class="bg-surface/95 text-brand-700 shadow-card absolute end-3 bottom-3 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-semibold"
            >
                <ZoomIn class="size-3.5" aria-hidden="true" />
                {{ $t('Zoom') }}
            </span>
        </button>

        <!-- PDF: embedded, with the Open link above -->
        <div
            v-else-if="payment.receiptUrl !== null && !payment.isImage"
            class="bg-app-alt"
        >
            <iframe
                :src="payment.receiptUrl"
                :title="
                    $t('PDF receipt sent by :name', { name: payment.customer })
                "
                class="hidden h-[360px] w-full border-0 md:block"
                loading="lazy"
            />
            <a
                :href="payment.receiptUrl"
                target="_blank"
                rel="noopener"
                class="hover:bg-brand-50 focus-visible:ring-brand-600/40 flex min-h-20 items-center gap-3 p-4 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset md:hidden"
            >
                <span
                    class="bg-brand-50 text-brand-600 grid size-11 shrink-0 place-items-center rounded-xl"
                >
                    <FileText class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span
                        class="text-brand-900 block truncate text-[13px] font-semibold"
                        >{{ payment.receiptName ?? $t('PDF receipt') }}</span
                    >
                    <span class="text-brand-600 text-[12px] font-semibold">
                        {{ $t('Open PDF') }}
                    </span>
                </span>
            </a>
        </div>

        <!-- The image could not load (moved or deleted file) -->
        <div
            v-else-if="payment.receiptUrl !== null"
            class="text-ink-slate flex min-h-40 flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <ImageOff class="text-ink-faint size-6" aria-hidden="true" />
            <p class="text-[12.5px]">
                {{
                    $t(
                        'The receipt could not be shown here. Try opening it in a new tab.',
                    )
                }}
            </p>
        </div>

        <!-- No file: reference only -->
        <div
            v-else
            class="text-ink-slate flex min-h-40 flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <span
                class="bg-app text-ink-faint grid size-11 place-items-center rounded-xl"
            >
                <ImageOff class="size-5" aria-hidden="true" />
            </span>
            <p class="text-ink text-[13px] font-semibold">
                {{ $t('No receipt — reference only') }}
            </p>
            <p class="max-w-64 text-[12px] leading-5">
                {{
                    $t(
                        'The customer sent a transaction reference instead of a file. Check it in your account history.',
                    )
                }}
            </p>
        </div>

        <footer
            v-if="payment.receiptUrl !== null && payment.receiptName"
            class="border-line text-ink-slate flex min-w-0 items-center gap-2 border-t px-4 py-2 text-[11.5px]"
        >
            <span class="truncate" dir="ltr">{{ payment.receiptName }}</span>
            <span v-if="sizeText" class="shrink-0">· {{ sizeText }}</span>
        </footer>

        <Dialog v-model:open="zoomed">
            <DialogContent
                class="bg-surface max-h-[92svh] gap-3 overflow-auto p-3 sm:max-w-[min(960px,calc(100%-2rem))]"
            >
                <DialogTitle class="sr-only">{{
                    $t('Payment receipt')
                }}</DialogTitle>
                <DialogDescription class="sr-only">{{
                    $t('Proof of payment sent by :name', {
                        name: payment.customer,
                    })
                }}</DialogDescription>
                <img
                    v-if="payment.receiptUrl !== null"
                    :src="payment.receiptUrl"
                    :alt="
                        $t('Proof of payment sent by :name', {
                            name: payment.customer,
                        })
                    "
                    class="mx-auto max-h-[84svh] w-auto max-w-full object-contain"
                />
            </DialogContent>
        </Dialog>
    </section>
</template>
