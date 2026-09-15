<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LessonsEditorPanel from '@/components/lessons/LessonsEditorPanel.vue';
import LessonsHeaderAccent from '@/components/lessons/LessonsHeaderAccent.vue';
import LessonsSidebarPanel from '@/components/lessons/LessonsSidebarPanel.vue';
import LessonsStructurePanel from '@/components/lessons/LessonsStructurePanel.vue';
import LessonsToolbar from '@/components/lessons/LessonsToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { dashboard, lessonsContent } from '@/routes';
import type {
    LessonEditor,
    LessonBuilderBlock,
    LessonsFilters,
    LessonsImageLibrary,
    LessonsTab,
    LessonsTabKey,
    LessonsTreeCourse,
} from '@/types';

type Props = {
    filters: LessonsFilters;
    tabs: LessonsTab[];
    activeTab: LessonsTabKey;
    courses: LessonsTreeCourse[];
    editor: LessonEditor;
    blocks: LessonBuilderBlock[];
    library: LessonsImageLibrary;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Lessons & Content',
                href: lessonsContent(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Lessons & Content" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Lessons & Content</h1>

        <PageHeader
            title="Lessons & Content"
            description="Create and manage courses, units and lessons with rich multimedia content."
            class="mb-1"
        >
            <template #accent>
                <LessonsHeaderAccent />
            </template>
        </PageHeader>

        <LessonsToolbar
            :filters="filters"
            :tabs="tabs"
            :active-tab="activeTab"
        />

        <div
            class="grid min-w-0 gap-3 lg:grid-cols-[228px_minmax(0,1fr)] xl:grid-cols-[228px_minmax(0,1fr)_318px] xl:items-start"
        >
            <LessonsStructurePanel :courses="courses" />
            <LessonsEditorPanel :editor="editor" />
            <LessonsSidebarPanel
                :blocks="blocks"
                :library="library"
                class="lg:col-span-2 xl:col-span-1"
            />
        </div>
    </