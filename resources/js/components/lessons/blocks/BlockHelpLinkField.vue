<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import LessonsField from '@/components/lessons/LessonsField.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LessonsEssentialsPanel } from '@/types';

/*
 * "Help from English Essentials" on a department lesson step (client
 * request 2026-10-18): the Essentials lesson the learner may open from
 * this step ("Want to learn spelling first? Tap here") and the line they
 * read. The learner comes back to this same step afterwards.
 */
type Props = {
    lessons: LessonsEssentialsPanel['lessons'];
    readOnly: boolean;
    error?: string;
};

defineProps<Props>();

const lessonId = defineModel<number | null>('lessonId', { required: true });
const prompt = defineModel<string>('prompt', { required: true });

const NONE = 'none';

function onLesson(value: AcceptableValue): void {
    lessonId.value =
        typeof value === 'string' && value !== NONE ? Number(value) : null;
}
</script>

<template>
    <section
        class="border-line bg-brand-50/40 grid gap-3 rounded-md border px-4 py-3"
        data-test="block-help-link"
    >
        <div>
            <h3 class="font-heading text-ink text-[13.5px] font-semibold">
                {{ $t('Help from English Essentials (optional)') }}
            </h3>
            <p class="text-ink-faint text-[11.5px]">
                {{
                    $t(
                        'Learners who need it open this Essentials lesson from the step, then come back to the same step.',
                    )
                }}
            </p>
        </div>

        <Select
            :model-value="lessonId === null ? NONE : String(lessonId)"
            :disabled="readOnly"
            @update:model-value="onLesson"
        >
            <SelectTrigger
                class="border-line bg-surface h-10 rounded-sm text-[13px]"
                :aria-label="$t('English Essentials lesson')"
                data-test="block-help-lesson"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent class="border-line shadow-pop">
                <SelectItem :value="NONE" class="text-[13px]">
                    {{ $t('No link') }}
                </SelectItem>
                <SelectItem
                    v-for="option in lessons"
                    :key="option.value"
                    :value="option.value"
                    class="text-[13px]"
                >
                    {{ option.label
                    }}{{ option.published ? '' : ` (${$t('Draft')})` }}
                </SelectItem>
            </SelectContent>
        </Select>
        <p v-if="lessons.length === 0" class="text-ink-faint text-[12px]">
            {{
                $t(
                    'No English Essentials lessons yet. Create them under English Essentials in the department list.',
                )
            }}
        </p>

        <LessonsField
            v-if="lessonId !== null"
            :model-value="prompt"
            meaning
            :label="$t('What the learner reads')"
            :placeholder="
                $t('Need help with this? Learn it in English Essentials.')
            "
            :error="error"
            @update:model-value="prompt = String($event ?? '')"
        />
    </section>
</template>
