<script setup lang="ts">
import { Mail, MessageCircle, Phone } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { telUrl, whatsappUrl } from '@/components/hotels/paymentFormat';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

/**
 * Who bought the plan online, with one-tap ways to reach them: email,
 * phone and WhatsApp (client request 2026-09-27).
 */
type Props = {
    name: string;
    email: string | null;
    phone: string | null;
    /** A second line under the name, e.g. "Hotel manager". */
    role?: string;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const { getInitials } = useInitials();

const whatsapp = computed(() =>
    props.phone ? whatsappUrl(props.phone) : null,
);

const link =
    'border-line bg-surface text-ink hover:border-brand-300 hover:bg-brand-50 focus-visible:border-brand-600 focus-visible:ring-brand-600/15 flex min-h-11 min-w-0 items-center gap-2.5 rounded-md border px-3 text-[12.5px] transition-colors focus-visible:ring-3 focus-visible:outline-none md:min-h-10';
</script>

<template>
    <div :class="cn('grid min-w-0 content-start gap-3', props.class)">
        <div class="flex min-w-0 items-center gap-3">
            <span
                class="bg-brand-100 text-brand-700 font-heading grid size-10 shrink-0 place-items-center rounded-full text-[13px] font-semibold"
                aria-hidden="true"
            >
                {{ getInitials(name) }}
            </span>
            <div class="min-w-0">
                <p
                    class="font-heading text-brand-900 truncate text-[14px] font-semibold"
                >
                    {{ name }}
                </p>
                <p v-if="role" class="text-ink-slate truncate text-[12px]">
                    {{ role }}
                </p>
            </div>
        </div>

        <ul class="grid gap-1.5">
            <li v-if="email">
                <a :href="`mailto:${email}`" :class="link">
                    <Mail
                        class="text-brand-600 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="sr-only">{{ $t('Email') }}:</span>
                    <span class="min-w-0 truncate">{{ email }}</span>
                </a>
            </li>
            <li v-if="phone">
                <a :href="telUrl(phone)" :class="link">
                    <Phone
                        class="text-brand-600 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="sr-only">{{ $t('Phone') }}:</span>
                    <bdi class="min-w-0 truncate">{{ phone }}</bdi>
                </a>
            </li>
            <li v-if="whatsapp">
                <a
                    :href="whatsapp"
                    target="_blank"
                    rel="noopener"
                    :class="link"
                >
                    <MessageCircle
                        class="text-success size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 truncate">{{
                        $t('Message on WhatsApp')
                    }}</span>
                    <span class="sr-only">{{
                        $t('(opens in a new tab)')
                    }}</span>
                </a>
            </li>
            <li
                v-if="!email && !phone"
                class="text-ink-muted text-[12px] leading-5"
            >
                {{ $t('No contact details were given.') }}
            </li>
        </ul>
    </div>
</template>
