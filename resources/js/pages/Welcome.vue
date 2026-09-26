<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    AudioLines,
    BookOpenCheck,
    Building2,
    ChartNoAxesCombined,
    Check,
    ChevronRight,
    CircleCheck,
    GraduationCap,
    Headphones,
    Hotel,
    Globe,
    Languages,
    MessageCircleMore,
    Mic2,
    ShieldCheck,
    Smartphone,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import AlgeriaFlagIcon from '@/components/icons/AlgeriaFlagIcon.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import { contact } from '@/routes';
import type {
    LandingPageContent,
    LandingPaymentMethod,
    LandingPlan,
} from '@/types';

const props = defineProps<{
    content: LandingPageContent;
    plans: LandingPlan[];
    paymentMethods: LandingPaymentMethod[];
}>();

const { t } = useI18n();

const visibleRoles = computed(() =>
    props.content.roles.items.filter(
        (role) => role.title.trim().toLowerCase() !== 'super admin',
    ),
);
const rolesEyebrow = computed(() =>
    props.content.roles.eyebrow === 'One platform, four clear experiences'
        ? 'One platform, three clear experiences'
        : props.content.roles.eyebrow,
);
// Algerian hotels pay in DZD, everyone else in USD (client decision
// 2026-09-26); both prices are set per plan by the Super Admin.
type Region = 'dz' | 'intl';
const region = ref<Region>('dz');
const regions = computed((): { key: Region; label: string }[] => [
    { key: 'dz', label: props.content.pricing.region_algeria },
    { key: 'intl', label: props.content.pricing.region_international },
]);
const featuredIndex = computed(() =>
    props.plans.length >= 4 ? 2 : Math.min(1, props.plans.length - 1),
);

const aiIcons: Component[] = [MessageCircleMore, Mic2, Sparkles, AudioLines];
const roleIcons: Component[] = [Building2, UsersRound, GraduationCap];
const whyIcons: Component[] = [Hotel, Smartphone, ChartNoAxesCombined];
const featureIcons: Component[] = [
    BookOpenCheck,
    UsersRound,
    ChartNoAxesCombined,
];
const featureShots = [
    '/landing/lesson-builder-ghasido.png',
    '/landing/team-management-ghasido.png',
    '/landing/reports-and-export-ghasido.png',
] as const;

const formatDzd = (value: number): string =>
    new Intl.NumberFormat('fr-DZ').format(value);

const formatUsd = (value: number): string =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);

/** A plan with no USD price yet is quoted on request, never shown as $0. */
function onRequest(plan: LandingPlan): boolean {
    return region.value === 'intl' && plan.priceUsd <= 0;
}

function price(plan: LandingPlan): { amount: string; currency: string } {
    if (region.value === 'dz') {
        return { amount: formatDzd(plan.priceDzd), currency: 'DZD' };
    }

    return onRequest(plan)
        ? { amount: t('On request'), currency: '' }
        : { amount: `$${formatUsd(plan.priceUsd)}`, currency: 'USD' };
}
</script>

<template>
    <Head :title="content.hero.title">
        <meta name="description" :content="content.hero.description" />
    </Head>

    <div
        class="bg-surface text-ink min-h-screen overflow-x-clip scroll-smooth motion-reduce:scroll-auto"
    >
        <LandingHeader :content="content" />

        <main>
            <section
                class="bg-app relative isolate overflow-hidden"
                aria-labelledby="hero-title"
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
                    class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-18 lg:grid-cols-[0.82fr_1.18fr] lg:gap-10 lg:px-10 lg:py-22"
                >
                    <div class="relative z-10 max-w-xl">
                        <span
                            class="border-brand-200 bg-surface text-brand-700 shadow-card rounded-pill inline-flex items-center gap-2 border px-3.5 py-2 text-[11px] font-bold tracking-[0.08em] uppercase"
                        >
                            <span class="bg-success size-2 rounded-full" />
                            {{ content.hero.eyebrow }}
                        </span>
                        <h1
                            id="hero-title"
                            class="font-heading text-ink-night mt-5 text-[clamp(2.45rem,5vw,4.35rem)] leading-[1.02] font-bold tracking-[-0.05em]"
                        >
                            {{ content.hero.title }}
                        </h1>
                        <p
                            class="text-ink-slate mt-6 max-w-lg text-[16px] leading-7 sm:text-[18px] sm:leading-8"
                        >
                            {{ content.hero.description }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <Button
                                as-child
                                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 h-12 rounded-md px-6 text-[14px] font-semibold"
                            >
                                <a href="#ai">
                                    {{ content.hero.primary_cta }}
                                    <ArrowRight class="size-4" />
                                </a>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="border-line-strong bg-surface text-brand-700 shadow-card hover:bg-brand-50 h-12 rounded-md px-6 text-[14px] font-semibold"
                            >
                                <a href="#pricing">{{
                                    content.hero.secondary_cta
                                }}</a>
                            </Button>
                            <InstallAppButton variant="outline" />
                        </div>
                        <div
                            class="border-line mt-8 grid gap-3 border-t pt-5 sm:grid-cols-3"
                        >
                            <div
                                v-for="(point, index) in content.hero
                                    .proof_points"
                                :key="point"
                                class="text-ink-indigo flex items-start gap-2 text-[11px] leading-5 font-semibold sm:text-[12px]"
                            >
                                <CircleCheck
                                    :class="
                                        index === 2
                                            ? 'text-ai'
                                            : 'text-brand-600'
                                    "
                                    class="mt-0.5 size-4 shrink-0"
                                />
                                {{ point }}
                            </div>
                        </div>
                    </div>

                    <figure
                        class="relative mx-auto w-full max-w-[720px] pb-10 sm:pb-14 lg:ms-auto"
                    >
                        <div
                            class="bg-brand-200/60 absolute -inset-3 rotate-1 rounded-xl"
                            aria-hidden="true"
                        />
                        <div
                            class="border-line bg-surface shadow-pop relative overflow-hidden rounded-xl border p-2 sm:p-3"
                        >
                            <div
                                class="border-line flex h-8 items-center gap-1.5 border-b px-1.5 sm:h-10 sm:px-2"
                            >
                                <span
                                    class="bg-danger/70 size-2 rounded-full sm:size-2.5"
                                />
                                <span
                                    class="bg-warning/80 size-2 rounded-full sm:size-2.5"
                                />
                                <span
                                    class="bg-success/80 size-2 rounded-full sm:size-2.5"
                                />
                                <span
                                    class="bg-app ms-2 h-4 flex-1 rounded-sm sm:h-5"
                                />
                            </div>
                            <img
                                src="/landing/admin-dashboard-desktop-ghasido.png"
                                :alt="content.hero.image_alt"
                                width="1600"
                                height="900"
                                fetchpriority="high"
                                class="mt-2 block aspect-[16/9] w-full rounded-lg object-cover object-top sm:mt-3"
                            />
                        </div>

                        <div
                            class="border-brand-900 bg-brand-900 shadow-pop absolute start-5 -bottom-1 w-[112px] overflow-hidden rounded-xl border-[5px] sm:start-8 sm:-bottom-2 sm:w-[150px] sm:border-[6px] lg:-start-7 lg:w-[168px]"
                        >
                            <img
                                src="/landing/employee-pretest-mobile-ghasido.png"
                                :alt="
                                    $t(
                                        'GHASIDO employee pre-test on a mobile phone',
                                    )
                                "
                                width="390"
                                height="844"
                                fetchpriority="high"
                                class="block aspect-[390/844] w-full object-cover object-top"
                            />
                        </div>

                        <div
                            class="border-line bg-surface shadow-hover absolute end-3 bottom-2 hidden max-w-[220px] items-center gap-3 rounded-lg border p-3 sm:flex lg:-end-4"
                        >
                            <span
                                class="bg-ai-tint text-ai grid size-10 shrink-0 place-items-center rounded-md"
                            >
                                <Sparkles class="size-5" />
                            </span>
                            <p
                                class="text-ink-indigo text-[11px] leading-4 font-semibold"
                            >
                                {{
                                    $t(
                                        'Real practice. Clear progress. One connected platform.',
                                    )
                                }}
                            </p>
                        </div>
                    </figure>
                </div>
            </section>

            <section
                id="ai"
                class="bg-brand-900 text-surface relative scroll-mt-20 overflow-hidden py-16 sm:py-20 lg:py-24"
                aria-labelledby="ai-title"
            >
                <div
                    class="bg-ai/20 pointer-events-none absolute -end-32 -top-40 size-[30rem] rounded-full blur-3xl"
                    aria-hidden="true"
                />
                <div class="relative mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div
                        class="grid items-end gap-5 lg:grid-cols-[0.75fr_1.25fr] lg:gap-12"
                    >
                        <div>
                            <p
                                class="text-brand-200 text-[12px] font-bold tracking-[0.16em] uppercase"
                            >
                                {{ content.ai.eyebrow }}
                            </p>
                            <h2
                                id="ai-title"
                                class="font-heading text-surface mt-3 text-[clamp(2rem,4vw,3.35rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                            >
                                {{ content.ai.title }}
                            </h2>
                        </div>
                        <p
                            class="text-brand-100 max-w-2xl text-[15px] leading-7 lg:justify-self-end lg:text-[17px]"
                        >
                            {{ content.ai.description }}
                        </p>
                    </div>

                    <div
                        class="mt-10 grid items-stretch gap-6 lg:mt-14 lg:grid-cols-[1.35fr_0.65fr]"
                    >
                        <figure
                            class="border-surface/15 bg-surface/10 shadow-pop overflow-hidden rounded-xl border p-2 sm:p-3"
                        >
                            <div
                                class="border-surface/10 flex h-9 items-center gap-2 border-b px-2"
                            >
                                <span
                                    class="bg-surface/25 size-2 rounded-full"
                                />
                                <span
                                    class="bg-surface/20 size-2 rounded-full"
                                />
                                <span
                                    class="bg-surface/15 size-2 rounded-full"
                                />
                                <span
                                    class="text-brand-200 ms-2 text-[10px] font-semibold tracking-wide uppercase"
                                    >{{ $t('AI scenario workspace') }}</span
                                >
                            </div>
                            <img
                                src="/landing/ai-roleplay-builder-ghasido.png"
                                :alt="
                                    $t(
                                        'GHASIDO AI role-play scenario builder and preview',
                                    )
                                "
                                width="1280"
                                height="853"
                                loading="lazy"
                                class="mt-2 block aspect-[1280/853] w-full rounded-lg object-cover object-top sm:mt-3"
                            />
                        </figure>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <article
                                v-for="(item, index) in content.ai.items"
                                :key="item.title"
                                class="border-surface/15 bg-surface/8 hover:bg-surface/12 flex gap-4 rounded-lg border p-4 transition-colors sm:p-5"
                            >
                                <span
                                    class="bg-ai/25 text-brand-100 grid size-10 shrink-0 place-items-center rounded-md"
                                >
                                    <component
                                        :is="aiIcons[index % aiIcons.length]"
                                        class="size-5"
                                    />
                                </span>
                                <div>
                                    <h3
                                        class="font-heading text-[14px] font-semibold sm:text-[15px]"
                                    >
                                        {{ item.title }}
                                    </h3>
                                    <p
                                        class="text-brand-100 mt-1.5 text-[12px] leading-5"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div
                        class="border-surface/15 bg-surface/8 mt-6 flex items-start gap-3 rounded-lg border px-4 py-3"
                    >
                        <ShieldCheck
                            class="text-success mt-0.5 size-5 shrink-0"
                        />
                        <p class="text-brand-100 text-[12px] leading-5">
                            {{ content.ai.review_note }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                id="about"
                class="bg-surface scroll-mt-20 py-16 sm:py-20 lg:py-24"
                aria-labelledby="about-title"
            >
                <div
                    class="mx-auto grid max-w-7xl items-center gap-10 px-5 sm:px-8 md:grid-cols-[0.78fr_1.22fr] md:gap-14 lg:px-10"
                >
                    <figure class="relative mx-auto w-full max-w-[330px]">
                        <div
                            class="bg-aqua-tint absolute inset-0 translate-x-4 translate-y-4 rounded-xl"
                            aria-hidden="true"
                        />
                        <div
                            class="border-brand-900 bg-brand-900 shadow-hover relative mx-auto w-[210px] overflow-hidden rounded-xl border-[7px] sm:w-[250px]"
                        >
                            <img
                                src="/landing/employee-pretest-mobile-ghasido.png"
                                :alt="content.about.image_alt"
                                width="390"
                                height="844"
                                loading="lazy"
                                class="block aspect-[390/844] w-full object-cover object-top"
                            />
                        </div>
                        <div
                            class="border-line bg-surface shadow-card absolute end-0 top-16 max-w-[158px] rounded-lg border p-3 sm:-end-4"
                        >
                            <Languages class="text-brand-600 size-5" />
                            <p
                                class="text-ink-indigo mt-2 text-[11px] leading-4 font-semibold"
                            >
                                {{
                                    $t(
                                        'English first. Arabic meaning only when the learner asks.',
                                    )
                                }}
                            </p>
                        </div>
                        <img
                            src="/decor/palm-island-tagline.png"
                            alt=""
                            class="pointer-events-none absolute -start-2 -bottom-6 w-28 opacity-70 select-none sm:w-34"
                        />
                    </figure>

                    <div class="max-w-2xl">
                        <p
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.about.eyebrow }}
                        </p>
                        <h2
                            id="about-title"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.2rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.about.title }}
                        </h2>
                        <p class="text-ink-slate mt-5 text-[16px] leading-8">
                            {{ content.about.description }}
                        </p>
                        <div
                            class="border-brand-100 bg-brand-50 mt-7 flex items-start gap-4 rounded-lg border p-5"
                        >
                            <span
                                class="bg-surface text-brand-700 shadow-card grid size-11 shrink-0 place-items-center rounded-md"
                            >
                                <Headphones class="size-5" />
                            </span>
                            <div>
                                <p
                                    class="font-heading text-brand-900 text-[15px] font-semibold"
                                >
                                    {{
                                        $t(
                                            'Designed for adults learning at work',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-ink-slate mt-1 text-[13px] leading-6"
                                >
                                    {{ content.about.learner_note }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="roles"
                class="bg-app scroll-mt-20 py-16 sm:py-20 lg:py-24"
                aria-labelledby="roles-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div
                        class="grid items-end gap-5 lg:grid-cols-[0.9fr_1.1fr] lg:gap-12"
                    >
                        <div>
                            <p
                                class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                            >
                                {{ rolesEyebrow }}
                            </p>
                            <h2
                                id="roles-title"
                                class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.15rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                            >
                                {{ content.roles.title }}
                            </h2>
                        </div>
                        <p
                            class="text-ink-slate max-w-2xl text-[15px] leading-7 lg:justify-self-end lg:text-[16px]"
                        >
                            {{ content.roles.description }}
                        </p>
                    </div>

                    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="(role, index) in visibleRoles"
                            :key="role.title"
                            class="border-line bg-surface shadow-card hover:shadow-hover group relative overflow-hidden rounded-xl border p-5 transition-all duration-200 hover:-translate-y-1 sm:p-6"
                        >
                            <div
                                class="bg-brand-600 absolute inset-x-0 top-0 h-1 opacity-0 transition-opacity group-hover:opacity-100"
                            />
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    :class="
                                        index === 2
                                            ? 'bg-aqua-tint text-aqua'
                                            : 'bg-brand-100 text-brand-700'
                                    "
                                    class="grid size-12 place-items-center rounded-md"
                                >
                                    <component
                                        :is="
                                            roleIcons[index % roleIcons.length]
                                        "
                                        class="size-6"
                                    />
                                </span>
                                <span
                                    class="text-ink-faint font-heading text-[11px] font-bold tracking-[0.15em]"
                                    >0{{ index + 1 }}</span
                                >
                            </div>
                            <h3
                                class="font-heading text-brand-900 mt-5 text-[18px] font-semibold"
                            >
                                {{ role.title }}
                            </h3>
                            <p
                                class="text-ink-slate mt-2.5 text-[13px] leading-6"
                            >
                                {{ role.description }}
                            </p>
                            <ul
                                class="border-line mt-5 space-y-2.5 border-t pt-4"
                            >
                                <li
                                    v-for="capability in role.capabilities"
                                    :key="capability"
                                    class="text-ink-indigo flex items-start gap-2 text-[12px] leading-5 font-medium"
                                >
                                    <Check
                                        class="text-success mt-0.5 size-4 shrink-0"
                                    />
                                    {{ capability }}
                                </li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <section
                class="bg-surface py-16 sm:py-20 lg:py-24"
                aria-labelledby="journey-title"
            >
                <div
                    class="mx-auto grid max-w-7xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-[0.72fr_1.28fr] lg:gap-16 lg:px-10"
                >
                    <div>
                        <p
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.journey.eyebrow }}
                        </p>
                        <h2
                            id="journey-title"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.1rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.journey.title }}
                        </h2>
                        <p class="text-ink-slate mt-4 text-[15px] leading-7">
                            {{ content.journey.description }}
                        </p>
                    </div>

                    <ol class="relative grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="(step, index) in content.journey.steps"
                            :key="step.title"
                            class="border-line bg-app shadow-card relative rounded-lg border p-5"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    :class="
                                        index === 2
                                            ? 'bg-ai text-surface'
                                            : 'bg-brand-600 text-surface'
                                    "
                                    class="font-heading rounded-pill grid size-9 shrink-0 place-items-center text-[13px] font-bold"
                                >
                                    {{ index + 1 }}
                                </span>
                                <h3
                                    class="font-heading text-brand-900 text-[15px] font-semibold"
                                >
                                    {{ step.title }}
                                </h3>
                            </div>
                            <p
                                class="text-ink-slate mt-3 ps-12 text-[12px] leading-5"
                            >
                                {{ step.description }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section
                id="platform"
                class="bg-app scroll-mt-20 py-16 sm:py-20 lg:py-24"
                aria-labelledby="features-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <p
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.features.eyebrow }}
                        </p>
                        <h2
                            id="features-title"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.2rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.features.title }}
                        </h2>
                        <p
                            class="text-ink-slate mx-auto mt-4 max-w-2xl text-[15px] leading-7"
                        >
                            {{ content.features.description }}
                        </p>
                    </div>

                    <div class="mt-12 space-y-8 lg:mt-16 lg:space-y-12">
                        <article
                            v-for="(item, index) in content.features.items"
                            :key="item.title"
                            class="border-line bg-surface shadow-card grid items-center gap-7 overflow-hidden rounded-xl border p-5 sm:p-7 lg:grid-cols-2 lg:gap-12 lg:p-8"
                        >
                            <div
                                :class="index % 2 === 1 ? 'lg:order-2' : ''"
                                class="relative"
                            >
                                <div
                                    :class="
                                        index === 0
                                            ? 'bg-brand-100'
                                            : index === 1
                                              ? 'bg-aqua-tint'
                                              : 'bg-gold-tint'
                                    "
                                    class="absolute -inset-2 rotate-1 rounded-xl"
                                    aria-hidden="true"
                                />
                                <figure
                                    class="border-line bg-surface shadow-hover relative overflow-hidden rounded-lg border p-2"
                                >
                                    <div
                                        class="border-line flex h-8 items-center gap-1.5 border-b px-1"
                                    >
                                        <span
                                            class="bg-danger/65 size-2 rounded-full"
                                        />
                                        <span
                                            class="bg-warning/75 size-2 rounded-full"
                                        />
                                        <span
                                            class="bg-success/75 size-2 rounded-full"
                                        />
                                    </div>
                                    <img
                                        :src="
                                            featureShots[
                                                index % featureShots.length
                                            ]
                                        "
                                        :alt="`${item.title} in the GHASIDO platform`"
                                        width="1280"
                                        height="853"
                                        loading="lazy"
                                        class="mt-2 block aspect-[1280/853] w-full rounded-md object-cover object-top"
                                    />
                                </figure>
                            </div>

                            <div
                                :class="index % 2 === 1 ? 'lg:order-1' : ''"
                                class="max-w-xl"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="bg-brand-100 text-brand-700 grid size-11 place-items-center rounded-md"
                                    >
                                        <component
                                            :is="
                                                featureIcons[
                                                    index % featureIcons.length
                                                ]
                                            "
                                            class="size-5"
                                        />
                                    </span>
                                    <span
                                        class="text-brand-600 text-[11px] font-bold tracking-[0.15em] uppercase"
                                        >{{
                                            $t('Feature :number', {
                                                number: `0${index + 1}`,
                                            })
                                        }}</span
                                    >
                                </div>
                                <h3
                                    class="font-heading text-ink-night mt-5 text-[clamp(1.55rem,3vw,2.25rem)] leading-[1.15] font-bold tracking-[-0.03em]"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="text-ink-slate mt-4 text-[14px] leading-7 sm:text-[15px]"
                                >
                                    {{ item.description }}
                                </p>
                                <div
                                    class="text-brand-700 mt-6 inline-flex items-center gap-2 text-[12px] font-semibold"
                                >
                                    <CircleCheck class="text-success size-4" />
                                    {{
                                        $t(
                                            'Connected to the same secure GHASIDO workspace',
                                        )
                                    }}
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                id="why-us"
                class="bg-surface scroll-mt-20 py-16 sm:py-20"
                aria-labelledby="why-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div
                        class="grid gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:gap-12"
                    >
                        <div>
                            <p
                                class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                            >
                                {{ content.why_us.eyebrow }}
                            </p>
                            <h2
                                id="why-title"
                                class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                            >
                                {{ content.why_us.title }}
                            </h2>
                            <p
                                class="text-ink-slate mt-4 text-[15px] leading-7"
                            >
                                {{ content.why_us.description }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <article
                                v-for="(item, index) in content.why_us.items"
                                :key="item.title"
                                class="border-line bg-app rounded-xl border p-5"
                            >
                                <component
                                    :is="whyIcons[index % whyIcons.length]"
                                    class="text-brand-600 size-6"
                                />
                                <h3
                                    class="font-heading text-brand-900 mt-4 text-[15px] font-semibold"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="text-ink-slate mt-2 text-[12px] leading-5"
                                >
                                    {{ item.description }}
                                </p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="pricing"
                class="bg-app scroll-mt-20 py-16 sm:py-20 lg:py-24"
                aria-labelledby="pricing-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <p
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.pricing.eyebrow }}
                        </p>
                        <h2
                            id="pricing-title"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.2rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.pricing.title }}
                        </h2>
                        <p
                            class="text-ink-slate mx-auto mt-4 max-w-2xl text-[15px] leading-7"
                        >
                            {{ content.pricing.description }}
                        </p>

                        <div
                            role="radiogroup"
                            :aria-label="$t('Pricing region')"
                            class="border-line bg-surface shadow-card rounded-pill mx-auto mt-7 inline-grid grid-cols-2 gap-1 border p-1"
                        >
                            <button
                                v-for="option in regions"
                                :key="option.key"
                                type="button"
                                role="radio"
                                :aria-checked="region === option.key"
                                :class="
                                    cn(
                                        'rounded-pill focus-visible:ring-brand-600 flex min-h-11 items-center justify-center gap-2.5 px-4 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none sm:px-6 sm:text-[14px]',
                                        region === option.key
                                            ? 'bg-brand-900 text-surface shadow-btn'
                                            : 'text-ink-indigo hover:bg-brand-50',
                                    )
                                "
                                :data-test="`pricing-region-${option.key}`"
                                @click="region = option.key"
                            >
                                <AlgeriaFlagIcon
                                    v-if="option.key === 'dz'"
                                    class="size-6 shrink-0"
                                />
                                <Globe
                                    v-else
                                    :class="
                                        region === option.key
                                            ? 'text-brand-200'
                                            : 'text-brand-600'
                                    "
                                    class="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="plans.length"
                        :class="
                            plans.length >= 4
                                ? 'max-w-7xl md:grid-cols-2 xl:grid-cols-4'
                                : 'max-w-6xl lg:grid-cols-3'
                        "
                        class="mx-auto mt-10 grid items-stretch gap-5 lg:mt-14"
                    >
                        <article
                            v-for="(plan, index) in plans"
                            :key="plan.id"
                            :class="
                                index === featuredIndex
                                    ? 'border-brand-600 bg-brand-900 text-surface shadow-pop lg:-translate-y-3'
                                    : 'border-line bg-surface text-ink shadow-card'
                            "
                            class="relative flex h-full flex-col rounded-xl border p-6 sm:p-7"
                        >
                            <span
                                v-if="index === featuredIndex"
                                class="bg-gold text-ink-night rounded-pill absolute end-6 top-0 -translate-y-1/2 px-3 py-1 text-[10px] font-bold tracking-[0.1em] uppercase"
                            >
                                {{ content.pricing.featured_label }}
                            </span>
                            <h3
                                :class="
                                    index === featuredIndex
                                        ? 'text-surface'
                                        : 'text-ink-night'
                                "
                                class="font-heading text-[21px] font-semibold"
                            >
                                {{ plan.name }}
                            </h3>
                            <p class="mt-5 flex flex-wrap items-end gap-2">
                                <span
                                    class="font-heading text-[38px] leading-none font-bold tracking-[-0.04em]"
                                >
                                    {{ price(plan).amount }}
                                </span>
                                <span
                                    v-if="!onRequest(plan)"
                                    :class="
                                        index === featuredIndex
                                            ? 'text-brand-200'
                                            : 'text-ink-slate'
                                    "
                                    class="pb-0.5 text-[12px]"
                                    >{{ price(plan).currency }} /
                                    {{ content.pricing.monthly_label }}</span
                                >
                            </p>

                            <div
                                :class="
                                    index === featuredIndex
                                        ? 'border-surface/15'
                                        : 'border-line'
                                "
                                class="mt-6 space-y-3 border-y py-5"
                            >
                                <div class="flex items-center gap-3">
                                    <UsersRound
                                        :class="
                                            index === featuredIndex
                                                ? 'text-brand-200'
                                                : 'text-brand-600'
                                        "
                                        class="size-5"
                                    />
                                    <p class="text-[13px]">
                                        <strong class="font-semibold">{{
                                            plan.employeeLimit
                                        }}</strong>
                                        {{ content.pricing.employees_label }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Sparkles
                                        :class="
                                            index === featuredIndex
                                                ? 'text-brand-200'
                                                : 'text-ai'
                                        "
                                        class="size-5"
                                    />
                                    <p class="text-[13px]">
                                        <strong class="font-semibold">{{
                                            plan.pointsPool.toLocaleString()
                                        }}</strong>
                                        {{ content.pricing.ai_points_label }}
                                    </p>
                                </div>
                            </div>

                            <ul class="mt-5 flex-1 space-y-3">
                                <li
                                    v-for="item in content.pricing.inclusions"
                                    :key="item"
                                    :class="
                                        index === featuredIndex
                                            ? 'text-brand-100'
                                            : 'text-ink-slate'
                                    "
                                    class="flex items-start gap-2 text-[12px] leading-5"
                                >
                                    <Check
                                        class="text-success mt-0.5 size-4 shrink-0"
                                    />
                                    {{ item }}
                                </li>
                            </ul>

                            <Button
                                as-child
                                :variant="
                                    index === featuredIndex
                                        ? 'secondary'
                                        : 'default'
                                "
                                :class="
                                    index === featuredIndex
                                        ? 'bg-surface text-brand-800 hover:bg-brand-50'
                                        : 'bg-brand-600 text-surface hover:bg-brand-700'
                                "
                                class="mt-7 h-12 w-full rounded-md text-[13px] font-semibold"
                            >
                                <Link
                                    :href="
                                        onRequest(plan)
                                            ? contact().url
                                            : `/checkout/${plan.slug}${region === 'intl' ? '?region=intl' : ''}`
                                    "
                                >
                                    {{ content.pricing.button_text }}
                                    <ChevronRight class="size-4" />
                                </Link>
                            </Button>
                        </article>
                    </div>

                    <!-- Hotel / Enterprise (client pricing mockup 2026-09-26):
                         larger teams are sent to Contact Us for a quote. -->
                    <div
                        class="border-brand-100 bg-brand-50 shadow-card mx-auto mt-10 grid max-w-7xl gap-6 rounded-xl border p-6 sm:p-8 lg:grid-cols-[1.2fr_1fr_auto] lg:items-center lg:gap-8"
                        data-test="pricing-enterprise"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="bg-surface text-brand-700 shadow-card grid size-16 shrink-0 place-items-center rounded-full"
                            >
                                <Hotel class="size-8" aria-hidden="true" />
                            </span>
                            <div class="min-w-0">
                                <h3
                                    class="font-heading text-ink-night text-[22px] leading-7 font-bold tracking-[-0.02em]"
                                >
                                    {{ content.contact.enterprise_title }}
                                </h3>
                                <p
                                    class="text-ink-indigo mt-1 text-[16px] font-semibold"
                                >
                                    {{ content.contact.enterprise_subtitle }}
                                </p>
                                <p
                                    class="text-ink-slate mt-2 text-[13px] leading-6"
                                >
                                    {{ content.contact.enterprise_description }}
                                </p>
                            </div>
                        </div>
                        <ul
                            class="border-brand-200 space-y-2 lg:border-s lg:ps-8"
                        >
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
                        <div
                            class="grid justify-items-start gap-2 lg:justify-items-center"
                        >
                            <Button
                                as-child
                                class="bg-brand-900 text-surface shadow-btn hover:bg-brand-800 rounded-pill h-12 px-7 text-[14px] font-semibold"
                            >
                                <Link :href="contact()">
                                    {{ content.contact.enterprise_button }}
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <p class="text-ink-slate text-[12px]">
                                {{ content.contact.enterprise_note }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mx-auto mt-7 flex max-w-3xl items-start justify-center gap-2 text-center"
                    >
                        <ShieldCheck
                            class="text-success mt-0.5 size-4 shrink-0"
                        />
                        <p class="text-ink-slate text-[12px] leading-5">
                            {{ content.pricing.footnote }}
                        </p>
                    </div>

                    <div
                        v-if="paymentMethods.length"
                        class="border-line bg-surface mx-auto mt-6 flex max-w-3xl flex-wrap items-center justify-center gap-x-5 gap-y-2 rounded-lg border px-4 py-3"
                    >
                        <span
                            class="text-ink-slate text-[11px] font-semibold tracking-wide uppercase"
                            >{{ $t('Available payment options') }}</span
                        >
                        <span
                            v-for="method in paymentMethods"
                            :key="method.id"
                            class="text-brand-800 text-[12px] font-semibold"
                        >
                            {{ method.name }}
                        </span>
                    </div>
                </div>
            </section>

            <section
                class="bg-surface py-16 sm:py-20"
                aria-labelledby="cta-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div
                        class="bg-brand-900 text-surface shadow-pop relative overflow-hidden rounded-xl px-6 py-10 sm:px-12 sm:py-14 lg:px-16"
                    >
                        <div
                            class="bg-ai/25 pointer-events-none absolute -end-20 -top-40 size-96 rounded-full blur-3xl"
                            aria-hidden="true"
                        />
                        <div
                            class="relative grid items-center gap-7 lg:grid-cols-[1fr_auto]"
                        >
                            <div class="max-w-3xl">
                                <p
                                    class="text-brand-200 text-[12px] font-bold tracking-[0.16em] uppercase"
                                >
                                    {{ content.call_to_action.eyebrow }}
                                </p>
                                <h2
                                    id="cta-title"
                                    class="font-heading text-surface mt-3 text-[clamp(1.9rem,4vw,3rem)] leading-[1.1] font-bold tracking-[-0.04em]"
                                >
                                    {{ content.call_to_action.title }}
                                </h2>
                                <p
                                    class="text-brand-100 mt-4 max-w-2xl text-[14px] leading-7 sm:text-[15px]"
                                >
                                    {{ content.call_to_action.description }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <Button
                                    as-child
                                    class="bg-surface text-brand-800 hover:bg-brand-50 h-12 rounded-md px-6 text-[14px] font-semibold"
                                >
                                    <a href="#pricing">
                                        {{ content.call_to_action.button_text }}
                                        <ArrowRight class="size-4" />
                                    </a>
                                </Button>
                                <InstallAppButton variant="light" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <LandingFooter :content="content" />
    </div>
</template>
