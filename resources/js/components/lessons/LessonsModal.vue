<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description: string;
    /** Dialog width class on desktop, e.g. `sm:max-w-[760px]`. */
    size?: 'md' | 'lg' | 'xl';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const open = defineModel<boolean>('open', { required: true });

// A centred dialog on desktop, a bottom sheet with a drag handle on a phone
// (AGENTS.md §7). The query is only trusted after mount, so the server and
// the first client render agree.
const phoneQuery = useMediaQuery('(max-width: 767px)');
const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});
const isPhone = computed(() => mounted.value && phoneQuery.value);

const width: Record<NonNullable<Props['size']>, string> = {
    md: 'sm:max-w-[560px]',
    lg: 'sm:max-w-[760px]',
    xl: 'sm:max-w-[960px]',
};
</script>

<template>
    <Sheet v-if="isPhone" v-model:open="open">
        <SheetContent
            side="bottom"
            :class="
                cn(
                    'bg-surface border-line max-h-[92dvh] overflow-y-auto rounded-t-xl px-4 pb-6',
                    props.class,
                )
            "
        >
            <div
                class="bg-line-strong mx-auto mt-3 mb-1 h-1.5 w-10 rounded-full"
                aria-hidden="true"
            />
            <SheetHeader class="px-0 text-start">
                <SheetTitle
                    class="font-heading text-brand-900 text-[17px] font-semibold"
                >
                    {{ title }}
                </SheetTitle>
                <SheetDescription class="text-ink-slate text-[12.5px]">
                    {{ description }}
                </SheetDescription>
            </SheetHeader>
            <slot />
        </SheetContent>
    </Sheet>

    <Dialog v-else v-model:open="open">
        <DialogContent
            :class="
                cn(
                    'bg-surface border-line shadow-pop max-h-[90vh] overflow-y-auto rounded-lg p-6',
                    width[size],
                    props.class,
                )
            "
        >
            <DialogHeader class="text-start">
                <DialogTitle
                    class="font-heading text-brand-900 text-[18px] font-semibold"
                >
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate text-[13px]">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <slot />
        </DialogContent>
    </Dialog>
</template>
