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
    };
};

export type LandingPaymentMethod = {
    id: number;
    name: string;
    recipientName: string | null;
    accountReference: string;
    instructions: string | null;
};

export type LandingPlan = {
    id: number;
    name: string;
    slug: string;
    employeeLimit: number;
    priceDzd: number;
    pointsPool: number;
};
