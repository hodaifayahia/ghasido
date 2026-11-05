<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Sprout } from '@lucide/vue';
import { computed, ref } from 'vue';
import EssentialsLessonDialog from '@/components/essentials-studio/EssentialsLessonDialog.vue';
import EssentialsStudioStats from '@/components/essentials-studio/EssentialsStudioStats.vue';
import EssentialsTopicDialog from '@/components/essentials-studio/EssentialsTopicDialog.vue';
import EssentialsTopicPanel from '@/components/essentials-studio/EssentialsTopicPanel.vue';
import EssentialsTopicRail from '@/components/essentials-studio/EssentialsTopicRail.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useCan } from '@/composables/useCan';
import { tk } from '@/lib/i18n';
import { dashboard, essentialsStudio } from '@/routes';
import { reorder as reorderTopics } from '@/routes/essentials-studio/topics';
import type {
    EssentialsStudioIconOption,
    EssentialsStudioLesson,
    EssentialsStudioOption,
    EssentialsStudioStats as Stats,
    EssentialsStudioTemplate,
    EssentialsStudioTopic,
} from '@/types';

/*
 * The English Essentials studio (client request 2026-11-05): beginner
 * topics are built here, apart from the department lesson builder, as
 * topic cards and flat lesson lists started from beginner templates. The
 * steps themselves are filled in the usual lesson editor (LESSON-02).
 * No mockup covers this screen; it reuses the approved chrome and the
 * design system's cards, chips and buttons.
 */
type Props = {
    stats: Stats;
    topics: EssentialsStudioTopic[];
    openTopic: { id: number; lessons: EssentialsStudioLesson[] } | null;
    departments: EssentialsStudioOption[];
    icons: EssentialsStudioIconOption[];
    templates: EssentialsStudioTemplate[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('English Essentials'), href: essentialsStudio() },
        ],
    },
});

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

const open = computed(
    () =>
        props.topics.find((topic) => topic.id === props.openTopic?.id) ?? null,
);

const topicDialog = ref(false);
const editing = ref<EssentialsStudioTopic | null>(null);
const lessonDialog = ref(false);
const reordering = ref(false);

function createTopic(): void {
    editing.value = null;
    topicDialog.value = true;
}

function editTopic(): void {
    editing.value = open.value;
    topicDialog.value = true;
}

function moveTopic(index: number, direction: -1 | 1): void {
    const order = props.topics.map((topic) => topic.id);
    const target = index + direction;

    if (target < 0 || target >= order.length) {
        return;
    }

    [order[index], order[target]] = [order[target], order[index]];
    router.put(
        reorderTopics.url(),
        { order },
        {
            preserveScroll: true,
            onStart: () => {
                reordering.value = true;
            },
            onFinish: () => {
                reordering.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('English Essentials')" />

    <div class="flex min-w-0 flex-col gap-4 px-4 pt-5 pb-8 md:px-6">
        <PageHeader
            :title="$t('English Essentials')"
            :description="
                $t(
                    'Beginner basics any employee can open when they need them: letters, numbers, time, greetings and more.',
                )
            "
        >
            <template #leading>
                <span
                    class="bg-success-tint text-success grid size-11 shrink-0 place-items-center rounded-xl"
                    aria-hidden="true"
                >
                    <Sprout class="size-6" />
                </span>
            </template>
        </PageHeader>

        <EssentialsStudioStats :stats="stats" />

        <div
            class="grid min-w-0 gap-4 min-[1100px]:grid-cols-[300px_minmax(0,1fr)] min-[1100px]:items-start"
        >
            <EssentialsTopicRail
                :topics="topics"
                :open-id="open?.id ?? null"
                :manage="manage"
                :busy="reordering"
                class="min-[1100px]:sticky min-[1100px]:top-[calc(var(--spacing-topbar)+20px)]"
                @create="createTopic"
                @move="moveTopic"
            />
            <EssentialsTopicPanel
                v-if="open && openTopic"
                :topic="open"
                :lessons="openTopic.lessons"
                :manage="manage"
                @edit="editTopic"
                @add-lesson="lessonDialog = true"
            />
        </div>

        <EssentialsTopicDialog
            v-model:open="topicDialog"
            :topic="editing"
            :icons="icons"
            :departments="departments"
        />
        <EssentialsLessonDialog
            v-model:open="lessonDialog"
            :topic="open"
            :templates="templates"
        />
    </div>
</template>
