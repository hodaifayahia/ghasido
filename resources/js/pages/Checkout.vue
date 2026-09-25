<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    Check,
    CreditCard,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    LandingPageContent,
    LandingPaymentMethod,
    LandingPlan,
} from '@/types';

const props = defineProps<{
    content: LandingPageContent;
    plan: LandingPlan;
    paymentMethods: LandingPaymentMethod[];
}>();

const form = useForm({
    name: '',
    city: '',
    manager_name: '',
    manager_email: '',
    manager_username: '',
    password: '',
    password_confirmation: '',
});

const formatDzd = (value: number): string =>
    new Intl.NumberFormat('fr-DZ').format(value);

function submit(): void {
    form.post(`/checkout/${props.plan.slug}`, {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="`${plan.name} plan checkout`">
        <meta name="description" :content="content.checkout.description" />
    </Head>

    <div class="bg-app text-ink min-h-svh overflow-x-clip">
        <header class="border-line bg-surface border-b">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3 sm:px-8 lg:px-10"
            >
                <Link href="/" aria-label="Guesvia home">
                    <img
                        src="/brand/guesvia-logo.png"
                        alt="Guesvia"
                        width="600"
                        height="180"
                        class="h-11 w-auto object-contain sm:h-13"
                    />
                </Link>
                <Link
                    href="/#pricing"
                    class="text-ink-indigo hover:text-brand-600 focus-visible:ring-brand-600 inline-flex min-h-11 items-center gap-2 rounded-md px-2 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                >
                    <ArrowLeft class="size-4" />
                    {{ content.checkout.back_to_plans }}
                </Link>
            </div>
        </header>

        <main class="relative isolate overflow-hidden">
            <div
                class="bg-brand-100/70 pointer-events-none absolute -top-48 -right-24 size-[32rem] rounded-full blur-3xl"
                aria-hidden="true"
            />
            <div
                class="bg-aqua-tint/80 pointer-events-none absolute top-1/2 -left-40 size-96 rounded-full blur-3xl"
                aria-hidden="true"
            />

            <div
                class="relative mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14 lg:px-10 lg:py-16"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                    >
                        {{ content.checkout.eyebrow }}
                    </p>
                    <h1
                        class="font-heading text-ink-royal mt-3 text-[clamp(2rem,4vw,3.25rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                    >
                        {{ content.checkout.title }}
                    </h1>
                    <p
                        class="text-ink-slate mt-4 max-w-2xl text-[16px] leading-7"
                    >
                        {{ content.checkout.description }}
                    </p>
                </div>

                <div
                    class="mt-9 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-8"
                >
                    <form
                        class="border-line bg-surface shadow-card rounded-xl border p-5 sm:p-7 lg:p-8"
                        @submit.prevent="submit"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="bg-brand-100 text-brand-700 grid size-11 shrink-0 place-items-center rounded-md"
                            >
                                <Building2 class="size-5" />
                            </span>
                            <div>
                                <h2
                                    class="font-heading text-brand-900 text-[20px] font-semibold"
                                >
                                    {{ content.checkout.form_title }}
                                </h2>
                                <p
                                    class="text-ink-slate mt-1 text-[13px] leading-5"
                                >
                                    Tell us about the hotel and create the
                                    account its manager will use after approval.
                                </p>
                            </div>
                        </div>

                        <div class="mt-7 grid gap-5 sm:grid-cols-2">
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="hotel-name">Hotel name</Label>
                                <Input
                                    id="hotel-name"
                                    v-model="form.name"
                                    name="name"
                                    required
                                    autofocus
                                    autocomplete="organization"
                                    placeholder="Blue Coast Hotel"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="hotel-city">City</Label>
                                <Input
                                    id="hotel-city"
                                    v-model="form.city"
                                    name="city"
                                    required
                                    autocomplete="address-level2"
                                    placeholder="Oran"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError :message="form.errors.city" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="manager-name">Manager name</Label>
                                <Input
                                    id="manager-name"
                                    v-model="form.manager_name"
                                    name="manager_name"
                                    required
                                    autocomplete="name"
                                    placeholder="Nassim Benali"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError
                                    :message="form.errors.manager_name"
                                />
                            </div>

                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="manager-email">Manager email</Label>
                                <Input
                                    id="manager-email"
                                    v-model="form.manager_email"
                                    name="manager_email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    placeholder="manager@hotel.com"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError
                                    :message="form.errors.manager_email"
                                />
                            </div>

                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="manager-username"
                                    >Manager username</Label
                                >
                                <Input
                                    id="manager-username"
                                    v-model="form.manager_username"
                                    name="manager_username"
                                    required
                                    autocomplete="username"
                                    placeholder="blue.coast.manager"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError
                                    :message="form.errors.manager_username"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    v-model="form.password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="At least 8 characters"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password-confirmation"
                                    >Confirm password</Label
                                >
                                <PasswordInput
                                    id="password-confirmation"
                                    v-model="form.password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Repeat password"
                                    class="border-line bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-12 rounded-md text-[15px] shadow-none focus-visible:ring-3"
                                />
                            </div>
                        </div>

                        <div
                            class="border-line bg-app-alt mt-7 rounded-lg border p-4"
                        >
                            <div class="flex items-start gap-3">
                                <ShieldCheck
                                    class="text-success mt-0.5 size-5 shrink-0"
                                />
                                <div>
                                    <p
                                        class="text-brand-900 text-[13px] font-semibold"
                                    >
                                        {{ content.checkout.approval_note }}
                                    </p>
                                    <p
                                        class="text-ink-slate mt-1 text-[12px] leading-5"
                                    >
                                        {{
                                            content.checkout.payment_description
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-brand-600 text-surface shadow-btn hover:bg-brand-700 mt-6 h-12 w-full rounded-md text-[14px] font-semibold"
                        >
                            <Spinner v-if="form.processing" />
                            {{ content.checkout.submit_button }}
                        </Button>
                    </form>

                    <aside class="space-y-4 lg:sticky lg:top-6">
                        <section
                            class="bg-brand-900 text-surface shadow-pop relative overflow-hidden rounded-xl p-6"
                            aria-labelledby="plan-summary-title"
                        >
                            <div
                                class="bg-ai/20 pointer-events-none absolute -top-20 -right-16 size-52 rounded-full blur-3xl"
                            />
                            <div class="relative">
                                <p
                                    id="plan-summary-title"
                                    class="text-brand-200 text-[11px] font-bold tracking-[0.15em] uppercase"
                                >
                                    {{ content.checkout.summary_title }}
                                </p>
                                <div
                                    class="mt-4 flex items-center justify-between gap-4"
                                >
                                    <h2
                                        class="font-heading text-surface text-[24px] font-semibold"
                                    >
                                        {{ plan.name }}
                                    </h2>
                                    <BadgeCheck class="text-brand-200 size-6" />
                                </div>
                                <p class="mt-4 flex items-end gap-2">
                                    <span
                                        class="font-heading text-[34px] leading-none font-bold"
                                    >
                                        {{ formatDzd(plan.priceDzd) }}
                                    </span>
                                    <span
                                        class="text-brand-200 pb-0.5 text-[12px]"
                                        >DZD /
                                        {{
                                            content.pricing.monthly_label
                                        }}</span
                                    >
                                </p>

                                <div
                                    class="border-surface/15 mt-6 border-t pt-5"
                                >
                                    <div class="flex items-center gap-3">
                                        <UsersRound
                                            class="text-brand-200 size-5"
                                        />
                                        <p class="text-[13px]">
                                            <strong class="font-semibold">{{
                                                plan.employeeLimit
                                            }}</strong>
                                            {{
                                                content.pricing.employees_label
                                            }}
                                        </p>
                                    </div>
                                    <div class="mt-3 flex items-center gap-3">
                                        <Sparkles
                                            class="text-brand-200 size-5"
                                        />
                                        <p class="text-[13px]">
                                            <strong class="font-semibold">{{
                                                plan.pointsPool.toLocaleString()
                                            }}</strong>
                                            {{
                                                content.pricing.ai_points_label
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <ul class="mt-5 space-y-2.5">
                                    <li
                                        v-for="item in content.pricing
                                            .inclusions"
                                        :key="item"
                                        class="text-brand-100 flex items-start gap-2 text-[12px]"
                                    >
                                        <Check
                                            class="text-success mt-0.5 size-4 shrink-0"
                                        />
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </section>

                        <section
                            class="border-line bg-surface shadow-card rounded-xl border p-5"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-ai-tint text-ai grid size-10 place-items-center rounded-md"
                                >
                                    <CreditCard class="size-5" />
                                </span>
                                <h2
                                    class="font-heading text-brand-900 text-[16px] font-semibold"
                                >
                                    {{ content.checkout.payment_title }}
                                </h2>
                            </div>

                            <div
                                v-if="paymentMethods.length"
                                class="mt-4 space-y-3"
                            >
                                <article
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    class="border-line bg-app rounded-md border p-3"
                                >
                                    <p
                                        class="text-brand-900 text-[13px] font-semibold"
                                    >
                                        {{ method.name }}
                                    </p>
                                    <p
                                        class="text-ink-slate mt-1 text-[11px] break-all"
                                    >
                                        {{ method.accountReference }}
                                    </p>
                                </article>
                            </div>
                            <p
                                v-else
                                class="text-ink-slate mt-4 text-[12px] leading-5"
                            >
                                {{ content.checkout.payment_description }}
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</template>
