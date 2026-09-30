<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Mail, MessageCircleMore, Phone } from '@lucide/vue';
import { computed } from 'vue';
import { vReveal } from '@/directives/vReveal';
import { contact, dashboard, login } from '@/routes';
import type { LandingPageContent } from '@/types';

/*
 * The public site's footer, shared by the landing page and Contact Us. The
 * phone, email and WhatsApp number come from Settings → Landing page
 * (client decision 2026-09-26); each is hidden while left blank.
 */
type Props = {
    content: LandingPageContent;
    anchorBase?: string;
};

const props = withDefaults(defineProps<Props>(), { anchorBase: '' });

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const signInHref = computed(() =>
    signedIn.value ? dashboard().url : login().url,
);
const phone = computed(() => props.content.support.phone.trim());
const email = computed(() => props.content.support.email.trim());
const whatsappHref = computed(() => {
    const number = props.content.support.whatsapp_number.replace(/\D/g, '');

    return number ? `https://wa.me/${number}` : null;
});
</script>

<template>
    <footer class="border-line bg-app border-t">
        <div
            class="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-8 md:grid-cols-[1fr_auto] lg:px-10"
        >
            <div v-reveal:fade class="max-w-sm">
                <img
                    src="/brand/ghasido-logo.png"
                    alt="GHASIDO"
                    width="600"
                    height="180"
                    class="h-11 w-auto object-contain object-left"
                />
                <p class="text-ink-slate mt-4 text-[12px] leading-5">
                    {{ content.footer.tagline }}
                </p>
            </div>
            <div
                v-reveal:fade="120"
                class="text-ink-indigo flex flex-wrap items-center gap-x-5 gap-y-3 text-[12px] font-semibold"
            >
                <a :href="`${anchorBase}#ai`" class="hover:text-brand-600">{{
                    content.navigation.ai_practice
                }}</a>
                <a
                    :href="`${anchorBase}#why-us`"
                    class="hover:text-brand-600"
                    >{{ content.navigation.why_us }}</a
                >
                <a
                    :href="`${anchorBase}#pricing`"
                    class="hover:text-brand-600"
                    >{{ content.navigation.pricing }}</a
                >
                <Link :href="contact()" class="hover:text-brand-600">{{
                    content.navigation.contact
                }}</Link>
                <a
                    v-if="phone"
                    :href="`tel:${phone.replace(/[^+\d]/g, '')}`"
                    class="hover:text-brand-600 inline-flex items-center gap-1.5"
                    dir="ltr"
                >
                    <Phone class="size-4" aria-hidden="true" />
                    {{ phone }}
                </a>
                <a
                    v-if="email"
                    :href="`mailto:${email}`"
                    class="hover:text-brand-600 inline-flex items-center gap-1.5"
                >
                    <Mail class="size-4" aria-hidden="true" />
                    {{ email }}
                </a>
                <a
                    v-if="whatsappHref"
                    :href="whatsappHref"
                    target="_blank"
                    rel="noreferrer"
                    class="hover:text-brand-600 inline-flex items-center gap-1.5"
                >
                    <MessageCircleMore class="size-4" />
                    {{ $t('WhatsApp support') }}
                </a>
                <Link :href="signInHref" class="hover:text-brand-600">
                    {{
                        signedIn
                            ? content.navigation.open_dashboard
                            : content.navigation.login
                    }}
                </Link>
            </div>
        </div>
    </footer>
</template>
