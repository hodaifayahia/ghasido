<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages, Sparkles, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
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
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

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
            ? 'All hotels'
            : (props.editor.hotelLabel ?? 'One hotel');

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
                Visible to:
                <span class="text-ink font-semibold">{{ visibleTo }}</span>
                <span v-if="editor.status !== 'published'">
                    — once published</span
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
            Generate with AI
        </Button>
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
                Translations
            </Link>
        </Button>
    </div>
</template>
