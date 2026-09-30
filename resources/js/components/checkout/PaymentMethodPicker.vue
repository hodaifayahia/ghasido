<script setup lang="ts">
import type { LandingPaymentMethod } from '@/types';
import { methodIcon, methodTone } from './methodIcon';

/*
 * "Choose a payment method" (client payment-flow reference, step 2): one
 * card per method, native radios underneath so arrow keys, Tab and screen
 * readers behave as a radio group.
 */
type Props = {
    methods: LandingPaymentMethod[];
    error?: string;
};

defineProps<Props>();

const selected = defineModel<number | null>({ required: true });
</script>

<template>
    <fieldset
        :data-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? 'payment-method-error' : undefined"
    >
        <legend class="sr-only">{{ $t('Payment method') }}</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <label
                v-for="(method, index) in methods"
                :key="method.id"
                :class="
                    selected === method.id
                        ? 'border-brand-600 bg-brand-50/70 shadow-card'
                        : 'border-line bg-surface hover:border-brand-300 hover:bg-app'
                "
                class="has-[input:focus-visible]:ring-brand-600/20 relative flex min-h-18 cursor-pointer items-center gap-3.5 rounded-lg border p-4 transition-[border-color,background-color,box-shadow] duration-150 has-[input:focus-visible]:ring-3 motion-reduce:transition-none"
            >
                <input
                    v-model="selected"
                    type="radio"
                    name="payment_method_id"
                    :value="method.id"
                    class="sr-only"
                />
                <span
                    :class="
                        selected === method.id
                            ? 'border-brand-600'
                            : 'border-line-strong'
                    "
                    class="bg-surface grid size-5 shrink-0 place-items-center rounded-full border-2 transition-colors motion-reduce:transition-none"
                    aria-hidden="true"
                >
                    <span
                        :class="
                            selected === method.id ? 'scale-100' : 'scale-0'
                        "
                        class="bg-brand-600 size-2.5 rounded-full transition-transform duration-150 motion-reduce:transition-none"
                    />
                </span>
                <span
                    :class="methodTone(index)"
                    class="grid size-11 shrink-0 place-items-center rounded-md"
                    aria-hidden="true"
                >
                    <component :is="methodIcon(method.name)" class="size-5.5" />
                </span>
                <span class="min-w-0">
                    <span
                        class="text-brand-900 block text-[15px] leading-5 font-semibold break-words"
                    >
                        {{ method.name }}
                    </span>
                    <span
                        v-if="method.recipientName"
                        class="text-ink-muted mt-0.5 block truncate text-[12px]"
                    >
                        {{ method.recipientName }}
                    </span>
                </span>
            </label>
        </div>
        <p
            v-if="error"
            id="payment-method-error"
            class="text-danger-text mt-3 text-[13px]"
        >
            {{ error }}
        </p>
    </fieldset>
</template>
