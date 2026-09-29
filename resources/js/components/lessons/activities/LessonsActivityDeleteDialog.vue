<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Confirm taking an activity or quiz question out of its block. Learners'
 * answers are never deleted (DATA-10); the server keeps an answered
 * activity and only removes it from the block.
 */
type Props = {
    /** Names the item, e.g. "Delete “Question 2”?". */
    title: string;
    description: string;
    processing?: boolean;
};

withDefaults(defineProps<Props>(), { processing: false });

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    confirm: [];
}>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="bg-surface border-line shadow-pop rounded-lg p-6 sm:max-w-[440px]"
        >
            <DialogHeader class="pe-8 text-start">
                <DialogTitle
                    class="font-heading text-brand-900 text-[17px] font-semibold"
                >
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate text-[13px]">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2 sm:justify-end">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-line text-brand-700 hover:bg-brand-50 h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="outline"
                    class="border-danger text-danger hover:bg-danger-tint hover:text-danger-text bg-surface h-10 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    :disabled="processing"
                    data-test="delete-activity-button"
                    @click="emit('confirm')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
