<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { BuildingComplex, Mail, Phone, Trash2, Users } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { intlLocale } from '@/lib/i18n';
import type { LandingContactMessage } from '@/types';

type Props = {
    message: LandingContactMessage;
};

const props = defineProps<Props>();

const emit = defineEmits<{ delete: [message: LandingContactMessage] }>();

function sentAt(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat(intlLocale(), {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '';
}

function markRead(): void {
    router.patch(props.message.readUrl, {}, { preserveScroll: true });
}
</script>

<template>
    <!-- Same unread / read treatment as the list in Website Management. -->
    <li
        :class="
            message.read
                ? 'border-line bg-surface'
                : 'border-brand-200 bg-brand-50'
        "
        class="rounded-md border p-4"
        :data-test="`contact-message-${message.id}`"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p
                    class="font-heading text-ink-night flex flex-wrap items-center gap-2 text-[14px] font-semibold"
                >
                    <span class="min-w-0 break-words">{{ message.name }}</span>
                    <span
                        v-if="!message.read"
                        class="bg-brand-600 inline-flex h-5 items-center rounded-[5px] px-1.5 text-[11px] leading-none font-semibold text-white"
                        >{{ $t('New') }}</span
                    >
                </p>
                <p class="text-ink-slate mt-0.5 text-[12px]">
                    {{ sentAt(message.sentAt) }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    v-if="!message.read"
                    type="button"
                    size="sm"
                    variant="outline"
                    :data-test="`mark-contact-message-${message.id}-read-button`"
                    @click="markRead"
                >
                    {{ $t('Mark as read') }}
                </Button>
                <span v-else class="text-ink-slate text-[12px] font-semibold">{{
                    $t('Read')
                }}</span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="border-danger text-danger-text hover:bg-danger-tint bg-surface relative before:absolute before:inset-x-0 before:-inset-y-1.5 md:before:hidden"
                    :aria-label="
                        $t('Delete the message from :name', {
                            name: message.name,
                        })
                    "
                    :data-test="`delete-contact-message-${message.id}-button`"
                    @click="emit('delete', message)"
                >
                    <Trash2 class="size-3.5" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </div>
        </div>

        <dl class="mt-3 grid gap-x-6 gap-y-2 text-[13px] sm:grid-cols-2">
            <div class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0">
                    <Mail class="text-brand-600 size-4" aria-hidden="true" />
                    <span class="sr-only">{{ $t('Email') }}</span>
                </dt>
                <dd class="min-w-0">
                    <a
                        :href="`mailto:${message.email}`"
                        class="text-brand-700 focus-visible:ring-brand-600/40 block truncate rounded-sm hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        >{{ message.email }}</a
                    >
                </dd>
            </div>
            <div class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0">
                    <Phone class="text-brand-600 size-4" aria-hidden="true" />
                    <span class="sr-only">{{ $t('Phone number') }}</span>
                </dt>
                <dd class="min-w-0">
                    <a
                        v-if="message.phone"
                        :href="`tel:${message.phone.replace(/[^+\d]/g, '')}`"
                        class="text-brand-700 focus-visible:ring-brand-600/40 block truncate rounded-sm hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        dir="ltr"
                        >{{ message.phone }}</a
                    >
                    <span v-else class="text-ink-faint">{{
                        $t('No phone number')
                    }}</span>
                </dd>
            </div>
            <div class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0">
                    <BuildingComplex
                        class="text-brand-600 size-4"
                        aria-hidden="true"
                    />
                    <span class="sr-only">{{
                        $t('Hotel or organisation')
                    }}</span>
                </dt>
                <dd
                    :class="
                        message.organisation ? 'text-ink' : 'text-ink-faint'
                    "
                    class="min-w-0 truncate"
                >
                    {{ message.organisation || $t('No organisation given') }}
                </dd>
            </div>
            <div class="flex min-w-0 items-center gap-2">
                <dt class="shrink-0">
                    <Users class="text-brand-600 size-4" aria-hidden="true" />
                    <span class="sr-only">{{ $t('Team size') }}</span>
                </dt>
                <dd
                    :class="message.employees ? 'text-ink' : 'text-ink-faint'"
                    class="min-w-0 truncate"
                >
                    {{
                        message.employees
                            ? $t(':count employees', {
                                  count: message.employees,
                              })
                            : $t('No team size given')
                    }}
                </dd>
            </div>
        </dl>

        <p
            class="text-ink border-line mt-3 border-t pt-3 text-[13px] leading-6 break-words whitespace-pre-line"
        >
            {{ message.message }}
        </p>
    </li>
</template>
