<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import CourseOutlineCard from '@/components/learning/CourseOutlineCard.vue';
import { index as essentialsIndex } from '@/routes/learn/essentials';
import type { CourseOutline, EssentialsIcon } from '@/types';

/*
 * One English Essentials topic (client request 2026-10-18): its units and
 * lessons, the same rows as My Lessons, with the way back to the topic
 * list.
 */
type Props = {
    course: CourseOutline;
    icon: EssentialsIcon;
};

defineProps<Props>();
</script>

<template>
    <Head :title="course.title" />
    <h1 class="sr-only">{{ course.title }}</h1>

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <Link
            :href="essentialsIndex()"
            class="text-brand-600 focus-visible:ring-brand-600/30 inline-flex min-h-11 items-center gap-2 self-start rounded-md text-sm font-semibold focus-visible:ring-3 focus-visible:outline-none"
        >
            <ArrowLeft class="size-4 rtl:rotate-180" aria-hidden="true" />
            {{ $t('English Essentials') }}
        </Link>

        <CourseOutlineCard :course="course" :icon="icon" />
    </div>
</template>
