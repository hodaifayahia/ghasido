<script setup lang="ts">
import { CircleAlert, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';

/*
 * Confirms deleting one test (client request 2026-09-29). A test someone has
 * already sat cannot be deleted: its attempts hold the learners' answers,
 * which are never removed (DATA-10). The dialog says so up front and offers
 * no delete; the server refuses it again whatever the page sends.
 */
type Props = {
    title: string;
    attemptCount: number;
    processing?: boolean;
};

const props = withDefaults(defineProps<Props>(), { processing: false });

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    confirm: [];
}>();

const { t, tc } = useI18n();

const blocked = computed(() => props.attemptCount > 0);

const description = computed(() =>
    blocked.value
        ? tc(
              ':count employee has already taken this test.|:count employees have already taken this test.',
              props.attemptCount,
          )
        : t(
              'Delete “:title”? Its questions are removed from this test. This cannot be undone.',
              { title: props.title },
          ),
);
</script>

<template>
    <LessonsModal
        v-model:open="open"
        :title="blocked ? $t('This test cannot be deleted') : $t('Delete test')"
        :description="description"
        size="md"
    >
        <div
            v-if="blocked"
            class="border-line bg-warning-tint text-warning-text mt-4 flex gap-2.5 rounded-md border p-3 text-[13px] leading-5"
            role="note"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                {{
                    $t(
                        'Their answers are kept for training follow-up and research, so “:title” stays in the library.',
                        { title },
                    )
                }}
            </p>
        </div>

        <div
            class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
        >
            <Button
                type="button"
                variant="outline"
                class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-xs font-semibold md:h-10"
                @click="open = false"
            >
                {{ blocked ? $t('Close') : $t('Cancel') }}
            </Button>
            <Button
                v-if="!blocked"
                type="button"
                variant="outline"
                class="border-danger text-danger-text hover:bg-danger-tint hover:text-danger-text h-11 gap-1.5 rounded-md px-4 text-xs font-semibold md:h-10"
                :disabled="processing"
                data-test="confirm-delete-test-button"
                @click="emit('confirm')"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                {{ $t('Delete test') }}
            </Button>
        </div>
    </LessonsModal>
</template>
