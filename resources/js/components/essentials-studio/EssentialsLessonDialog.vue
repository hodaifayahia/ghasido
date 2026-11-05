<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Clock } from '@lucide/vue';
import { ref, watch } from 'vue';
import { stepTone } from '@/components/essentials-studio/essentialsStudioTones';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EssentialsStudioTemplate, EssentialsStudioTopic } from '@/types';

/*
 * Add a lesson to an English Essentials topic from a beginner template
 * (client request 2026-11-05). Each card shows the steps the lesson will
 * start with; the steps are filled in the usual lesson editor, which
 * "Add and fill in" opens straight away.
 */
type Props = {
    topic: EssentialsStudioTopic | null;
    templates: EssentialsStudioTemplate[];
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { required: true });

const title = ref('');
const template = ref('word_list');
const saving = ref<'draft' | 'edit' | null>(null);
const error = ref('');

watch(open, (value) => {
    if (value) {
        title.value = '';
        template.value = props.templates[0]?.value ?? 'word_list';
        error.value = '';
    }
});

function save(openEditor: boolean): void {
    if (
        props.topic === null ||
        saving.value !== null ||
        title.value.trim() === ''
    ) {
        return;
    }

    router.post(
        props.topic.addLessonUrl,
        {
            title: title.value.trim(),
            template: template.value,
            open_editor: openEditor,
        },
        {
            preserveScroll: true,
            onStart: () => {
                saving.value = openEditor ? 'edit' : 'draft';
            },
            onFinish: () => {
                saving.value = null;
            },
            onSuccess: () => {
                open.value = false;
            },
            onError: (bag: Record<string, string>) => {
                error.value = bag.title ?? bag.template ?? '';
            },
        },
    );
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="$t('Add a lesson')"
        :description="
            topic
                ? $t(
                      'A short beginner lesson in “:topic”. Pick the shape that fits, then fill in the steps.',
                      { topic: topic.title },
                  )
                : ''
        "
        size="lg"
    >
        <form
            class="mt-4 grid gap-5"
            data-test="essentials-lesson-form"
            @submit.prevent="save(true)"
        >
            <div class="grid gap-1.5">
                <label
                    for="essentials-lesson-title"
                    class="text-brand-900 text-[12.5px] font-semibold"
                >
                    {{ $t('Lesson title') }}
                    <span class="text-danger-text">*</span>
                </label>
                <input
                    id="essentials-lesson-title"
                    v-model="title"
                    maxlength="100"
                    required
                    :placeholder="$t('e.g. Spelling a guest’s name')"
                    class="border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-11 w-full rounded-sm border px-3 text-[13.5px] focus-visible:ring-3 focus-visible:outline-none"
                    data-test="essentials-lesson-title"
                />
                <p v-if="error" class="text-danger-text text-xs">{{ error }}</p>
            </div>

            <fieldset class="grid gap-2">
                <legend class="text-brand-900 mb-2 text-[12.5px] font-semibold">
                    {{ $t('Lesson shape') }}
                </legend>
                <div class="grid gap-2.5 sm:grid-cols-2" role="radiogroup">
                    <button
                        v-for="option in templates"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="template === option.value"
                        :class="
                            cn(
                                'focus-visible:ring-brand-600/20 relative grid gap-2 rounded-lg border p-3.5 text-start transition-[border-color,box-shadow] duration-150 focus-visible:ring-3 focus-visible:outline-none motion-reduce:transition-none',
                                template === option.value
                                    ? 'border-brand-600 bg-brand-50/50 shadow-card'
                                    : 'border-line hover:border-brand-300',
                            )
                        "
                        :data-test="`essentials-template-${option.value}`"
                        @click="template = option.value"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span
                                class="font-heading text-brand-900 text-[14px] font-semibold"
                            >
                                {{ option.label }}
                            </span>
                            <span
                                :class="
                                    cn(
                                        'grid size-5 shrink-0 place-items-center rounded-full border',
                                        template === option.value
                                            ? 'border-brand-600 bg-brand-600 text-white'
                                            : 'border-line-strong',
                                    )
                                "
                                aria-hidden="true"
                            >
                                <Check
                                    v-if="template === option.value"
                                    class="size-3"
                                />
                            </span>
                        </span>
                        <span class="text-ink-slate text-[12px] leading-5">
                            {{ option.description }}
                        </span>
                        <span class="flex flex-wrap items-center gap-1">
                            <span
                                v-for="(step, index) in option.steps"
                                :key="index"
                                :class="
                                    cn(
                                        'rounded-pill px-2 py-0.5 text-[10.5px] font-semibold',
                                        stepTone(step.type),
                                    )
                                "
                            >
                                {{ index + 1 }}. {{ step.label }}
                            </span>
                            <span
                                class="text-ink-faint ms-auto inline-flex items-center gap-1 text-[11px]"
                            >
                                <Clock class="size-3" aria-hidden="true" />
                                {{
                                    $t(':minutes min', {
                                        minutes: option.minutes,
                                    })
                                }}
                            </span>
                        </span>
                    </button>
                </div>
            </fieldset>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 h-11 rounded-md px-4 md:h-10"
                    :disabled="saving !== null || title.trim() === ''"
                    data-test="essentials-lesson-save-draft"
                    @click="save(false)"
                >
                    {{ $t('Add as draft') }}
                </Button>
                <Button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 shadow-btn h-11 rounded-md px-5 font-semibold text-white active:scale-[.97] md:h-10"
                    :disabled="saving !== null || title.trim() === ''"
                    data-test="essentials-lesson-save-edit"
                >
                    {{ $t('Add and fill in') }}
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
