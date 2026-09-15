<script setup lang="ts">
import { ChevronDown, ChevronRight, CirclePlus } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import PanelCard from '@/components/common/PanelCard.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { LessonsTreeCourse, LessonsTreeTone } from '@/types';

type Props = {
    courses: LessonsTreeCourse[];
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const toneClass: Record<LessonsTreeTone, string> = {
    brand: 'bg-brand-100 text-brand-700',
    aqua: 'bg-aqua-tint text-aqua',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    gold: 'bg-gold-tint text-gold',
    danger: 'bg-danger-tint text-danger',
};
</script>

<template>
    <PanelCard
        title="Course Structure"
        title-id="course-structure"
        :class="cn('min-w-0 px-3 pt-3 pb-3.5', props.class)"
        body-class="mt-2.5"
    >
        <template #actions>
            <Button
                type="button"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                Add
            </Button>
        </template>

        <div class="space-y-1.5">
            <div
                v-for="course in courses"
                :key="course.id"
                class="rounded-md border border-transparent"
            >
                <button
                    type="button"
                    class="text-brand-900 flex w-full items-center gap-2 rounded-md px-1 py-[3px] text-start text-[12.5px] font-medium"
                >
                    <component
                        :is="course.expanded ? ChevronDown : ChevronRight"
                        class="text-ink-slate size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        :class="
                            cn(
                                'grid size-[18px] shrink-0 place-items-center rounded-md',
                                toneClass[course.tone ?? 'brand'],
                            )
                        "
                    >
                        <span class="size-2 rounded-full bg-current" />
                    </span>
                    <span class="truncate">{{ course.title }}</span>
                </button>

                <div
                    v-if="course.expanded && course.units?.length"
                    class="border-line ms-[13px] mt-1 border-s ps-3"
                >
                    <div
                        v-for="unit in course.units"
                        :key="unit.id"
                        class="pb-1"
                    >
                        <button
                            type="button"
                            class="text-brand-900 flex w-full items-center gap-1.5 rounded-md py-[3px] pe-1 text-start text-[12.5px]"
                        >
                            <component
                                :is="unit.expanded ? ChevronDown : ChevronRight"
                                class="text-ink-slate size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ unit.title }}</span>
                        </button>

                        <div
                            v-if="unit.expanded"
                            class="border-line ms-[9px] mt-1 space-y-1 border-s ps-3"
                        >
                            <button
                                v-for="lesson in unit.lessons ?? []"
                                :key="lesson.id"
                                type="button"
                                :class="
                                    cn(
                                        'flex min-h-8 w-full items-center rounded-md px-3 text-start text-[12.5px] font-medium',
                                        lesson.active
                                            ? 'bg-brand-100/80 text-brand-800 shadow-card'
                                            : 'text-ink-muted hover:bg-brand-50/65',
                                    )
                                "
                            >
                                {{ lesson.title }}
                            </button>

                            <button
                                v-if="unit.addLessonLabel"
                                type="button"
                                class="text-brand-700 hover:bg-brand-50/70 flex min-h-8 w-full items-center rounded-md px-3 text-start text-[12.5px] font-semibold"
                            >
                                {{ unit.addLessonLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PanelCard>
</template>
