<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, ChevronDown, CreditCard, UserRound } from '@lucide/vue';
import { computed, nextTick } from 'vue';
import CheckoutField from '@/components/checkout/CheckoutField.vue';
import CheckoutNextSteps from '@/components/checkout/CheckoutNextSteps.vue';
import CheckoutPlanSummary from '@/components/checkout/CheckoutPlanSummary.vue';
import CheckoutReview from '@/components/checkout/CheckoutReview.vue';
import type { ReviewRow } from '@/components/checkout/CheckoutReview.vue';
import CheckoutSection from '@/components/checkout/CheckoutSection.vue';
import CheckoutShell from '@/components/checkout/CheckoutShell.vue';
import CheckoutStepper from '@/components/checkout/CheckoutStepper.vue';
import type { CheckoutStep } from '@/components/checkout/CheckoutStepper.vue';
import { checkoutInputClass } from '@/components/checkout/fields';
import { formatPrice } from '@/components/checkout/money';
import type { Currency } from '@/components/checkout/money';
import PaymentDetails from '@/components/checkout/PaymentDetails.vue';
import PaymentMethodPicker from '@/components/checkout/PaymentMethodPicker.vue';
import ProofUpload from '@/components/checkout/ProofUpload.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { home } from '@/routes';
import { store } from '@/routes/checkout';
import type {
    CheckoutDepartmentOption,
    CheckoutPlan,
    LandingPageContent,
    LandingPaymentMethod,
} from '@/types';

/*
 * Buying a plan online with a manual payment (client payment-flow
 * reference, 2026-09-27): your details → payment method → pay and upload
 * the proof → review and submit, as numbered sections on one page. Hotel
 * plans open a pending hotel and its manager; individual plans a pending
 * learner (AUTH-02, SUB-01). With no payment method set up, the payment
 * steps are hidden and the team contacts the customer instead.
 */
type Props = {
    content: LandingPageContent;
    plan: CheckoutPlan;
    paymentMethods: LandingPaymentMethod[];
    departments?: CheckoutDepartmentOption[];
    proofTypes?: string[];
    proofMaxKb?: number;
    region: 'dz' | 'intl';
};

const props = withDefaults(defineProps<Props>(), {
    departments: () => [],
    proofTypes: () => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    proofMaxKb: 5120,
});

const isIndividual = computed(() => props.plan.audience === 'individual');
const acceptsPayment = computed(() => props.paymentMethods.length > 0);

// Algeria pays the DZD price, international customers the USD price
// (client decision 2026-09-26), as picked on the pricing switch.
const currency: Currency = props.region === 'intl' ? 'USD' : 'DZD';
const amountValue =
    currency === 'USD' ? props.plan.priceUsd : props.plan.priceDzd;
const price = formatPrice(amountValue, currency);

const form = useForm({
    region: props.region,
    name: '',
    city: '',
    manager_name: '',
    manager_email: '',
    manager_username: '',
    email: '',
    username: '',
    department_id: '' as number | '',
    phone: '',
    password: '',
    password_confirmation: '',
    // One method only: pick it for the customer.
    payment_method_id: (props.paymentMethods.length === 1
        ? (props.paymentMethods[0]?.id ?? null)
        : null) as number | null,
    proof: null as File | null,
    reference: '',
});

const selectedMethodIndex = computed(() =>
    props.paymentMethods.findIndex(
        (method) => method.id === form.payment_method_id,
    ),
);
const selectedMethod = computed(
    () => props.paymentMethods[selectedMethodIndex.value] ?? null,
);

const loginName = computed(() =>
    (isIndividual.value ? form.username : form.manager_username).trim(),
);

// A note that lets the team match the transfer to this request.
const suggestedReference = computed(() =>
    ['GHASIDO', props.plan.slug, loginName.value]
        .filter((part) => part !== '')
        .join('-')
        .toUpperCase(),
);

const filled = (...values: (string | number)[]): boolean =>
    values.every((value) => String(value).trim() !== '');

const detailsComplete = computed(() => {
    const account = isIndividual.value
        ? filled(form.name, form.email, form.username, form.department_id)
        : filled(
              form.name,
              form.city,
              form.manager_name,
              form.manager_email,
              form.manager_username,
          );

    return (
        account &&
        filled(form.phone) &&
        form.password.length >= 8 &&
        form.password === form.password_confirmation
    );
});
const methodComplete = computed(() => form.payment_method_id !== null);
const proofComplete = computed(
    () => form.proof !== null || form.reference.trim() !== '',
);

const steps = computed((): CheckoutStep[] =>
    acceptsPayment.value
        ? [
              {
                  id: 'checkout-details',
                  label: t('Your details'),
                  complete: detailsComplete.value,
              },
              {
                  id: 'checkout-method',
                  label: t('Payment method'),
                  complete: methodComplete.value,
              },
              {
                  id: 'checkout-proof',
                  label: t('Pay & upload proof'),
                  complete: proofComplete.value,
              },
              {
                  id: 'checkout-review',
                  label: t('Review & submit'),
                  complete: false,
              },
          ]
        : [
              {
                  id: 'checkout-details',
                  label: t('Your details'),
                  complete: detailsComplete.value,
              },
              {
                  id: 'checkout-review',
                  label: t('Review & submit'),
                  complete: false,
              },
          ],
);

const reviewRows = computed((): ReviewRow[] => {
    const rows: ReviewRow[] = [
        { label: t('Plan'), value: props.plan.name },
        {
            label: t('Amount'),
            value: `${price} / ${props.content.pricing.monthly_label}`,
        },
        {
            label: isIndividual.value ? t('Learner') : t('Hotel'),
            value: form.name.trim() || t('Not filled in yet'),
            missing: form.name.trim() === '',
        },
        {
            label: t('Username'),
            value: loginName.value || t('Not filled in yet'),
            missing: loginName.value === '',
        },
    ];

    if (acceptsPayment.value) {
        rows.push(
            {
                label: t('Payment method'),
                value: selectedMethod.value?.name ?? t('Not chosen yet'),
                missing: selectedMethod.value === null,
            },
            {
                label: t('Proof of payment'),
                value: form.proof
                    ? form.proof.name
                    : form.reference.trim()
                      ? t('Reference :reference', {
                            reference: form.reference.trim(),
                        })
                      : t('Receipt or reference still needed'),
                missing: !proofComplete.value,
            },
        );
    }

    return rows;
});

const progress = computed(() => form.progress?.percentage ?? null);

function describedBy(id: string, error?: string, hint = false): string {
    if (error) {
        return `${id}-error`;
    }

    return hint ? `${id}-hint` : '';
}

async function focusFirstError(): Promise<void> {
    await nextTick();

    const invalid = document.querySelector<HTMLElement>(
        '[aria-invalid="true"], [data-invalid="true"]',
    );

    if (!invalid) {
        return;
    }

    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    invalid.scrollIntoView({
        behavior: reduce ? 'auto' : 'smooth',
        block: 'center',
    });

    const target = invalid.matches('input, select, textarea')
        ? invalid
        : invalid.querySelector<HTMLElement>('input, select, textarea');
    target?.focus({ preventScroll: true });
}

function submit(): void {
    if (form.processing) {
        return;
    }

    form.transform((data) => {
        const account = isIndividual.value
            ? {
                  name: data.name,
                  email: data.email,
                  username: data.username,
                  department_id: data.department_id,
              }
            : {
                  name: data.name,
                  city: data.city,
                  manager_name: data.manager_name,
                  manager_email: data.manager_email,
                  manager_username: data.manager_username,
              };
        const payment = acceptsPayment.value
            ? {
                  payment_method_id: data.payment_method_id ?? '',
                  reference: data.reference,
                  ...(data.proof ? { proof: data.proof } : {}),
              }
            : {};

        return {
            region: data.region,
            phone: data.phone,
            password: data.password,
            password_confirmation: data.password_confirmation,
            ...account,
            ...payment,
        };
    }).post(store.url(props.plan.slug), {
        forceFormData: true,
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="$t(':plan plan checkout', { plan: plan.name })">
        <meta name="description" :content="content.checkout.description" />
    </Head>

    <CheckoutShell
        :content="content"
        :back-href="`${home.url()}#pricing`"
        :back-label="content.checkout.back_to_plans"
    >
        <div
            class="mx-auto max-w-7xl px-4 py-9 sm:px-8 sm:py-14 lg:px-10 lg:py-16"
        >
            <div class="max-w-3xl">
                <p
                    class="text-brand-600 text-[12px] font-bold tracking-[0.16em] uppercase"
                >
                    {{ content.checkout.eyebrow }}
                </p>
                <h1
                    class="font-heading text-ink-royal mt-3 text-[clamp(1.875rem,4vw,3.25rem)] leading-[1.08] font-bold tracking-[-0.04em]"
                >
                    {{ content.checkout.title }}
                </h1>
                <p class="text-ink-slate mt-4 max-w-2xl text-[16px] leading-7">
                    {{ content.checkout.description }}
                </p>
            </div>

            <div
                class="mt-8 grid items-start gap-6 lg:mt-10 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-8"
            >
                <form
                    class="grid min-w-0 gap-5"
                    novalidate
                    @submit.prevent="submit"
                >
                    <CheckoutStepper :steps="steps" />

                    <!-- 1. Your details -->
                    <CheckoutSection
                        id="checkout-details"
                        :step="1"
                        :title="$t('Your details')"
                        :description="
                            isIndividual
                                ? $t(
                                      'Create the account you will learn with. It opens once your payment is confirmed.',
                                  )
                                : $t(
                                      'Tell us about the hotel and create the account its manager will use after approval.',
                                  )
                        "
                        :complete="detailsComplete"
                    >
                        <div
                            v-if="isIndividual"
                            class="grid gap-5 sm:grid-cols-2"
                        >
                            <CheckoutField
                                id="full-name"
                                :label="$t('Full name')"
                                :error="form.errors.name"
                                class="sm:col-span-2"
                            >
                                <Input
                                    id="full-name"
                                    v-model="form.name"
                                    name="name"
                                    required
                                    autocomplete="name"
                                    :placeholder="$t('Nassim Benali')"
                                    :aria-invalid="
                                        form.errors.name ? 'true' : undefined
                                    "
                                    :aria-describedby="
                                        describedBy(
                                            'full-name',
                                            form.errors.name,
                                        )
                                    "
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>

                            <CheckoutField
                                id="email"
                                :label="$t('Email')"
                                :hint="
                                    $t(
                                        'We email you here when your account is ready.',
                                    )
                                "
                                :error="form.errors.email"
                            >
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    name="email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    inputmode="email"
                                    placeholder="name@example.com"
                                    dir="ltr"
                                    :aria-invalid="
                                        form.errors.email ? 'true' : undefined
                                    "
                                    :aria-describedby="
                                        describedBy(
                                            'email',
                                            form.errors.email,
                                            true,
                                        )
                                    "
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>

                            <CheckoutField
                                id="phone"
                                :label="$t('Phone')"
                                :error="form.errors.phone"
                            >
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    name="phone"
                                    type="tel"
                                    required
                                    autocomplete="tel"
                                    inputmode="tel"
                                    placeholder="+213 555 12 34 56"
                                    dir="ltr"
                                    :aria-invalid="
                                        form.errors.phone ? 'true' : undefined
                                    "
                                    :aria-describedby="
                                        describedBy('phone', form.errors.phone)
                                    "
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>

                            <CheckoutField
                                id="username"
                                :label="$t('Username')"
                                :hint="
                                    $t(
                                        'Lowercase letters, digits, dots, dashes and underscores.',
                                    )
                                "
                                :error="form.errors.username"
                            >
                                <Input
                                    id="username"
                                    v-model="form.username"
                                    name="username"
                                    required
                                    autocomplete="username"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    placeholder="nassim.benali"
                                    dir="ltr"
                                    :aria-invalid="
                                        form.errors.username
                                            ? 'true'
                                            : undefined
                                    "
                                    :aria-describedby="
                                        describedBy(
                                            'username',
                                            form.errors.username,
                                            true,
                                        )
                                    "
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>

                            <CheckoutField
                                id="department"
                                :label="$t('Department')"
                                :hint="
                                    $t('Your lessons are built for this job.')
                                "
                                :error="form.errors.department_id"
                            >
                                <div class="relative">
                                    <select
                                        id="department"
                                        v-model="form.department_id"
                                        name="department_id"
                                        required
                                        :aria-invalid="
                                            form.errors.department_id
                                                ? 'true'
                                                : undefined
                                        "
                                        :aria-describedby="
                                            describedBy(
                                                'department',
                                                form.errors.department_id,
                                                true,
                                            )
                                        "
                                        :class="checkoutInputClass"
                                        class="w-full appearance-none border ps-3 pe-10 outline-none"
                                    >
                                        <option value="" disabled>
                                            {{ $t('Choose a department') }}
                                        </option>
                                        <option
                                            v-for="department in departments"
                                            :key="department.value"
                                            :value="department.value"
                                        >
                                            {{ department.label }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="text-ink-muted pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                </div>
                            </CheckoutField>

                            <CheckoutField
                                id="password"
                                :label="$t('Password')"
                                :error="form.errors.password"
                            >
                                <PasswordInput
                                    id="password"
                                    v-model="form.password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    :placeholder="$t('At least 8 characters')"
                                    :aria-invalid="
                                        form.errors.password
                                            ? 'true'
                                            : undefined
                                    "
                                    :aria-describedby="
                                        describedBy(
                                            'password',
                                            form.errors.password,
                                        )
                                    "
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>

                            <CheckoutField
                                id="password-confirmation"
                                :label="$t('Confirm password')"
                            >
                                <PasswordInput
                                    id="password-confirmation"
                                    v-model="form.password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    :placeholder="$t('Repeat password')"
                                    :class="checkoutInputClass"
                                />
                            </CheckoutField>
                        </div>

                        <div v-else class="grid gap-7">
                            <fieldset class="grid min-w-0 gap-5 sm:grid-cols-2">
                                <legend
                                    class="text-ink-indigo mb-4 flex items-center gap-2 text-[13px] font-bold tracking-[0.08em] uppercase"
                                >
                                    <Building2
                                        class="text-brand-600 size-4"
                                        aria-hidden="true"
                                    />
                                    {{ $t('The hotel') }}
                                </legend>
                                <CheckoutField
                                    id="hotel-name"
                                    :label="$t('Hotel name')"
                                    :error="form.errors.name"
                                >
                                    <Input
                                        id="hotel-name"
                                        v-model="form.name"
                                        name="name"
                                        required
                                        autocomplete="organization"
                                        :placeholder="$t('Blue Coast Hotel')"
                                        :aria-invalid="
                                            form.errors.name
                                                ? 'true'
                                                : undefined
                                        "
                                        :aria-describedby="
                                            describedBy(
                                                'hotel-name',
                                                form.errors.name,
                                            )
                                        "
                                        :class="checkoutInputClass"
                                    />
                                </CheckoutField>

                                <CheckoutField
                                    id="hotel-city"
                                    :label="$t('City')"
                                    :error="form.errors.city"
                                >
                                    <Input
                                        id="hotel-city"
                                        v-model="form.city"
                                        name="city"
                                        required
                                        autocomplete="address-level2"
                                        :placeholder="$t('Oran')"
                                        :aria-invalid="
                                            form.errors.city
                                                ? 'true'
                                                : undefined
                                        "
                                        :aria-describedby="
                                            describedBy(
                                                'hotel-city',
                                                form.errors.city,
                                            )
                                        "
                                        :class="checkoutInputClass"
                                    />
                                </CheckoutField>
                            </fieldset>

                            <div class="border-line border-t pt-6">
                                <fieldset
                                    class="grid min-w-0 gap-5 sm:grid-cols-2"
                                >
                                    <legend
                                        class="text-ink-indigo mb-4 flex items-center gap-2 text-[13px] font-bold tracking-[0.08em] uppercase"
                                    >
                                        <UserRound
                                            class="text-brand-600 size-4"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Manager account') }}
                                    </legend>
                                    <CheckoutField
                                        id="manager-name"
                                        :label="$t('Manager name')"
                                        :error="form.errors.manager_name"
                                    >
                                        <Input
                                            id="manager-name"
                                            v-model="form.manager_name"
                                            name="manager_name"
                                            required
                                            autocomplete="name"
                                            :placeholder="$t('Nassim Benali')"
                                            :aria-invalid="
                                                form.errors.manager_name
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'manager-name',
                                                    form.errors.manager_name,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>

                                    <CheckoutField
                                        id="phone"
                                        :label="$t('Phone')"
                                        :error="form.errors.phone"
                                    >
                                        <Input
                                            id="phone"
                                            v-model="form.phone"
                                            name="phone"
                                            type="tel"
                                            required
                                            autocomplete="tel"
                                            inputmode="tel"
                                            placeholder="+213 555 12 34 56"
                                            dir="ltr"
                                            :aria-invalid="
                                                form.errors.phone
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'phone',
                                                    form.errors.phone,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>

                                    <CheckoutField
                                        id="manager-email"
                                        :label="$t('Manager email')"
                                        :hint="
                                            $t(
                                                'We email the manager here when the hotel is ready.',
                                            )
                                        "
                                        :error="form.errors.manager_email"
                                    >
                                        <Input
                                            id="manager-email"
                                            v-model="form.manager_email"
                                            name="manager_email"
                                            type="email"
                                            required
                                            autocomplete="email"
                                            inputmode="email"
                                            placeholder="manager@hotel.com"
                                            dir="ltr"
                                            :aria-invalid="
                                                form.errors.manager_email
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'manager-email',
                                                    form.errors.manager_email,
                                                    true,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>

                                    <CheckoutField
                                        id="manager-username"
                                        :label="$t('Manager username')"
                                        :hint="
                                            $t(
                                                'Lowercase letters, digits, dots, dashes and underscores.',
                                            )
                                        "
                                        :error="form.errors.manager_username"
                                    >
                                        <Input
                                            id="manager-username"
                                            v-model="form.manager_username"
                                            name="manager_username"
                                            required
                                            autocomplete="username"
                                            autocapitalize="none"
                                            spellcheck="false"
                                            placeholder="blue.coast.manager"
                                            dir="ltr"
                                            :aria-invalid="
                                                form.errors.manager_username
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'manager-username',
                                                    form.errors
                                                        .manager_username,
                                                    true,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>

                                    <CheckoutField
                                        id="password"
                                        :label="$t('Password')"
                                        :error="form.errors.password"
                                    >
                                        <PasswordInput
                                            id="password"
                                            v-model="form.password"
                                            name="password"
                                            required
                                            autocomplete="new-password"
                                            :placeholder="
                                                $t('At least 8 characters')
                                            "
                                            :aria-invalid="
                                                form.errors.password
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'password',
                                                    form.errors.password,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>

                                    <CheckoutField
                                        id="password-confirmation"
                                        :label="$t('Confirm password')"
                                    >
                                        <PasswordInput
                                            id="password-confirmation"
                                            v-model="form.password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autocomplete="new-password"
                                            :placeholder="$t('Repeat password')"
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>
                                </fieldset>
                            </div>
                        </div>
                    </CheckoutSection>

                    <template v-if="acceptsPayment">
                        <!-- 2. Payment method -->
                        <CheckoutSection
                            id="checkout-method"
                            :step="2"
                            :title="$t('Choose a payment method')"
                            :description="
                                $t(
                                    'Pick the app or bank you will pay with. Its details appear in the next step.',
                                )
                            "
                            :complete="methodComplete"
                        >
                            <PaymentMethodPicker
                                v-model="form.payment_method_id"
                                :methods="paymentMethods"
                                :error="form.errors.payment_method_id"
                            />
                        </CheckoutSection>

                        <!-- 3. Pay & upload proof -->
                        <CheckoutSection
                            id="checkout-proof"
                            :step="3"
                            :title="$t('Pay and upload the proof')"
                            :description="
                                $t(
                                    'Send the payment, then upload the receipt or type the transaction number.',
                                )
                            "
                            :complete="proofComplete"
                        >
                            <div class="grid gap-6">
                                <PaymentDetails
                                    v-if="selectedMethod"
                                    :method="selectedMethod"
                                    :method-index="selectedMethodIndex"
                                    :amount="price"
                                    :amount-value="String(amountValue)"
                                    :suggested-reference="suggestedReference"
                                />
                                <div
                                    v-else
                                    class="border-line-strong bg-app text-ink-slate flex items-center gap-3 rounded-lg border border-dashed px-4 py-5 text-[13px] leading-5"
                                >
                                    <CreditCard
                                        class="text-brand-600 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{
                                        $t(
                                            'Choose a payment method above to see where to send the payment.',
                                        )
                                    }}
                                </div>

                                <div class="grid gap-4">
                                    <div>
                                        <h3
                                            class="font-heading text-brand-900 text-[16px] font-semibold"
                                        >
                                            {{ $t('Proof of payment') }}
                                        </h3>
                                        <p
                                            id="proof-help"
                                            class="text-ink-slate mt-1 text-[13px] leading-5"
                                        >
                                            {{
                                                $t(
                                                    'Upload the receipt, type the transaction reference, or both. At least one is required.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <ProofUpload
                                        v-model="form.proof"
                                        :types="proofTypes"
                                        :max-kb="proofMaxKb"
                                        :error="form.errors.proof"
                                    />

                                    <div
                                        class="text-ink-faint flex items-center gap-3 text-[12px] font-semibold tracking-[0.1em] uppercase"
                                        aria-hidden="true"
                                    >
                                        <span class="bg-line h-px flex-1" />
                                        {{ $t('and / or') }}
                                        <span class="bg-line h-px flex-1" />
                                    </div>

                                    <CheckoutField
                                        id="payment-reference"
                                        :label="
                                            $t(
                                                'Transaction reference or number',
                                            )
                                        "
                                        :hint="
                                            $t(
                                                'Shown on the receipt or in your app’s history.',
                                            )
                                        "
                                        :error="form.errors.reference"
                                    >
                                        <Input
                                            id="payment-reference"
                                            v-model="form.reference"
                                            name="reference"
                                            maxlength="120"
                                            autocomplete="off"
                                            spellcheck="false"
                                            placeholder="TX-2026-000123"
                                            dir="ltr"
                                            :aria-invalid="
                                                form.errors.reference
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :aria-describedby="
                                                describedBy(
                                                    'payment-reference',
                                                    form.errors.reference,
                                                    true,
                                                )
                                            "
                                            :class="checkoutInputClass"
                                        />
                                    </CheckoutField>
                                </div>
                            </div>
                        </CheckoutSection>
                    </template>

                    <!-- Review & submit -->
                    <CheckoutSection
                        id="checkout-review"
                        :step="acceptsPayment ? 4 : 2"
                        :title="$t('Review and submit')"
                        :description="
                            $t('Check everything once, then send your request.')
                        "
                    >
                        <CheckoutReview
                            :rows="reviewRows"
                            :approval-note="content.checkout.approval_note"
                            :approval-description="
                                content.checkout.payment_description
                            "
                            :submit-label="
                                acceptsPayment
                                    ? $t('Submit payment')
                                    : content.checkout.submit_button
                            "
                            :processing="form.processing"
                            :progress="form.proof ? progress : null"
                            :has-errors="form.hasErrors"
                        />
                    </CheckoutSection>
                </form>

                <!-- On a phone the plan comes first and "what happens next"
                     last; on desktop both stay in view beside the form. -->
                <aside
                    class="order-first grid gap-4 lg:sticky lg:top-6 lg:order-none"
                >
                    <CheckoutPlanSummary
                        :content="content"
                        :plan="plan"
                        :currency="currency"
                    />
                    <CheckoutNextSteps
                        class="hidden lg:block"
                        :is-individual="isIndividual"
                        :accepts-payment="acceptsPayment"
                    />
                </aside>
                <CheckoutNextSteps
                    class="lg:hidden"
                    :is-individual="isIndividual"
                    :accepts-payment="acceptsPayment"
                />
            </div>
        </div>
    </CheckoutShell>
</template>
