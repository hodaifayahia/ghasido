<script setup lang="ts">
import { CircleQuestionMark, Clock, Eye, Send, Sparkles } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
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
import type { AiScenarioPreview, AiScenarioSettings } from '@/types';

type Props = {
    preview: AiScenarioPreview;
    settings: AiScenarioSettings;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const attempts = ref(props.settings.attempts);
const feedbackStyle = ref(props.settings.feedbackStyle);

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
</script>

<template>
    <div :class="cn('flex min-w-0 flex-col gap-3', props.class)">
        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    Conversation Preview (Example)
                </h2>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-8 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                >
                    <Clock class="size-3.5" aria-hidden="true" />
                    Test Scenario
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
                        alt="Employee avatar"
                        class="border-line rounded-pill size-9 shrink-0 border"
                    />
                </div>
            </div>

            <div
                class="border-line bg-surface mt-3 flex items-center gap-2 rounded-md border px-3 py-2"
            >
                <Input
                    :default-value="''"
                    :placeholder="preview.placeholder"
                    class="h-auto border-0 bg-transparent px-0 py-0 text-[12.5px] shadow-none focus-visible:ring-0"
                />
                <button
                    type="button"
                    class="text-brand-600 hover:bg-brand-50 rounded-pill inline-flex size-8 shrink-0 items-center justify-center"
                    aria-label="Send preview message"
                >
                    <Send class="size-4" aria-hidden="true" />
                </button>
            </div>
        </section>

        <section
            class="border-line bg-surface shadow-card rounded-lg border p-3"
        >
            <h2 class="font-heading text-brand-800 text-base font-semibold">
                Scenario Settings
            </h2>

            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-2">
                <div class="grid gap-1.5">
                    <label class="text-brand-900 text-[12px] font-semibold">
                        Number of Attempts
                    </label>
                    <Select
                        :model-value="attempts"
                        @update:model-value="onSelect('attempts', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
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
                        Feedback Style
                    </label>
                    <Select
                        :model-value="feedbackStyle"
                        @update:model-value="onSelect('feedbackStyle', $event)"
                    >
                        <SelectTrigger
                            class="border-line text-ink bg-surface h-10 rounded-md px-3 text-[12.5px] shadow-none"
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
                    Focus Areas
                </h3>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="area in settings.focusAreas"
                        :key="area.label"
                        class="text-brand-900 flex items-center gap-2 rounded-md py-0.5 text-[12px] leading-4.5"
                    >
                        <Checkbox :model-value="area.checked" />
                        <span>{{ area.label }}</span>
                    </label>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <button
                    type="button"
                    class="flex items-center justify-between gap-3 text-start"
                    :aria-pressed="settings.allowHints"
                >
                    <span class="flex items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill relative inline-flex h-6 w-11 shrink-0 transition-colors duration-150',
                                    settings.allowHints
                                        ? 'bg-brand-600'
                                        : 'bg-line-strong',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-pill absolute top-1 size-4 bg-white transition-transform duration-150',
                                        settings.allowHints
                                            ? 'translate-x-6'
                                            : 'translate-x-1',
                                    )
                                "
                            />
                        </span>
                        <span class="text-brand-900 text-[12px] font-medium">
                            Allow hints during conversation
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
                    :aria-pressed="settings.showSuggestions"
                >
                    <span class="flex items-center gap-2">
                        <span
                            :class="
                                cn(
                                    'rounded-pill relative inline-flex h-6 w-11 shrink-0 transition-colors duration-150',
                                    settings.showSuggestions
                                        ? 'bg-brand-600'
                                        : 'bg-line-strong',
                                )
                            "
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-pill absolute top-1 size-4 bg-white transition-transform duration-150',
                                        settings.showSuggestions
                                            ? 'translate-x-6'
                                            : 'translate-x-1',
                                    )
                                "
                            />
                        </span>
                        <span class="text-brand-900 text-[12px] font-medium">
                            Show suggested language after completion
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
                    Tags (Keywords)
                </h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="tag in settings.tags"
                        :key="tag"
                        class="rounded-pill bg-brand-50 text-brand-700 inline-flex min-h-7 items-center px-2.5 text-[11px] font-medium"
                    >
                        {{ tag }}
                    </span>
                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 inline-flex min-h-7 items-center rounded-md border px-2.5 text-[11px] font-semibold"
                    >
                        + Add Tag
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                >
                    <Eye class="size-3.5" aria-hidden="true" />
                    Preview
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold shadow-none"
                >
                    Save as Draft
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-3 text-[12px] font-semibold text-white"
                >
                    Update Scenario
                </Button>
            </div>
        </section>
    </div>
</template>
