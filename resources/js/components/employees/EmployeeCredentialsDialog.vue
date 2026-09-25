<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref, watch } from 'vue';
import HotelsModal from '@/components/hotels/HotelsModal.vue';
import { Button } from '@/components/ui/button';
import type { EmployeeCredentials } from '@/types';

type Props = {
    credentials: EmployeeCredentials | null;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const copied = ref(false);

watch(open, () => {
    copied.value = false;
});

async function copy(): Promise<void> {
    if (props.credentials === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(
            `Username: ${props.credentials.username}\nPassword: ${props.credentials.password}`,
        );
        copied.value = true;
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <HotelsModal
        v-model:open="open"
        :title="`New password for ${credentials?.name ?? 'employee'}`"
        description="Hand these to the employee now. The password is shown once and is not stored in clear anywhere."
    >
        <div v-if="credentials" class="mt-2 grid gap-3">
            <dl
                class="border-line bg-app grid gap-2 rounded-md border p-4 text-[13px]"
            >
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-slate">Username</dt>
                    <dd
                        class="text-brand-900 font-semibold"
                        data-test="reset-username"
                    >
                        {{ credentials.username }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-slate">Password</dt>
                    <dd
                        class="text-brand-900 font-mono text-[15px] font-semibold tracking-[0.06em]"
                        data-test="reset-password"
                    >
                        {{ credentials.password }}
                    </dd>
                </div>
            </dl>

            <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 bg-surface h-10 gap-2 rounded-md px-4 text-[12.5px] font-semibold shadow-none"
                    data-test="copy-credentials-button"
                    @click="copy"
                >
                    <component
                        :is="copied ? Check : Copy"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>
                <Button
                    type="button"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-10 rounded-md px-4 text-[12.5px] font-semibold text-white active:scale-[.97]"
                    data-test="close-credentials-button"
                    @click="open = false"
                >
                    Done
                </Button>
            </div>
        </div>
    </HotelsModal>
</template>
