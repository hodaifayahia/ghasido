<script setup lang="ts">
import { CircleQuestionMark, Clock, Eye, Send, Sparkles } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    AiScenarioFocusArea,
    AiScenarioPreview,
    AiScenarioSavePayload,
    AiScenarioSettings,
} from '@/types';

type Props = {
    preview: AiScenarioPreview;
    settings: AiScenarioSettings;
    /** The sample conversation card; the editor's Test tab replaces it. */
    showExample?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { showExample: true });

const emit = defineEmits<{
    preview: [];
    save: [settings: AiScenarioSavePayload['settings']];
    update: [settings: AiScenarioSavePayload['settings']];
}>();

const attempts = ref(props.settings.attempts);
const feedbackStyle = ref(props.settings.feedbackStyle);
const focusAreas = ref<AiScenarioFocusArea[]>(
    props.settings.focusAreas.map((area) => ({ ...area })),
);
const allowHints = ref(props.settings.allowHints);
const showSuggestions = ref(props.settings.showSuggestions);
const tags = ref([...props.settings.tags]);
const previewMessage = ref('');
const addingTag = ref(false);
const newTag = ref('');

watch(
    () => props.settings,
    (settings) => {
        attempts.value = settings.attempts;
        feedbackStyle.value = settings.feedbackStyle;
        focusAreas.value = settings.focusAreas.map((area) => ({ ...area }));
        allowHints.value = settings.allowHints;
        showSuggestions.value = settings.showSuggestions;
        tags.value = [...settings.tags];
    },
    { deep: true },
);

function onSelect(
    target: 'attempts' | 'feedbackStyle',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'attempts') {
        attempts.value = value;
        return;
    }

    feedbackStyle.value = value;
}

function toggleFocusArea(
    index: number,
    value: boolean | 'indeterminate',
): void {
    if (value === 'indeterminate') return;
    focusAreas.value[index].checked = value;
}

function addTag(): void {
    const tag = newTag.value.trim();
    if (tag === '') return;

    if (!tags.value.includes(tag)) {
        tags.value.push(tag);
    }

    newTag.value = '';
    addingTag.value = false;
}

function removeTag(tag: string): void {
    tags.value = tags.value.filter((item) => item !== tag);
}

function settingsPayload(): AiScenarioSavePayload['settings'] {
    return {
        attempts_allowed: Number(attempts.value),
        feedback_style: feedbackStyle.value,
        focus_areas: focusAreas.value.map((area) => ({ ...area })),
        allow_hints: allowHints.value,
        show_suggestions: showSuggestions.value,
        tags: [...tags.value],
    };
}
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <section
            v-if="showExample"
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    {{ $t('Conversation Preview (Example)') }}
                </h2>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="emit('preview')"
                >
                    <Clock class="size-3.5" aria-hidden="true" />
                    {{ $t('Test Scenario') }}
                </Button>
            </div>

            <div class="mt-3 grid gap-3">
                <div
                    v-for="message in preview.messages"
                    :key="message.id"
                    :class="
                        cn(
                            'flex items-end gap-2.5',
                            message.actor === 'employee' && 'justify-end',
                        )
                    "
                >
                    <span
                        v-if="message.actor === 'guest'"
                        class="bg-ai/12 text-ai rounded-pill grid size-9 shrink-0 place-items-center"
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                    </span>

                    <div
                        :class="
                            cn(
                                'max-w-[236px] rounded-2xl px-3 py-2 text-[12.5px] leading-[1.45]',
                                message.actor === 'guest'
                                    ? 'bg-tint-header text-ink'
                                    : 'bg-brand-100/70 text-brand-900',
                            )
                        "
                    >
                        {{ message.text }}
                    </div>

                    <LessonsMockupCrop
                        v-if="
                            message.actor === 'employee' && message.avatarCrop
                        "
                        :crop="message.avatarCrop"
                        src="/decor/ai-scenarios-mockup.jpg"
                        :alt="$t('Employee avatar')"
                        class="border-line rounded-pill size-9 shrink-0 border"
                    />
                </div>
            </div>

            <div
                class="border-line bg-surface mt-3 flex items-center gap-2 rounded-md border px-3 py-2"
            >
                <Input
                    v-model="previewMessage"
                    :placeholder="preview.placeholder"
                    class="h-auto border-0 bg-transparent px-0 py-0 text-[12.5px] shadow-none focus-visible:ring-0"
                    @keyup.enter="emit('preview')"
                />
                <button
                    type="button"
                    class="text-brand-600 hover:bg-brand-50 rounded-pill inline-flex size-8 shrink-0 items-center justify-center disabled:opacity-40"
                    :aria-label="$t('Send preview message')"
                    :disabled="previewMessage.trim() === ''"
                    @click="emit('preview')"
                >
                    <Send class="size-4" aria-hidden="true" />
                </button>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <h2 class="font-heading text-brand-800 text-base font-semibold">
                {{ $t('Scenario Settings') }}
            </h2>

            <div class="mt-3 grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))]">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Number of Attempts') }}
                    </label>
                    <Select
                        :model-value="attempts"
                        @update:model-value="onSelect('attempts', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 w-full min-w-0 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in settings.attemptOptions"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        {{ $t('Feedback Style') }}
                    </label>
                    <Select
                        :model-value="feedbackStyle"
                        @update:model-value="onSelect('feedbackStyle', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 w-full min-w-0 rounded-md px-3 text-[12.5px] shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="border-line shadow-pop">
                            <SelectItem
                                v-for="option in settings.feedbackStyles"
                                :key="option.value"
                                :value="option.value"
                                class="text-[13px]"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Focus Areas') }}
                </h3>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="(area, index) in focusAreas"
                        :key="area.label"
                        class="text-brand-900 flex items-center gap-2 rounded-md py-0.5 text-[12px] leading-4.5"
                    >
                        <Checkbox
                            :model-value="area.checked"
                            @update:model-value="toggleFocusArea(index, $event)"
                        />
                        <span>{{ area.label }}</span>
                    </label>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <button
                    type="button"
                    class="flex items-center justify-between gap-3 text-start"
                    :aria-pressed="allowHints"
                    @click="allowHints = !allowHints"
                >
                    <span class="flex items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill relative inline-flex h-6 w-11 shrink-0 transition-colors duration-150',
                                    allowHints
                                        ? 'bg-brand-600'
                                        : 'bg-line-strong',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-pill absolute top-1 size-4 bg-white transition-transform duration-150',
                                        allowHints
                                            ? 'translate-x-6 rtl:-translate-x-6'
                                            : 'translate-x-1 rtl:-translate-x-1',
                                    )
                                "
                            />
                        </span>
                        <span class="text-brand-900 text-[12px] font-medium">
                            {{ $t('Allow hints during conversation') }}
                        </span>
                    </span>
                    <CircleQuestionMark
                        class="text-ink-faint size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                </button>

                <button
                    type="button"
                    class="flex items-center justify-between gap-3 text-start"
                    :aria-pressed="showSuggestions"
                    @click="showSuggestions = !showSuggestions"
                >
                    <span class="flex items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill relative inline-flex h-6 w-11 shrink-0 transition-colors duration-150',
                                    showSuggestions
                                        ? 'bg-brand-600'
                                        : 'bg-line-strong',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-pill absolute top-1 size-4 bg-white transition-transform duration-150',
                                        showSuggestions
                                            ? 'translate-x-6 rtl:-translate-x-6'
                                            : 'translate-x-1 rtl:-translate-x-1',
                                    )
                                "
                            />
                        </span>
                        <span class="text-brand-900 text-[12px] font-medium">
                            {{ $t('Show suggested language after completion') }}
                        </span>
                    </span>
                    <CircleQuestionMark
                        class="text-ink-faint size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <div class="mt-4">
                <h3 class="text-brand-900 text-[12px] font-semibold">
                    {{ $t('Tags (Keywords)') }}
                </h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="tag in tags"
                        :key="tag"
                        type="button"
                        :aria-label="$t('Remove :item', { item: tag })"
                        @click="removeTag(tag)"
                        class="rounded-pill bg-brand-50 text-brand-700 inline-flex min-h-7 items-center px-2.5 text-[11px] font-medium"
                    >
                        {{ tag }}
                    </button>
                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 inline-flex min-h-7 items-center rounded-md border px-2.5 text-[11px] font-semibold"
                        @click="addingTag = !addingTag"
                    >
                        {{ $t('+ Add Tag') }}
                    </button>
                </div>
                <div v-if="addingTag" class="mt-2 flex gap-2">
                    <Input
                        v-model="newTag"
                        autofocus
                        :placeholder="$t('e.g. check-in')"
                        class="border-line h-8 text-xs"
                        @keyup.enter="addTag"
                    />
                    <Button
                        type="button"
                        class="bg-brand-600 hover:bg-brand-700 h-8 px-3 text-xs font-semibold text-white"
                        @click="addTag"
                    >
                        {{ $t('Add') }}
                    </Button>
                </div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="emit('preview')"
                >
                    <Eye class="size-3.5" aria-hidden="true" />
                    {{ $t('Preview') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                    @click="emit('save', settingsPayload())"
                >
                    {{ $t('Save as Draft') }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-3 text-[12px] font-semibold text-white"
                    @click="emit('update', settingsPayload())"
                >
                    {{ $t('Update Scenario') }}
                </Button>
            </div>
        </section>
    </div>
</template>
