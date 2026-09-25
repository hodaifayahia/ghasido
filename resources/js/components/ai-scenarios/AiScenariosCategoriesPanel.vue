<script setup lang="ts">
import {
    BookOpen,
    EllipsisVertical,
    FolderOpen,
    Layers,
    Plus,
    Users,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type {
    AiScenarioCategories,
    AiScenarioStatus,
    AiScenarioSummaryStat,
} from '@/types';

type Props = {
    categories: AiScenarioCategories;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    save: [items: AiScenarioCategories['items']];
}>();

const items = ref([...props.categories.items]);
const formOpen = ref(false);
const editingId = ref<string | null>(null);
const name = ref('');
const description = ref('');
const department = ref('All Departments');
const scenarioCount = ref(0);
const status = ref<AiScenarioStatus>('draft');

watch(
    () => props.categories.items,
    (value) => {
        items.value = [...value];
    },
    { deep: true },
);

function editCategory(category: AiScenarioCategories['items'][number]): void {
    formOpen.value = true;
    editingId.value = category.id;
    name.value = category.name;
    description.value = category.description;
    department.value = category.department;
    scenarioCount.value = category.scenarioCount;
    status.value = category.status;
}

function newCategory(): void {
    formOpen.value = true;
    editingId.value = null;
    name.value = '';
    description.value = '';
    department.value = 'All Departments';
    scenarioCount.value = 0;
    status.value = 'draft';
}

function cancelEdit(): void {
    formOpen.value = false;
    editingId.value = null;
    name.value = '';
}

function saveCategory(): void {
    const trimmedName = name.value.trim();
    if (trimmedName === '') return;

    const category = {
        id: editingId.value ?? 'new-category-' + Date.now(),
        name: trimmedName,
        description: description.value.trim(),
        department: department.value.trim() || 'All Departments',
        scenarioCount: Math.max(0, scenarioCount.value),
        status: status.value,
    };
    const index = items.value.findIndex((item) => item.id === category.id);

    if (index === -1) {
        items.value.push(category);
    } else {
        items.value[index] = category;
    }

    emit('save', items.value);
    formOpen.value = false;
    editingId.value = null;
}

function removeCategory(id: string): void {
    items.value = items.value.filter((item) => item.id !== id);
    emit('save', items.value);
}

const statusTone: Record<AiScenarioStatus, string> = {
    published: 'bg-success-tint text-success-text',
    draft: 'bg-warning-tint text-warning-text',
};

const summaryTone: Record<AiScenarioSummaryStat['tone'], string> = {
    brand: 'bg-brand-50 text-brand-600',
    success: 'bg-success-tint text-success',
    ai: 'bg-ai-tint text-ai',
    warning: 'bg-warning-tint text-warning',
};
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-4 md:p-5',
                props.class,
            )
        "
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-heading text-brand-800 text-base font-semibold">
                    Scenario Categories
                </h2>
                <p class="text-ink-slate mt-0.5 text-[12.5px]">
                    Group role-play scenarios by department and theme so staff
                    find the right practice.
                </p>
            </div>
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-9 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                @click="newCategory"
            >
                <Plus class="size-4" aria-hidden="true" />
                New Category
            </Button>
        </div>

        <div
            v-if="formOpen"
            class="border-brand-200 bg-brand-50/35 mt-4 grid gap-3 rounded-lg border p-3 md:grid-cols-2"
        >
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold"
                    >Category name</label
                >
                <Input
                    v-model="name"
                    placeholder="e.g. Front Desk Essentials"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold"
                    >Department</label
                >
                <Input
                    v-model="department"
                    placeholder="Reception"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5 md:col-span-2">
                <label class="text-brand-900 text-[12px] font-semibold"
                    >Description</label
                >
                <Input
                    v-model="description"
                    placeholder="What conversations belong here?"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold"
                    >Scenario count</label
                >
                <Input
                    v-model.number="scenarioCount"
                    type="number"
                    min="0"
                    class="border-line h-9 text-[12px]"
                />
            </div>
            <div class="grid gap-1.5">
                <label class="text-brand-900 text-[12px] font-semibold"
                    >Status</label
                >
                <select
                    v-model="status"
                    class="border-line text-ink bg-surface h-9 rounded-md border px-2 text-[12px]"
                >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line h-9 text-[12px] shadow-none"
                    @click="cancelEdit"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    class="bg-brand-600 hover:bg-brand-700 h-9 text-[12px] font-semibold text-white"
                    @click="saveCategory"
                    >Save category</Button
                >
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="stat in categories.summary"
                :key="stat.label"
                class="border-line bg-surface flex items-center gap-3 rounded-md border px-3 py-2.5"
            >
                <span
                    :class="
                        cn(
                            'grid size-9 shrink-0 place-items-center rounded-xl',
                            summaryTone[stat.tone],
                        )
                    "
                >
                    <Layers class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p
                        class="font-heading text-ink text-[18px] leading-none font-bold"
                    >
                        {{ stat.value }}
                    </p>
                    <p class="text-ink-slate mt-1 truncate text-[11.5px]">
                        {{ stat.label }}
                    </p>
                </div>
            </div>
        </div>

        <div class="divide-line border-line mt-4 divide-y rounded-lg border">
            <article
                v-for="category in items"
                :key="category.id"
                class="hover:bg-brand-50/35 flex items-start gap-3 px-3 py-3 transition-colors duration-150"
            >
                <span
                    class="bg-brand-50 text-brand-600 grid size-10 shrink-0 place-items-center rounded-xl"
                >
                    <FolderOpen class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p
                            class="text-brand-900 truncate text-[13.5px] font-semibold"
                        >
                            {{ category.name }}
                        </p>
                        <span
                            :class="
                                cn(
                                    'rounded-pill inline-flex min-h-5 items-center px-2 text-[10.5px] font-semibold',
                                    statusTone[category.status],
                                )
                            "
                        >
                            {{
                                category.status === 'published'
                                    ? 'Published'
                                    : 'Draft'
                            }}
                        </span>
                    </div>
                    <p class="text-ink-slate mt-0.5 line-clamp-1 text-[12px]">
                        {{ category.description }}
                    </p>
                    <div
                        class="text-ink-faint mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px]"
                    >
                        <span class="inline-flex items-center gap-1">
                            <Users class="size-3.5" aria-hidden="true" />
                            {{ category.department }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <BookOpen class="size-3.5" aria-hidden="true" />
                            {{ category.scenarioCount }} scenarios
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-ink-faint hover:bg-brand-50 inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                    @click="editCategory(category)"
                    :aria-label="`More actions for ${category.name}`"
                >
                    <EllipsisVertical class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="text-danger hover:bg-danger-tint inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                    :aria-label="'Delete ' + category.name"
                    @click="removeCategory(category.id)"
                >
                    <span class="sr-only">Delete</span>
                    ×
                </button>
            </article>
        </div>
    </section>
</template>
