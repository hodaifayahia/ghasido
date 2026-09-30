<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    ChartNoAxesCombined,
    Check,
    ChevronRight,
    CircleCheck,
    Hotel,
    Globe,
    MessageCircleMore,
    Mic2,
    ShieldCheck,
    Smartphone,
    Sparkles,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { individualInclusions } from '@/components/checkout/individualPlan';
import AlgeriaFlagIcon from '@/components/icons/AlgeriaFlagIcon.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { vReveal } from '@/directives/vReveal';
import { cn } from '@/lib/utils';
import { contact } from '@/routes';
import { show as checkoutShow } from '@/routes/checkout';
import type {
    LandingPageContent,
    LandingPaymentMethod,
    LandingPlan,
} from '@/types';

const props = defineProps<{
    content: LandingPageContent;
    plans: LandingPlan[];
    individualPlans?: LandingPlan[];
    paymentMethods: LandingPaymentMethod[];
}>();

const { t } = useI18n();

// Algerian hotels pay in DZD, everyone else in USD (client decision
// 2026-09-26); both prices are set per plan by the Super Admin.
type Region = 'dz' | 'intl';
const region = ref<Region>('dz');
const regions = computed((): { key: Region; label: string }[] => [
    { key: 'dz', label: props.content.pricing.region_algeria },
    { key: 'intl', label: props.content.pricing.region_international },
]);
// Hotel teams or one learner on their own (user request 2026-09-27).
// Individual plans carry monthly AI points, never seats.
type Audience = 'hotel' | 'individual';
const audience = ref<Audience>('hotel');
const audiences = computed(
    (): { key: Audience; label: string; icon: Component }[] => [
        { key: 'hotel', label: t('Hotels'), icon: Hotel },
        { key: 'individual', label: t('Individuals'), icon: UserRound },
    ],
);
const individualPlans = computed(() => props.individualPlans ?? []);
const isIndividual = computed(() => audience.value === 'individual');
const shownPlans = computed(() =>
    isIndividual.value ? individualPlans.value : props.plans,
);
const featuredIndex = computed(() =>
    shownPlans.value.length >= 4 ? 2 : Math.min(1, shownPlans.value.length - 1),
);

/** Arrow keys move between the audience options, as in a radio group. */
function onAudienceKey(event: KeyboardEvent): void {
    if (
        !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)
    ) {
        return;
    }

    event.preventDefault();
    audience.value = isIndividual.value ? 'hotel' : 'individual';

    const group = (event.currentTarget as HTMLElement).closest(
        '[role="radiogroup"]',
    );
    group
        ?.querySelector<HTMLElement>(`[data-audience="${audience.value}"]`)
        ?.focus();
}

function checkoutHref(plan: LandingPlan): string {
    if (onRequest(plan)) {
        return contact().url;
    }

    return checkoutShow.url(
        plan.slug,
        region.value === 'intl' ? { query: { region: 'intl' } } : undefined,
    );
}

const aiIcons: Component[] = [MessageCircleMore, Mic2, Hotel];
const whyIcons: Component[] = [Hotel, Smartphone, ChartNoAxesCombined];
const featureIcons: Component[] = [
    BookOpenCheck,
    UsersRound,
    ChartNoAxesCombined,
];

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
                    class="bg-brand-100/80 animate-landing-drift pointer-events-none absolute -end-32 -top-40 size-[34rem] rounded-full blur-3xl motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <div
                    class="bg-aqua-tint/70 animate-landing-drift-offset pointer-events-none absolute -start-40 -bottom-56 size-[30rem] rounded-full blur-3xl motion-reduce:animate-none"
                    aria-hidden="true"
                />

                <div
                    class="relative mx-auto grid max-w-7xl grid-cols-[minmax(0,1fr)] items-center gap-12 px-5 py-14 sm:px-8 sm:py-18 lg:grid-cols-[0.82fr_1.18fr] lg:gap-10 lg:px-10 lg:py-22"
                >
                    <div class="relative z-10 max-w-xl min-w-0">
                        <span
                            v-reveal
                            class="border-brand-200 bg-surface text-brand-700 shadow-card rounded-pill inline-flex items-center gap-2 border px-3.5 py-2 text-[11px] font-bold tracking-[0.08em] uppercase"
                        >
                            <span class="relative flex size-2">
                                <span
                                    class="bg-success absolute inline-flex size-full animate-ping rounded-full opacity-60 motion-reduce:animate-none"
                                />
                                <span
                                    class="bg-success relative inline-flex size-2 rounded-full"
                                />
                            </span>
                            {{ content.hero.eyebrow }}
                        </span>
                        <h1
                            id="hero-title"
                            v-reveal="80"
                            class="font-heading text-ink-night mt-5 text-[clamp(2.45rem,5vw,4.35rem)] leading-[1.02] font-bold tracking-[-0.05em]"
                        >
                            {{ content.hero.title }}
                        </h1>
                        <p
                            v-reveal="160"
                            class="text-ink-slate mt-6 max-w-lg text-[16px] leading-7 sm:text-[18px] sm:leading-8"
                        >
                            {{ content.hero.description }}
                        </p>
                        <div
                            v-reveal="240"
                            class="mt-8 flex flex-wrap items-center gap-3"
                        >
                            <Button
                                as-child
                                class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 group h-12 rounded-md px-6 text-[14px] font-semibold"
                            >
                                <a href="#how-it-works">
                                    {{ content.hero.primary_cta }}
                                    <ArrowRight
                                        class="size-4 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none rtl:group-hover:-translate-x-0.5"
                                    />
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
                                v-reveal="320 + index * 90"
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
                        v-reveal:end="200"
                        class="relative mx-auto w-full max-w-[720px] min-w-0 pb-10 sm:pb-14 lg:ms-auto"
                    >
                        <div
                            class="bg-brand-200/60 absolute -inset-3 rotate-1 rounded-xl"
                            aria-hidden="true"
                        />
                        <div
                            v-reveal:tilt="450"
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
                            v-reveal:rise="650"
                            class="border-brand-900 bg-brand-900 shadow-pop animate-landing-float absolute start-5 -bottom-1 w-[112px] overflow-hidden rounded-xl border-[5px] motion-reduce:animate-none sm:start-8 sm:-bottom-2 sm:w-[150px] sm:border-[6px] lg:-start-7 lg:w-[168px]"
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
                            v-reveal:pop="850"
                            class="border-line bg-surface shadow-hover animate-landing-float-slow absolute end-3 bottom-2 hidden max-w-[220px] items-center gap-3 rounded-lg border p-3 motion-reduce:animate-none sm:flex lg:-end-4"
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
                id="how-it-works"
                class="bg-surface scroll-mt-20 py-14 sm:py-18 lg:py-20"
                aria-labelledby="journey-title"
            >
                <div
                    class="mx-auto grid max-w-7xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-[0.72fr_1.28fr] lg:gap-16 lg:px-10"
                >
                    <div>
                        <p
                            v-reveal
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.journey.eyebrow }}
                        </p>
                        <h2
                            id="journey-title"
                            v-reveal="80"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.1rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.journey.title }}
                        </h2>
                        <p
                            v-reveal="160"
                            class="text-ink-slate mt-4 text-[15px] leading-7"
                        >
                            {{ content.journey.description }}
                        </p>
                    </div>

                    <ol class="relative grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="(step, index) in content.journey.steps"
                            :key="step.title"
                            v-reveal="index * 100"
                            class="border-line bg-app shadow-card hover:bg-surface hover:shadow-hover relative rounded-lg border p-5 transition-[translate,box-shadow,background-color] duration-200 hover:-translate-y-1 motion-reduce:transition-none"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    v-reveal:pop="index * 100 + 250"
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
                id="ai"
                class="bg-brand-900 text-surface relative scroll-mt-20 overflow-hidden py-14 sm:py-18 lg:py-20"
                aria-labelledby="ai-title"
            >
                <div
                    class="bg-ai/20 animate-landing-drift pointer-events-none absolute -end-32 -top-40 size-[30rem] rounded-full blur-3xl motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <div
                    class="relative mx-auto grid max-w-7xl items-center gap-8 px-5 sm:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12 lg:px-10"
                >
                    <div>
                        <p
                            v-reveal
                            class="text-brand-200 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.ai.eyebrow }}
                        </p>
                        <h2
                            id="ai-title"
                            v-reveal="80"
                            class="font-heading text-surface mt-3 text-[clamp(1.9rem,4vw,3.1rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.ai.title }}
                        </h2>
                        <p
                            v-reveal="160"
                            class="text-brand-100 mt-4 text-[15px] leading-7"
                        >
                            {{ content.ai.description }}
                        </p>
                        <!-- Short points only (client request 2026-09-30). -->
                        <ul
                            v-reveal="220"
                            class="mt-6 flex flex-wrap gap-2"
                            data-test="landing-ai-points"
                        >
                            <li
                                v-for="(item, index) in content.ai.items"
                                :key="item.title"
                                class="border-surface/15 bg-surface/8 rounded-pill inline-flex min-h-9 items-center gap-2 border px-3.5 text-[12.5px] font-semibold"
                            >
                                <component
                                    :is="aiIcons[index % aiIcons.length]"
                                    class="text-brand-200 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ item.title }}
                            </li>
                        </ul>
                    </div>

                    <figure
                        v-reveal:zoom="100"
                        class="border-surface/15 bg-surface/10 shadow-pop overflow-hidden rounded-xl border p-2 sm:p-3"
                    >
                        <div
                            class="border-surface/10 flex h-9 items-center gap-2 border-b px-2"
                        >
                            <span class="bg-surface/25 size-2 rounded-full" />
                            <span class="bg-surface/20 size-2 rounded-full" />
                            <span class="bg-surface/15 size-2 rounded-full" />
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
                </div>
            </section>

            <section
                id="platform"
                class="bg-app scroll-mt-20 py-14 sm:py-18 lg:py-20"
                aria-labelledby="features-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <p
                            v-reveal
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.features.eyebrow }}
                        </p>
                        <h2
                            id="features-title"
                            v-reveal="80"
                            class="font-heading text-ink-night mt-3 text-[clamp(1.9rem,4vw,3rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.features.title }}
                        </h2>
                        <p
                            v-if="content.features.description"
                            v-reveal="160"
                            class="text-ink-slate mx-auto mt-3 max-w-2xl text-[15px] leading-7"
                        >
                            {{ content.features.description }}
                        </p>
                    </div>

                    <!-- Train / Manage / Measure and one screenshot
                         (client request 2026-09-30). -->
                    <div class="mt-8 grid gap-3 sm:grid-cols-3 lg:mt-10">
                        <article
                            v-for="(item, index) in content.features.items"
                            :key="item.title"
                            v-reveal="index * 100"
                            class="border-line bg-surface shadow-card flex items-start gap-3 rounded-lg border p-4"
                        >
                            <span
                                class="bg-brand-100 text-brand-700 grid size-10 shrink-0 place-items-center rounded-md"
                            >
                                <component
                                    :is="
                                        featureIcons[
                                            index % featureIcons.length
                                        ]
                                    "
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <div class="min-w-0">
                                <h3
                                    class="font-heading text-brand-900 text-[15px] font-semibold"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="text-ink-slate mt-1 text-[12.5px] leading-5"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <figure
                        v-reveal:zoom="150"
                        class="border-line bg-surface shadow-hover mx-auto mt-6 max-w-5xl overflow-hidden rounded-xl border p-2 lg:mt-8"
                    >
                        <div
                            class="border-line flex h-8 items-center gap-1.5 border-b px-1"
                        >
                            <span class="bg-danger/65 size-2 rounded-full" />
                            <span class="bg-warning/75 size-2 rounded-full" />
                            <span class="bg-success/75 size-2 rounded-full" />
                        </div>
                        <img
                            src="/landing/reports-and-export-ghasido.png"
                            :alt="
                                $t(
                                    'GHASIDO reports with training statistics and progress',
                                )
                            "
                            width="1280"
                            height="853"
                            loading="lazy"
                            class="mt-2 block aspect-[1280/853] w-full rounded-md object-cover object-top"
                        />
                    </figure>
                </div>
            </section>

            <section
                id="why-us"
                class="bg-surface scroll-mt-20 py-12 sm:py-16"
                aria-labelledby="why-title"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div
                        class="grid gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:gap-12"
                    >
                        <div>
                            <p
                                v-reveal
                                class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                            >
                                {{ content.why_us.eyebrow }}
                            </p>
                            <h2
                                id="why-title"
                                v-reveal="80"
                                class="font-heading text-ink-night mt-3 text-[clamp(1.75rem,3.5vw,2.5rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                            >
                                {{ content.why_us.title }}
                            </h2>
                            <p
                                v-reveal="160"
                                class="text-ink-slate mt-4 text-[15px] leading-7"
                            >
                                {{ content.why_us.description }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <article
                                v-for="(item, index) in content.why_us.items"
                                :key="item.title"
                                v-reveal="index * 110"
                                class="border-line bg-app hover:bg-surface hover:shadow-hover group rounded-lg border p-4 transition-[translate,box-shadow,background-color] duration-200 hover:-translate-y-1 motion-reduce:transition-none"
                            >
                                <h3
                                    class="font-heading text-brand-900 flex items-center gap-2 text-[14px] font-semibold"
                                >
                                    <component
                                        :is="whyIcons[index % whyIcons.length]"
                                        class="text-brand-600 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ item.title }}
                                </h3>
                                <p
                                    class="text-ink-slate mt-1.5 text-[12px] leading-5"
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
                            v-reveal
                            class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                        >
                            {{ content.pricing.eyebrow }}
                        </p>
                        <h2
                            id="pricing-title"
                            v-reveal="80"
                            class="font-heading text-ink-night mt-3 text-[clamp(2rem,4vw,3.2rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                        >
                            {{ content.pricing.title }}
                        </h2>
                        <p
                            v-reveal="160"
                            class="text-ink-slate mx-auto mt-4 max-w-2xl text-[15px] leading-7"
                        >
                            {{ content.pricing.description }}
                        </p>

                        <div
                            v-reveal="200"
                            class="mt-7 flex flex-wrap items-center justify-center gap-3"
                        >
                            <div
                                v-if="individualPlans.length"
                                role="radiogroup"
                                :aria-label="$t('Plans for')"
                                class="border-line bg-surface shadow-card rounded-pill inline-grid grid-cols-2 gap-1 border p-1"
                            >
                                <button
                                    v-for="option in audiences"
                                    :key="option.key"
                                    type="button"
                                    role="radio"
                                    :aria-checked="audience === option.key"
                                    :tabindex="audience === option.key ? 0 : -1"
                                    :data-audience="option.key"
                                    :class="
                                        cn(
                                            'rounded-pill focus-visible:ring-brand-600 flex min-h-11 items-center justify-center gap-2 px-4 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none sm:px-6 sm:text-[14px]',
                                            audience === option.key
                                                ? 'bg-brand-600 text-surface shadow-btn'
                                                : 'text-ink-indigo hover:bg-brand-50',
                                        )
                                    "
                                    :data-test="`pricing-audience-${option.key}`"
                                    @click="audience = option.key"
                                    @keydown="onAudienceKey"
                                >
                                    <component
                                        :is="option.icon"
                                        :class="
                                            audience === option.key
                                                ? 'text-brand-100'
                                                : 'text-brand-600'
                                        "
                                        class="size-4.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ option.label }}
                                </button>
                            </div>

                            <div
                                role="radiogroup"
                                :aria-label="$t('Pricing region')"
                                class="border-line bg-surface shadow-card rounded-pill inline-grid grid-cols-2 gap-1 border p-1"
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
                    </div>

                    <div
                        v-if="shownPlans.length"
                        :key="audience"
                        :class="
                            shownPlans.length >= 4
                                ? 'max-w-7xl md:grid-cols-2 xl:grid-cols-4'
                                : 'max-w-6xl lg:grid-cols-3'
                        "
                        class="mx-auto mt-10 grid items-stretch gap-5 lg:mt-14"
                    >
                        <article
                            v-for="(plan, index) in shownPlans"
                            :key="plan.id"
                            v-reveal="index * 110"
                            :class="
                                index === featuredIndex
                                    ? 'border-brand-600 bg-brand-900 text-surface shadow-pop hover:-translate-y-1 lg:-translate-y-3 lg:hover:-translate-y-4'
                                    : 'border-line bg-surface text-ink shadow-card hover:shadow-hover hover:-translate-y-1'
                            "
                            class="relative flex h-full flex-col rounded-xl border p-6 transition-[translate,box-shadow] duration-200 motion-reduce:transition-none sm:p-7"
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
                                <div
                                    v-if="!isIndividual"
                                    class="flex items-center gap-3"
                                >
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
                                        {{
                                            isIndividual
                                                ? $t('AI points per month')
                                                : content.pricing
                                                      .ai_points_label
                                        }}
                                    </p>
                                </div>
                            </div>

                            <ul class="mt-5 flex-1 space-y-3">
                                <li
                                    v-for="item in isIndividual
                                        ? individualInclusions.map((line) =>
                                              $t(line),
                                          )
                                        : content.pricing.inclusions"
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
                                <Link :href="checkoutHref(plan)">
                                    {{ content.pricing.button_text }}
                                    <ChevronRight class="size-4" />
                                </Link>
                            </Button>
                        </article>
                    </div>

                    <!-- Hotel / Enterprise (client pricing mockup 2026-09-26):
                         larger teams are sent to Contact Us for a quote. -->
                    <div
                        v-if="!isIndividual"
                        v-reveal
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
                        v-reveal:fade
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
                        v-reveal:fade="120"
                        class="mx-auto mt-4 flex max-w-3xl flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center"
                        data-test="landing-payment-line"
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
                        v-reveal:zoom
                        class="bg-brand-900 text-surface shadow-pop relative overflow-hidden rounded-xl px-6 py-10 sm:px-12 sm:py-14 lg:px-16"
                    >
                        <div
                            class="bg-ai/25 animate-landing-drift pointer-events-none absolute -end-20 -top-40 size-96 rounded-full blur-3xl motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        <div
                            class="bg-brand-600/30 animate-landing-drift-offset pointer-events-none absolute -start-24 -bottom-48 size-80 rounded-full blur-3xl motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        <div
                            class="relative grid items-center gap-7 lg:grid-cols-[1fr_auto]"
                        >
                            <div class="max-w-3xl">
                                <p
                                    v-reveal="200"
                                    class="text-brand-200 text-[12px] font-bold tracking-[0.16em] uppercase"
                                >
                                    {{ content.call_to_action.eyebrow }}
                                </p>
                                <h2
                                    id="cta-title"
                                    v-reveal="280"
                                    class="font-heading text-surface mt-3 text-[clamp(1.9rem,4vw,3rem)] leading-[1.1] font-bold tracking-[-0.04em]"
                                >
                                    {{ content.call_to_action.title }}
                                </h2>
                                <p
                                    v-reveal="360"
                                    class="text-brand-100 mt-4 max-w-2xl text-[14px] leading-7 sm:text-[15px]"
                                >
                                    {{ content.call_to_action.description }}
                                </p>
                            </div>
                            <div
                                v-reveal="440"
                                class="flex flex-wrap items-center gap-3"
                            >
                                <Button
                                    as-child
                                    class="bg-surface text-brand-800 hover:bg-brand-50 group h-12 rounded-md px-6 text-[14px] font-semibold"
                                >
                                    <a href="#pricing">
                                        {{ content.call_to_action.button_text }}
                                        <ArrowRight
                                            class="size-4 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transition-none rtl:group-hover:-translate-x-0.5"
                                        />
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
