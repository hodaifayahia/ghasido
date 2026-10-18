<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import CourseOutlineCard from '@/components/learning/CourseOutlineCard.vue';
import EssentialsCard from '@/components/learning/essentials/EssentialsCard.vue';
import LearnerEmptyState from '@/components/learning/LearnerEmptyState.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { home } from '@/routes/learn';
import type { CourseOutline, EssentialsSummary, JourneyState } from '@/types';

/*
 * My Lessons (JOURNEY-01, JOURNEY-03; spec 0003 Part E). No client mockup
 * covers this page yet: it is the design system's cards, refined later by
 * the learn-support lane. English Essentials (client request 2026-10-18)
 * sits under the department training, in the same column, when anything
 * is published for the learner.
 */
type Props = {
    courses: CourseOutline[];
    essentials: EssentialsSummary | null;
    journey: JourneyState;
};

defineProps<Props>();
</script>

<template>
    <Head :title="$t('My Lessons')" />
    <h1 class="sr-only">{{ $t('My Lessons') }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('My Lessons')"
            :description="
                journey.lessonsUnlocked
                    ? $t(':completed of :total lessons completed', {
                          completed: journey.lessonsCompleted,
                          total: journey.lessonsTotal,
                      })
                    : $t(
                          'Your lessons unlock as soon as you have taken the Pre-test.',
                      )
            "
        />

        <LearnerEmptyState
            v-if="courses.length === 0"
            :icon="BookOpen"
            :text="
                $t(
                    'No lessons have been published for your department yet. Please check back soon.',
                )
            "
            :action="{ label: $t('Back to Home'), href: home() }"
        />

        <CourseOutlineCard
            v-for="course in courses"
            :key="course.id"
            :course="course"
        />

        <EssentialsCard v-if="essentials" :essentials="essentials" />
    </div>
</template>
