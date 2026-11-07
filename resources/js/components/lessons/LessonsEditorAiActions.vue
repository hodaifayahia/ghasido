<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages, Sparkles, Users, WandSparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import TransText from '@/components/common/TransText.vue';
import LessonsAiFillDialog from '@/components/lessons/LessonsAiFillDialog.vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { translations } from '@/routes';
import type { LessonEditor, LessonsFilters } from '@/types';

/*
 * Who will see the open lesson, and "Generate with AI" (GEN-01, ORG-04,
 * CMS-04, JOURNEY-03; spec 0004). They sit in the "Back to Lesson Directory"
 * row, not in the toolbar, so the approved tab row of the Lessons & Content
 * mockup (tabs + Save Draft + Publish Lesson) keeps its layout.
 */
type Props = {
    filters: LessonsFilters;
    editor: LessonEditor;
    /** The open lesson's steps, so "Fill with AI" can warn it replaces them. */
    stepCount?: number;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { stepCount: 0 });

// "Fill this lesson with AI" (client request 2026-11-07).
const fillOpen = ref(false);

const fillContext = computed((): string => {
    const unit = props.filters.units.find(
        (option) => option.value === props.filters.unit,
    );
    const course = props.filters.courses.find(
        (option) => option.value === props.filters.course,
    );

    return [unit?.label, course?.label].filter(Boolean).join(' · ');
});

const emit = defineEmits<{
    generate: [];
}>();

const { can } = useCan();
const manage = computed(() => can('lessons.manage'));

/**
 * Who will see this lesson once published: its course's department, and
 * every hotel (shared) or one hotel (ORG-04, CMS-04, JOURNEY-03).
 */
const visibleTo = computed((): string | null => {
    if (props.editor.id === null || props.editor.departmentLabel === null) {
        return null;
    }

    const hotels =
        props.filters.hotel === 'shared'
            ? t('All hotels')
            : (props.editor.hotelLabel ?? t('One hotel'));

    return `${props.editor.departmentLabel} — ${hotels}`;
});
</script>

<template>
    <div
        :class="
            cn(
                'flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2',
                props.class,
            )
        "
    >
        <p
            v-if="visibleTo !== null"
            class="text-ink-slate flex min-w-0 items-center gap-1.5 text-[11.5px]"
            data-test="lesson-visible-to"
        >
            <Users class="size-3.5 shrink-0" aria-hidden="true" />
            <span class="min-w-0">
                <TransText text="Visible to: :who">
                    <template #who>
                        <span class="text-ink font-semibold">{{
                            visibleTo
                        }}</span>
                    </template>
                </TransText>
                <span v-if="editor.status !== 'published'">
                    {{ $t('— once published') }}</span
                >
            </span>
        </p>
        <Button
            v-if="manage"
            type="button"
            variant="outline"
            data-test="generate-with-ai-button"
            class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
            @click="emit('generate')"
        >
            <Sparkles class="size-3.5" aria-hidden="true" />
            {{ $t('Generate with AI') }}
        </Button>
        <!-- Write THIS lesson from a brief (client request 2026-11-07). -->
        <Button
            v-if="manage && editor.id !== null"
            type="button"
            data-test="fill-lesson-with-ai-button"
            class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 gap-1.5 rounded-md px-3 text-[11.5px] font-semibold text-white active:scale-[.97] md:h-8"
            @click="fillOpen = true"
        >
            <WandSparkles class="size-3.5" aria-hidden="true" />
            {{ $t('Fill this lesson with AI') }}
        </Button>
        <LessonsAiFillDialog
            v-if="manage && editor.id !== null"
            v-model:open="fillOpen"
            :lesson-id="editor.id"
            :lesson-title="editor.title"
            :context="fillContext"
            :has-steps="stepCount > 1"
        />
        <!-- This lesson's Show Meaning translations (user request 2026-09-26). -->
        <Button
            v-if="manage && editor.id !== null"
            as-child
            variant="outline"
            class="border-line text-brand-700 hover:bg-brand-50 h-11 gap-1.5 rounded-md px-2.5 text-[11.5px] font-semibold shadow-none md:h-8"
        >
            <Link
                :href="
                    translations({
                        query: { content: `lesson:${editor.id}` },
                    })
                "
                data-test="lesson-translations-link"
            >
                <Languages class="size-3.5" aria-hidden="true" />
                {{ $t('Translations') }}
            </Link>
        </Button>
    </div>
</template>
