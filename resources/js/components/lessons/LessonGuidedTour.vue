<script setup lang="ts">
import { ArrowLeft, ArrowRight, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import { t, tk } from '@/lib/i18n';

type Placement = 'top' | 'bottom';

type TourStep = {
    target: string;
    title: string;
    body: string;
    tip: string;
    placement?: Placement;
    clickSelector?: string;
};

type Box = {
    top: number;
    left: number;
    right: number;
    bottom: number;
    width: number;
    height: number;
};

const open = defineModel<boolean>('open', { required: true });
const currentIndex = ref(0);
const targetBox = ref<Box | null>(null);
const card = ref<HTMLElement | null>(null);
const cardBox = ref<Box | null>(null);
const retrying = ref(false);

const steps: TourStep[] = [
    {
        target: '[data-test="lesson-title-input"]',
        title: tk('1. Lesson title'),
        body: tk(
            'This is the name employees will see. Click the field and type a clear topic, such as Handling a Room Request.',
        ),
        tip: tk(
            'The title saves automatically when you click outside the field.',
        ),
        placement: 'bottom',
    },
    {
        target: '[data-test="change-cover-button"]',
        title: tk('2. Lesson cover image'),
        body: tk(
            'Click Change Image to choose an image from the library or upload your own hotel image.',
        ),
        tip: tk(
            'Use an image that helps the employee understand the situation before reading.',
        ),
        placement: 'bottom',
    },
    {
        target: '[data-test="lesson-introduction-input"]',
        title: tk('3. Lesson introduction'),
        body: tk(
            'Write one short explanation of what the employee will practise in this lesson.',
        ),
        tip: tk(
            'Keep it short and practical. Example: Learn how to respond when a guest asks for an extra towel.',
        ),
        placement: 'top',
    },
    {
        target: '[data-test="add-objective-button"]',
        title: tk('4. Lesson objectives'),
        body: tk(
            'Click Add Objective, write one learning goal, then press Enter. Repeat for each goal.',
        ),
        tip: tk(
            'Good objectives start with an action: Understand, ask, explain, confirm, or respond.',
        ),
        placement: 'top',
    },
    {
        target: '[data-tour="lesson-blocks"]',
        title: tk('5. Lesson Blocks are employee steps'),
        body: tk(
            'Every row is one step employees complete. Use Edit to fill the step, the arrows to reorder it, the eye to hide it, and the trash icon to remove it.',
        ),
        tip: tk(
            'The default lesson starts with Situation, Vocabulary, Expressions, Listen & Repeat, Dialogue, Video, Practice, AI Role-play, and Complete.',
        ),
        placement: 'top',
    },
    {
        target: '[data-tour="vocabulary-edit"]',
        title: tk('6. Open Vocabulary'),
        body: tk(
            'This arrow points to the real Edit button for the Vocabulary step. Press the button below to open it for you.',
        ),
        tip: tk(
            'If your lesson does not contain Vocabulary, press Next to skip this step.',
        ),
        placement: 'bottom',
        clickSelector: '[data-tour="vocabulary-edit"]',
    },
    {
        target: '[data-tour="add-lexicon-item"]',
        title: tk('7. Add a word'),
        body: tk(
            'Click Add word. The form opens inside the current lesson, so you do not leave the page.',
        ),
        tip: tk(
            'For Useful Expressions, the same button is called Add expression.',
        ),
        placement: 'bottom',
        clickSelector: '[data-tour="add-lexicon-item"]',
    },
    {
        target: '[data-tour="lexicon-english-field"]',
        title: tk('8. Enter the English word'),
        body: tk(
            'Type the English word or expression employees should learn. You can also add pronunciation, an image, and part of speech.',
        ),
        tip: tk(
            'Example: towel. Do not put the Arabic translation in the English field.',
        ),
        placement: 'bottom',
    },
    {
        target: '[data-tour="lexicon-meaning-field"]',
        title: tk('9. Add Show Meaning content'),
        body: tk(
            'Enter the Arabic meaning, a simple explanation, and a hotel example. The employee sees Arabic only after tapping Show Meaning.',
        ),
        tip: tk(
            'Review AI suggestions before applying them. AI drafts are not saved until you apply and save them.',
        ),
        placement: 'top',
    },
    {
        target: '[data-tour="save-lexicon-item"]',
        title: tk('10. Save the word'),
        body: tk(
            'Click Save to attach the word to this Vocabulary step. The word will then appear in the lesson block.',
        ),
        tip: tk('You can generate audio after saving the word.'),
        placement: 'top',
        clickSelector: '[data-tour="save-lexicon-item"]',
    },
    {
        target: '[data-tour="save-block"]',
        title: tk('11. Save the Vocabulary block'),
        body: tk(
            'The word is saved, but the Vocabulary step is still open. Click Save block so the step itself is saved before moving to Dialogue.',
        ),
        tip: tk(
            'This closes the Vocabulary editor and returns you to the Lesson Blocks list.',
        ),
        placement: 'top',
        clickSelector: '[data-tour="save-block"]',
    },
    {
        target: '[data-tour="dialogue-edit"]',
        title: tk('12. Open Dialogue'),
        body: tk(
            'Now open the real Dialogue step. A dialogue is written one line at a time between Staff and Guest.',
        ),
        tip: tk('If your lesson has no Dialogue step, press Next to skip it.'),
        placement: 'bottom',
        clickSelector: '[data-tour="dialogue-edit"]',
    },
    {
        target: '[data-tour="add-dialogue-line"]',
        title: tk('13. Add a dialogue line'),
        body: tk(
            'Click Add line, choose Staff or Guest, and write the English sentence. Add the Arabic meaning for Show Meaning.',
        ),
        tip: tk('Example: Guest — Could I have an extra towel, please?'),
        placement: 'bottom',
        clickSelector: '[data-tour="add-dialogue-line"]',
    },
    {
        target: '[data-tour="dialogue-english-field"]',
        title: tk('14. Write the English sentence'),
        body: tk(
            'Use one short sentence per line. Add another line for the other speaker so the conversation feels real.',
        ),
        tip: tk('Example: Staff — Of course. I will send one right away.'),
        placement: 'bottom',
    },
    {
        target: '[data-tour="generate-audio"]',
        title: tk('15. Generate Normal and Slow audio'),
        body: tk(
            'After you have entered English sentences, click Generate audio. The system creates a Normal clip and a Slow clip for each sentence.',
        ),
        tip: tk(
            'Audio is stored once and then played by employees; it is not generated during the lesson.',
        ),
        placement: 'top',
        clickSelector: '[data-tour="generate-audio"]',
    },
    {
        target: '[data-tour="save-block"]',
        title: tk('16. Save the whole block'),
        body: tk(
            'Click Save block to save the Dialogue settings and close the editor.',
        ),
        tip: tk(
            'If you changed a block but do not click Save block, those block changes will not be kept.',
        ),
        placement: 'top',
        clickSelector: '[data-tour="save-block"]',
    },
    {
        target: '[data-test="lessons-tab-preview"]',
        title: tk('17. Preview before publishing'),
        body: tk(
            'Open Preview and check the real employee experience on Phone (390px) and Desktop. Then use Save Draft while testing or Publish Lesson when ready.',
        ),
        tip: tk('Preview does not record employee progress.'),
        placement: 'bottom',
    },
];

const step = computed(() => steps[currentIndex.value]);
const progress = computed(() =>
    t(':current of :total', {
        current: currentIndex.value + 1,
        total: steps.length,
    }),
);
const isLast = computed(() => currentIndex.value === steps.length - 1);

function boxFor(element: Element): Box {
    const rect = element.getBoundingClientRect();
    const padding = 6;

    return {
        top: Math.max(8, rect.top - padding),
        left: Math.max(8, rect.left - padding),
        right: Math.min(window.innerWidth - 8, rect.right + padding),
        bottom: Math.min(window.innerHeight - 8, rect.bottom + padding),
        width: rect.width + padding * 2,
        height: rect.height + padding * 2,
    };
}

function updateLayout(): void {
    const element = document.querySelector(step.value.target);

    targetBox.value = element === null ? null : boxFor(element);

    if (card.value !== null) {
        cardBox.value = boxFor(card.value);
    }
}

function focusCurrentStep(): void {
    retrying.value = false;

    nextTick(() => {
        const element = document.querySelector(step.value.target);

        if (element !== null) {
            element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            window.setTimeout(updateLayout, 250);
        } else {
            updateLayout();
        }
    });
}

function retry(): void {
    retrying.value = true;
    window.setTimeout(() => {
        focusCurrentStep();
    }, 150);
}

function onViewportChange(): void {
    updateLayout();
}

function goTo(index: number): void {
    currentIndex.value = Math.max(0, Math.min(index, steps.length - 1));
    focusCurrentStep();
}

function advance(): void {
    const selector = step.value.clickSelector;
    const element =
        selector === undefined ? null : document.querySelector(selector);

    if (element !== null) {
        (element as HTMLElement).click();
        window.setTimeout(() => {
            if (isLast.value) {
                open.value = false;
            } else {
                goTo(currentIndex.value + 1);
            }
        }, 450);

        return;
    }

    if (isLast.value) {
        open.value = false;

        return;
    }

    goTo(currentIndex.value + 1);
}

const masks = computed(() => {
    if (targetBox.value === null) {
        return null;
    }

    return {
        top: { height: `${targetBox.value.top}px` },
        bottom: {
            top: `${targetBox.value.bottom}px`,
        },
        left: {
            top: `${targetBox.value.top}px`,
            width: `${targetBox.value.left}px`,
            height: `${targetBox.value.height}px`,
        },
        right: {
            top: `${targetBox.value.top}px`,
            left: `${targetBox.value.right}px`,
            height: `${targetBox.value.height}px`,
        },
    };
});

const targetStyle = computed(() => {
    if (targetBox.value === null) {
        return {};
    }

    return {
        top: `${targetBox.value.top}px`,
        left: `${targetBox.value.left}px`,
        width: `${targetBox.value.width}px`,
        height: `${targetBox.value.height}px`,
    };
});

const cardStyle = computed(() => {
    if (targetBox.value === null || typeof window === 'undefined') {
        return {
            left: '16px',
            right: '16px',
            bottom: '16px',
        };
    }

    const width = Math.min(380, window.innerWidth - 32);
    const left = Math.max(
        16,
        Math.min(
            targetBox.value.left + targetBox.value.width / 2 - width / 2,
            window.innerWidth - width - 16,
        ),
    );
    const estimatedHeight = 270;
    const preferredTop =
        step.value.placement === 'top'
            ? targetBox.value.top - estimatedHeight - 18
            : targetBox.value.bottom + 18;
    const top = Math.max(
        16,
        Math.min(preferredTop, window.innerHeight - estimatedHeight - 16),
    );

    return {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
    };
});

const arrow = computed(() => {
    if (targetBox.value === null || cardBox.value === null) {
        return null;
    }

    const targetX = targetBox.value.left + targetBox.value.width / 2;
    const targetY = targetBox.value.top + targetBox.value.height / 2;
    const cardX = Math.max(
        cardBox.value.left + 28,
        Math.min(targetX, cardBox.value.right - 28),
    );
    const cardAboveTarget = cardBox.value.bottom < targetY;

    return {
        x1: cardX,
        y1: cardAboveTarget ? cardBox.value.bottom : cardBox.value.top,
        x2: targetX,
        y2: targetY,
    };
});

watch(open, (isOpen) => {
    if (isOpen) {
        currentIndex.value = 0;
        focusCurrentStep();
    }
});

onMounted(() => {
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
            <div
                v-if="masks === null"
                class="bg-ink/45 fixed inset-0 z-[1000]"
                aria-hidden="true"
            />

            <template v-if="masks !== null">
                <div
                    class="bg-ink/45 fixed top-0 left-0 z-[1001] w-full"
                    :style="masks.top"
                    aria-hidden="true"
                />
                <div
                    class="bg-ink/45 fixed bottom-0 left-0 z-[1001] w-full"
                    :style="masks.bottom"
                    aria-hidden="true"
                />
                <div
                    class="bg-ink/45 fixed left-0 z-[1001]"
                    :style="masks.left"
                    aria-hidden="true"
                />
                <div
                    class="bg-ink/45 fixed right-0 z-[1001]"
                    :style="masks.right"
                    aria-hidden="true"
                />
                <div
                    class="border-brand-400 pointer-events-none fixed z-[1002] rounded-md border-2 shadow-[0_0_0_4px_color-mix(in_srgb,var(--color-brand-400)_25%,transparent)]"
                    :style="targetStyle"
                    aria-hidden="true"
                />
            </template>

            <svg
                v-if="arrow !== null"
                class="text-brand-400 pointer-events-none fixed inset-0 z-[1003] h-full w-full"
                aria-hidden="true"
            >
                <defs>
                    <marker
                        id="lesson-tour-arrowhead"
                        markerWidth="8"
                        markerHeight="8"
                        refX="6"
                        refY="3"
                        orient="auto"
                    >
                        <path d="M0,0 L0,6 L6,3 z" fill="currentColor" />
                    </marker>
                </defs>
                <line
                    :x1="arrow.x1"
                    :y1="arrow.y1"
                    :x2="arrow.x2"
                    :y2="arrow.y2"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    marker-end="url(#lesson-tour-arrowhead)"
                />
            </svg>

            <section
                ref="card"
                class="border-line bg-surface shadow-pop fixed z-[1004] max-h-[calc(100dvh-32px)] overflow-y-auto rounded-lg border p-4"
                :style="cardStyle"
                role="dialog"
                aria-modal="true"
                aria-labelledby="lesson-guided-tour-title"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-brand-600 text-[11px] font-semibold">
                            {{
                                $t('Guided lesson tour · :progress', {
                                    progress,
                                })
                            }}
                        </p>
                        <h2
                            id="lesson-guided-tour-title"
                            class="font-heading text-brand-900 mt-1 text-[17px] font-semibold"
                        >
                            {{ $t(step.title) }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="text-ink-muted hover:bg-brand-50 inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                        :aria-label="$t('Close guided tour')"
                        @click="open = false"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <p class="text-ink-slate mt-3 text-[13px] leading-5">
                    {{ $t(step.body) }}
                </p>
                <p
                    class="text-brand-700 bg-brand-50/70 mt-3 rounded-md px-3 py-2 text-[11.5px] leading-5"
                >
                    {{ $t(step.tip) }}
                </p>

                <p
                    v-if="targetBox === null"
                    class="text-warning-text bg-warning-tint mt-3 rounded-md px-3 py-2 text-[11.5px] leading-5"
                >
                    {{
                        $t(
                            'This control is not visible right now. It may have been removed from the lesson, or the previous step still needs to be opened.',
                        )
                    }}
                </p>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentIndex === 0"
                        class="border-line text-brand-700 hover:bg-brand-50 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="goTo(currentIndex - 1)"
                    >
                        <ArrowLeft class="size-3.5" aria-hidden="true" />
                        {{ $t('Back') }}
                    </Button>
                    <Button
                        v-if="targetBox === null"
                        type="button"
                        variant="outline"
                        :disabled="retrying"
                        class="border-line text-brand-700 hover:bg-brand-50 h-9 rounded-md px-3 text-[12px] font-semibold shadow-none"
                        @click="retry"
                    >
                        {{ $t('Try again') }}
                    </Button>
                    <Button
                        type="button"
                        class="bg-brand-600 hover:bg-brand-700 shadow-btn h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                        @click="advance"
                    >
                        {{
                            isLast
                                ? $t('Finish')
                                : step.clickSelector
                                  ? $t('Show me')
                                  : $t('Next')
                        }}
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Button>
                </div>
            </section>
        </template>
    </Teleport>
</template>
