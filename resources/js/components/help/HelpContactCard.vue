<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Mail, MessageCircle, Phone, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';
import { message as sendMessage } from '@/routes/help';

/*
 * "Message the GHASIDO team" on Help (client request 2026-10-02): an
 * employee, manager or hotel admin reports a problem or asks to extend
 * their plan. The message reaches the Super Admin's bell and the support
 * email; WhatsApp, phone and email links appear when the team set them in
 * Settings → Landing page.
 */
type Support = { whatsapp: string; phone: string; email: string };

const page = usePage<{ support?: Support }>();
const support = computed(
    (): Support => page.props.support ?? { whatsapp: '', phone: '', email: '' },
);

const whatsappUrl = computed(() => {
    const digits = support.value.whatsapp.replace(/\D/g, '');

    return digits === '' ? null : `https://wa.me/${digits}`;
});

const topics = [
    { value: 'problem', label: tk('A problem with the platform') },
    { value: 'extension', label: tk('Extend or change our plan') },
    { value: 'question', label: tk('A question') },
    { value: 'other', label: tk('Something else') },
];

const topic = ref('problem');
const text = ref('');
const sending = ref(false);
const errors = ref<Record<string, string>>({});

function send(): void {
    sending.value = true;
    router.post(
        sendMessage.url(),
        { topic: topic.value, message: text.value },
        {
            preserveScroll: true,
            onError: (bag) => {
                errors.value = bag;
            },
            onSuccess: () => {
                errors.value = {};
                text.value = '';
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <PanelCard
        :title="$t('Message the GHASIDO team')"
        title-id="help-contact"
        data-test="help-contact-card"
    >
        <template #icon>
            <span
                class="bg-brand-100 text-brand-600 grid size-8 shrink-0 place-items-center rounded-full"
            >
                <MessageCircle class="size-4" aria-hidden="true" />
            </span>
        </template>

        <form class="grid min-w-0 gap-3" @submit.prevent="send">
            <p class="text-ink-slate text-[13px] leading-5">
                {{
                    $t(
                        'Something not working, or want to extend your plan? Write to us here; we answer as soon as we can.',
                    )
                }}
            </p>

            <label class="grid min-w-0 gap-1.5">
                <span class="text-brand-900 text-[12px] font-semibold">{{
                    $t('About')
                }}</span>
                <select
                    v-model="topic"
                    data-test="help-contact-topic"
                    class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-md border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                >
                    <option
                        v-for="option in topics"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ $t(option.label) }}
                    </option>
                </select>
            </label>

            <label class="grid min-w-0 gap-1.5">
                <span class="text-brand-900 text-[12px] font-semibold">{{
                    $t('Your message')
                }}</span>
                <textarea
                    v-model="text"
                    rows="4"
                    minlength="10"
                    maxlength="3000"
                    required
                    dir="auto"
                    data-test="help-contact-message"
                    class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 min-h-28 w-full resize-y rounded-md border px-3 py-2 text-[13px] leading-[1.6] focus-visible:ring-3 focus-visible:outline-none"
                />
                <InputError :message="errors.message ?? errors.topic" />
            </label>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="sending || text.trim().length < 10"
                    data-test="help-contact-send"
                >
                    <Send class="size-4" aria-hidden="true" />
                    {{ sending ? $t('Sending…') : $t('Send message') }}
                </Button>
                <a
                    v-if="whatsappUrl"
                    :href="whatsappUrl"
                    target="_blank"
                    rel="noopener"
                    class="border-line text-ink hover:bg-brand-50 inline-flex min-h-11 items-center gap-1.5 rounded-md border px-3 text-[13px] font-semibold"
                >
                    <MessageCircle
                        class="text-success size-4"
                        aria-hidden="true"
                    />
                    WhatsApp
                </a>
                <a
                    v-if="support.phone"
                    :href="`tel:${support.phone}`"
                    class="border-line text-ink hover:bg-brand-50 inline-flex min-h-11 items-center gap-1.5 rounded-md border px-3 text-[13px] font-semibold"
                    dir="ltr"
                >
                    <Phone class="text-brand-600 size-4" aria-hidden="true" />
                    {{ support.phone }}
                </a>
                <a
                    v-if="support.email"
                    :href="`mailto:${support.email}`"
                    class="border-line text-ink hover:bg-brand-50 inline-flex min-h-11 items-center gap-1.5 rounded-md border px-3 text-[13px] font-semibold"
                    dir="ltr"
                >
                    <Mail class="text-brand-600 size-4" aria-hidden="true" />
                    {{ support.email }}
                </a>
            </div>
        </form>
    </PanelCard>
</template>
