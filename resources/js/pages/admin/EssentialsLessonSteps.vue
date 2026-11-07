<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Eye, PencilRuler, WandSparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import LessonsAiFillDialog from '@/components/lessons/LessonsAiFillDialog.vue';
import LessonsBlockList from '@/components/lessons/LessonsBlockList.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { t, tk } from '@/lib/i18n';
import { dashboard, essentialsStudio } from '@/routes';
import type {
    LessonBlockRow,
    LessonBlockTypeOption,
    LessonEditor,
    LessonScenarioOption,
    LessonsImageLibrary,
} from '@/types';

/*
 * "Fill in steps" for one English Essentials lesson (client request
 * 2026-11-05): its own page, opened from the studio, with a way back to
 * the topic it came from. The steps are listed in learner order and each
 * one opens the builder's own step editor (BLD-03, LESSON-02), so a step
 * saves exactly as it does in the full lesson editor.
 */
type Props = {
    topic: { id: number; title: string };
    backUrl: string;
    fullEditorUrl: string;
    previewUrl: string;
    editor: LessonEditor;
    lessonBlocks: LessonBlockRow[];
    blockTypes: LessonBlockTypeOption[];
    scenarios: LessonScenarioOption[];
    library: LessonsImageLibrary;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: tk('Dashboard'), href: dashboard() },
            { title: tk('English Essentials'), href: essentialsStudio() },
            { title: tk('Fill in steps'), href: essentialsStudio() },
        ],
    },
});

const stepCount = computed(() => props.lessonBlocks.length);

// Write this lesson from a brief (client request 2026-11-07).
const fillOpen = ref(false);

const secondaryAction =
    'border-line bg-surface text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/20 inline-flex h-11 items-center gap-1.5 rounded-md border px-3 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:h-10';
</script>

<template>
    <Head :title="t('Fill in steps')" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="t('Fill in steps')"
            :description="
                t(':lesson — :topic', {
                    lesson: editor.title,
                    topic: topic.title,
                })
            "
        />

        <div class="flex flex-wrap items-center gap-2">
            <Link
                :href="backUrl"
                class="text-brand-700 hover:bg-brand-50 focus-visible:ring-brand-600/20 inline-flex h-11 items-center gap-1.5 rounded-md px-2 text-[13px] font-semibold focus-visible:ring-3 focus-visible:outline-none md:h-10"
                data-test="essentials-steps-back"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('Back to :topic', { topic: topic.title }) }}
            </Link>

            <div class="ms-auto flex flex-wrap items-center gap-2">
                <button
                    v-if="editor.id !== null"
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn focus-visible:ring-brand-600/30 inline-flex h-11 items-center gap-1.5 rounded-md px-3 text-[13px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] md:h-10"
                    data-test="essentials-fill-with-ai"
                    @click="fillOpen = true"
                >
                    <WandSparkles class="size-4" aria-hidden="true" />
                    {{ t('Fill this lesson with AI') }}
                </button>
                <a
                    :href="previewUrl"
                    target="_blank"
                    rel="noopener"
                    :class="secondaryAction"
                >
                    <Eye class="size-4" aria-hidden="true" />
                    {{ t('Preview as an employee') }}
                </a>
                <Link :href="fullEditorUrl" :class="secondaryAction">
                    <PencilRuler class="size-4" aria-hidden="true" />
                    {{ t('Open full lesson editor') }}
                </Link>
            </div>
        </div>

        <PanelCard
            :title="
                t(':count steps', {
                    count: String(stepCount),
                })
            "
            title-id="essentials-steps-title"
        >
            <p class="text-ink-slate mb-3 text-[13px]">
                {{
                    t(
                        'Open each step in order and fill it in. Steps are saved one by one.',
                    )
                }}
            </p>
            <LessonsBlockList
                v-if="editor.id !== null"
                :lesson-id="editor.id"
                :blocks="lessonBlocks"
                :block-types="blockTypes"
                :scenarios="scenarios"
                :library="library"
            />
        </PanelCard>
        <LessonsAiFillDialog
            v-if="editor.id !== null"
            v-model:open="fillOpen"
            :lesson-id="editor.id"
            :lesson-title="editor.title"
            :context="topic.title"
            :has-steps="stepCount > 1"
        />
    </div>
</template>
