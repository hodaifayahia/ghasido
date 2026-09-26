<script setup lang="ts">
import { CirclePlus, Trash2 } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import LessonsField from '@/components/lessons/LessonsField.vue';
import { settingList, settingString } from '@/components/lessons/lessonsBlocks';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import type { BlockSettings, LessonBlockRow } from '@/types';

/**
 * Situation (Intro) block (spec 0003 B.10): the quote and the three objective
 * rows with their icons. Title, introduction, objectives text and cover
 * come from the lesson row and are edited on the main form.
 */
type Props = {
    block: LessonBlockRow;
    readOnly: boolean;
};

type Objective = { icon: string; text: string };

defineProps<Props>();

const settings = defineModel<BlockSettings>('settings', { required: true });

const quote = computed({
    get: () => settingString(settings.value, 'quote'),
    set: (value: string | number | null) => {
        settings.value = { ...settings.value, quote: value ?? '' };
    },
});

const objectives = computed(() =>
    settingList<Objective>(settings.value, 'objectives'),
);

const icons = [
    { value: 'chat', label: tk('Speech bubble') },
    { value: 'people', label: tk('People') },
    { value: 'check', label: tk('Check mark') },
];

function setObjectives(next: Objective[]): void {
    settings.value = { ...settings.value, objectives: next };
}

function updateObjective(index: number, patch: Partial<Objective>): void {
    setObjectives(
        objectives.value.map((row, i) =>
            i === index ? { ...row, ...patch } : row,
        ),
    );
}

function onIcon(index: number, value: AcceptableValue): void {
    if (typeof value === 'string') {
        updateObjective(index, { icon: value });
    }
}

function addObjective(): void {
    setObjectives([...objectives.value, { icon: 'check', text: '' }]);
}

function removeObjective(index: number): void {
    setObjectives(objectives.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="grid gap-4">
        <LessonsField
            v-model="quote"
            :label="$t('Quote')"
            type="textarea"
            :rows="2"
            :hint="$t('Shown in the quote box under the objectives.')"
        />

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-3">
                <span
                    class="text-brand-900 text-[12px] font-semibold tracking-[0.02em]"
                >
                    {{ $t('Objective rows') }}
                </span>
                <Button
                    v-if="!readOnly"
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="addObjective"
                >
                    <CirclePlus class="size-3.5" aria-hidden="true" />
                    {{ $t('Add row') }}
                </Button>
            </div>

            <div
                v-for="(objective, index) in objectives"
                :key="index"
                class="grid gap-2 sm:grid-cols-[150px_minmax(0,1fr)_auto]"
            >
                <Select
                    :model-value="objective.icon"
                    :disabled="readOnly"
                    @update:model-value="onIcon(index, $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-10 rounded-sm text-[13px] shadow-none"
                        :aria-label="
                            $t('Icon of objective :number', {
                                number: index + 1,
                            })
                        "
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="icon in icons"
                            :key="icon.value"
                            :value="icon.value"
                            class="text-[13px]"
                        >
                            {{ $t(icon.label) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input
                    :value="objective.text"
                    type="text"
                    :readonly="readOnly"
                    :aria-label="$t('Objective :number', { number: index + 1 })"
                    class="border-line text-ink bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 h-10 w-full rounded-sm border px-3 text-[13px] focus-visible:ring-3 focus-visible:outline-none"
                    @input="
                        updateObjective(index, {
                            text: ($event.target as HTMLInputElement).value,
                        })
                    "
                />
                <button
                    v-if="!readOnly"
                    type="button"
                    class="text-ink-faint hover:bg-danger-tint hover:text-danger-text inline-flex size-10 items-center justify-center rounded-md"
                    :aria-label="
                        $t('Remove objective :number', { number: index + 1 })
                    "
                    @click="removeObjective(index)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>
</template>
