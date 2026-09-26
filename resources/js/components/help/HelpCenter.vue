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
import { tk } from '@/lib/i18n';

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
        title: tk('Use the training area'),
        icon: LifeBuoy,
        permissions: ['training.self'],
        roles: ['employee', 'manager'],
        steps: [
            tk(
                'Open Home and complete the Pre-test first. Lessons stay locked until the Pre-test is submitted.',
            ),
            tk(
                'Open My Lessons, choose the next lesson, and complete its steps in order. Your progress is saved as you go.',
            ),
            tk(
                'After the required lessons are complete, take the Post-test. Open Certificate to download it when it becomes available.',
            ),
        ],
        actions: [{ label: tk('Open training home'), href: '/learn' }],
    },
    {
        id: 'lessons',
        title: tk('Create a lesson'),
        icon: BookOpen,
        permissions: ['lessons.manage'],
        steps: [
            tk(
                'In Lessons & Content, use Add to create a Course, then add a Unit and a Lesson. Choose the department and hotel scope so the right employees can see it.',
            ),
            tk(
                'Open the lesson editor. Add a short introduction, objectives and the content blocks employees will complete. Edit each block and add its text, image, audio or activity.',
            ),
            tk(
                'Reorder blocks to set the lesson sequence. Use Preview to check the employee view on phone and desktop.',
            ),
            tk(
                'Save as a draft while you work. Publish only when the content and audience are correct; drafts stay hidden from employees.',
            ),
        ],
        actions: [
            {
                label: tk('Open Lessons & Content'),
                href: '/lessons-content',
                permissions: ['lessons.view', 'lessons.manage'],
            },
        ],
    },
    {
        id: 'tests',
        title: tk('Create a Pre-test or Post-test'),
        icon: ClipboardCheck,
        permissions: ['tests.manage'],
        steps: [
            tk(
                'Open Pre-test & Post-test and choose Create New Test. Enter a title, select Pre-test or Post-test, choose its department, and set an optional time limit.',
            ),
            tk(
                'Add questions in the builder. Add the question text and answer choices or media, then arrange the questions in the order employees should see them.',
            ),
            tk(
                'Review test settings and employee result visibility. Preview the test before publishing it.',
            ),
            tk(
                'Create a matching test for the other stage. Keep the skill coverage and difficulty aligned, but use different questions and situations.',
            ),
            tk(
                'Publish each test when it is ready. The Pre-test gates lessons; the Post-test becomes available under the configured completion condition.',
            ),
        ],
        actions: [
            {
                label: tk('Open test builder'),
                href: '/tests',
                permissions: ['tests.view', 'tests.manage'],
            },
        ],
    },
    {
        id: 'scenarios',
        title: tk('Create an AI role-play scenario'),
        icon: Bot,
        permissions: ['scenarios.manage'],
        steps: [
            tk(
                'In AI Role-play Scenarios, select Create New Scenario. Give it one realistic hotel situation, then choose its department and English level.',
            ),
            tk(
                'Describe what the guest needs and what the employee should achieve. Define both roles, learning objectives, and any special AI instructions.',
            ),
            tk(
                'Set attempts, feedback and practice criteria. Use Preview & Test to run a full conversation and review the feedback.',
            ),
            tk(
                'Save as a draft while editing. Publish only after checking the wording, roles and settings; employees then see it in the assigned department.',
            ),
        ],
        actions: [
            {
                label: tk('Open AI scenarios'),
                href: '/ai-scenarios',
                permissions: ['scenarios.view', 'scenarios.manage'],
            },
        ],
    },
    {
        id: 'hotel-approval',
        title: tk('Approve a new hotel account'),
        icon: Building2,
        permissions: ['hotels.approve'],
        steps: [
            tk(
                'Open Hotels and filter for Pending to find a new hotel account awaiting review.',
            ),
            tk(
                'Open the hotel row and choose Approve. Approval starts its contract today.',
            ),
            tk(
                'Assign a subscription plan and set the hotel’s department seat limits so it is ready for employee accounts.',
            ),
        ],
        actions: [
            {
                label: tk('Open Hotels'),
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.approve'],
            },
        ],
    },
    {
        id: 'hotel-reactivation',
        title: tk('Resume a paused hotel account'),
        icon: Building2,
        permissions: ['hotels.manage'],
        steps: [
            tk('Open Hotels and find the hotel with Paused status.'),
            tk(
                'Choose Resume Access after checking the subscription plan and contract dates. Extend the contract first if needed.',
            ),
            tk(
                'Open the hotel details to review its account status, employee seats and recent activity.',
            ),
        ],
        actions: [
            {
                label: tk('Open Hotels'),
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.manage'],
            },
        ],
    },
    {
        id: 'subscriptions',
        title: tk('Set up subscriptions and seat limits'),
        icon: CreditCard,
        permissions: ['subscriptions.manage'],
        steps: [
            tk(
                'Open Subscriptions and create or edit a plan. Set the employee limit, price, AI point allowance and which payment methods are available.',
            ),
            tk(
                'Assign an active plan to the hotel. Review contract dates and the allowed seats for each department in Hotels.',
            ),
            tk(
                'Seat limits apply per department. If you lower a limit below current usage, existing accounts remain and new accounts are blocked until there is room.',
            ),
        ],
        actions: [
            { label: tk('Open Subscriptions'), href: '/subscriptions' },
            {
                label: tk('Manage hotel seats'),
                href: '/hotels',
                permissions: ['hotels.view', 'hotels.manage'],
            },
        ],
    },
    {
        id: 'ai-point-plan',
        title: tk('Set AI credits (AI points) in a plan'),
        icon: Coins,
        permissions: ['subscriptions.manage'],
        steps: [
            tk(
                'AI credits are called AI points in GHASIDO. The subscription plan sets the base points per employee, the shared bonus per seat, and the points charged for voice and other AI actions.',
            ),
            tk(
                'Open Subscriptions, edit a plan, review its monthly point pool and save the updated allowances and usage costs.',
            ),
        ],
        actions: [
            { label: tk('Edit subscription plans'), href: '/subscriptions' },
        ],
    },
    {
        id: 'employee-ai-points',
        title: tk('Allocate AI points to employees'),
        icon: Coins,
        permissions: ['ai_points.manage'],
        roles: ['manager'],
        steps: [
            tk(
                'Open AI Points to allocate points to employees in your hotel and review monthly usage.',
            ),
            tk(
                'Allocations must fit the hotel plan pool and cannot be lower than points already used this month. If someone runs low, review their allocation and the remaining hotel pool.',
            ),
        ],
        actions: [
            {
                label: tk('Allocate employee points'),
                href: '/ai-points',
                permissions: ['ai_points.manage'],
            },
        ],
    },
    {
        id: 'employee-create',
        title: tk('Create an employee account'),
        icon: Users,
        permissions: ['employees.create'],
        steps: [
            tk(
                'In Employees, choose Add Employee and enter the employee details. Select the correct hotel and department; available departments depend on the selected hotel.',
            ),
            tk(
                'Check the account status before saving. A new account needs an available seat in its department.',
            ),
        ],
        actions: [
            {
                label: tk('Open Employees'),
                href: '/employees',
                permissions: ['employees.view', 'employees.create'],
            },
        ],
    },
    {
        id: 'employee-activate',
        title: tk('Activate an employee account'),
        icon: Users,
        permissions: ['employees.manage'],
        steps: [
            tk(
                'Find the inactive employee account in Employees and choose Activate.',
            ),
            tk(
                'Deactivating an account blocks sign-in but keeps its training data. Activating it restores access when the hotel account and department seat are available.',
            ),
        ],
        actions: [
            {
                label: tk('Open Employees'),
                href: '/employees',
                permissions: ['employees.view', 'employees.manage'],
            },
        ],
    },
    {
        id: 'departments',
        title: tk('Set up departments'),
        icon: Building2,
        permissions: ['departments.manage'],
        steps: [
            tk(
                'Create or update departments in Departments. Lessons, tests and scenarios can then be assigned to the department they are for.',
            ),
            tk(
                'Use Hotels to set each hotel’s seat quota by department. Quotas control future employee accounts and do not delete existing accounts.',
            ),
        ],
        actions: [
            {
                label: tk('Open Departments'),
                href: '/departments',
                permissions: ['departments.view', 'departments.manage'],
            },
        ],
    },
    {
        id: 'roles',
        title: tk('Review roles and permissions'),
        icon: ShieldCheck,
        permissions: ['roles.manage'],
        steps: [
            tk(
                'Open Roles & Permissions and review what each role can open or manage.',
            ),
            tk(
                'Give access only to people who need it. The server checks these permissions on every protected page and action.',
            ),
        ],
        actions: [
            {
                label: tk('Open Roles & Permissions'),
                href: '/roles',
                permissions: ['roles.view', 'roles.manage'],
            },
        ],
    },
    {
        id: 'reminders',
        title: tk('Send reminders'),
        icon: Mail,
        permissions: ['employees.manage', 'messages.manage'],
        steps: [
            tk(
                'Open Employees to select people who need a reminder, or open Messages & Reminders to manage reminder templates and automatic rules.',
            ),
            tk(
                'Check the hotel, department and employee list before sending. Reminders are sent only to employees who have consented to receive them.',
            ),
            tk(
                'Use the notification bell to review in-app reminders and mark them as seen.',
            ),
        ],
        actions: [
            {
                label: tk('Open Employees'),
                href: '/employees',
                permissions: ['employees.view', 'employees.manage'],
            },
            {
                label: tk('Open Messages & Reminders'),
                href: '/messages-reminders',
                permissions: ['messages.view', 'messages.manage'],
            },
        ],
    },
    {
        id: 'reports',
        title: tk('View reports and export data'),
        icon: ChartNoAxesCombined,
        permissions: ['reports.view'],
        steps: [
            tk(
                'Open Reports & Export and choose the hotel, department, dates and report type you need.',
            ),
            tk(
                'Review the selected scope before exporting. Use the anonymised export option for research data when it is available to your role.',
            ),
            tk(
                'Hotel managers only see their own hotel’s data; full AI transcripts and recordings require the Super Admin permission.',
            ),
        ],
        actions: [
            { label: tk('Open Reports & Export'), href: '/reports-export' },
        ],
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
    <Head :title="$t('Help')" />

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('Help & Guides')"
            :description="
                $t(
                    'Step-by-step help for training, hotels, accounts, AI points and reports.',
                )
            "
        />

        <div class="grid min-w-0 gap-3 xl:grid-cols-2">
            <PanelCard
                v-for="guide in visibleGuides"
                :key="guide.id"
                :title="$t(guide.title)"
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
                        <span>{{ $t(step) }}</span>
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
                        {{ $t(action.label) }}
                    </Link>
                </div>
            </PanelCard>
        </div>
    </div>
</template>
