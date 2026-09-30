<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import HomeCard from '@/components/learning/home/HomeCard.vue';
import HomeGoodToKnowCard from '@/components/learning/home/HomeGoodToKnowCard.vue';
import HomePhotoCard from '@/components/learning/home/HomePhotoCard.vue';
import HomeRememberCard from '@/components/learning/home/HomeRememberCard.vue';
import CoachCard from '@/components/learning/CoachCard.vue';
import JourneyStepper from '@/components/learning/JourneyStepper.vue';
import StreakCard from '@/components/learning/StreakCard.vue';
import { useI18n } from '@/composables/useI18n';
import { lessons } from '@/routes/learn';
import { start } from '@/routes/learn/tests';
import type {
    AttemptInProgress,
    CoachSummary,
    ContinueLesson,
    HomeTest,
    JourneyState,
    MediaRef,
    PreTestFactIcon,
    PreTestIntro,
    StreakSummary,
} from '@/types';
import LevelSuggestionCard from '@/components/learning/level/LevelSuggestionCard.vue';

/*
 * The employee home (JOURNEY-01, JOURNEY-02, PROG-05; spec 0003 Part E).
 * Until the Pre-test is submitted the card is its intro (desginphotos/
 * employ/photo_20); afterwards the same layout says "Continue where you
 * left off" from the shared journey. Layout at 1280×853: left column 484px
 * from x 216 (stepper at y 98, card at y 186), right column from x 701 to
 * x 1268 with the photo at y 76 and the two cards 19px / 11px inside it.
 */
type Props = {
    test: HomeTest | null;
    attemptInProgress: AttemptInProgress | null;
    continueLesson: ContinueLesson | null;
    journey: JourneyState;
    photo: MediaRef | null;
    streak: StreakSummary;
    coach: CoachSummary;
};

const props = defineProps<Props>();

const { t } = useI18n();

const intro = computed((): PreTestIntro => props.test?.intro ?? {});
// The server sends the Post-test here once every lesson is done and it has
// not been taken yet (spec 0005 §3.1); the Pre-test until it is submitted.
const isPostTest = computed(() => props.test?.type === 'post');
const showIntro = computed(
    () =>
        props.test !== null &&
        (isPostTest.value || !props.journey.preTestSubmitted),
);
const testLabel = computed(() =>
    isPostTest.value ? t('Post-test') : t('Pre-test'),
);

const primaryClass =
    'bg-brand-600 ease-brand hover:bg-brand-700 focus-visible:ring-brand-600/40 flex h-[50px] w-full items-center justify-center gap-[14px] rounded-lg px-6 text-xl leading-none font-semibold text-white transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.98] disabled:opacity-70';
const secondaryClass =
    'text-brand-600 focus-visible:ring-brand-600/40 inline-flex min-h-11 items-center rounded-sm px-1 text-sm leading-5 font-medium underline md:min-h-0 underline-offset-[3px] focus-visible:ring-2 focus-visible:outline-none';

type Fact = { icon: PreTestFactIcon; label: string; text: string };

const continueFacts = computed((): Fact[] => {
    const j = props.journey;
    const facts: Fact[] = [
        {
            icon: 'chart',
            label: t('Your progress'),
            text: t(':completed of :total lessons completed', {
                completed: j.lessonsCompleted,
                total: j.lessonsTotal,
            }),
        },
    ];

    if (props.continueLesson) {
        facts.push(
            {
                icon: 'list',
                label: t('Next lesson'),
                text: props.continueLesson.title,
            },
            {
                icon: 'target',
                label: t('Next step'),
                text: props.continueLesson.stepLabel,
            },
        );
    } else if (j.postTestUnlocked) {
        facts.push({
            icon: 'target',
            label: t('Next step'),
            text: j.certificateAvailable
                ? t('Your certificate is ready')
                : t('Take the Post-test'),
        });
    }

    return facts;
});
</script>

<template>
    <Head :title="$t('Home')" />
    <h1 class="sr-only">{{ $t('Home') }}</h1>

    <div
        class="grid min-w-0 gap-6 ps-4 pe-3 pt-1 pb-6 min-[1100px]:grid-cols-[484px_minmax(0,1fr)] min-[1100px]:gap-px"
    >
        <div class="flex min-w-0 flex-col">
            <JourneyStepper :journey="journey" class="mt-[22px]" />
            <LevelSuggestionCard class="mt-5" />

            <HomeCard
                v-if="showIntro && test"
                :eyebrow="
                    intro.eyebrow ??
                    (isPostTest
                        ? $t('The last step')
                        : $t('Welcome to GHASIDO'))
                "
                :heading="intro.heading ?? test.title"
                :paragraphs="intro.paragraphs ?? []"
                emphasis="not a pass or fail test"
                :facts="intro.facts ?? []"
                class="mt-[34px]"
            >
                <template #primary>
                    <Link
                        v-if="attemptInProgress"
                        :href="attemptInProgress.url"
                        :class="primaryClass"
                        data-test="resume-pretest-link"
                    >
                        {{ $t('Resume :test', { test: testLabel }) }}
                        <ArrowRight
                            class="size-6 stroke-[2.25]"
                            aria-hidden="true"
                        />
                    </Link>
                    <Form
                        v-else
                        v-bind="start.form({ test: test.id })"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            :class="primaryClass"
                            :data-test="
                                isPostTest
                                    ? 'start-posttest-button'
                                    : 'start-pretest-button'
                            "
                        >
                            {{
                                intro.primary ??
                                $t('Start :test', { test: testLabel })
                            }}
                            <ArrowRight
                                class="size-6 stroke-[2.25]"
                                aria-hidden="true"
                            />
                        </button>
                    </Form>
                </template>
                <template #secondary>
                    <Link :href="lessons()" :class="secondaryClass">
                        {{ intro.secondary ?? $t('I’ll do it later') }}
                    </Link>
                </template>
            </HomeCard>

            <HomeCard
                v-else
                :eyebrow="$t('Welcome back')"
                :heading="$t('Continue where you left off')"
                :paragraphs="[
                    continueLesson
                        ? $t('Your next step is :step in :lesson.', {
                              step: continueLesson.stepLabel,
                              lesson: continueLesson.title,
                          })
                        : journey.certificateAvailable
                          ? $t(
                                'You have finished your training. Your certificate is ready to view.',
                            )
                          : journey.postTestUnlocked
                            ? $t(
                                  'You have finished every lesson. The Post-test is the last step.',
                              )
                            : $t('Your lessons are ready whenever you are.'),
                    $t(
                        'Everything you do is saved automatically, so you can stop and come back at any time.',
                    ),
                ]"
                :facts="continueFacts"
                class="mt-[34px]"
            >
                <template #primary>
                    <Link
                        v-if="journey.continueUrl"
                        :href="journey.continueUrl"
                        :class="primaryClass"
                        data-test="continue-lesson-link"
                    >
                        {{ $t('Continue') }}
                        <ArrowRight
                            class="size-6 stroke-[2.25]"
                            aria-hidden="true"
                        />
                    </Link>
                    <Link
                        v-else
                        :href="lessons()"
                        :class="primaryClass"
                        data-test="open-lessons-link"
                    >
                        {{ $t('My Lessons') }}
                        <ArrowRight
                            class="size-6 stroke-[2.25]"
                            aria-hidden="true"
                        />
                    </Link>
                </template>
                <template #secondary>
                    <Link :href="lessons()" :class="secondaryClass">
                        {{ $t('See all lessons') }}
                    </Link>
                </template>
            </HomeCard>
        </div>

        <div class="relative flex min-w-0 flex-col">
            <HomePhotoCard :photo="photo" :quote="intro.script" />

            <!-- The test's own "Good to know" and "Remember" belong to its
                 intro (photo_20); once it is done the learner's momentum
                 takes their place, so the column still fits the window
                 (spec 0005 §3.2, §3.5). -->
            <HomeGoodToKnowCard
                v-if="showIntro && intro.good_to_know?.length"
                :title="intro.good_to_know_title ?? $t('Good to know')"
                :items="intro.good_to_know"
                class="mt-[17px] min-[1100px]:ms-[19px] min-[1100px]:me-[11px]"
            />
            <HomeRememberCard
                v-if="showIntro && intro.remember"
                :title="intro.remember.title"
                :text="intro.remember.text"
                class="mt-3 min-[1100px]:ms-[19px] md:w-[373px]"
            />

            <StreakCard
                v-if="!showIntro"
                :streak="streak"
                class="mt-[17px] min-[1100px]:ms-[19px] min-[1100px]:me-[11px]"
            />
            <CoachCard
                v-if="!showIntro"
                :coach="coach"
                class="mt-3 min-[1100px]:ms-[19px] min-[1100px]:me-[11px]"
            />

            <!-- The client's "Better Communication Brighter Careers"
                 handwriting, bottom-right as photo_20 draws it. -->
            <img
                v-if="showIntro"
                src="/decor/better-communication.png"
                alt=""
                aria-hidden="true"
                width="420"
                height="210"
                draggable="false"
                class="pointer-events-none absolute end-[14px] bottom-[30px] hidden w-[148px] -rotate-[14deg] opacity-85 select-none min-[1100px]:block"
            />
        </div>
    </div>
</template>
