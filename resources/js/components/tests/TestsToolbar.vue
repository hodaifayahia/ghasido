<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartColumn,
    CirclePlus,
    ClipboardList,
    Languages,
    ListChecks,
    Settings,
    Upload,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { translations } from '@/routes';
import { cn } from '@/lib/utils';
import type { TestsTab, TestsTabKey } from '@/types';

type Props = {
    tabs: TestsTab[];
    activeTab: TestsTabKey;
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    create: [];
    import: [];
}>();

const activeTab = defineModel<TestsTabKey>('activeTab', { required: true });

const tabIcons: Record<TestsTabKey, Component> = {
    tests: ListChecks,
    'question-bank': ClipboardList,
    results: ChartColumn,
    settings: Settings,
};
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between',
                props.class,
            )
        "
    >
        <div class="flex min-w-0 gap-2 overflow-x-auto pb-1 xl:pb-0">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :aria-pressed="activeTab === tab.key"
                :class="
                    cn(
                        'inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-[12.5px] font-semibold whitespace-nowrap transition-colors duration-150',
                        activeTab === tab.key
                            ? 'border-brand-600 bg-brand-600 shadow-btn text-white'
                            : 'border-line bg-brand-50/55 text-brand-700 hover:bg-brand-100/70',
                    )
                "
                @click="activeTab = tab.key"
            >
                <component :is="tabIcons[tab.key]" class="size-4 shrink-0" />
                {{ tab.label }}
            </button>
        </div>

        <div class="flex shrink-0 items-center gap-2 self-end xl:self-auto">
            <Button
                type="button"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white"
                @click="emit('create')"
            >
                <CirclePlus class="size-4" aria-hidden="true" />
                {{ $t('Create New Test') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                data-test="open-import-questions"
                @click="emit('import')"
            >
                <Upload class="size-4" aria-hidden="true" />
                {{ $t('Import Questions') }}
            </Button>
            <!-- Show Meaning translations (user request 2026-09-26). -->
            <Button
                as-child
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 h-10 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
            >
                <Link
                    :href="translations()"
                    data-test="tests-translations-link"
                >
                    <Languages class="size-4" aria-hidden="true" />
                    {{ $t('Translations') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
