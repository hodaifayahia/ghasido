<script setup lang="ts">
import { useId } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/*
 * A model picker for Settings → AI models (API-04): the preset list from
 * the client's plan plus free text, so any model id can be typed. Blank
 * means "use .env", shown as the placeholder.
 */
type Props = {
    label: string;
    presets: string[];
    envValue?: string;
    error?: string;
    hint?: string;
};

const props = defineProps<Props>();

const model = defineModel<string>({ required: true });

const id = useId();
const listId = `${id}-presets`;
</script>

<template>
    <div class="grid min-w-0 gap-1.5">
        <Label :for="id" class="text-brand-900 text-xs font-semibold">
            {{ label }}
        </Label>
        <Input
            :id="id"
            v-model="model"
            :list="listId"
            autocomplete="off"
            spellcheck="false"
            class="border-line h-11 text-sm"
            :placeholder="
                props.envValue
                    ? `.env: ${props.envValue}`
                    : 'Type any model id, or leave blank for .env'
            "
        />
        <datalist :id="listId">
            <option v-for="preset in presets" :key="preset" :value="preset" />
        </datalist>
        <p v-if="hint && !error" class="text-ink-muted text-xs">{{ hint }}</p>
        <p v-if="error" class="text-danger-text text-xs">{{ error }}</p>
    </div>
</template>
