<script setup lang="ts">
import { useId } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/*
 * A labelled text field of Settings → Email, with its hint and its
 * validation error (icon-free text, announced with the input).
 */
type Props = {
    label: string;
    type?: string;
    hint?: string;
    error?: string;
    placeholder?: string;
    autocomplete?: string;
    inputmode?: 'text' | 'email' | 'numeric';
    dir?: 'ltr' | 'rtl' | 'auto';
    dataTest?: string;
};

withDefaults(defineProps<Props>(), {
    type: 'text',
    hint: undefined,
    error: undefined,
    placeholder: undefined,
    autocomplete: 'off',
    inputmode: 'text',
    dir: 'ltr',
    dataTest: undefined,
});

const model = defineModel<string | number>({ required: true });

const id = useId();
</script>

<template>
    <div class="grid min-w-0 gap-1.5">
        <Label :for="id" class="text-brand-900 text-xs font-semibold">
            {{ label }}
        </Label>
        <Input
            :id="id"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :dir="dir"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="hint || error ? `${id}-help` : undefined"
            :data-test="dataTest"
            class="border-line h-11 text-start"
        />
        <p
            v-if="error"
            :id="`${id}-help`"
            class="text-danger-text text-xs"
            role="alert"
        >
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-help`" class="text-ink-muted text-xs">
            {{ hint }}
        </p>
    </div>
</template>
