<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award, BookOpen, Flame, TrendingUp } from '@lucide/vue';
import StatCard from '@/components/common/StatCard.vue';
import CoachCard from '@/components/learning/CoachCard.vue';
import ProgressCourseCard from '@/components/learning/ProgressCourseCard.vue';
import TestResultCard from '@/components/learning/TestResultCard.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useI18n } from '@/composables/useI18n';
import { intlLocale } from '@/lib/i18n';
import type {
    CoachSummary,
    JourneyState,
    ProgressCourse,
    ProgressStats,
    StreakSummary,
    TestResultSummary,
} from '@/types';
import LevelSuggestionCard from '@/components/learning/level/LevelSuggestionCard.vue';
import MyLevelCard from '@/components/learning/level/MyLevelCard.vue';

/*
 * My Progress (PROG-02, PROG-05, TEST-04; spec 0003 Part E). No client
 * mockup covers this page yet: design-system cards, refined later.
 */
type Props = {
    courses: ProgressCourse[];
    stats: ProgressStats;
    tests: { pre: TestResultSummary | null; post: TestResultSummary | null };
    journey: JourneyState;
    streak: StreakSummary;
    coach: CoachSummary;
};

defineProps<Props>();

const { t } = useI18n();

function since(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleDateString(intlLocale(), {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : t('Not started yet');
}
</script>

<template>
    <Head :title="$t('My Progress')" />
    <h1 class="sr-only">{{ $t('My Progress') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('My Progress')"
            :description="
                $t('Training started :date', {
                    date: since(stats.trainingStartedAt),
                })
            "
        />

        <LevelSuggestionCard />
        <MyLevelCard />

        <div class="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <StatCard
                :value="stats.percent"
                unit="%"
                :label="$t('Overall progress')"
                :detail="
                    $t('Last activity :date', {
                        date: since(stats.lastActivityAt),
                    })
                "
                tone="brand"
            >
                <template #icon>
                    <TrendingUp class="size-6" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="stats.lessonsCompleted"
                :label="$t('Lessons completed')"
                :detail="$t('of :total lessons', { total: stats.lessonsTotal })"
                tone="success"
            >
                <template #icon>
                    <BookOpen class="size-6" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="journey.certificateAvailable ? 1 : 0"
                :label="$t('Certificate')"
                :detail="
                    journey.certificateAvailable
                        ? $t('Ready to view')
                        : journey.postTestUnlocked
                          ? $t('Take the Post-test to earn it')
                          : $t('Finish every lesson first')
                "
                tone="warning"
            >
                <template #icon>
                    <Award class="size-6" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                :value="streak.current"
                :label="$t('Day streak')"
                :detail="
                    streak.activeToday
                        ? $t('You practised today · best :best', {
                              best: streak.best,
                          })
                        : $t('Practise today to keep it · best :best', {
                              best: streak.best,
                          })
                "
                tone="ai"
            >
                <template #icon>
                    <Flame class="size-6" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <CoachCard :coach="coach" />

        <div class="grid min-w-0 gap-3 md:grid-cols-2">
            <TestResultCard :title="$t('Pre-test')" :result="tests.pre" />
            <TestResultCard :title="$t('Post-test')" :result="tests.post" />
        </div>

        <div class="grid min-w-0 gap-3 md:grid-cols-2">
            <ProgressCourseCard
                v-for="course in courses"
                :key="course.id"
                :course="course"
            />
        </div>
    </div>
</template>
