<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CirclePlus, Languages, Upload } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { translations } from '@/routes';
import { cn } from '@/lib/utils';

/*
 * The page's actions. The Tests / Question Bank / Results & Analytics /
 * Settings tabs that used to sit on the start of this row were removed at
 * the client's request (2026-09-29).
 */
type Props = {
    class?: HTMLAttributes['class'];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    create: [];
    import: [];
}>();
</script>

<template>
    <div
        :class="
            cn('flex flex-wrap items-center justify-end gap-2', props.class)
        "
    >
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
            <Link :href="translations()" data-test="tests-translations-link">
                <Languages class="size-4" aria-hidden="true" />
                {{ $t('Translations') }}
            </Link>
        </Button>
    </div>
</template>
