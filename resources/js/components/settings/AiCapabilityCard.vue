<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
import { computed, useId } from 'vue';
import AiCheckResult from '@/components/settings/AiCheckResult.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AiCapabilityMode,
    AiCheckCapability,
    AiCheckState,
    AiEffectiveCapability,
} from '@/types';

/*
 * One capability on Settings → AI models (API-04): its on/off switch over
 * .env, its model fields (default slot), what is live now, and a "Test"
 * button per check with the last result (PERF-04, ACC-02).
 */
type CheckRow = {
    capability: AiCheckCapability;
    label: string;
    check: AiCheckState | null;
    effective: AiEffectiveCapability;
};

type Props = {
    title: string;
    description: string;
    envProvider?: string;
    realDefault?: string;
    checks: CheckRow[];
    testing?: AiCheckCapability | null;
};

const props = withDefaults(defineProps<Props>(), {
    envProvider: undefined,
    realDefault: undefined,
    testing: null,
});

const mode = defineModel<AiCapabilityMode>('mode');

const emit = defineEmits<{ test: [capability: AiCheckCapability] }>();

const selectId = useId();

// reka-ui's SelectItem refuses an empty value, so "follow .env" is 'env'.
const selectValue = computed({
    get: (): string => mode.value || 'env',
    set: (value: string) => {
        mode.value = value === 'fake' || value === 'real' ? value : '';
    },
});

const realLabel = computed((): string => {
    const env = props.envProvider;

    return `On — real provider (${env && env !== 'fake' ? env : props.realDefault})`;
});

function busy(check: AiCheckState | null): boolean {
    return check?.status === 'pending' || check?.status === 'running';
}
</script>

<template>
    <section
        class="border-line bg-surface shadow-card grid min-w-0 gap-4 rounded-lg border p-5"
    >
        <header class="grid gap-0.5">
            <h3 class="font-heading text-brand-800 text-base font-semibold">
                {{ title }}
            </h3>
            <p class="text-ink-muted text-sm">{{ description }}</p>
        </header>

        <div v-if="mode !== undefined" class="grid gap-1.5">
            <Label :for="selectId" class="text-brand-900 text-xs font-semibold">
                Provider
            </Label>
            <Select v-model="selectValue">
                <SelectTrigger :id="selectId" class="border-line h-11 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="env">
                        Follow .env ({{ envProvider }})
                    </SelectItem>
                    <SelectItem value="fake">
                        Off — fake, no network, no bill
                    </SelectItem>
                    <SelectItem value="real">{{ realLabel }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <slot />

        <ul class="border-line grid list-none gap-3 border-t pt-4">
            <li
                v-for="row in checks"
                :key="row.capability"
                class="flex min-w-0 flex-col gap-2 md:flex-row md:items-start md:justify-between"
            >
                <div class="grid min-w-0 gap-1">
                    <p class="text-ink text-sm font-medium">
                        {{ row.label }}:
                        <span class="text-ink-muted font-normal break-all">
                            {{ row.effective.provider }} ·
                            {{ row.effective.model || 'no model set' }}
                        </span>
                    </p>
                    <p
                        v-if="
                            row.effective.provider !== 'fake' &&
                            !row.effective.keyConfigured
                        "
                        class="text-warning-text text-xs"
                    >
                        No key in .env for this provider.
                    </p>
                    <AiCheckResult :check="row.check" />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 shrink-0"
                    :disabled="busy(row.check) || testing === row.capability"
                    :data-test="`test-${row.capability}-button`"
                    @click="emit('test', row.capability)"
                >
                    <FlaskConical class="size-4" aria-hidden="true" />
                    Test
                </Button>
            </li>
        </ul>
    </section>
</template>
