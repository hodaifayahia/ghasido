<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Search,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import LessonsMockupCrop from '@/components/lessons/LessonsMockupCrop.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { AiScenarioLibrary, AiScenarioStatus } from '@/types';

type Props = {
    library: AiScenarioLibrary;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const search = ref(props.library.search);
const department = ref(props.library.department);
const status = ref(props.library.status);

const statusTone: Record<AiScenarioStatus, string> = {
    published: 'bg-success-tint text-success-text',
    draft: 'bg-warning-tint text-warning-text',
};

function onSelect(
    target: 'department' | 'status',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    status.value = value;
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-3',
                props.class,
            )
        "
    >
        <div class="flex min-h-8 items-center justify-between gap-3">
            <h2
                class="font-heading text-brand-800 truncate text-base font-semibold"
            >
                Scenario Library
            </h2>
        </div>

        <div class="mt-3 flex flex-col gap-2">
            <div class="relative min-w-0">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search scenarios..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in library.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[13px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="status"
                    @update:model-value="onSelect('status', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 rounded-md px-3 text-[12.5px] shadow-none"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in library.statuses"
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

        <div
            class="divide-line border-line/70 bg-surface mt-3 divide-y rounded-lg border"
        >
            <article
                v-for="scenario in library.scenarios"
                :key="scenario.id"
                class="hover:bg-brand-50/35 flex items-start gap-3 px-3 py-2 transition-colors duration-150"
            >
                <LessonsMockupCrop
                    :crop="scenario.crop"
                    src="/decor/ai-scenarios-mockup.jpg"
                    :alt="scenario.title"
                    class="border-line mt-px w-[78px] shrink-0 rounded-md border"
                />

                <div class="min-w-0 flex-1">
                    <p
                        class="text-brand-900 truncate text-[13px] font-semibold"
                    >
                        {{ scenario.title }}
                    </p>
                    <p
                        class="text-ink-slate truncate text-[11.5px] leading-4.5"
                    >
                        {{ scenario.department }}
                    </p>
                    <p
                        class="text-ink-slate truncate text-[11.5px] leading-4.5"
                    >
                        {{ scenario.level }}
                    </p>
                </div>

                <div class="flex items-start gap-2 pt-1">
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 min-w-[72px] items-center justify-center px-2 text-[10.5px] font-semibold whitespace-nowrap',
                                statusTone[scenario.status],
                            )
                        "
                    >
                        {{
                            scenario.status === 'published'
                                ? 'Published'
                                : 'Draft'
                        }}
                    </span>
                    <button
                        type="button"
                        class="text-ink-faint hover:bg-brand-50 inline-flex size-6 items-center justify-center rounded-md"
                        :aria-label="`More actions for ${scenario.title}`"
                    >
                        <EllipsisVertical class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </article>
        </div>

        <div
            class="text-ink-slate mt-3 flex items-center justify-between gap-3 text-[11.5px]"
        >
            <p>{{ library.showing }}</p>

            <nav aria-label="Scenario pages" class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="text-brand-700 hover:bg-brand-50 inline-flex size-7 items-center justify-center rounded-md"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </button>

                <button
                    v-for="page in library.pages"
                    :key="page"
                    type="button"
                    :class="
                        cn(
                            'inline-flex size-7 items-center justify-center rounded-md text-[11.5px] font-semibold',
                            page === library.currentPage
                                ? 'bg-brand-600 shadow-btn text-white'
                                : 'text-brand-700 hover:bg-brand-50',
                        )
                    "
                >
                    {{ page }}
                </button>

                <button
                    type="button"
                    class="text-brand-700 hover:bg-brand-50 inline-flex size-7 items-center justify-center rounded-md"
                >
                    <ChevronRight class="size-4" aria-hidden="true" />
                </button>
            </nav>
        </div>
    </section>
</template>
