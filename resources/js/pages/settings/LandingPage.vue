<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Mail, Phone, Save } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { Button } from '@/components/ui/button';
import { intlLocale, tk } from '@/lib/i18n';
import type { LandingContactMessage, LandingPageContent } from '@/types';

const props = defineProps<{
    content: LandingPageContent;
    /** The Arabic copy of the same page (I18N-02). */
    contentAr: LandingPageContent;
    contactMessages: LandingContactMessage[];
}>();

const unreadMessages = computed(
    () => props.contactMessages.filter((message) => !message.read).length,
);

function markRead(message: LandingContactMessage): void {
    router.patch(message.readUrl, {}, { preserveScroll: true });
}

function sentAt(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat(intlLocale(), {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '';
}

// Two copies of the page, one per interface language (I18N-02, user request
// 2026-09-26). Each keeps its own unsaved edits while the other is shown.
const editing = ref<'en' | 'ar'>('en');
const forms = {
    en: useForm({ locale: 'en', content: props.content }),
    ar: useForm({ locale: 'ar', content: props.contentAr }),
};
const form = computed(() => forms[editing.value]);
const copies = [
    { value: 'en' as const, label: 'English', dir: 'ltr' },
    { value: 'ar' as const, label: 'العربية', dir: 'rtl' },
];
const editorSections = [
    { href: '#landing-editor-navigation', label: tk('Navigation') },
    { href: '#landing-editor-hero', label: tk('Hero') },
    { href: '#landing-editor-roles', label: tk('Roles') },
    { href: '#landing-editor-journey', label: tk('Journey') },
    { href: '#landing-editor-why', label: tk('Why GHASIDO') },
    { href: '#landing-editor-about', label: tk('About') },
    { href: '#landing-editor-features', label: tk('Features') },
    { href: '#landing-editor-ai', label: tk('AI') },
    { href: '#landing-editor-pricing', label: tk('Plans') },
    { href: '#landing-editor-checkout', label: tk('Checkout') },
    { href: '#landing-editor-cta', label: tk('Closing CTA') },
    { href: '#landing-editor-support', label: tk('Contact details') },
    { href: '#landing-editor-contact', label: tk('Contact page') },
    { href: '#landing-editor-messages', label: tk('Messages') },
    { href: '#landing-editor-footer', label: tk('Footer') },
];

function save(): void {
    const current = form.value;

    current.patch('/settings/landing-page', {
        preserveScroll: true,
        onSuccess: () => current.defaults(),
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Settings'), href: '/settings/profile' },
            { title: tk('Landing page'), href: '/settings/landing-page' },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Landing page content')" />

    <h1 class="sr-only">{{ $t('Landing page content') }}</h1>

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                :title="$t('Landing page content')"
                :description="
                    $t('Edit the words visitors see on GHASIDO’s public page.')
                "
            />
            <Button as-child variant="outline" size="sm" class="shrink-0">
                <Link href="/" target="_blank" rel="noreferrer">
                    {{ $t('Preview page') }} <ExternalLink class="size-3.5" />
                </Link>
            </Button>
        </div>

        <div
            class="border-line bg-surface shadow-card flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
        >
            <div>
                <p class="text-ink-indigo text-[13px] font-semibold">
                    {{ $t('Page language') }}
                </p>
                <p class="text-ink-slate text-[12px]">
                    {{
                        $t(
                            'Edit the English and the Arabic page separately. Contact details are shared.',
                        )
                    }}
                </p>
            </div>
            <div
                class="bg-app inline-flex rounded-md p-1"
                role="tablist"
                :aria-label="$t('Page language')"
            >
                <button
                    v-for="copy in copies"
                    :key="copy.value"
                    type="button"
                    role="tab"
                    :aria-selected="editing === copy.value"
                    :data-test="`landing-copy-${copy.value}`"
                    class="focus-visible:ring-brand-600/15 min-h-9 rounded px-4 text-[12px] font-semibold transition-colors focus-visible:ring-3 focus-visible:outline-none"
                    :class="
                        editing === copy.value
                            ? 'bg-surface text-brand-700 shadow-card'
                            : 'text-ink-slate hover:bg-brand-50'
                    "
                    @click="editing = copy.value"
                >
                    <span :lang="copy.value" :dir="copy.dir">{{
                        copy.label
                    }}</span>
                    <span
                        v-if="forms[copy.value].isDirty"
                        class="bg-warning ms-1.5 inline-block size-1.5 rounded-full align-middle"
                        :aria-label="$t('Unsaved changes')"
                    />
                </button>
            </div>
        </div>

        <nav
            :aria-label="$t('Landing page editor sections')"
            class="border-line bg-surface/95 shadow-card sticky top-2 z-10 grid grid-cols-2 gap-1 rounded-lg border p-1 backdrop-blur-sm sm:grid-cols-4"
        >
            <a
                v-for="section in editorSections"
                :key="section.href"
                :href="section.href"
                class="text-ink-indigo hover:bg-brand-50 focus-visible:ring-brand-600 flex min-w-0 items-center justify-center rounded-md px-2 py-2 text-center text-[12px] font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
            >
                {{ $t(section.label) }}
            </a>
        </nav>

        <!-- Arabic copy fields are typed right to left. -->
        <div
            class="space-y-5"
            :class="
                editing === 'ar' &&
                '[&_input:not([type=tel]):not([type=email])]:[direction:rtl] [&_textarea]:[direction:rtl]'
            "
        >
            <PanelCard
                id="landing-editor-navigation"
                class="scroll-mt-20"
                :title="$t('Navigation labels')"
                title-id="landing-navigation"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.navigation.why_us"
                        :label="$t('Why us link')"
                        :error="form.errors['content.navigation.why_us']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.about"
                        :label="$t('About link')"
                        :error="form.errors['content.navigation.about']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.platform"
                        :label="$t('Features link')"
                        :error="form.errors['content.navigation.platform']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.ai_practice"
                        :label="$t('AI link')"
                        :error="form.errors['content.navigation.ai_practice']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.roles"
                        :label="$t('Roles link')"
                        :error="form.errors['content.navigation.roles']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.pricing"
                        :label="$t('Plans link')"
                        :error="form.errors['content.navigation.pricing']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.login"
                        :label="$t('Log in button')"
                        :error="form.errors['content.navigation.login']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.get_started"
                        :label="$t('Get started button')"
                        :error="form.errors['content.navigation.get_started']"
                    />
                    <LessonsField
                        v-model="form.content.navigation.open_dashboard"
                        :label="$t('Signed-in button')"
                        :error="
                            form.errors['content.navigation.open_dashboard']
                        "
                    />
                    <LessonsField
                        v-model="form.content.navigation.contact"
                        :label="$t('Contact link')"
                        :error="form.errors['content.navigation.contact']"
                    />
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-hero"
                class="scroll-mt-20"
                :title="$t('Hero section')"
                title-id="landing-hero"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.hero.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.hero.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.hero.title"
                        :label="$t('Headline')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.hero.title']"
                    />
                    <LessonsField
                        v-model="form.content.hero.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="3"
                        :error="form.errors['content.hero.description']"
                    />
                    <LessonsField
                        v-model="form.content.hero.image_alt"
                        :label="$t('Product screenshot description')"
                        :error="form.errors['content.hero.image_alt']"
                    />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <LessonsField
                            v-for="(point, index) in form.content.hero
                                .proof_points"
                            :key="`proof-${index}`"
                            v-model="form.content.hero.proof_points[index]"
                            :label="
                                $t('Proof point :number', { number: index + 1 })
                            "
                            :error="
                                form.errors[
                                    `content.hero.proof_points.${index}`
                                ]
                            "
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.hero.primary_cta"
                            :label="$t('Main button')"
                            :error="form.errors['content.hero.primary_cta']"
                        />
                        <LessonsField
                            v-model="form.content.hero.secondary_cta"
                            :label="$t('Second button')"
                            :error="form.errors['content.hero.secondary_cta']"
                        />
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-roles"
                class="scroll-mt-20"
                :title="$t('Roles')"
                title-id="landing-roles"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.roles.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.roles.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.roles.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.roles.title']"
                    />
                    <LessonsField
                        v-model="form.content.roles.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.roles.description']"
                    />
                    <div
                        v-for="(role, roleIndex) in form.content.roles.items"
                        :key="`role-${roleIndex}`"
                        class="border-line bg-app/60 grid gap-3 rounded-md border p-3"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <LessonsField
                                v-model="role.title"
                                :label="
                                    $t('Role :number title', {
                                        number: roleIndex + 1,
                                    })
                                "
                                :error="
                                    form.errors[
                                        `content.roles.items.${roleIndex}.title`
                                    ]
                                "
                            />
                            <LessonsField
                                v-model="role.description"
                                :label="
                                    $t('Role :number description', {
                                        number: roleIndex + 1,
                                    })
                                "
                                type="textarea"
                                :rows="2"
                                :error="
                                    form.errors[
                                        `content.roles.items.${roleIndex}.description`
                                    ]
                                "
                            />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <LessonsField
                                v-for="(
                                    _, capabilityIndex
                                ) in role.capabilities"
                                :key="`role-${roleIndex}-capability-${capabilityIndex}`"
                                v-model="role.capabilities[capabilityIndex]"
                                :label="
                                    $t('Capability :number', {
                                        number: capabilityIndex + 1,
                                    })
                                "
                                :error="
                                    form.errors[
                                        `content.roles.items.${roleIndex}.capabilities.${capabilityIndex}`
                                    ]
                                "
                            />
                        </div>
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-journey"
                class="scroll-mt-20"
                :title="$t('Learner journey')"
                title-id="landing-journey"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.journey.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.journey.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.journey.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.journey.title']"
                    />
                    <LessonsField
                        v-model="form.content.journey.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.journey.description']"
                    />
                    <div
                        v-for="(step, index) in form.content.journey.steps"
                        :key="`journey-${index}`"
                        class="border-line bg-app/60 grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <LessonsField
                            v-model="step.title"
                            :label="
                                $t('Step :number title', { number: index + 1 })
                            "
                            :error="
                                form.errors[
                                    `content.journey.steps.${index}.title`
                                ]
                            "
                        />
                        <LessonsField
                            v-model="step.description"
                            :label="
                                $t('Step :number description', {
                                    number: index + 1,
                                })
                            "
                            type="textarea"
                            :rows="2"
                            :error="
                                form.errors[
                                    `content.journey.steps.${index}.description`
                                ]
                            "
                        />
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-why"
                class="scroll-mt-20"
                :title="$t('Why GHASIDO')"
                title-id="landing-why-us"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.why_us.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.why_us.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.why_us.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.why_us.title']"
                    />
                    <LessonsField
                        v-model="form.content.why_us.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.why_us.description']"
                    />
                    <div
                        v-for="(item, index) in form.content.why_us.items"
                        :key="`why-${index}`"
                        class="border-line bg-app/60 grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <LessonsField
                            v-model="item.title"
                            :label="
                                $t('Benefit :number title', {
                                    number: index + 1,
                                })
                            "
                            :error="
                                form.errors[
                                    `content.why_us.items.${index}.title`
                                ]
                            "
                        />
                        <LessonsField
                            v-model="item.description"
                            :label="
                                $t('Benefit :number description', {
                                    number: index + 1,
                                })
                            "
                            type="textarea"
                            :rows="2"
                            :error="
                                form.errors[
                                    `content.why_us.items.${index}.description`
                                ]
                            "
                        />
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-about"
                class="scroll-mt-20"
                :title="$t('About us')"
                title-id="landing-about"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.about.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.about.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.about.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.about.title']"
                    />
                    <LessonsField
                        v-model="form.content.about.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="4"
                        :error="form.errors['content.about.description']"
                    />
                    <LessonsField
                        v-model="form.content.about.learner_note"
                        :label="$t('Learner note')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.about.learner_note']"
                    />
                    <LessonsField
                        v-model="form.content.about.image_alt"
                        :label="$t('Learner screenshot description')"
                        :error="form.errors['content.about.image_alt']"
                    />
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-features"
                class="scroll-mt-20"
                :title="$t('Features')"
                title-id="landing-features"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.features.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.features.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.features.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.features.title']"
                    />
                    <LessonsField
                        v-model="form.content.features.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.features.description']"
                    />
                    <div
                        v-for="(item, index) in form.content.features.items"
                        :key="`feature-${index}`"
                        class="border-line bg-app/60 grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <LessonsField
                            v-model="item.title"
                            :label="
                                $t('Feature :number title', {
                                    number: index + 1,
                                })
                            "
                            :error="
                                form.errors[
                                    `content.features.items.${index}.title`
                                ]
                            "
                        />
                        <LessonsField
                            v-model="item.description"
                            :label="
                                $t('Feature :number description', {
                                    number: index + 1,
                                })
                            "
                            type="textarea"
                            :rows="2"
                            :error="
                                form.errors[
                                    `content.features.items.${index}.description`
                                ]
                            "
                        />
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-ai"
                class="scroll-mt-20"
                :title="$t('AI capabilities')"
                title-id="landing-ai"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.ai.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.ai.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.ai.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.ai.title']"
                    />
                    <LessonsField
                        v-model="form.content.ai.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.ai.description']"
                    />
                    <LessonsField
                        v-model="form.content.ai.review_note"
                        :label="$t('AI review note')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.ai.review_note']"
                    />
                    <div
                        v-for="(item, index) in form.content.ai.items"
                        :key="`ai-${index}`"
                        class="border-line bg-app/60 grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <LessonsField
                            v-model="item.title"
                            :label="
                                $t('AI capability :number title', {
                                    number: index + 1,
                                })
                            "
                            :error="
                                form.errors[`content.ai.items.${index}.title`]
                            "
                        />
                        <LessonsField
                            v-model="item.description"
                            :label="
                                $t('AI capability :number description', {
                                    number: index + 1,
                                })
                            "
                            type="textarea"
                            :rows="2"
                            :error="
                                form.errors[
                                    `content.ai.items.${index}.description`
                                ]
                            "
                        />
                    </div>
                    <p class="text-ink-slate text-[12px] leading-5">
                        {{
                            $t(
                                'This note appears beside the AI capabilities to explain that generated content is reviewed before publishing.',
                            )
                        }}
                    </p>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-pricing"
                class="scroll-mt-20"
                :title="$t('Subscription plans')"
                title-id="landing-pricing"
            >
                <div class="grid gap-4">
                    <p class="text-ink-slate text-[12px] leading-5">
                        {{
                            $t(
                                'Plan names, prices, seat limits and AI points come from the Subscriptions screen. These fields control the public copy.',
                            )
                        }}
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.pricing.eyebrow"
                            :label="$t('Eyebrow')"
                            :error="form.errors['content.pricing.eyebrow']"
                        />
                        <LessonsField
                            v-model="form.content.pricing.featured_label"
                            :label="$t('Featured plan badge')"
                            :error="
                                form.errors['content.pricing.featured_label']
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.pricing.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.pricing.title']"
                    />
                    <LessonsField
                        v-model="form.content.pricing.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.pricing.description']"
                    />
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <LessonsField
                            v-model="form.content.pricing.monthly_label"
                            :label="$t('Billing period label')"
                            :error="
                                form.errors['content.pricing.monthly_label']
                            "
                        />
                        <LessonsField
                            v-model="form.content.pricing.employees_label"
                            :label="$t('Employee limit label')"
                            :error="
                                form.errors['content.pricing.employees_label']
                            "
                        />
                        <LessonsField
                            v-model="form.content.pricing.ai_points_label"
                            :label="$t('AI points label')"
                            :error="
                                form.errors['content.pricing.ai_points_label']
                            "
                        />
                        <LessonsField
                            v-model="form.content.pricing.button_text"
                            :label="$t('Plan button')"
                            :error="form.errors['content.pricing.button_text']"
                        />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LessonsField
                            v-for="(_, index) in form.content.pricing
                                .inclusions"
                            :key="`inclusion-${index}`"
                            v-model="form.content.pricing.inclusions[index]"
                            :label="
                                $t('Shared inclusion :number', {
                                    number: index + 1,
                                })
                            "
                            :error="
                                form.errors[
                                    `content.pricing.inclusions.${index}`
                                ]
                            "
                        />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.pricing.region_algeria"
                            :label="$t('Algeria price switch (DZD)')"
                            :error="
                                form.errors['content.pricing.region_algeria']
                            "
                        />
                        <LessonsField
                            v-model="form.content.pricing.region_international"
                            :label="$t('International price switch (USD)')"
                            :hint="
                                $t(
                                    'Set each plan\'s DZD and USD prices in Subscriptions.',
                                )
                            "
                            :error="
                                form.errors[
                                    'content.pricing.region_international'
                                ]
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.pricing.footnote"
                        :label="$t('Approval note')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.pricing.footnote']"
                    />
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-checkout"
                class="scroll-mt-20"
                :title="$t('Checkout page')"
                title-id="landing-checkout"
            >
                <div class="grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.checkout.eyebrow"
                            :label="$t('Eyebrow')"
                            :error="form.errors['content.checkout.eyebrow']"
                        />
                        <LessonsField
                            v-model="form.content.checkout.back_to_plans"
                            :label="$t('Back link')"
                            :error="
                                form.errors['content.checkout.back_to_plans']
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.checkout.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.checkout.title']"
                    />
                    <LessonsField
                        v-model="form.content.checkout.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.checkout.description']"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.checkout.form_title"
                            :label="$t('Form title')"
                            :error="form.errors['content.checkout.form_title']"
                        />
                        <LessonsField
                            v-model="form.content.checkout.summary_title"
                            :label="$t('Plan summary title')"
                            :error="
                                form.errors['content.checkout.summary_title']
                            "
                        />
                        <LessonsField
                            v-model="form.content.checkout.payment_title"
                            :label="$t('Payment title')"
                            :error="
                                form.errors['content.checkout.payment_title']
                            "
                        />
                        <LessonsField
                            v-model="form.content.checkout.submit_button"
                            :label="$t('Submit button')"
                            :error="
                                form.errors['content.checkout.submit_button']
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.checkout.payment_description"
                        :label="$t('Payment explanation')"
                        type="textarea"
                        :rows="2"
                        :error="
                            form.errors['content.checkout.payment_description']
                        "
                    />
                    <LessonsField
                        v-model="form.content.checkout.approval_note"
                        :label="$t('Approval note')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.checkout.approval_note']"
                    />
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-cta"
                class="scroll-mt-20"
                :title="$t('Closing call to action')"
                title-id="landing-cta"
            >
                <div class="grid gap-4">
                    <LessonsField
                        v-model="form.content.call_to_action.eyebrow"
                        :label="$t('Eyebrow')"
                        :error="form.errors['content.call_to_action.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.call_to_action.title"
                        :label="$t('Heading')"
                        :error="form.errors['content.call_to_action.title']"
                    />
                    <LessonsField
                        v-model="form.content.call_to_action.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="
                            form.errors['content.call_to_action.description']
                        "
                    />
                    <LessonsField
                        v-model="form.content.call_to_action.button_text"
                        :label="$t('Button')"
                        :error="
                            form.errors['content.call_to_action.button_text']
                        "
                    />
                </div>
            </PanelCard>

            <PanelCard
                v-if="editing === 'en'"
                id="landing-editor-support"
                class="scroll-mt-20"
                :title="$t('Contact details')"
                title-id="landing-support"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.support.phone"
                        :label="$t('Phone number')"
                        placeholder="+213 555 12 34 56"
                        :hint="
                            $t(
                                'Shown on the Contact Us page and in the footer. Leave blank to hide it.',
                            )
                        "
                        :error="form.errors['content.support.phone']"
                    />
                    <LessonsField
                        v-model="form.content.support.email"
                        :label="$t('Email address')"
                        placeholder="contact@ghasido.com"
                        :hint="
                            $t(
                                'Shown on the Contact Us page; contact form messages are also sent here.',
                            )
                        "
                        :error="form.errors['content.support.email']"
                    />
                    <LessonsField
                        v-model="form.content.support.whatsapp_number"
                        :label="$t('WhatsApp support number')"
                        placeholder="+213 555 12 34 56"
                        :hint="
                            $t(
                                'Use the international country code. Leave blank to hide WhatsApp support from the public page.',
                            )
                        "
                        :error="form.errors['content.support.whatsapp_number']"
                    />
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-contact"
                class="scroll-mt-20"
                :title="$t('Contact Us page')"
                title-id="landing-contact"
            >
                <div class="grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.contact.eyebrow"
                            :label="$t('Eyebrow')"
                            :error="form.errors['content.contact.eyebrow']"
                        />
                        <LessonsField
                            v-model="form.content.contact.title"
                            :label="$t('Heading')"
                            :error="form.errors['content.contact.title']"
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.contact.description"
                        :label="$t('Description')"
                        type="textarea"
                        :rows="2"
                        :error="form.errors['content.contact.description']"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.contact.form_title"
                            :label="$t('Form heading')"
                            :error="form.errors['content.contact.form_title']"
                        />
                        <LessonsField
                            v-model="form.content.contact.submit_button"
                            :label="$t('Send button')"
                            :error="
                                form.errors['content.contact.submit_button']
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.contact.success_message"
                        :label="$t('Message after sending')"
                        :error="form.errors['content.contact.success_message']"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.contact.enterprise_title"
                            :label="$t('Enterprise block title')"
                            :error="
                                form.errors['content.contact.enterprise_title']
                            "
                        />
                        <LessonsField
                            v-model="form.content.contact.enterprise_subtitle"
                            :label="$t('Enterprise block subtitle')"
                            :error="
                                form.errors[
                                    'content.contact.enterprise_subtitle'
                                ]
                            "
                        />
                    </div>
                    <LessonsField
                        v-model="form.content.contact.enterprise_description"
                        :label="$t('Enterprise block description')"
                        type="textarea"
                        :rows="2"
                        :error="
                            form.errors[
                                'content.contact.enterprise_description'
                            ]
                        "
                    />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LessonsField
                            v-for="(_, index) in form.content.contact
                                .enterprise_points"
                            :key="`enterprise-${index}`"
                            v-model="
                                form.content.contact.enterprise_points[index]
                            "
                            :label="
                                $t('Enterprise point :number', {
                                    number: index + 1,
                                })
                            "
                            :error="
                                form.errors[
                                    `content.contact.enterprise_points.${index}`
                                ]
                            "
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <LessonsField
                            v-model="form.content.contact.enterprise_button"
                            :label="$t('Enterprise button')"
                            :error="
                                form.errors['content.contact.enterprise_button']
                            "
                        />
                        <LessonsField
                            v-model="form.content.contact.enterprise_note"
                            :label="$t('Text under the button')"
                            :error="
                                form.errors['content.contact.enterprise_note']
                            "
                        />
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                id="landing-editor-messages"
                class="scroll-mt-20"
                :title="
                    unreadMessages
                        ? $t('Contact messages (:count new)', {
                              count: unreadMessages,
                          })
                        : $t('Contact messages')
                "
                title-id="landing-messages"
            >
                <p
                    v-if="contactMessages.length === 0"
                    class="text-ink-slate text-[13px]"
                >
                    {{
                        $t(
                            'No messages yet. Messages sent from the Contact Us page appear here.',
                        )
                    }}
                </p>
                <ul v-else class="grid gap-3">
                    <li
                        v-for="message in contactMessages"
                        :key="message.id"
                        :class="
                            message.read
                                ? 'border-line bg-surface'
                                : 'border-brand-200 bg-brand-50'
                        "
                        class="rounded-md border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-ink-night text-[14px] font-semibold"
                                >
                                    {{ message.name }}
                                    <span
                                        v-if="message.organisation"
                                        class="text-ink-slate font-normal"
                                        >· {{ message.organisation }}</span
                                    >
                                    <span
                                        v-if="message.employees"
                                        class="text-ink-slate font-normal"
                                        >·
                                        {{
                                            $t(':count employees', {
                                                count: message.employees,
                                            })
                                        }}</span
                                    >
                                </p>
                                <p
                                    class="text-ink-slate mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[12px]"
                                >
                                    <a
                                        :href="`mailto:${message.email}`"
                                        class="text-brand-700 inline-flex items-center gap-1 hover:underline"
                                    >
                                        <Mail
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ message.email }}
                                    </a>
                                    <a
                                        v-if="message.phone"
                                        :href="`tel:${message.phone}`"
                                        class="text-brand-700 inline-flex items-center gap-1 hover:underline"
                                        dir="ltr"
                                    >
                                        <Phone
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ message.phone }}
                                    </a>
                                    <span>{{ sentAt(message.sentAt) }}</span>
                                </p>
                            </div>
                            <Button
                                v-if="!message.read"
                                size="sm"
                                variant="outline"
                                @click="markRead(message)"
                            >
                                {{ $t('Mark as read') }}
                            </Button>
                            <span
                                v-else
                                class="text-ink-slate text-[12px] font-semibold"
                                >{{ $t('Read') }}</span
                            >
                        </div>
                        <p
                            class="text-ink mt-3 text-[13px] leading-6 whitespace-pre-line"
                        >
                            {{ message.message }}
                        </p>
                    </li>
                </ul>
            </PanelCard>

            <PanelCard
                id="landing-editor-footer"
                class="scroll-mt-20"
                :title="$t('Footer')"
                title-id="landing-footer"
            >
                <LessonsField
                    v-model="form.content.footer.tagline"
                    :label="$t('Footer tagline')"
                    :error="form.errors['content.footer.tagline']"
                />
            </PanelCard>
        </div>

        <div
            class="border-line bg-surface/95 shadow-hover sticky bottom-3 flex items-center justify-between gap-3 rounded-lg border p-3 backdrop-blur-sm"
        >
            <p
                class="text-ink-slate text-[12px]"
                role="status"
                aria-live="polite"
            >
                {{
                    form.processing
                        ? $t('Saving changes…')
                        : form.isDirty
                          ? $t('Unsaved changes')
                          : $t('Changes saved')
                }}
            </p>
            <Button
                :disabled="form.processing || !form.isDirty"
                class="shrink-0"
                @click="save"
            >
                <Save class="size-4" />
                {{
                    form.processing
                        ? $t('Saving…')
                        : form.isDirty
                          ? $t('Save changes')
                          : $t('Saved')
                }}
            </Button>
        </div>
    </div>
</template>
