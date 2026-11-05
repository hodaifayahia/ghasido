<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { ArrowRight, ArrowUp, Mail, MessageCircleMore, Phone } from "@lucide/vue";
import { usePreferredReducedMotion } from "@vueuse/core";
import { computed } from "vue";
import LanguageToggle from "@/components/landing/LanguageToggle.vue";
import { vReveal } from "@/directives/vReveal";
import { contact, dashboard, login } from "@/routes";
import type { LandingPageContent } from "@/types";

/*
 * The public site's footer, shared by the landing page, Contact Us and
 * checkout (redesigned on user request 2026-11-05): the brand lockup and
 * the footer tagline, then the page's sections, the support details and
 * the account links in columns, and a legal line with "Back to top". The
 * phone, email and WhatsApp number come from Settings → Landing page
 * (client decision 2026-09-26); each is hidden while left blank.
 */
type Props = {
    content: LandingPageContent;
    anchorBase?: string;
};

const props = withDefaults(defineProps<Props>(), { anchorBase: "" });

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const signInHref = computed(() => (signedIn.value ? dashboard().url : login().url));
const phone = computed(() => props.content.support.phone.trim());
const email = computed(() => props.content.support.email.trim());
const whatsappHref = computed(() => {
    const number = props.content.support.whatsapp_number.replace(/\D/g, "");

    return number ? `https://wa.me/${number}` : null;
});

const sections = computed(() => [
    {
        href: `${props.anchorBase}#how-it-works`,
        label: props.content.journey.eyebrow,
    },
    {
        href: `${props.anchorBase}#ai`,
        label: props.content.navigation.ai_practice,
    },
    {
        href: `${props.anchorBase}#platform`,
        label: props.content.navigation.platform,
    },
    {
        href: `${props.anchorBase}#why-us`,
        label: props.content.navigation.why_us,
    },
    {
        href: `${props.anchorBase}#pricing`,
        label: props.content.navigation.pricing,
    },
]);

const year = new Date().getFullYear();
const reduced = usePreferredReducedMotion();

function backToTop(): void {
    window.scrollTo({
        top: 0,
        behavior: reduced.value === "reduce" ? "auto" : "smooth",
    });
}

const linkClass =
    "text-ink-indigo hover:text-brand-600 focus-visible:ring-brand-600 inline-flex min-h-11 items-center gap-2 rounded-sm text-[14px] transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none md:min-h-9";
const headingClass = "text-ink-slate text-[11.5px] font-bold tracking-[0.14em] uppercase";
</script>

<template>
    <footer
        class="border-line bg-app relative isolate overflow-hidden border-t"
        data-test="landing-footer"
    >
        <!-- The client's palm island, drawn on the footer's horizon. -->
        <img
            src="/decor/palm-island.png"
            alt=""
            aria-hidden="true"
            width="540"
            height="252"
            loading="lazy"
            class="pointer-events-none absolute end-[-40px] bottom-[72px] -z-10 hidden w-[440px] opacity-[0.09] select-none md:block rtl:-scale-x-100"
        />

        <div class="mx-auto max-w-7xl px-5 pt-14 pb-8 sm:px-8 lg:px-10 lg:pt-16">
            <div
                class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,0.9fr)_minmax(0,1.2fr)_minmax(0,0.9fr)] lg:gap-12"
            >
                <div v-reveal:fade class="max-w-sm sm:col-span-2 lg:col-span-1">
                    <Link
                        href="/"
                        :aria-label="$t('GHASIDO home')"
                        class="focus-visible:ring-brand-600 inline-block rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <img
                            src="/brand/ghasido-logo.png"
                            alt="GHASIDO"
                            width="690"
                            height="205"
                            loading="lazy"
                            class="h-12 w-auto object-contain"
                        />
                    </Link>
                    <p
                        class="font-heading text-ink-night mt-5 text-[19px] leading-7 font-semibold tracking-[-0.02em] text-balance"
                    >
                        {{ content.footer.tagline }}
                    </p>
                    <LanguageToggle class="border-line-strong bg-surface shadow-card mt-6 border" />
                </div>

                <nav v-reveal:fade="80" :aria-label="$t('Explore')" class="min-w-0">
                    <h2 :class="headingClass">{{ $t("Explore") }}</h2>
                    <ul class="mt-3 grid md:gap-1">
                        <li v-for="section in sections" :key="section.href">
                            <a :href="section.href" :class="linkClass">{{ section.label }}</a>
                        </li>
                    </ul>
                </nav>

                <div v-reveal:fade="160" class="min-w-0">
                    <h2 :class="headingClass">{{ $t("Get in touch") }}</h2>
                    <p class="text-ink-slate mt-3 text-[13px] leading-6">
                        {{ content.contact.description }}
                    </p>
                    <ul class="mt-2 grid md:gap-1">
                        <li v-if="phone">
                            <a
                                :href="`tel:${phone.replace(/[^+\d]/g, '')}`"
                                :class="linkClass"
                                dir="ltr"
                            >
                                <Phone class="text-brand-600 size-4 shrink-0" aria-hidden="true" />
                                {{ phone }}
                            </a>
                        </li>
                        <li v-if="email">
                            <a
                                :href="`mailto:${email}`"
                                :class="linkClass"
                                class="max-w-full break-all"
                            >
                                <Mail class="text-brand-600 size-4 shrink-0" aria-hidden="true" />
                                {{ email }}
                            </a>
                        </li>
                        <li v-if="whatsappHref">
                            <a
                                :href="whatsappHref"
                                target="_blank"
                                rel="noreferrer"
                                :class="linkClass"
                            >
                                <MessageCircleMore
                                    class="text-brand-600 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ $t("WhatsApp support") }}
                            </a>
                        </li>
                        <li>
                            <Link
                                :href="contact()"
                                :class="linkClass"
                                class="text-brand-700 group font-semibold"
                            >
                                {{ content.navigation.contact }}
                                <ArrowRight
                                    class="size-4 transition-[translate] duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none rtl:group-hover:-translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </div>

                <nav v-reveal:fade="240" :aria-label="$t('Account')" class="min-w-0">
                    <h2 :class="headingClass">{{ $t("Account") }}</h2>
                    <ul class="mt-3 grid md:gap-1">
                        <li>
                            <Link :href="signInHref" :class="linkClass">
                                {{
                                    signedIn
                                        ? content.navigation.open_dashboard
                                        : content.navigation.login
                                }}
                            </Link>
                        </li>
                        <li>
                            <a :href="`${anchorBase}#pricing`" :class="linkClass">{{
                                content.navigation.get_started
                            }}</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <div
                class="border-line mt-12 flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-ink-slate text-[12.5px]">
                    © {{ year }} GHASIDO. {{ $t("All rights reserved.") }}
                </p>
                <button
                    type="button"
                    class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600 -ms-3 inline-flex min-h-11 items-center gap-2 self-start rounded-md px-3 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none sm:ms-0 sm:-me-3 sm:self-auto"
                    data-test="landing-back-to-top"
                    @click="backToTop"
                >
                    <ArrowUp class="size-4" aria-hidden="true" />
                    {{ $t("Back to top") }}
                </button>
            </div>
        </div>
    </footer>
</template>
