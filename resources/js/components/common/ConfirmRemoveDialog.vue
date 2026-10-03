<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/*
 * Confirm a delete that cannot be undone (client request 2026-10-02): the
 * Super Admin types the name to confirm. Used for an employee and for an
 * archived hotel; both are anonymised, with their answers kept (DATA-10).
 */
type Props = {
    title: string;
    description: string;
    /** What must be typed to confirm. */
    confirmText: string;
    busy?: boolean;
};

const props = withDefaults(defineProps<Props>(), { busy: false });
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [] }>();

const typed = ref('');
watch(open, () => {
    typed.value = '';
});

const matches = computed(
    () =>
        typed.value.trim().toLowerCase() ===
        props.confirmText.trim().toLowerCase(),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md" data-test="confirm-remove-dialog">
            <DialogHeader>
                <span
                    class="bg-danger-tint text-danger mb-1 grid size-11 place-items-center rounded-xl"
                    aria-hidden="true"
                >
                    <Trash2 class="size-5" />
                </span>
                <DialogTitle class="font-heading text-brand-900 text-lg">
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="text-ink-slate">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <label class="grid gap-1.5">
                <span class="text-ink text-[13px]">
                    {{ $t('Type :name to confirm.', { name: confirmText }) }}
                </span>
                <input
                    v-model="typed"
                    type="text"
                    autocomplete="off"
                    dir="auto"
                    data-test="confirm-remove-input"
                    class="border-line bg-surface text-ink focus-visible:border-danger focus-visible:ring-danger/15 h-11 rounded-md border px-3 text-sm focus-visible:ring-3 focus-visible:outline-none"
                />
            </label>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    @click="open = false"
                >
                    {{ $t('Cancel') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-danger text-danger-text hover:bg-danger-tint min-h-11"
                    :disabled="!matches || busy"
                    data-test="confirm-remove-button"
                    @click="emit('confirm')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ $t('Delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
