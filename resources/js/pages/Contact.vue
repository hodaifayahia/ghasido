<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Check,
    Mail,
    MessageCircleMore,
    Phone,
    Send,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/contact';
import type { LandingPageContent } from '@/types';

/*
 * Contact Us (client decision 2026-09-26): the phone, email and WhatsApp
 * number the Super Admin set in Settings → Landing page, and a form whose
 * messages are stored and forwarded to that email. Built from the landing
 * page's own header, footer, tokens and card style.
 */
const props = defineProps<{ content: LandingPageContent }>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    organisation: '',
    employees: '',
    message: '',
    website: '',
});

const phone = computed(() => props.content.support.phone.trim());
const email = computed(() => props.content.support.email.trim());
const whatsappHref = computed(() => {
    const number = props.content.support.whatsapp_number.replace(/\D/g, '');

    return number ? `https://wa.me/${number}` : null;
});

const teamSizes = ['1–4', '5–15', '16–50', '51–200', '200+'];

const inputClass =
    'border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 rounded-md text-[14px] focus-visible:ring-3';

function submit(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="content.contact.eyebrow">
        <meta name="description" :content="content.contact.description" />
    </Head>

    <div
        class="bg-surface text-ink min-h-screen overflow-x-clip scroll-smooth motion-reduce:scroll-auto"
    >
        <LandingHeader :content="content" anchor-base="/" active="contact" />

        <main>
            <section
                class="bg-app relative isolate overflow-hidden"
                aria-labelledby="contact-title"
            >
                <div
                    class="bg-brand-100/80 pointer-events-none absolute -end-32 -top-40 size-[34rem] rounded-full blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="bg-aqua-tint/70 pointer-events-none absolute -start-40 -bottom-56 size-[30rem] rounded-full blur-3xl"
                    aria-hidden="true"
                />

                <div
                    class="relative mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 sm:py-18 lg:grid-cols-[0.9fr_1.1fr] lg:gap-12 lg:px-10 lg:py-20"
                >
                    <div class="relative z-10 min-w-0">
                        <p
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.contact.eyebrow }}
                        </p>
                        <h1
                            id="contact-title"
                            class="font-heading text-ink-night mt-3 text-[clamp(2.1rem,4.2vw,3.4rem)] leading-[1.06] font-bold tracking-[-0.04em]"
                        >
                            {{ content.contact.title }}
                        </h1>
                        <p
                            class="text-ink-slate mt-5 max-w-lg text-[16px] leading-7"
                        >
                            {{ content.contact.description }}
                        </p>

                        <ul class="mt-8 grid gap-3">
                            <li v-if="phone">
                                <a
                                    :href="`tel:${phone.replace(/[^+\d]/g, '')}`"
                                    class="border-line bg-surface shadow-card hover:border-brand-300 focus-visible:ring-brand-600 flex min-h-16 items-center gap-4 rounded-lg border px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    data-test="contact-phone"
                                >
                                    <span
                                        class="bg-brand-50 text-brand-600 grid size-11 shrink-0 place-items-center rounded-xl"
                                    >
                                        <Phone
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="text-ink-slate block text-[12px] font-semibold tracking-[0.02em] uppercase"
                                            >{{ $t('Phone') }}</span
                                        >
                                        <span
                                            class="text-ink-night block text-[16px] font-semibold"
                                            dir="ltr"
                                            >{{ phone }}</span
                                        >
                                    </span>
                                </a>
                            </li>
                            <li v-if="email">
                                <a
                                    :href="`mailto:${email}`"
                                    class="border-line bg-surface shadow-card hover:border-brand-300 focus-visible:ring-brand-600 flex min-h-16 items-center gap-4 rounded-lg border px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    data-test="contact-email"
                                >
                                    <span
                                        class="bg-ai-tint text-ai grid size-11 shrink-0 place-items-center rounded-xl"
                                    >
                                        <Mail
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="text-ink-slate block text-[12px] font-semibold tracking-[0.02em] uppercase"
                                            >{{ $t('Email') }}</span
                                        >
                                        <span
                                            class="text-ink-night block text-[16px] font-semibold break-all"
                                            >{{ email }}</span
                                        >
                                    </span>
                                </a>
                            </li>
                            <li v-if="whatsappHref">
                                <a
                                    :href="whatsappHref"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="border-line bg-surface shadow-card hover:border-brand-300 focus-visible:ring-brand-600 flex min-h-16 items-center gap-4 rounded-lg border px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <span
                                        class="bg-success-tint text-success grid size-11 shrink-0 place-items-center rounded-xl"
                                    >
                                        <MessageCircleMore
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="text-ink-slate block text-[12px] font-semibold tracking-[0.02em] uppercase"
                                            >WhatsApp</span
                                        >
                                        <span
                                            class="text-ink-night block text-[16px] font-semibold"
                                            dir="ltr"
                                            >{{
                                                content.support.whatsapp_number
                                            }}</span
                                        >
                                    </span>
                                </a>
                            </li>
                        </ul>

                        <div
                            class="border-brand-100 bg-surface/80 mt-8 rounded-xl border p-5"
                        >
                            <p
                                class="font-heading text-ink-night text-[18px] font-semibold"
                            >
                                {{ content.contact.enterprise_title }}
                                <span
                                    class="text-ink-slate font-sans text-[14px] font-medium"
                                >
                                    · {{ content.contact.enterprise_subtitle }}
                                </span>
                            </p>
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                <li
                                    v-for="point in content.contact
                                        .enterprise_points"
                                    :key="point"
                                    class="text-ink-indigo flex items-start gap-2 text-[13px] leading-5"
                                >
                                    <Check
                                        class="text-brand-600 mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ point }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <section
                        class="border-line bg-surface shadow-pop relative z-10 min-w-0 rounded-xl border p-6 sm:p-8"
                        aria-labelledby="contact-form-title"
                    >
                        <h2
                            id="contact-form-title"
                            class="font-heading text-brand-900 text-[22px] font-semibold"
                        >
                            {{ content.contact.form_title }}
                        </h2>

                        <form
                            class="mt-6 grid gap-4 sm:grid-cols-2"
                            novalidate
                            @submit.prevent="submit"
                        >
                            <div class="grid gap-1.5">
                                <Label for="contact-name">{{
                                    $t('Full name')
                                }}</Label>
                                <Input
                                    id="contact-name"
                                    v-model="form.name"
                                    name="name"
                                    autocomplete="name"
                                    required
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="contact-email">{{
                                    $t('Email')
                                }}</Label>
                                <Input
                                    id="contact-email"
                                    v-model="form.email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="contact-phone">{{
                                    $t('Phone number')
                                }}</Label>
                                <Input
                                    id="contact-phone"
                                    v-model="form.phone"
                                    name="phone"
                                    type="tel"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    dir="ltr"
                                    required
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="contact-organisation">
                                    {{ $t('Hotel or organisation') }}
                                    <span class="text-ink-slate font-normal">{{
                                        $t('(optional)')
                                    }}</span>
                                </Label>
                                <Input
                                    id="contact-organisation"
                                    v-model="form.organisation"
                                    name="organisation"
                                    autocomplete="organization"
                                    :class="inputClass"
                                />
                                <InputError
                                    :message="form.errors.organisation"
                                />
                            </div>
                            <fieldset class="grid gap-2 sm:col-span-2">
                                <legend
                                    class="text-sm leading-none font-medium"
                                >
                                    {{ $t('Team size') }}
                                    <span class="text-ink-slate font-normal">{{
                                        $t('(optional)')
                                    }}</span>
                                </legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="size in teamSizes"
                                        :key="size"
                                        type="button"
                                        :aria-pressed="form.employees === size"
                                        :class="
                                            form.employees === size
                                                ? 'border-brand-600 bg-brand-50 text-brand-700'
                                                : 'border-line text-ink-indigo hover:bg-brand-50'
                                        "
                                        class="rounded-pill focus-visible:ring-brand-600 min-h-11 border px-4 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                        @click="
                                            form.employees =
                                                form.employees === size
                                                    ? ''
                                                    : size
                                        "
                                    >
                                        {{ size }}
                                    </button>
                                </div>
                                <InputError :message="form.errors.employees" />
                            </fieldset>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label for="contact-message">{{
                                    $t('Message')
                                }}</Label>
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    name="message"
                                    rows="5"
                                    required
                                    class="border-line bg-surface text-ink focus-visible:border-brand-600 focus-visible:ring-brand-600/15 rounded-md border px-3 py-2 text-[14px] leading-6 focus-visible:ring-3 focus-visible:outline-none"
                                />
                                <InputError :message="form.errors.message" />
                            </div>
                            <!-- Left empty by people; bots fill it in. -->
                            <div class="hidden" aria-hidden="true">
                                <label for="contact-website">{{
                                    $t('Website')
                                }}</label>
                                <input
                                    id="contact-website"
                                    v-model="form.website"
                                    name="website"
                                    tabindex="-1"
                                    autocomplete="off"
                                />
                            </div>

                            <div class="grid gap-3 sm:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 h-12 w-full rounded-md text-[14px] font-semibold active:scale-[.97]"
                                    data-test="send-contact-message-button"
                                >
                                    <Spinner v-if="form.processing" />
                                    <Send v-else class="size-4" />
                                    {{ content.contact.submit_button }}
                                </Button>
                                <p
                                    v-if="form.wasSuccessful"
                                    class="text-success-text flex items-center gap-2 text-[13px] font-semibold"
                                    role="status"
                                >
                                    <Check
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ content.contact.success_message }}
                                </p>
                                <p
                                    class="text-ink-slate flex items-start gap-2 text-[12px] leading-5"
                                >
                                    <ShieldCheck
                                        class="text-success mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{
                                        $t(
                                            'We only use your details to answer your message.',
                                        )
                                    }}
                                </p>
                            </div>
                        </form>
                    </section>
                </div>
            </section>
        </main>

        <LandingFooter :content="content" anchor-base="/" />
    </div>
</template>
