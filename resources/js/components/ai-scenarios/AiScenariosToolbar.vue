<script setup lang="ts">
import {
    BookOpen,
    Bot,
    CirclePlus,
    Eye,
    FileText,
    FolderOpen,
} from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { AiScenarioTab, AiScenarioTabKey } from '@/types';

type Props = {
    tabs: AiScenarioTab[];
    showTabs?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    showTabs: true,
});

const emit = defineEmits<{
    create: [];
    select: [tab: AiScenarioTabKey];
}>();

const activeTab = defineModel<AiScenarioTabKey>('activeTab', {
    required: true,
});

const tabIcons: Record<AiScenarioTabKey, Component> = {
    scenarios: BookOpen,
    categories: FolderOpen,
    instructions: Bot,
    feedback: FileText,
    preview: Eye,
};
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col gap-2 xl:flex-row xl:items-center',
                props.showTabs
                    ? 'xl:justify-between'
                    : 'items-end xl:justify-end',
                props.class,
            )
        "
    >
        <div
            v-if="props.showTabs"
            class="flex min-w-0 gap-2 overflow-x-auto pb-1 xl:pb-0"
        >
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
                @click="emit('select', tab.key)"
            >
                <component :is="tabIcons[tab.key]" class="size-4 shrink-0" />
                {{ tab.label }}
            </button>
        </div>

        <Button
            type="button"
            class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 self-end rounded-md px-4 text-[12.5px] font-semibold text-white xl:self-auto"
            @click="emit('create')"
        >
            <CirclePlus class="size-4" aria-hidden="true" />
            {{ $t('Create New Scenario') }}
        </Button>
    </div>
</template>
