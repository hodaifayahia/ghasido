export type LandingPageItem = {
    title: string;
    description: string;
};

export type LandingRoleItem = LandingPageItem & {
    capabilities: string[];
};

export type LandingPageContent = {
    navigation: {
        why_us: string;
        about: string;
        platform: string;
        ai_practice: string;
        roles: string;
        pricing: string;
        login: string;
        get_started: string;
        open_dashboard: string;
        contact: string;
    };
    roles: {
        eyebrow: string;
        title: string;
        description: string;
        items: LandingRoleItem[];
    };
    journey: {
        eyebrow: string;
        title: string;
        description: string;
        steps: LandingPageItem[];
    };
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        primary_cta: string;
        secondary_cta: string;
        image_alt: string;
        proof_points: string[];
    };
    why_us: {
        eyebrow: string;
        title: string;
        description: string;
        items: LandingPageItem[];
    };
    about: {
        eyebrow: string;
        title: string;
        description: string;
        learner_note: string;
        image_alt: string;
    };
    features: {
        eyebrow: string;
        title: string;
        description: string;
        items: LandingPageItem[];
    };
    ai: {
        eyebrow: string;
        title: string;
        description: string;
        review_note: string;
        items: LandingPageItem[];
    };
    pricing: {
        eyebrow: string;
        title: string;
        description: string;
        monthly_label: string;
        employees_label: string;
        ai_points_label: string;
        button_text: string;
        featured_label: string;
        inclusions: string[];
        footnote: string;
        region_algeria: string;
        region_international: string;
    };
    checkout: {
        eyebrow: string;
        title: string;
        description: string;
        summary_title: string;
        form_title: string;
        payment_title: string;
        payment_description: string;
        submit_button: string;
        approval_note: string;
        back_to_plans: string;
    };
    call_to_action: {
        eyebrow: string;
        title: string;
        description: string;
        button_text: string;
    };
    footer: {
        tagline: string;
    };
    support: {
        whatsapp_number: string;
        phone: string;
        email: string;
    };
    contact: {
        eyebrow: string;
        title: string;
        description: string;
        form_title: string;
        submit_button: string;
        success_message: string;
        enterprise_title: string;
        enterprise_subtitle: string;
        enterprise_description: string;
        enterprise_points: string[];
        enterprise_button: string;
        enterprise_note: string;
    };
};

export type LandingContactMessage = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    organisation: string | null;
    employees: string | null;
    message: string;
    read: boolean;
    sentAt: string | null;
    readUrl: string;
};

export type LandingPaymentMethod = {
    id: number;
    name: string;
    recipientName: string | null;
    accountReference: string;
    instructions: string | null;
};

/** Who a plan is sold to: a hotel team, or one learner on their own. */
export type PlanAudience = 'hotel' | 'individual';

export type LandingPlan = {
    id: number;
    name: string;
    slug: string;
    audience?: PlanAudience;
    employeeLimit: number;
    priceDzd: number;
    priceUsd: number;
    pointsPool: number;
};

/** The plan on the checkout page; its audience decides the form shown. */
export type CheckoutPlan = LandingPlan & {
    audience: PlanAudience;
};

export type CheckoutDepartmentOption = {
    value: number;
    label: string;
};

export type PaymentSubmissionStatus = 'pending' | 'confirmed' | 'rejected';

/** What the "Payment submitted" page shows about one payment. */
export type CheckoutSubmittedPayment = {
    planName: string;
    amount: number;
    currency: 'DZD' | 'USD';
    method: string;
    reference: string | null;
    hasReceipt: boolean;
    submittedAt: string | null;
    status: PaymentSubmissionStatus;
    statusLabel: string;
    isIndividual: boolean;
};
