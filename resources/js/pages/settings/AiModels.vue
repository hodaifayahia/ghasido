<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { AudioLines } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import VoiceAgentSettingsDialog from '@/components/ai-scenarios/VoiceAgentSettingsDialog.vue';
import Heading from '@/components/Heading.vue';
import AiCapabilityCard from '@/components/settings/AiCapabilityCard.vue';
import AiModelCombobox from '@/components/settings/AiModelCombobox.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tk } from '@/lib/i18n';
import { check, edit, update } from '@/routes/ai-models';
import type {
    AiCheckCapability,
    AiChecks,
    AiModelSettingsPayload,
    AiModelValues,
    VoiceAgentSettingsPayload,
} from '@/types';

/*
 * Settings → AI models (API-04, PERF-04; Super Admin only): switch the
 * text, fast, image, speech and transcription models while testing, turn a
 * paid capability off, and test each one with a queued real call. Blank
 * fields follow .env. Keys stay in .env and never reach this page (SEC-03).
 */
type Props = {
    settings: AiModelSettingsPayload;
    checks: AiChecks;
    voiceAgent: { settings: VoiceAgentSettingsPayload };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: tk('AI models'), href: edit() }],
    },
});

const form = reactive<AiModelValues>({ ...props.settings.values });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const testing = ref<AiCheckCapability | null>(null);
const voiceSettingsOpen = ref(false);

const env = computed(() => props.settings.env);
const effective = computed(() => props.settings.effective);

watch(
    () => props.settings.values,
    (values) => Object.assign(form, values),
);

const expressivityValue = computed({
    get: (): string =>
        form.ttsExpressivity === null ? 'env' : String(form.ttsExpressivity),
    set: (value: string) => {
        form.ttsExpressivity = value === 'env' ? null : Number(value);
    },
});

function save(): void {
    saving.value = true;
    router.patch(update.url(), form, {
        preserveScroll: true,
        onError: (bag) => {
            errors.value = bag;
        },
        onSuccess: () => {
            errors.value = {};
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function runCheck(capability: AiCheckCapability): void {
    testing.value = capability;
    router.post(
        check.url(capability),
        {},
        {
            preserveScroll: true,
            only: ['checks'],
            onFinish: () => {
                testing.value = null;
            },
        },
    );
}

// Poll the stored check state while any test is queued or running
// (PERF-04: a real in-progress state, never a silent spinner).
const anyBusy = computed(() =>
    Object.values(props.checks).some(
        (state) => state?.status === 'pending' || state?.status === 'running',
    ),
);

const { start, stop } = usePoll(
    2000,
    { only: ['checks'] },
    { autoStart: false },
);

watch(
    anyBusy,
    (busy) => {
        if (busy) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

const expressivityOptions = [
    { value: '-2', label: tk('Very calm (−2)') },
    { value: '-1', label: tk('Calm (−1)') },
    { value: '0', label: tk('Neutral (0)') },
    { value: '1', label: tk('Lively (+1)') },
    { value: '2', label: tk('Very lively (+2)') },
];
</script>

<template>
    <Head :title="$t('AI models')" />

    <h1 class="sr-only">{{ $t('AI models') }}</h1>

    <div class="flex min-w-0 flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('AI models')"
            :description="
                $t(
                    'Switch the models behind lesson generation, images, audio and transcription. Blank fields follow the server\'s .env. Keys stay on the server.',
                )
            "
        />

        <form class="grid min-w-0 gap-4" @submit.prevent="save">
            <AiCapabilityCard
                v-model:mode="form.aiMode"
                :title="$t('Text generation')"
                :description="
                    $t(
                        'Lessons, tests, scenarios and evaluations use the main model; learner-facing role-play turns use the fast one.',
                    )
                "
                :env-provider="env.aiProvider"
                :real-default="settings.realDefaults.ai"
                :testing="testing"
                :checks="[
                    {
                        capability: 'ai',
                        label: $t('Main model'),
                        check: checks.ai,
                        effective: effective.ai,
                    },
                    {
                        capability: 'fast',
                        label: $t('Fast model'),
                        check: checks.fast,
                        effective: effective.fast,
                    },
                ]"
                @test="runCheck"
            >
                <AiModelCombobox
                    v-model="form.aiModel"
                    :label="$t('Main model')"
                    :presets="settings.presets.text"
                    :env-value="env.aiModel"
                    :error="errors.aiModel"
                />
                <AiModelCombobox
                    v-model="form.aiFastModel"
                    :label="$t('Fast model')"
                    :presets="settings.presets.text"
                    :env-value="env.aiFastModel || env.aiModel"
                    :error="errors.aiFastModel"
                    :hint="$t('Blank uses AI_FAST_MODEL, then the main model.')"
                />
            </AiCapabilityCard>

            <AiCapabilityCard
                v-model:mode="form.imageMode"
                :title="$t('Images')"
                :description="
                    $t('Lesson covers, situations and vocabulary pictures.')
                "
                :env-provider="env.imageProvider"
                :real-default="settings.realDefaults.image"
                :testing="testing"
                :checks="[
                    {
                        capability: 'image',
                        label: $t('Image model'),
                        check: checks.image,
                        effective: effective.image,
                    },
                ]"
                @test="runCheck"
            >
                <AiModelCombobox
                    v-model="form.imageModel"
                    :label="$t('Image model')"
                    :presets="settings.presets.image"
                    :env-value="env.imageModel"
                    :error="errors.imageModel"
                />
                <div class="grid gap-4 md:grid-cols-2">
                    <AiModelCombobox
                        v-model="form.imageSizeLandscape"
                        :label="$t('Landscape size')"
                        :presets="settings.presets.imageLandscape"
                        :env-value="env.imageSizeLandscape"
                        :error="errors.imageSizeLandscape"
                        :hint="$t('Width*height, e.g. 1664*928.')"
                    />
                    <AiModelCombobox
                        v-model="form.imageSizeSquare"
                        :label="$t('Square size')"
                        :presets="settings.presets.imageSquare"
                        :env-value="env.imageSizeSquare"
                        :error="errors.imageSizeSquare"
                        :hint="$t('Used by the Test button too.')"
                    />
                </div>
            </AiCapabilityCard>

            <AiCapabilityCard
                v-model:mode="form.ttsMode"
                :title="$t('Speech (lesson audio)')"
                :description="
                    $t(
                        'The stored normal and slow audio for every sentence. Changing the voice makes new clips; regenerate old ones from the lesson screen.',
                    )
                "
                :env-provider="env.ttsProvider"
                :real-default="settings.realDefaults.tts"
                :testing="testing"
                :checks="[
                    {
                        capability: 'tts',
                        label: $t('Voice'),
                        check: checks.tts,
                        effective: effective.tts,
                    },
                ]"
                @test="runCheck"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <AiModelCombobox
                        v-model="form.ttsVoice"
                        :label="$t('Voice / model')"
                        :presets="settings.presets.ttsVoices"
                        :env-value="env.ttsVoice"
                        :error="errors.ttsVoice"
                    />
                    <div class="grid min-w-0 gap-1.5">
                        <Label
                            for="tts-expressivity"
                            class="text-brand-900 text-xs font-semibold"
                        >
                            {{ $t('Expressivity') }}
                        </Label>
                        <Select v-model="expressivityValue">
                            <SelectTrigger
                                id="tts-expressivity"
                                class="border-line h-11 w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="env">
                                    {{
                                        $t('Follow .env (:provider)', {
                                            provider: env.ttsExpressivity ?? '',
                                        })
                                    }}
                                </SelectItem>
                                <SelectItem
                                    v-for="option in expressivityOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ $t(option.label) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.ttsExpressivity"
                            class="text-danger-text text-xs"
                        >
                            {{ errors.ttsExpressivity }}
                        </p>
                    </div>
                </div>
            </AiCapabilityCard>

            <AiCapabilityCard
                v-model:mode="form.sttMode"
                :title="$t('Transcription')"
                :description="
                    $t(
                        'Turns recorded spoken answers into text before the AI scores them. The Test button transcribes a spoken test sentence.',
                    )
                "
                :env-provider="env.sttProvider"
                :real-default="settings.realDefaults.stt"
                :testing="testing"
                :checks="[
                    {
                        capability: 'stt',
                        label: $t('Transcription model'),
                        check: checks.stt,
                        effective: effective.stt,
                    },
                ]"
                @test="runCheck"
            >
                <AiModelCombobox
                    v-model="form.sttModel"
                    :label="$t('Transcription model')"
                    :presets="settings.presets.stt"
                    :env-value="env.sttModel"
                    :error="errors.sttModel"
                />
            </AiCapabilityCard>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="saving"
                    data-test="update-ai-models-button"
                >
                    {{ $t('Save') }}
                </Button>
                <p class="text-ink-muted text-xs">
                    {{
                        $t(
                            'Saved models apply to the next job; Test uses what is saved.',
                        )
                    }}
                </p>
            </div>
        </form>

        <section
            class="border-line bg-surface shadow-card flex min-w-0 flex-col gap-3 rounded-lg border p-5 md:flex-row md:items-center md:justify-between"
        >
            <div class="grid gap-0.5">
                <h3 class="font-heading text-brand-800 text-base font-semibold">
                    {{ $t('Live voice call') }}
                </h3>
                <p class="text-ink-muted text-sm">
                    {{
                        $t(
                            'How the spoken role-play agent listens, thinks and speaks.',
                        )
                    }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 shrink-0"
                data-test="open-voice-agent-settings-button"
                @click="voiceSettingsOpen = true"
            >
                <AudioLines class="size-4" aria-hidden="true" />
                {{ $t('Voice call settings') }}
            </Button>
        </section>

        <VoiceAgentSettingsDialog
            v-model:open="voiceSettingsOpen"
            :settings="voiceAgent.settings"
        />
    </div>
</template>
