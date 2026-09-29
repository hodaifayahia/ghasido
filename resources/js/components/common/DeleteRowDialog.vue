<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TriangleAlert, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

/**
 * The one "Delete" confirmation every admin table uses (safe delete, owner
 * decision 2026-09-27). The server deletes a row only when nothing depends
 * on it; otherwise it answers with a `delete` error naming what does
 * ("12 employees still belong to this hotel"), shown here in place, so a
 * mistaken click can never remove learner or research data (DATA-10).
 */
type Props = {
    /** DELETE url of the row, or null while no row is chosen. */
    url: string | null;
    /** What is being deleted, e.g. "Sofitel Algiers". */
    name: string;
    /** The kind of row, already translated, e.g. "hotel". */
    kind: string;
    /** Shown under the warning, e.g. what goes with the row. */
    note?: string;
};

const props = withDefaults(defineProps<Props>(), { note: '' });

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ deleted: [] }>();

const { t } = useI18n();

const processing = ref(false);
const refusal = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        refusal.value = null;
    }
});

function confirmDelete(): void {
    if (props.url === null || processing.value) {
        return;
    }

    processing.value = true;
    refusal.value = null;

    router.delete(props.url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            emit('deleted');
        },
        onError: (errors) => {
            refusal.value =
                errors.delete ??
                Object.values(errors)[0] ??
                t('This could not be deleted. Try again.');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="t('Delete :kind?', { kind })"
        :description="
            t(':name will be deleted permanently. This cannot be undone.', {
                name,
            })
        "
        class="sm:max-w-[480px]"
    >
        <div class="mt-2 grid gap-3" data-test="delete-row-dialog">
            <p
                v-if="note && refusal === null"
                class="text-ink-slate text-[12.5px] leading-5"
            >
                {{ note }}
            </p>

            <div
                v-if="refusal"
                class="bg-warning-tint text-warning-text flex items-start gap-2.5 rounded-md px-3 py-2.5 text-[12.5px] leading-5"
                role="alert"
                data-test="delete-refusal"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ refusal }}</span>
            </div>

            <div
                class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-11 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                    @click="open = false"
                >
                    {{ refusal ? t('Close') : t('Keep it') }}
                </Button>
                <Button
                    v-if="refusal === null"
                    type="button"
                    variant="outline"
                    :disabled="processing || url === null"
                    class="border-danger text-danger-text hover:bg-danger-tint bg-surface h-11 gap-1.5 rounded-md px-4 text-[12.5px] font-semibold shadow-none md:h-10"
                    data-test="confirm-delete-button"
                    @click="confirmDelete"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    {{ processing ? t('Deleting…') : t('Delete') }}
                </Button>
            </div>
        </div>
    </HotelsModal>
</template>
