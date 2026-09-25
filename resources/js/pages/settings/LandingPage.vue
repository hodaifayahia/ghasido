<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, Save } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PanelCard from '@/components/common/PanelCard.vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { Button } from '@/components/ui/button';
import type { LandingPageContent } from '@/types';

const props = defineProps<{ content: LandingPageContent }>();

const form = useForm({ content: props.content });
const editorSections = [
    { href: '#landing-editor-navigation', label: 'Navigation' },
    { href: '#landing-editor-hero', label: 'Hero' },
    { href: '#landing-editor-roles', label: 'Roles' },
    { href: '#landing-editor-journey', label: 'Journey' },
    { href: '#landing-editor-why', label: 'Why Guesvia' },
    { href: '#landing-editor-about', label: 'About' },
    { href: '#landing-editor-features', label: 'Features' },
    { href: '#landing-editor-ai', label: 'AI' },
    { href: '#landing-editor-pricing', label: 'Plans' },
    { href: '#landing-editor-checkout', label: 'Checkout' },
    { href: '#landing-editor-cta', label: 'Closing CTA' },
    { href: '#landing-editor-support', label: 'Support' },
    { href: '#landing-editor-footer', label: 'Footer' },
];

function save(): void {
    form.patch('/settings/landing-page', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/profile' },
            { title: 'Landing page', href: '/settings/landing-page' },
        ],
    },
});
</script>

<template>
    <Head title="Landing page content" />

    <h1 class="sr-only">Landing page content</h1>

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Landing page content"
                description="Edit the words visitors see on Guesvia’s public page."
            />
            <Button as-child variant="outline" size="sm" class="shrink-0">
                <Link href="/" target="_blank" rel="noreferrer">
                    Preview page <ExternalLink class="size-3.5" />
                </Link>
            </Button>
        </div>

        <nav
            aria-label="Landing page editor sections"
            class="border-line bg-surface/95 shadow-card sticky top-2 z-10 grid grid-cols-2 gap-1 rounded-lg border p-1 backdrop-blur-sm sm:grid-cols-4"
        >
            <a
                v-for="section in editorSections"
                :key="section.href"
                :href="section.href"
                class="text-ink-indigo hover:bg-brand-50 focus-visible:ring-brand-600 flex min-w-0 items-center justify-center rounded-md px-2 py-2 text-center text-[12px] font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-none"
            >
                {{ section.label }}
            </a>
        </nav>

        <PanelCard
            id="landing-editor-navigation"
            class="scroll-mt-20"
            title="Navigation labels"
            title-id="landing-navigation"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <LessonsField
                    v-model="form.content.navigation.why_us"
                    label="Why us link"
                    :error="form.errors['content.navigation.why_us']"
                />
                <LessonsField
                    v-model="form.content.navigation.about"
                    label="About link"
                    :error="form.errors['content.navigation.about']"
                />
                <LessonsField
                    v-model="form.content.navigation.platform"
                    label="Features link"
                    :error="form.errors['content.navigation.platform']"
                />
                <LessonsField
                    v-model="form.content.navigation.ai_practice"
                    label="AI link"
                    :error="form.errors['content.navigation.ai_practice']"
                />
                <LessonsField
                    v-model="form.content.navigation.roles"
                    label="Roles link"
                    :error="form.errors['content.navigation.roles']"
                />
                <LessonsField
                    v-model="form.content.navigation.pricing"
                    label="Plans link"
                    :error="form.errors['content.navigation.pricing']"
                />
                <LessonsField
                    v-model="form.content.navigation.login"
                    label="Log in button"
                    :error="form.errors['content.navigation.login']"
                />
                <LessonsField
                    v-model="form.content.navigation.get_started"
                    label="Get started button"
                    :error="form.errors['content.navigation.get_started']"
                />
                <LessonsField
                    v-model="form.content.navigation.open_dashboard"
                    label="Signed-in button"
                    :error="form.errors['content.navigation.open_dashboard']"
                />
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-hero"
            class="scroll-mt-20"
            title="Hero section"
            title-id="landing-hero"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.hero.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.hero.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.hero.title"
                    label="Headline"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.hero.title']"
                />
                <LessonsField
                    v-model="form.content.hero.description"
                    label="Description"
                    type="textarea"
                    :rows="3"
                    :error="form.errors['content.hero.description']"
                />
                <LessonsField
                    v-model="form.content.hero.image_alt"
                    label="Product screenshot description"
                    :error="form.errors['content.hero.image_alt']"
                />
                <div class="grid gap-4 sm:grid-cols-3">
                    <LessonsField
                        v-for="(point, index) in form.content.hero.proof_points"
                        :key="`proof-${index}`"
                        v-model="form.content.hero.proof_points[index]"
                        :label="`Proof point ${index + 1}`"
                        :error="
                            form.errors[`content.hero.proof_points.${index}`]
                        "
                    />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.hero.primary_cta"
                        label="Main button"
                        :error="form.errors['content.hero.primary_cta']"
                    />
                    <LessonsField
                        v-model="form.content.hero.secondary_cta"
                        label="Second button"
                        :error="form.errors['content.hero.secondary_cta']"
                    />
                </div>
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-roles"
            class="scroll-mt-20"
            title="Roles"
            title-id="landing-roles"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.roles.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.roles.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.roles.title"
                    label="Heading"
                    :error="form.errors['content.roles.title']"
                />
                <LessonsField
                    v-model="form.content.roles.description"
                    label="Description"
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
                            :label="`Role ${roleIndex + 1} title`"
                            :error="
                                form.errors[
                                    `content.roles.items.${roleIndex}.title`
                                ]
                            "
                        />
                        <LessonsField
                            v-model="role.description"
                            :label="`Role ${roleIndex + 1} description`"
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
                            v-for="(_, capabilityIndex) in role.capabilities"
                            :key="`role-${roleIndex}-capability-${capabilityIndex}`"
                            v-model="role.capabilities[capabilityIndex]"
                            :label="`Capability ${capabilityIndex + 1}`"
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
            title="Learner journey"
            title-id="landing-journey"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.journey.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.journey.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.journey.title"
                    label="Heading"
                    :error="form.errors['content.journey.title']"
                />
                <LessonsField
                    v-model="form.content.journey.description"
                    label="Description"
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
                        :label="`Step ${index + 1} title`"
                        :error="
                            form.errors[`content.journey.steps.${index}.title`]
                        "
                    />
                    <LessonsField
                        v-model="step.description"
                        :label="`Step ${index + 1} description`"
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
            title="Why Guesvia"
            title-id="landing-why-us"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.why_us.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.why_us.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.why_us.title"
                    label="Heading"
                    :error="form.errors['content.why_us.title']"
                />
                <LessonsField
                    v-model="form.content.why_us.description"
                    label="Description"
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
                        :label="`Benefit ${index + 1} title`"
                        :error="
                            form.errors[`content.why_us.items.${index}.title`]
                        "
                    />
                    <LessonsField
                        v-model="item.description"
                        :label="`Benefit ${index + 1} description`"
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
            title="About us"
            title-id="landing-about"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.about.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.about.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.about.title"
                    label="Heading"
                    :error="form.errors['content.about.title']"
                />
                <LessonsField
                    v-model="form.content.about.description"
                    label="Description"
                    type="textarea"
                    :rows="4"
                    :error="form.errors['content.about.description']"
                />
                <LessonsField
                    v-model="form.content.about.learner_note"
                    label="Learner note"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.about.learner_note']"
                />
                <LessonsField
                    v-model="form.content.about.image_alt"
                    label="Learner screenshot description"
                    :error="form.errors['content.about.image_alt']"
                />
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-features"
            class="scroll-mt-20"
            title="Features"
            title-id="landing-features"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.features.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.features.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.features.title"
                    label="Heading"
                    :error="form.errors['content.features.title']"
                />
                <LessonsField
                    v-model="form.content.features.description"
                    label="Description"
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
                        :label="`Feature ${index + 1} title`"
                        :error="
                            form.errors[`content.features.items.${index}.title`]
                        "
                    />
                    <LessonsField
                        v-model="item.description"
                        :label="`Feature ${index + 1} description`"
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
            title="AI capabilities"
            title-id="landing-ai"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.ai.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.ai.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.ai.title"
                    label="Heading"
                    :error="form.errors['content.ai.title']"
                />
                <LessonsField
                    v-model="form.content.ai.description"
                    label="Description"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.ai.description']"
                />
                <LessonsField
                    v-model="form.content.ai.review_note"
                    label="AI review note"
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
                        :label="`AI capability ${index + 1} title`"
                        :error="form.errors[`content.ai.items.${index}.title`]"
                    />
                    <LessonsField
                        v-model="item.description"
                        :label="`AI capability ${index + 1} description`"
                        type="textarea"
                        :rows="2"
                        :error="
                            form.errors[`content.ai.items.${index}.description`]
                        "
                    />
                </div>
                <p class="text-ink-slate text-[12px] leading-5">
                    This note appears beside the AI capabilities to explain that
                    generated content is reviewed before publishing.
                </p>
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-pricing"
            class="scroll-mt-20"
            title="Subscription plans"
            title-id="landing-pricing"
        >
            <div class="grid gap-4">
                <p class="text-ink-slate text-[12px] leading-5">
                    Plan names, prices, seat limits and AI points come from the
                    Subscriptions screen. These fields control the public copy.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.pricing.eyebrow"
                        label="Eyebrow"
                        :error="form.errors['content.pricing.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.pricing.featured_label"
                        label="Featured plan badge"
                        :error="form.errors['content.pricing.featured_label']"
                    />
                </div>
                <LessonsField
                    v-model="form.content.pricing.title"
                    label="Heading"
                    :error="form.errors['content.pricing.title']"
                />
                <LessonsField
                    v-model="form.content.pricing.description"
                    label="Description"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.pricing.description']"
                />
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <LessonsField
                        v-model="form.content.pricing.monthly_label"
                        label="Billing period label"
                        :error="form.errors['content.pricing.monthly_label']"
                    />
                    <LessonsField
                        v-model="form.content.pricing.employees_label"
                        label="Employee limit label"
                        :error="form.errors['content.pricing.employees_label']"
                    />
                    <LessonsField
                        v-model="form.content.pricing.ai_points_label"
                        label="AI points label"
                        :error="form.errors['content.pricing.ai_points_label']"
                    />
                    <LessonsField
                        v-model="form.content.pricing.button_text"
                        label="Plan button"
                        :error="form.errors['content.pricing.button_text']"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <LessonsField
                        v-for="(_, index) in form.content.pricing.inclusions"
                        :key="`inclusion-${index}`"
                        v-model="form.content.pricing.inclusions[index]"
                        :label="`Shared inclusion ${index + 1}`"
                        :error="
                            form.errors[`content.pricing.inclusions.${index}`]
                        "
                    />
                </div>
                <LessonsField
                    v-model="form.content.pricing.footnote"
                    label="Approval note"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.pricing.footnote']"
                />
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-checkout"
            class="scroll-mt-20"
            title="Checkout page"
            title-id="landing-checkout"
        >
            <div class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.checkout.eyebrow"
                        label="Eyebrow"
                        :error="form.errors['content.checkout.eyebrow']"
                    />
                    <LessonsField
                        v-model="form.content.checkout.back_to_plans"
                        label="Back link"
                        :error="form.errors['content.checkout.back_to_plans']"
                    />
                </div>
                <LessonsField
                    v-model="form.content.checkout.title"
                    label="Heading"
                    :error="form.errors['content.checkout.title']"
                />
                <LessonsField
                    v-model="form.content.checkout.description"
                    label="Description"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.checkout.description']"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <LessonsField
                        v-model="form.content.checkout.form_title"
                        label="Form title"
                        :error="form.errors['content.checkout.form_title']"
                    />
                    <LessonsField
                        v-model="form.content.checkout.summary_title"
                        label="Plan summary title"
                        :error="form.errors['content.checkout.summary_title']"
                    />
                    <LessonsField
                        v-model="form.content.checkout.payment_title"
                        label="Payment title"
                        :error="form.errors['content.checkout.payment_title']"
                    />
                    <LessonsField
                        v-model="form.content.checkout.submit_button"
                        label="Submit button"
                        :error="form.errors['content.checkout.submit_button']"
                    />
                </div>
                <LessonsField
                    v-model="form.content.checkout.payment_description"
                    label="Payment explanation"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.checkout.payment_description']"
                />
                <LessonsField
                    v-model="form.content.checkout.approval_note"
                    label="Approval note"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.checkout.approval_note']"
                />
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-cta"
            class="scroll-mt-20"
            title="Closing call to action"
            title-id="landing-cta"
        >
            <div class="grid gap-4">
                <LessonsField
                    v-model="form.content.call_to_action.eyebrow"
                    label="Eyebrow"
                    :error="form.errors['content.call_to_action.eyebrow']"
                />
                <LessonsField
                    v-model="form.content.call_to_action.title"
                    label="Heading"
                    :error="form.errors['content.call_to_action.title']"
                />
                <LessonsField
                    v-model="form.content.call_to_action.description"
                    label="Description"
                    type="textarea"
                    :rows="2"
                    :error="form.errors['content.call_to_action.description']"
                />
                <LessonsField
                    v-model="form.content.call_to_action.button_text"
                    label="Button"
                    :error="form.errors['content.call_to_action.button_text']"
                />
            </div>
        </PanelCard>

        <PanelCard
            id="landing-editor-support"
            class="scroll-mt-20"
            title="Support contact"
            title-id="landing-support"
        >
            <LessonsField
                v-model="form.content.support.whatsapp_number"
                label="WhatsApp support number"
                placeholder="+213 555 12 34 56"
                hint="Use the international country code. Leave blank to hide WhatsApp support from the public page."
                :error="form.errors['content.support.whatsapp_number']"
            />
        </PanelCard>

        <PanelCard
            id="landing-editor-footer"
            class="scroll-mt-20"
            title="Footer"
            title-id="landing-footer"
        >
            <LessonsField
                v-model="form.content.footer.tagline"
                label="Footer tagline"
                :error="form.errors['content.footer.tagline']"
            />
        </PanelCard>

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
                        ? 'Saving changes…'
                        : form.isDirty
                          ? 'Unsaved changes'
                          : 'Changes saved'
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
                        ? 'Saving…'
                        : form.isDirty
                          ? 'Save changes'
                          : 'Saved'
                }}
            </Button>
        </div>
    </div>
</template>
