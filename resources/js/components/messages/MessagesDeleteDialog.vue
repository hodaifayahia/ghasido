<script setup lang="ts">
import { TriangleAlert, Trash2 } from '@lucide/vue';
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

type Props = {
    /** "Delete Weekend nudge?" — names the item being deleted. */
    title: string;
    description: string;
    /**
     * Why the item cannot be deleted right now (a template still used by
     * automation rules). The Delete button is then disabled; the server
     * refuses the same case on its own.
     */
    blocked?: string;
    processing?: boolean;
    /** `data-test` of the confirm button. */
    confirmTest: string;
};

withDefaults(defineProps<Props>(), { blocked: '', processing: false });

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
            <DialogHeader class="pr-8 text-start sm:text-start">
                <DialogTitle
                    class="font-heading text-brand-900 text-[17px] font-semibold"
                >
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate text-[13px]">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="blocked"
                class="bg-warning-tint text-warning-text flex items-start gap-2 rounded-md px-3 py-2.5 text-[12.5px] leading-5"
                role="alert"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ blocked }}</span>
            </p>

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
                    :disabled="processing || blocked !== ''"
                    :data-test="confirmTest"
                    @click="emit('confirm')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
