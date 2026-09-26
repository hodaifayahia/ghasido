<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    Bot,
    Check,
    CircleHelp,
    Image,
    Play,
    Sparkles,
    User,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { tk } from '@/lib/i18n';

type Callout = {
    number: string;
    label: string;
    targetX: number;
    targetY: number;
    labelX: number;
    labelY: number;
};

type GuideStep = {
    number: string;
    kicker: string;
    title: string;
    description: string;
    caption: string;
    screen: 'library' | 'brief' | 'roles' | 'settings' | 'preview';
    callouts: Callout[];
    notes: { title: string; text: string }[];
};

const open = defineModel<boolean>('open', { required: true });
const currentIndex = ref(0);

// RP-01, RP-04, GEN-03: explain the real scenario authoring path without
// hiding the draft, review, preview, and publish safeguards.
const steps: GuideStep[] = [
    {
        number: '1',
        kicker: tk('Start in the Scenario Library'),
        title: tk('Create a new scenario'),
        description: tk(
            'Open AI Role-play Scenarios and click Create New Scenario. Give the practice conversation a clear hotel situation, then choose its department and English level.',
        ),
        caption: tk(
            'A scenario starts as a draft, so you can safely complete and review it before employees see it.',
        ),
        screen: 'library',
        callouts: [
            {
                number: '1',
                label: tk('Scenario Library'),
                targetX: 27,
                targetY: 25,
                labelX: 13,
                labelY: 14,
            },
            {
                number: '2',
                label: tk('Search and filters'),
                targetX: 29,
                targetY: 42,
                labelX: 13,
                labelY: 56,
            },
            {
                number: '3',
                label: tk('Create New Scenario'),
                targetX: 82,
                targetY: 80,
                labelX: 78,
                labelY: 65,
            },
        ],
        notes: [
            {
                title: tk('Good title'),
                text: 'Handling a late check-out request',
            },
            {
                title: tk('Choose one department'),
                text: tk(
                    'Employees only see scenarios assigned to their department.',
                ),
            },
        ],
    },
    {
        number: '2',
        kicker: tk('Write the scenario brief'),
        title: tk('Set the situation and level'),
        description: tk(
            'Complete the title, department, level, cover image, and short description. Describe one realistic hotel moment in simple English so the AI can keep the conversation focused.',
        ),
        caption: tk(
            'Keep the description practical: who the guest is, what they need, and what the employee should achieve.',
        ),
        screen: 'brief',
        callouts: [
            {
                number: '1',
                label: tk('Scenario title'),
                targetX: 48,
                targetY: 29,
                labelX: 25,
                labelY: 17,
            },
            {
                number: '2',
                label: tk('Department + level'),
                targetX: 50,
                targetY: 42,
                labelX: 21,
                labelY: 54,
            },
            {
                number: '3',
                label: tk('Description'),
                targetX: 53,
                targetY: 75,
                labelX: 82,
                labelY: 64,
            },
        ],
        notes: [
            {
                title: tk('Short is better'),
                text: tk(
                    'Use one clear situation instead of several different problems.',
                ),
            },
            {
                title: tk('Cover image'),
                text: tk('Use a relevant hotel image at 1200 × 628 px.'),
            },
        ],
    },
    {
        number: '3',
        kicker: tk('Define both roles'),
        title: tk('Make the conversation clear'),
        description: tk(
            'Describe the AI guest and the employee role. Then add the learning objectives employees should practise and use Edit AI Instructions when the guest needs special behaviour.',
        ),
        caption: tk(
            'The employee role is the learner’s point of view. The AI role is the guest the learner will speak with.',
        ),
        screen: 'roles',
        callouts: [
            {
                number: '1',
                label: tk('AI Role (Guest)'),
                targetX: 34,
                targetY: 40,
                labelX: 17,
                labelY: 24,
            },
            {
                number: '2',
                label: tk('Employee Role'),
                targetX: 68,
                targetY: 40,
                labelX: 84,
                labelY: 24,
            },
            {
                number: '3',
                label: tk('Learning objectives'),
                targetX: 52,
                targetY: 75,
                labelX: 81,
                labelY: 82,
            },
        ],
        notes: [
            {
                title: tk('Objective example'),
                text: 'Offer a polite solution and confirm the guest’s request.',
            },
            {
                title: tk('AI instructions'),
                text: tk(
                    'Keep replies short, natural, and appropriate for the selected level.',
                ),
            },
        ],
    },
    {
        number: '4',
        kicker: tk('Choose coaching rules'),
        title: tk('Set attempts and feedback'),
        description: tk(
            'In Scenario Settings, choose the number of attempts, feedback style, focus areas, hints, suggestions, and tags. These settings shape the practice experience without changing the scenario brief.',
        ),
        caption: tk(
            'Communicative success is the goal. Choose the criteria that matter for this hotel situation.',
        ),
        screen: 'settings',
        callouts: [
            {
                number: '1',
                label: tk('Attempts + feedback'),
                targetX: 73,
                targetY: 31,
                labelX: 53,
                labelY: 15,
            },
            {
                number: '2',
                label: tk('Focus Areas'),
                targetX: 72,
                targetY: 54,
                labelX: 89,
                labelY: 48,
            },
            {
                number: '3',
                label: tk('Hints and suggestions'),
                targetX: 70,
                targetY: 73,
                labelX: 48,
                labelY: 86,
            },
        ],
        notes: [
            {
                title: tk('Default practice'),
                text: tk('Three attempts gives employees room to try again.'),
            },
            {
                title: tk('Useful focus areas'),
                text: tk(
                    'Task completion, fluency, pronunciation, vocabulary, and grammar.',
                ),
            },
        ],
    },
    {
        number: '5',
        kicker: tk('Test before employees practise'),
        title: tk('Preview, save, then publish'),
        description: tk(
            'Use Preview & Test to run a complete conversation and check the feedback. Save as Draft while the scenario is unfinished. Publish only after the roles, objectives, settings, and wording are ready.',
        ),
        caption: tk(
            'AI output is a draft for review. Check the conversation yourself before publishing it to a department.',
        ),
        screen: 'preview',
        callouts: [
            {
                number: '1',
                label: tk('Test Scenario'),
                targetX: 21,
                targetY: 37,
                labelX: 13,
                labelY: 22,
            },
            {
                number: '2',
                label: tk('Conversation preview'),
                targetX: 61,
                targetY: 49,
                labelX: 78,
                labelY: 31,
            },
            {
                number: '3',
                label: tk('Save Draft / Update'),
                targetX: 82,
                targetY: 83,
                labelX: 80,
                labelY: 68,
            },
        ],
        notes: [
            {
                title: tk('Preview is safe'),
                text: tk(
                    'Testing does not create an employee attempt or progress record.',
                ),
            },
            {
                title: tk('Publish last'),
                text: tk(
                    'Published scenarios can be attached to lessons or offered in AI Role-play.',
                ),
            },
        ],
    },
] as const;

const currentStep = computed(() => steps[currentIndex.value]);
const progress = computed(() => `${currentIndex.value + 1} / ${steps.length}`);

function previous(): void {
    currentIndex.value = Math.max(0, currentIndex.value - 1);
}

function next(): void {
    currentIndex.value = Math.min(steps.length - 1, currentIndex.value + 1);
}

function selectStep(index: number): void {
    currentIndex.value = index;
}

watch(open, (value) => {
    if (value) currentIndex.value = 0;
});
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('How to create an AI role-play scenario')"
        :description="
            $t(
                'A visual walkthrough of the GHASIDO scenario builder, from a new draft to a tested conversation.',
            )
        "
        size="xl"
    >
        <div class="mt-2 grid gap-3">
            <div
                class="border-ai/25 bg-ai-tint/45 text-brand-900 rounded-md border px-4 py-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[12px] font-semibold">
                            {{ $t('Follow the real workflow') }}
                        </p>
                        <p class="text-ink-slate mt-1 text-[11.5px]">
                            {{
                                $t(
                                    'Library > Brief > Roles > Settings > Preview > Publish',
                                )
                            }}
                        </p>
                    </div>
                    <span
                        class="bg-surface border-ai/25 text-ai rounded-pill inline-flex items-center gap-1.5 border px-2.5 py-1 text-[11px] font-semibold"
                    >
                        <CircleHelp class="size-3.5" aria-hidden="true" />
                        {{ progress }}
                    </span>
                </div>
            </div>

            <div
                class="border-line bg-surface overflow-hidden rounded-lg border"
            >
                <div
                    class="bg-brand-900 flex items-center justify-between gap-3 px-3 py-2.5 text-white sm:px-4"
                >
                    <div class="min-w-0">
                        <p class="text-brand-100 text-[10.5px] font-semibold">
                            {{ $t(currentStep.kicker) }}
                        </p>
                        <h3 class="mt-0.5 truncate text-[14px] font-semibold">
                            {{ $t(currentStep.title) }}
                        </h3>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="currentIndex === 0"
                            class="border-brand-200/40 text-brand-100 hover:bg-brand-800 h-8 w-8 rounded-md p-0 shadow-none"
                            :aria-label="$t('Previous visual guide step')"
                            @click="previous"
                        >
                            <ArrowLeft class="size-3.5" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            class="bg-brand-400 text-brand-900 hover:bg-brand-300 h-8 gap-1 rounded-md px-2.5 text-[11px] font-semibold shadow-none"
                            :disabled="currentIndex === steps.length - 1"
                            @click="next"
                        >
                            {{ $t('Next') }}
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Button>
                    </div>
                </div>

                <div class="bg-app-alt/50 p-2 sm:p-3">
                    <div
                        class="shadow-card relative mx-auto aspect-[1280/853] w-full overflow-hidden rounded-md border border-white"
                    >
                        <div
                            class="bg-app-alt absolute inset-0 grid grid-rows-[14%_86%]"
                        >
                            <div
                                class="bg-surface border-line flex items-center justify-between border-b px-[3%]"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="bg-brand-600 grid size-5 place-items-center rounded-md text-white sm:size-7"
                                    >
                                        <Sparkles
                                            class="size-3 sm:size-4"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span
                                        class="text-brand-900 text-[7px] font-bold sm:text-[11px]"
                                        >{{
                                            $t('AI Role-play Scenarios')
                                        }}</span
                                    >
                                </div>
                                <span
                                    class="text-ink-muted hidden text-[8px] sm:block"
                                    >{{ $t('Admin workspace') }}</span
                                >
                            </div>

                            <div
                                class="grid min-h-0 grid-cols-[28%_72%] gap-[2%] p-[3%]"
                            >
                                <template
                                    v-if="currentStep.screen === 'library'"
                                >
                                    <div
                                        class="border-line bg-surface rounded-md border p-[7%]"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-1"
                                        >
                                            <span
                                                class="text-brand-900 text-[8px] font-bold sm:text-[13px]"
                                                >{{
                                                    $t('Scenario Library')
                                                }}</span
                                            >
                                            <span
                                                class="text-ink-muted text-[6px] sm:text-[9px]"
                                                >{{ $t('12 scenarios') }}</span
                                            >
                                        </div>
                                        <div
                                            class="border-line bg-brand-50/35 mt-[10%] grid gap-1 rounded-md border p-[6%]"
                                        >
                                            <span
                                                class="border-line bg-surface text-ink-muted rounded border px-1.5 py-1 text-[6px] sm:text-[9px]"
                                                >{{
                                                    $t('Search scenarios...')
                                                }}</span
                                            >
                                            <span
                                                class="border-line bg-surface rounded border px-1.5 py-1 text-[6px] sm:text-[9px]"
                                                >{{
                                                    $t('All Departments')
                                                }}</span
                                            >
                                            <span
                                                class="border-line bg-surface rounded border px-1.5 py-1 text-[6px] sm:text-[9px]"
                                                >{{ $t('All Statuses') }}</span
                                            >
                                        </div>
                                        <div class="mt-[10%] grid gap-1">
                                            <div
                                                v-for="item in [
                                                    'Room request',
                                                    'Late check-out',
                                                    'Guest complaint',
                                                ]"
                                                :key="item"
                                                class="border-line flex items-center gap-1 rounded border p-1.5"
                                            >
                                                <span
                                                    class="bg-ai-tint text-ai grid size-4 shrink-0 place-items-center rounded sm:size-6"
                                                    ><Bot
                                                        class="size-2.5 sm:size-3.5"
                                                        aria-hidden="true"
                                                /></span>
                                                <span
                                                    class="text-ink truncate text-[6px] sm:text-[9px]"
                                                    >{{ item }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="border-line bg-surface rounded-md border p-[4%]"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <div>
                                                <span
                                                    class="text-ink-muted text-[7px] sm:text-[10px]"
                                                    >{{
                                                        $t('AI Role-play')
                                                    }}</span
                                                >
                                                <p
                                                    class="text-brand-900 mt-0.5 text-[10px] font-bold sm:text-[16px]"
                                                >
                                                    {{
                                                        $t(
                                                            'Build realistic practice',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                class="bg-brand-600 rounded px-2 py-1 text-[6px] font-semibold text-white sm:text-[9px]"
                                                >{{
                                                    $t('Create New Scenario')
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="mt-[8%] grid grid-cols-3 gap-2"
                                        >
                                            <div
                                                v-for="n in 3"
                                                :key="n"
                                                class="border-line bg-brand-50/35 h-10 rounded border sm:h-20"
                                            />
                                        </div>
                                    </div>
                                </template>

                                <template
                                    v-else-if="currentStep.screen === 'brief'"
                                >
                                    <div
                                        class="border-line bg-surface rounded-md border p-[7%]"
                                    >
                                        <span
                                            class="text-brand-900 text-[8px] font-bold sm:text-[13px]"
                                            >{{ $t('Scenario Library') }}</span
                                        >
                                        <div class="mt-[14%] grid gap-2">
                                            <span
                                                v-for="item in [
                                                    'Room request',
                                                    'Late check-out',
                                                    'Guest complaint',
                                                ]"
                                                :key="item"
                                                class="border-line text-ink-muted rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                >{{ item }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="border-line bg-surface rounded-md border p-[4%]"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-brand-900 text-[10px] font-bold sm:text-[15px]"
                                                >{{ $t('Edit Scenario') }}</span
                                            ><span
                                                class="bg-warning-tint text-warning-text rounded px-1.5 py-0.5 text-[6px] font-semibold sm:text-[9px]"
                                                >{{ $t('draft') }}</span
                                            >
                                        </div>
                                        <div class="mt-[5%] grid gap-[4%]">
                                            <div
                                                class="border-line rounded border p-[2.5%]"
                                            >
                                                <span
                                                    class="text-ink-muted block text-[6px] sm:text-[9px]"
                                                    >{{
                                                        $t('Scenario Title *')
                                                    }}</span
                                                ><span
                                                    class="text-ink mt-1 block text-[7px] sm:text-[11px]"
                                                    >Handling a late check-out
                                                    request</span
                                                >
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div
                                                    class="border-line rounded border p-[2.5%]"
                                                >
                                                    <span
                                                        class="text-ink-muted block text-[6px] sm:text-[9px]"
                                                        >{{
                                                            $t('Department *')
                                                        }}</span
                                                    ><span
                                                        class="text-ink mt-1 block text-[7px] sm:text-[11px]"
                                                        >Reception</span
                                                    >
                                                </div>
                                                <div
                                                    class="border-line rounded border p-[2.5%]"
                                                >
                                                    <span
                                                        class="text-ink-muted block text-[6px] sm:text-[9px]"
                                                        >{{
                                                            $t('Level *')
                                                        }}</span
                                                    ><span
                                                        class="text-ink mt-1 block text-[7px] sm:text-[11px]"
                                                        >{{
                                                            $t('Elementary')
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-[35%_65%] gap-2"
                                            >
                                                <div
                                                    class="bg-brand-100/70 grid min-h-12 place-items-center rounded sm:min-h-20"
                                                >
                                                    <Image
                                                        class="text-brand-600 size-5 sm:size-8"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                                <div
                                                    class="border-line rounded border p-[2.5%]"
                                                >
                                                    <span
                                                        class="text-ink-muted block text-[6px] sm:text-[9px]"
                                                        >{{
                                                            $t(
                                                                'Scenario Description *',
                                                            )
                                                        }}</span
                                                    ><span
                                                        class="text-ink mt-1 block text-[7px] leading-tight sm:text-[10px]"
                                                        >The guest asks for a
                                                        later check-out and the
                                                        employee offers a clear
                                                        solution.</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template
                                    v-else-if="currentStep.screen === 'roles'"
                                >
                                    <div
                                        class="border-line bg-surface rounded-md border p-[7%]"
                                    >
                                        <span
                                            class="text-brand-900 text-[8px] font-bold sm:text-[13px]"
                                            >{{ $t('Edit Scenario') }}</span
                                        >
                                        <div class="mt-[15%] grid gap-2">
                                            <span
                                                v-for="item in [
                                                    $t('Brief'),
                                                    $t('Roles'),
                                                    $t('Objectives'),
                                                ]"
                                                :key="item"
                                                class="bg-brand-50 text-brand-700 rounded px-2 py-1.5 text-[6px] font-semibold sm:text-[9px]"
                                                >{{ item }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="border-line bg-surface rounded-md border p-[4%]"
                                    >
                                        <div class="grid grid-cols-2 gap-2">
                                            <div
                                                class="border-ai/25 bg-ai-tint/35 rounded border p-[4%]"
                                            >
                                                <span
                                                    class="text-ai text-[7px] font-bold sm:text-[11px]"
                                                    >{{
                                                        $t('AI Role (Guest)')
                                                    }}</span
                                                ><span
                                                    class="bg-ai/12 text-ai mx-auto mt-[12%] grid size-7 place-items-center rounded-full sm:size-11"
                                                    ><Sparkles
                                                        class="size-3.5 sm:size-5"
                                                        aria-hidden="true" /></span
                                                ><span
                                                    class="text-ink mt-2 block text-center text-[6px] leading-tight sm:text-[9px]"
                                                    >A guest who needs a later
                                                    check-out.</span
                                                ><span
                                                    class="border-line text-brand-700 mt-[10%] block rounded border px-1 py-1 text-center text-[6px] font-semibold sm:text-[9px]"
                                                    >{{
                                                        $t(
                                                            'Edit AI Instructions',
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="border-success/25 bg-success-tint/45 rounded border p-[4%]"
                                            >
                                                <span
                                                    class="text-success-text text-[7px] font-bold sm:text-[11px]"
                                                    >{{
                                                        $t(
                                                            'Employee Role (User)',
                                                        )
                                                    }}</span
                                                ><span
                                                    class="bg-success/20 text-success mx-auto mt-[12%] grid size-7 place-items-center rounded-full sm:size-11"
                                                    ><User
                                                        class="size-3.5 sm:size-5"
                                                        aria-hidden="true" /></span
                                                ><span
                                                    class="text-ink mt-2 block text-center text-[6px] leading-tight sm:text-[9px]"
                                                    >A reception employee who
                                                    offers a solution.</span
                                                >
                                            </div>
                                        </div>
                                        <div class="mt-[5%]">
                                            <span
                                                class="text-brand-900 text-[8px] font-bold sm:text-[11px]"
                                                >{{
                                                    $t('Learning Objectives')
                                                }}</span
                                            >
                                            <div class="mt-1 grid gap-1">
                                                <span
                                                    v-for="item in [
                                                        'Ask a clarifying question',
                                                        'Offer a polite solution',
                                                    ]"
                                                    :key="item"
                                                    class="border-line flex items-center gap-1 rounded border p-1.5 text-[6px] sm:text-[9px]"
                                                    ><Check
                                                        class="text-success size-2.5 sm:size-3.5"
                                                        aria-hidden="true"
                                                    />{{ item }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template
                                    v-else-if="
                                        currentStep.screen === 'settings'
                                    "
                                >
                                    <div
                                        class="border-line bg-surface rounded-md border p-[7%]"
                                    >
                                        <span
                                            class="text-brand-900 text-[8px] font-bold sm:text-[13px]"
                                            >{{ $t('Edit Scenario') }}</span
                                        >
                                        <div class="mt-[15%] grid gap-2">
                                            <span
                                                v-for="item in [
                                                    $t('Scenario brief'),
                                                    $t('Roles & objectives'),
                                                    $t('Scenario Settings'),
                                                ]"
                                                :key="item"
                                                class="border-line text-ink-muted rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                >{{ item }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="border-line bg-surface rounded-md border p-[4%]"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-brand-900 text-[10px] font-bold sm:text-[15px]"
                                                >{{
                                                    $t('Scenario Settings')
                                                }}</span
                                            ><span
                                                class="bg-ai-tint text-ai rounded px-1.5 py-0.5 text-[6px] font-semibold sm:text-[9px]"
                                                >{{ $t('coaching') }}</span
                                            >
                                        </div>
                                        <div class="mt-[5%] grid gap-[3%]">
                                            <div class="grid grid-cols-2 gap-2">
                                                <span
                                                    class="border-line rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                    >{{
                                                        $t('Attempts: 3')
                                                    }}</span
                                                ><span
                                                    class="border-line rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                    >{{
                                                        $t(
                                                            'Feedback: Encouraging',
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div>
                                                <span
                                                    class="text-ink-muted text-[6px] sm:text-[9px]"
                                                    >{{
                                                        $t('Focus Areas')
                                                    }}</span
                                                >
                                                <div
                                                    class="mt-1 grid grid-cols-3 gap-1"
                                                >
                                                    <span
                                                        v-for="item in [
                                                            $t('Fluency'),
                                                            $t(
                                                                'Task completion',
                                                            ),
                                                            $t('Pronunciation'),
                                                            $t('Vocabulary'),
                                                            $t('Grammar'),
                                                            $t('Tone'),
                                                        ]"
                                                        :key="item"
                                                        class="bg-brand-50 text-brand-700 rounded px-1 py-1 text-[5.5px] sm:text-[8px]"
                                                        >✓ {{ item }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <span
                                                    class="border-line rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                    >{{
                                                        $t('Allow hints: On')
                                                    }}</span
                                                ><span
                                                    class="border-line rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                    >{{
                                                        $t('Suggestions: On')
                                                    }}</span
                                                >
                                            </div>
                                            <span
                                                class="border-line text-ink-muted rounded border p-1.5 text-[6px] sm:p-2 sm:text-[9px]"
                                                >{{
                                                    $t('Tags: :tags', {
                                                        tags: 'reception, requests',
                                                    })
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div
                                        class="border-line bg-surface rounded-md border p-[7%]"
                                    >
                                        <span
                                            class="text-brand-900 text-[8px] font-bold sm:text-[13px]"
                                            >{{ $t('Preview & Test') }}</span
                                        ><span
                                            class="bg-brand-600 mt-[15%] block rounded px-2 py-1.5 text-center text-[6px] font-semibold text-white sm:text-[9px]"
                                            >{{ $t('Test Scenario') }}</span
                                        >
                                        <div class="mt-3 grid gap-1">
                                            <span
                                                class="border-line text-ink-muted rounded border p-1.5 text-[6px] sm:text-[9px]"
                                                >{{
                                                    $t('Select scenario')
                                                }}</span
                                            ><span
                                                class="border-line text-ink-muted rounded border p-1.5 text-[6px] sm:text-[9px]"
                                                >{{
                                                    $t(
                                                        'Run a conversation safely',
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="border-line bg-surface flex min-h-0 flex-col rounded-md border p-[4%]"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-brand-900 text-[10px] font-bold sm:text-[15px]"
                                                >{{
                                                    $t('Conversation Preview')
                                                }}</span
                                            ><span
                                                class="bg-success-tint text-success-text rounded px-1.5 py-0.5 text-[6px] font-semibold sm:text-[9px]"
                                                >{{ $t('Not saved') }}</span
                                            >
                                        </div>
                                        <div class="mt-[7%] grid gap-2">
                                            <div
                                                class="bg-tint-header text-ink max-w-[74%] rounded-xl px-2 py-1.5 text-[6px] sm:px-3 sm:py-2 sm:text-[9px]"
                                            >
                                                Could I check out later, please?
                                            </div>
                                            <div
                                                class="bg-brand-100/70 text-brand-900 ms-auto max-w-[74%] rounded-xl px-2 py-1.5 text-[6px] sm:px-3 sm:py-2 sm:text-[9px]"
                                            >
                                                Of course. What time would you
                                                prefer?
                                            </div>
                                            <div
                                                class="bg-tint-header text-ink max-w-[74%] rounded-xl px-2 py-1.5 text-[6px] sm:px-3 sm:py-2 sm:text-[9px]"
                                            >
                                                Around two o'clock, please.
                                            </div>
                                        </div>
                                        <div
                                            class="mt-auto grid grid-cols-3 gap-1.5 pt-3"
                                        >
                                            <span
                                                class="border-line text-brand-700 rounded border px-1 py-1 text-center text-[5.5px] font-semibold sm:text-[8px]"
                                                >{{ $t('Preview') }}</span
                                            ><span
                                                class="border-line text-brand-700 rounded border px-1 py-1 text-center text-[5.5px] font-semibold sm:text-[8px]"
                                                >{{ $t('Save as Draft') }}</span
                                            ><span
                                                class="bg-brand-600 rounded px-1 py-1 text-center text-[5.5px] font-semibold text-white sm:text-[8px]"
                                                >{{
                                                    $t('Update Scenario')
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <svg
                            class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible"
                            viewBox="0 0 100 100"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <defs>
                                <marker
                                    id="scenario-guide-arrowhead"
                                    markerWidth="3.5"
                                    markerHeight="3.5"
                                    refX="2.8"
                                    refY="1.75"
                                    orient="auto"
                                >
                                    <path
                                        d="M0,0 L0,3.5 L3.5,1.75 z"
                                        fill="currentColor"
                                    />
                                </marker>
                            </defs>
                            <line
                                v-for="callout in currentStep.callouts"
                                :key="`${callout.number}-line`"
                                :x1="callout.labelX"
                                :y1="callout.labelY"
                                :x2="callout.targetX"
                                :y2="callout.targetY"
                                class="text-danger"
                                stroke="currentColor"
                                stroke-width="0.55"
                                stroke-linecap="round"
                                marker-end="url(#scenario-guide-arrowhead)"
                            />
                        </svg>
                        <div
                            v-for="callout in currentStep.callouts"
                            :key="callout.number"
                            class="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-1/2"
                            :style="{
                                left: `${callout.labelX}%`,
                                top: `${callout.labelY}%`,
                            }"
                        >
                            <span
                                class="border-danger bg-surface text-danger shadow-pop inline-flex max-w-[145px] items-center gap-1 rounded-md border px-1.5 py-1 text-[9px] leading-tight font-bold sm:max-w-[175px] sm:text-[10px]"
                                ><span
                                    class="bg-danger text-surface grid size-4 shrink-0 place-items-center rounded-full text-[9px]"
                                    >{{ callout.number }}</span
                                >{{ $t(callout.label) }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="grid gap-2 px-3 py-3 sm:px-4">
                    <p class="text-ink-slate text-[12px] leading-5">
                        {{ $t(currentStep.description) }}
                    </p>
                    <p
                        class="text-ai bg-ai-tint/45 rounded-md px-3 py-2 text-[11px] leading-5"
                    >
                        {{ $t(currentStep.caption) }}
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="note in currentStep.notes"
                            :key="note.title"
                            class="border-line bg-app-alt rounded-md border px-3 py-2.5"
                        >
                            <p
                                class="text-brand-900 text-[11.5px] font-semibold"
                            >
                                {{ $t(note.title) }}
                            </p>
                            <p
                                class="text-ink-slate mt-1 text-[11px] leading-4.5"
                            >
                                {{ $t(note.text) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-1.5">
                <button
                    v-for="(step, index) in steps"
                    :key="step.number"
                    type="button"
                    :aria-label="
                        $t('Open visual guide step :number', {
                            number: step.number,
                        })
                    "
                    :aria-current="currentIndex === index ? 'step' : undefined"
                    :class="[
                        'grid size-6 place-items-center rounded-full text-[10px] font-semibold transition-colors',
                        currentIndex === index
                            ? 'bg-brand-600 text-white'
                            : 'bg-brand-100 text-brand-700 hover:bg-brand-200',
                    ]"
                    @click="selectStep(index)"
                >
                    {{ step.number }}
                </button>
            </div>

            <div
                class="border-line flex items-start gap-2 rounded-md border p-3"
            >
                <Check
                    class="text-success mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="text-ink-slate text-[12px] leading-5">
                    {{
                        $t(
                            'Save as Draft while you work. Test the complete conversation, review the feedback, and publish only when the scenario is ready for the selected department.',
                        )
                    }}
                </p>
            </div>

            <div class="flex justify-end">
                <Button
                    type="button"
                    variant="outline"
                    data-test="close-ai-scenario-creation-tutorial"
                    @click="open = false"
                    >{{ $t('Close') }}</Button
                >
            </div>
        </div>
    </LessonsModal>
</template>
