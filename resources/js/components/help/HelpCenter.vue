<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Bot,
    Building2,
    ChartNoAxesCombined,
    ClipboardCheck,
    Coins,
    CreditCard,
    LifeBuoy,
    Mail,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PanelCard from '@/components/common/PanelCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useCan } from '@/composables/useCan';

type HelpAction = {
    label: string;
    href: string;
    permissions?: string[];
};

type HelpGuide = {
    id: string;
    title: string;
    icon: Component;
    /** Any matching permission allows this guide to appear. */
    permissions?: string[];
    roles?: string[];
    steps: string[];
    actions?: HelpAction[];
};

const { can } = useCan();
const page = usePage();
const userRole = computed(() => page.props.auth.user?.role ?? null);

// Admin instructions follow the CMS, test, scenario, hotel, AI point,
// reminder and reporting workflows (ADM-02, CMS-01..06, TEST-01..10,
// RP-12..13, SUB-01..08, AIL-01, REM-05, REP-01..08).
const guides: HelpGuide[] = [
    {
        id: 'training',
        title: 'Use the training area',
        icon: LifeBuoy,
        permissions: ['training.self'],
        roles: ['employee', 'manager'],
        steps: [
            'Open Home and complete the Pre-test first. Lessons stay locked until the Pre-test is submitted.',
            'Open My Lessons, choose the next lesson, and complete its steps in order. Your progress is saved as you go.',
            'After the required lessons are complete, take the Post-test. Open Certificate to download it when it becomes available.',
        ],
        actions: [{ label: 'Open training home', href: '/learn' }],
    },
    {
        id: 'lessons',
        title: 'Create a lesson',
        icon: BookOpen,
        permissions: ['lessons.manage'],
        steps: [
            'In Lessons & Content, use Add to create a Course, then add a Unit and a Lesson. Choose the department and hotel scope so the right employees can see it.',
            'Open the lesson editor. Add a short introduction, objectives and the content blocks employees will complete. Edit each block and add its text, image, audio or activity.',
            'Reorder blocks to set the lesson sequence. Use Preview to check the employee view on phone and desktop.',
            'Save as a draft while you work. Publish only when the content and audience are correct; drafts stay hidden from employees.',
        ],
        actions: [
            {
                label: 'Open Lessons & Content',
                href: '/lessons-content',
                permissions: ['lessons.view', 'lessons.manage'],
            },
        ],
    },
    {
        id: 'tests',
        title: 'Create a Pre-test or Post-test',
        icon: ClipboardCheck,
        permissions: ['tests.manage'],
        steps: [
            'Open Pre-test & Post-test and choose Create New Test. Enter a title, select Pre-test or Post-test, choose its department, and set an optional time limit.',
            'Add questions in the builder. Add the question text and answer choices or media, then arrange the questions in the order employees should see them.',
            'Review test settings and employee result visibility. Preview the test before publishing it.',
            'Create a matching test for the other stage. Keep the skill coverage and difficulty aligned, but use different questions and situations.',
            'Publish each test when it is ready. The Pre-test gates lessons; the Post-test becomes available under the configured completion condition.',
        ],
        actions: [
            {
                label: 'Open test builder',
                href: '/tests',
                permissions: ['tests.view', 'tests.manage'],
            },
        ],
    },
    {
        id: 'scenarios',
        title: 'Create an AI role-play scenario',
        icon: Bot,
        permissions: ['scenarios.manage'],
        steps: [
            'In AI Role-play Scenarios, select Create New Scenario. Give it one realistic hotel situation, then choose its department and English level.',
            'Describe what the guest needs and what the employee should achieve. Define both roles, learning objectives, and any special AI instructions.',
            'Set attempts, feedback and practice criteria. Use Preview & Test to run a full conversation and review the feedback.',
            'Save as a draft while editing. Publish only after checking the wording, roles and settings; employees then see it in the assigned department.',
        ],
        actions: [
            {
                label: 'Open AI scenarios',
                href: '/ai-scenarios',
                permissions: ['scenarios.view', 'scenarios.manage'],
            },
        ],
    },
    {
        id: 'hotel-approval',
        title: 'Approve a new hotel account',
        icon: Building2,
        permissions: ['hotels.approve'],
        steps: [
            'Open Hotels and filter for Pending to find a new hotel account awaiting review.',
            'Open the hotel row and choose Approve. Approval starts its contract today.',
            'Assign a subscription plan and set the hotel’s department seat limits so it is ready for employee accounts.',
        ],
        actions: [
            {
                label: 'Open Hotels',
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.approve'],
            },
        ],
    },
    {
        id: 'hotel-reactivation',
        title: 'Resume a paused hotel account',
        icon: Building2,
        permissions: ['hotels.manage'],
        steps: [
            'Open Hotels and find the hotel with Paused status.',
            'Choose Resume Access after checking the subscription plan and contract dates. Extend the contract first if needed.',
            'Open the hotel details to review its account status, employee seats and recent activity.',
        ],
        actions: [
            {
                label: 'Open Hotels',
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.manage'],
            },
        ],
    },
    {
        id: 'subscriptions',
        title: 'Set up subscriptions and seat limits',
        icon: CreditCard,
        permissions: ['subscriptions.manage'],
        steps: [
            'Open Subscriptions and create or edit a plan. Set the employee limit, price, AI point allowance and which payment methods are available.',
            'Assign an active plan to the hotel. Review contract dates and the allowed seats for each department in Hotels.',
            'Seat limits apply per department. If you lower a limit below current usage, existing accounts remain and new accounts are blocked until there is room.',
        ],
        actions: [
            { label: 'Open Subscriptions', href: '/subscriptions' },
            {
                label: 'Manage hotel seats',
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.manage'],
            },
        ],
    },
    {
        id: 'ai-point-plan',
        title: 'Set AI credits (AI points) in a plan',
        icon: Coins,
        permissions: ['subscriptions.manage'],
        steps: [
            'AI credits are called AI points in GHASIDO. The subscription plan sets the base points per employee, the shared bonus per seat, and the points charged for voice and other AI actions.',
            'Open Subscriptions, edit a plan, review its monthly point pool and save the updated allowances and usage costs.',
        ],
        actions: [{ label: 'Edit subscription plans', href: '/subscriptions' }],
    },
    {
        id: 'employee-ai-points',
        title: 'Allocate AI points to employees',
        icon: Coins,
        permissions: ['ai_points.manage'],
        roles: ['manager'],
        steps: [
            'Open AI Points to allocate points to employees in your hotel and review monthly usage.',
            'Allocations must fit the hotel plan pool and cannot be lower than points already used this month. If someone runs low, review their allocation and the remaining hotel pool.',
        ],
        actions: [
            {
                label: 'Allocate employee points',
                href: '/ai-points',
                permissions: ['ai_points.manage'],
            },
        ],
    },
    {
        id: 'employee-create',
        title: 'Create an employee account',
        icon: Users,
        permissions: ['employees.create'],
        steps: [
            'In Employees, choose Add Employee and enter the employee details. Select the correct hotel and department; available departments depend on the selected hotel.',
            'Check the account status before saving. A new account needs an available seat in its department.',
        ],
        actions: [
            {
                label: 'Open Employees',
                href: '/employees',
                permissions: ['employees.view', 'employees.create'],
            },
        ],
    },
    {
        id: 'employee-activate',
        title: 'Activate an employee account',
        icon: Users,
        permissions: ['employees.manage'],
        steps: [
            'Find the inactive employee account in Employees and choose Activate.',
            'Deactivating an account blocks sign-in but keeps its training data. Activating it restores access when the hotel account and department seat are available.',
        ],
        actions: [
            {
                label: 'Open Employees',
                href: '/employees',
                permissions: ['employees.view', 'employees.manage'],
            },
        ],
    },
    {
        id: 'departments',
        title: 'Set up departments',
        icon: Building2,
        permissions: ['departments.manage'],
        steps: [
            'Create or update departments in Departments. Lessons, tests and scenarios can then be assigned to the department they are for.',
            'Use Hotels to set each hotel’s seat quota by department. Quotas control future employee accounts and do not delete existing accounts.',
        ],
        actions: [
            {
                label: 'Open Departments',
                href: '/departments',
                permissions: ['departments.view', 'departments.manage'],
            },
        ],
    },
    {
        id: 'roles',
        title: 'Review roles and permissions',
        icon: ShieldCheck,
        permissions: ['roles.manage'],
        steps: [
            'Open Roles & Permissions and review what each role can open or manage.',
            'Give access only to people who need it. The server checks these permissions on every protected page and action.',
        ],
        actions: [
            {
                label: 'Open Roles & Permissions',
                href: '/roles',
                permissions: ['roles.view', 'roles.manage'],
            },
        ],
    },
    {
        id: 'reminders',
        title: 'Send reminders',
        icon: Mail,
        permissions: ['employees.manage', 'messages.manage'],
        steps: [
            'Open Employees to select people who need a reminder, or open Messages & Reminders to manage reminder templates and automatic rules.',
            'Check the hotel, department and employee list before sending. Reminders are sent only to employees who have consented to receive them.',
            'Use the notification bell to review in-app reminders and mark them as seen.',
        ],
        actions: [
            {
                label: 'Open Employees',
                href: '/employees',
                permissions: ['employees.view', 'employees.manage'],
            },
            {
                label: 'Open Messages & Reminders',
                href: '/messages-reminders',
                permissions: ['messages.view', 'messages.manage'],
            },
        ],
    },
    {
        id: 'reports',
        title: 'View reports and export data',
        icon: ChartNoAxesCombined,
        permissions: ['reports.view'],
        steps: [
            'Open Reports & Export and choose the hotel, department, dates and report type you need.',
            'Review the selected scope before exporting. Use the anonymised export option for research data when it is available to your role.',
            'Hotel managers only see their own hotel’s data; full AI transcripts and recordings require the Super Admin permission.',
        ],
        actions: [{ label: 'Open Reports & Export', href: '/reports-export' }],
    },
];

const visibleGuides = computed(() =>
    guides.filter(
        (guide) =>
            (!guide.permissions ||
                guide.permissions.some((permission) => can(permission))) &&
            (!guide.roles ||
                (userRole.value !== null &&
                    guide.roles.includes(userRole.value))),
    ),
);

function visibleActions(guide: HelpGuide): HelpAction[] {
    return (guide.actions ?? []).filter(
        (action) =>
            !action.permissions ||
            action.permissions.every((permission) => can(permission)),
    );
}
</script>

<template>
    <Head title="Help" />

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            title="Help & Guides"
            description="Step-by-step help for training, hotels, accounts, AI points and reports."
        />

        <div class="grid min-w-0 gap-3 xl:grid-cols-2">
            <PanelCard
                v-for="guide in visibleGuides"
                :key="guide.id"
                :title="guide.title"
                :title-id="`help-${guide.id}`"
                class="h-full"
            >
                <template #icon>
                    <span
                        class="bg-brand-100 text-brand-600 grid size-8 shrink-0 place-items-center rounded-full"
                    >
                        <component
                            :is="guide.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                </template>

                <ol class="text-ink-slate grid gap-2.5 text-[13px] leading-5">
                    <li
                        v-for="(step, index) in guide.steps"
                        :key="step"
                        class="flex min-w-0 items-start gap-2.5"
                    >
                        <span
                            class="bg-brand-50 text-brand-700 mt-px grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-semibold"
                            aria-hidden="true"
                        >
                            {{ index + 1 }}
                        </span>
                        <span>{{ step }}</span>
                    </li>
                </ol>

                <div
                    v-if="visibleActions(guide).length"
                    class="border-line/80 mt-4 flex flex-wrap gap-2 border-t pt-3"
                >
                    <Link
                        v-for="action in visibleActions(guide)"
                        :key="action.href"
                        :href="action.href"
                        class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/40 inline-flex min-h-9 items-center rounded-md px-2.5 text-[12px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    >
                        {{ action.label }}
                    </Link>
                </div>
            </PanelCard>
        </div>
    </div>
</template>
