<script setup lang="ts">
import { ExternalLink, FileText, ImageOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';

/**
 * The proof of payment a customer uploaded at checkout (client request
 * 2026-09-27). An image shows as a thumbnail that opens full size in a new
 * tab; a PDF shows as a tile that opens it. The URL streams the private
 * file to signed-in admins only.
 */
type Props = {
    url: string | null;
    isImage: boolean;
    /** Who sent it, for the image's alt text. */
    customer: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const failed = ref(false);
</script>

<template>
    <div :class="cn('min-w-0', props.class)">
        <a
            v-if="url !== null && isImage && !failed"
            :href="url"
            target="_blank"
            rel="noopener"
            class="group border-line bg-app-alt focus-visible:ring-brand-600/15 focus-visible:border-brand-600 relative block overflow-hidden rounded-md border focus-visible:ring-3 focus-visible:outline-none"
            data-test="payment-receipt-image-link"
        >
            <img
                :src="url"
                :alt="$t('Proof of payment sent by :name', { name: customer })"
                loading="lazy"
                decoding="async"
                class="h-44 w-full object-cover object-top transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                @error="failed = true"
            />
            <span
                class="bg-surface/95 text-brand-700 shadow-card absolute end-2 bottom-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-semibold"
            >
                <ExternalLink class="size-3.5" aria-hidden="true" />
                {{ $t('Open full size') }}
                <span class="sr-only">{{ $t('(opens in a new tab)') }}</span>
            </span>
        </a>

        <a
            v-else-if="url !== null"
            :href="url"
            target="_blank"
            rel="noopener"
            class="border-line bg-app-alt hover:border-brand-300 focus-visible:ring-brand-600/15 focus-visible:border-brand-600 flex min-h-24 items-center gap-3 rounded-md border p-3 transition-colors focus-visible:ring-3 focus-visible:outline-none"
            data-test="payment-receipt-file-link"
        >
            <span
                class="bg-brand-50 text-brand-600 grid size-11 shrink-0 place-items-center rounded-xl"
            >
                <FileText class="size-5" aria-hidden="true" />
            </span>
            <span class="min-w-0">
                <span class="text-brand-900 block text-[13px] font-semibold">
                    {{ isImage ? $t('Receipt image') : $t('PDF receipt') }}
                </span>
                <span
                    class="text-brand-600 mt-0.5 inline-flex items-center gap-1 text-[12px] font-semibold"
                >
                    {{ $t('Open receipt') }}
                    <ExternalLink class="size-3.5" aria-hidden="true" />
                    <span class="sr-only">{{
                        $t('(opens in a new tab)')
                    }}</span>
                </span>
            </span>
        </a>

        <div
            v-else
            class="border-line text-ink-muted flex min-h-24 flex-col items-center justify-center gap-1.5 rounded-md border border-dashed p-3 text-center"
        >
            <ImageOff class="size-5" aria-hidden="true" />
            <p class="text-[12px]">
                {{ $t('No receipt uploaded, only a transaction reference.') }}
            </p>
        </div>
    </div>
</template>
