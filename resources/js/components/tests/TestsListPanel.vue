<script setup lang="ts">
import { EllipsisVertical, Search } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
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
import type { TestsList, TestStatus, TestVariant } from '@/types';

type Props = {
    list: TestsList;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    open: [id: string];
}>();

const search = ref(props.list.search);
const hotel = ref(props.list.hotel);
const department = ref(props.list.department);
const type = ref(props.list.type);

const typeTone: Record<TestVariant, string> = {
    pre: 'bg-brand-50 text-brand-700',
    post: 'bg-aqua-tint text-aqua',
};

const typeLabel: Record<TestVariant, string> = {
    pre: 'Pre-test',
    post: 'Post-test',
};

const statusTone: Record<TestStatus, string> = {
    active: 'bg-success-tint text-success-text',
    draft: 'bg-warning-tint text-warning-text',
};

const statusLabel: Record<TestStatus, string> = {
    active: 'Active',
    draft: 'Draft',
};

function onSelect(
    target: 'hotel' | 'department' | 'type',
    value: AcceptableValue,
): void {
    if (typeof value !== 'string') {
        return;
    }

    if (target === 'hotel') {
        hotel.value = value;
        return;
    }

    if (target === 'department') {
        department.value = value;
        return;
    }

    type.value = value;
}

function optionLabel(options: TestsList['hotels'], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

const filteredItems = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    const hotelLabel = optionLabel(
        props.list.hotels,
        hotel.value,
    ).toLocaleLowerCase();
    const departmentLabel = optionLabel(
        props.list.departments,
        department.value,
    ).toLocaleLowerCase();

    return props.list.items.filter((item) => {
        const searchable =
            `${item.title} ${item.department} ${item.hotel}`.toLocaleLowerCase();

        return (
            (term === '' || searchable.includes(term)) &&
            (hotel.value === 'all-hotels' ||
                item.hotel.toLocaleLowerCase() === hotelLabel) &&
            (department.value === 'all-departments' ||
                item.department.toLocaleLowerCase() === departmentLabel) &&
            (type.value === 'all-types' || item.type === type.value)
        );
    });
});
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
        <h2
            class="font-heading text-brand-800 truncate text-base font-semibold"
        >
            Test List
        </h2>

        <div class="mt-3 flex flex-col gap-2">
            <div class="relative min-w-0">
                <Search
                    aria-hidden="true"
                    class="text-ink-faint absolute start-3 top-1/2 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search tests..."
                    class="border-line placeholder:text-ink-faint bg-surface h-9 rounded-md ps-9 pe-3 text-[12.5px] shadow-none"
                />
            </div>

            <div class="grid grid-cols-3 gap-1.5">
                <Select
                    :model-value="hotel"
                    @update:model-value="onSelect('hotel', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-0 rounded-md px-2 text-[10.5px] shadow-none [&>span]:truncate [&>svg]:shrink-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in list.hotels"
                            :key="option.value"
                            :value="option.value"
                            class="text-[12.5px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="department"
                    @update:model-value="onSelect('department', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-0 rounded-md px-2 text-[10.5px] shadow-none [&>span]:truncate [&>svg]:shrink-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in list.departments"
                            :key="option.value"
                            :value="option.value"
                            class="text-[12.5px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="type"
                    @update:model-value="onSelect('type', $event)"
                >
                    <SelectTrigger
                        class="border-line text-ink bg-surface h-9 min-w-0 rounded-md px-2 text-[10.5px] shadow-none [&>span]:truncate [&>svg]:shrink-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent class="border-line shadow-pop">
                        <SelectItem
                            v-for="option in list.types"
                            :key="option.value"
                            :value="option.value"
                            class="text-[12.5px]"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="divide-line/70 mt-2 divide-y">
            <article
                v-for="item in filteredItems"
                :key="item.id"
                class="hover:bg-brand-50/35 flex items-start gap-2.5 rounded-md px-1.5 py-2 transition-colors duration-150"
            >
                <LessonsMockupCrop
                    :crop="item.crop"
                    src="/decor/tests-mockup.jpg"
                    :alt="item.title"
                    class="border-line mt-0.5 size-11 shrink-0 rounded-md border"
                />

                <div class="min-w-0 flex-1">
                    <p
                        class="text-brand-900 truncate text-[12.5px] font-semibold"
                    >
                        <button
                            type="button"
                            class="text-brand-900 block max-w-full truncate text-left text-[12.5px] font-semibold hover:underline"
                            @click="emit('open', item.id)"
                        >
                            {{ item.title }}
                        </button>
                    </p>
                    <p class="text-ink-slate truncate text-[11px] leading-4">
                        {{ item.department }}
                    </p>
                    <p class="text-ink-slate truncate text-[11px] leading-4">
                        {{ item.meta }}
                    </p>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 items-center px-2 text-[10px] font-semibold whitespace-nowrap',
                                typeTone[item.type],
                            )
                        "
                    >
                        {{ typeLabel[item.type] }}
                    </span>
                    <span
                        :class="
                            cn(
                                'rounded-pill inline-flex min-h-5 items-center px-2 text-[10px] font-semibold whitespace-nowrap',
                                statusTone[item.status],
                            )
                        "
                    >
                        {{ statusLabel[item.status] }}
                    </span>
                </div>

                <button
                    type="button"
                    class="text-ink-faint hover:bg-brand-50 inline-flex size-6 shrink-0 items-center justify-center self-center rounded-md"
                    :aria-label="`More actions for ${item.title}`"
                >
                    <EllipsisVertical class="size-4" aria-hidden="true" />
                </button>
            </article>
        </div>
    </section>
</template>
