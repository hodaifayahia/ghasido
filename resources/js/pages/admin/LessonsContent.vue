<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CircleHelp } from '@lucide/vue';
import { ref, watch } from 'vue';
import LessonCreateStartDialog from '@/components/lessons/LessonCreateStartDialog.vue';
import LessonCreationTutorial from '@/components/lessons/LessonCreationTutorial.vue';
import LessonsAiGenerateDialog from '@/components/lessons/LessonsAiGenerateDialog.vue';
import LessonGuidedTour from '@/components/lessons/LessonGuidedTour.vue';
import LessonsDirectoryTable from '@/components/lessons/LessonsDirectoryTable.vue';
import type { LessonDirectoryFilterValues } from '@/components/lessons/LessonsDirectoryTable.vue';
import LessonsDirectoryStats from '@/components/lessons/LessonsDirectoryStats.vue';
import LessonsEditorAiActions from '@/components/lessons/LessonsEditorAiActions.vue';
import LessonsEditorPanel from '@/components/lessons/LessonsEditorPanel.vue';
import LessonsHeaderAccent from '@/components/lessons/LessonsHeaderAccent.vue';
import LessonsSidebarPanel from '@/components/lessons/LessonsSidebarPanel.vue';
import LessonsStructurePanel from '@/components/lessons/LessonsStructurePanel.vue';
import LessonsToolbar from '@/components/lessons/LessonsToolbar.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { dashboard, lessonsContent } from '@/routes';
import { edit as editLesson } from '@/routes/lessons';
import type {
    LessonBlockRow,
    LessonBlockTypeOption,
    LessonDirectoryFilters,
    LessonDirectoryMetric,
    LessonDirectoryPagination,
    LessonBuilderBlock,
    LessonDirectoryRow,
    LessonEditor,
    LessonLibraryImage,
    LessonScenarioOption,
    LessonsFilters,
    LessonsImageLibrary,
    LessonsTab,
    LessonsTabKey,
    LessonsTreeCourse,
    TtsSettings,
} from '@/types';

type Props = {
    filters: LessonsFilters;
    tabs: LessonsTab[];
    activeTab: LessonsTabKey;
    builderOpen: boolean;
    courses: LessonsTreeCourse[];
    editor: LessonEditor;
    blocks: LessonBuilderBlock[];
    library: LessonsImageLibrary;
    lessonBlocks: LessonBlockRow[];
    scenarios: LessonScenarioOption[];
    blockTypes: LessonBlockTypeOption[];
    lessonDirectory: LessonDirectoryRow[];
    directoryStats: LessonDirectoryMetric[];
    directoryFilters: LessonDirectoryFilters;
    directoryPagination: LessonDirectoryPagination;
    tts: TtsSettings;
};

const props = defineProps<Props>();

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

// The builder is already read-only without lessons.manage; this also hides the
// "how to create a lesson" guide from non-admins (managers, per client).
const { can } = useCan();
const canManage = can('lessons.manage');

const editorPanel = ref<InstanceType<typeof LessonsEditorPanel> | null>(null);
const builderOpen = ref(props.builderOpen);
const createLessonOpen = ref(false);
const tutorialOpen = ref(false);
const guidedTourOpen = ref(false);
const generateOpen = ref(false);
const directoryLoading = ref(false);

watch(
    () => props.builderOpen,
    (value) => {
        builderOpen.value = value;
    },
);

function openCreateLesson(): void {
    createLessonOpen.value = true;
}

type DirectoryQuery = {
    directorySearch?: string;
    directoryHotel?: string;
    directoryDepartment?: string;
    directoryCourse?: string;
    directoryStatus?: string;
    directoryPage?: number;
    directoryPerPage?: number;
};

function directoryQuery(
    values: LessonDirectoryFilterValues,
    page?: number,
    perPage?: number,
): DirectoryQuery {
    const query: DirectoryQuery = {};

    if (values.search !== '') {
        query.directorySearch = values.search;
    }
    if (values.hotel !== 'all-hotels') {
        query.directoryHotel = values.hotel;
    }
    if (values.department !== 'all-departments') {
        query.directoryDepartment = values.department;
    }
    if (values.course !== 'all-courses') {
        query.directoryCourse = values.course;
    }
    if (values.status !== 'all-statuses') {
        query.directoryStatus = values.status;
    }
    if (page !== undefined && page > 1) {
        query.directoryPage = page;
    }
    if (perPage !== undefined && perPage !== 10) {
        query.directoryPerPage = perPage;
    }

    return query;
}

function currentDirectoryFilters(): LessonDirectoryFilterValues {
    return {
        search: props.directoryFilters.search,
        hotel: props.directoryFilters.hotel,
        department: props.directoryFilters.department,
        course: props.directoryFilters.course,
        status: props.directoryFilters.status,
    };
}

function visitDirectory(query: DirectoryQuery): void {
    router.get(
        lessonsContent.url({ query }),
        {},
        {
            only: [
                'lessonDirectory',
                'directoryStats',
                'directoryFilters',
                'directoryPagination',
            ],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                directoryLoading.value = true;
            },
            onFinish: () => {
                directoryLoading.value = false;
            },
        },
    );
}

function applyDirectoryFilters(values: LessonDirectoryFilterValues): void {
    visitDirectory(
        directoryQuery(values, 1, props.directoryPagination.perPage),
    );
}

function goToDirectoryPage(page: number): void {
    visitDirectory(
        directoryQuery(
            currentDirectoryFilters(),
            page,
            props.directoryPagination.perPage,
        ),
    );
}

function changeDirectoryPageSize(size: number): void {
    visitDirectory(directoryQuery(currentDirectoryFilters(), 1, size));
}

function startGuidedTour(): void {
    if (!builderOpen.value && props.editor.id !== null) {
        router.visit(editLesson(props.editor.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                guidedTourOpen.value = true;
            },
        });

        return;
    }

    guidedTourOpen.value = true;
}

/** An image picked in the library panel becomes the lesson cover (MED-02). */
function onPick(image: LessonLibraryImage): void {
    if (props.editor.id !== null) {
        editorPanel.value?.setCover(image);
    }
}
</script>

<template>
    <Head title="Lessons & Content" />

    <div class="flex min-w-0 flex-col gap-2.5 px-4 pt-5 pb-5 md:px-6">
        <h1 class="sr-only">Lessons & Content</h1>

        <PageHeader
            :title="builderOpen ? 'Edit Lesson' : 'Lessons & Content'"
            :description="
                builderOpen
                    ? 'Edit the lesson content and employee steps.'
                    : 'Create and manage courses, units and lessons with rich multimedia content.'
            "
            class="mb-1"
        >
            <template #accent>
                <LessonsHeaderAccent />
            </template>
        </PageHeader>

        <template v-if="!builderOpen">
            <LessonsDirectoryStats :stats="directoryStats" />
            <LessonsDirectoryTable
                :lessons="lessonDirectory"
                :filters="directoryFilters"
                :pagination="directoryPagination"
                :loading="directoryLoading"
                @create="openCreateLesson"
                @generate="generateOpen = true"
                @tutorial="tutorialOpen = true"
                @filter="applyDirectoryFilters"
                @page="goToDirectoryPage"
                @page-size="changeDirectoryPageSize"
            />

            <LessonCreateStartDialog
                v-model:open="createLessonOpen"
                :departments="filters.departments"
                :default-department="filters.department"
            />
        </template>

        <template v-if="builderOpen">
            <!-- Left-aligned: the header photo accent covers the far end. -->
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="lessonsContent.url()"
                    class="text-brand-700 hover:text-brand-800 focus-visible:ring-brand-600/15 inline-flex min-h-11 w-fit items-center gap-1.5 rounded-sm text-[12px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:min-h-0"
                    data-test="back-to-lesson-directory"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    Back to Lesson Directory
                </Link>
                <LessonsEditorAiActions
                    :filters="filters"
                    :editor="editor"
                    class="ms-2"
                    @generate="generateOpen = true"
                />
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
                    data-test="lesson-creation-tutorial-editor-button"
                    @click="tutorialOpen = true"
                >
                    <CircleHelp class="size-3.5" aria-hidden="true" />
                    Visual lesson guide
                </Button>
            </div>

            <LessonsToolbar
                :filters="filters"
                :tabs="tabs"
                :active-tab="activeTab"
                :editor="editor"
            />

            <div
                class="grid min-w-0 gap-3 lg:grid-cols-[228px_minmax(0,1fr)] xl:grid-cols-[228px_minmax(0,1fr)_318px] xl:items-start"
            >
                <LessonsStructurePanel :courses="courses" :filters="filters" />
                <LessonsEditorPanel
                    ref="editorPanel"
                    :editor="editor"
                    :active-tab="activeTab"
                    :tts="tts"
                    :lesson-directory="lessonDirectory"
                    :lesson-blocks="lessonBlocks"
                    :block-types="blockTypes"
                    :scenarios="scenarios"
                    :library="library"
                />
                <LessonsSidebarPanel
                    :blocks="blocks"
                    :library="library"
                    :lesson-id="editor.id"
                    class="lg:col-span-2 xl:col-span-1"
                    @pick="onPick"
                />
            </div>
        </template>

        <LessonCreationTutorial
            v-if="canManage"
            v-model:open="tutorialOpen"
            :has-lesson="props.editor.id !== null"
            @start-tour="startGuidedTour"
        />

        <LessonGuidedTour v-if="canManage" v-model:open="guidedTourOpen" />

        <LessonsAiGenerateDialog
            v-model:open="generateOpen"
            :departments="filters.departments"
            :hotels="filters.hotels"
            :default-department="filters.department"
            :default-hotel="filters.hotel || 'shared'"
        />
    </div>
</template>
