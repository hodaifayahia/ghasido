<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AudioLines, Brain, Ear, Info, Settings2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import LessonsModal from '@/components/lessons/LessonsModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    VoiceAgentScenario,
    VoiceAgentSettingsPayload,
    VoiceAgentValues,
} from '@/types';

/*
 * Every live voice-call control the Super Admin can change (RP-03, RP-04,
 * API-04, ADM-02; spec 0004): how the agent listens, which voice speaks,
 * which model thinks, the greeting and extra prompt, and the call limits.
 * With a scenario open, its own voice and greeting can override the global
 * ones. No key is ever shown or accepted here (API-02, SEC-03).
 */
type Props = {
    settings: VoiceAgentSettingsPayload;
    scenario?: VoiceAgentScenario | null;
};

const props = withDefaults(defineProps<Props>(), { scenario: null });

const open = defineModel<boolean>('open', { required: true });

const GLOBAL = 'global';

const form = reactive<VoiceAgentValues>({ ...props.settings.values });
const keyterms = ref('');
const scenarioVoice = ref(GLOBAL);
const scenarioVoiceId = ref('');
const scenarioGreeting = ref('');
const errors = ref<Record<string, string>>({});
const saving = ref(false);

function reset(): void {
    Object.assign(form, props.settings.values);
    keyterms.value = props.settings.values.keyterms.join(', ');
    scenarioVoice.value = props.scenario?.overrides.speakModel ?? GLOBAL;
    scenarioVoiceId.value = props.scenario?.overrides.elevenVoiceId ?? '';
    scenarioGreeting.value = props.scenario?.overrides.greeting ?? '';
    errors.value = {};
}

watch(open, (value) => {
    if (value) reset();
});

const thinkLabels: Record<string, string> = {
    open_ai: 'OpenAI (billed by Deepgram)',
    anthropic: 'Anthropic (billed by Deepgram)',
    google: 'Google (billed by Deepgram)',
};

const modelSuggestions = computed(
    () => props.settings.options.thinkModelSuggestions[form.thinkProvider],
);

const fieldClass = 'border-line h-11 w-full text-sm';
const labelClass = 'text-brand-900 text-xs font-semibold';
const textareaClass =
    'border-line bg-surface text-ink placeholder:text-ink-faint focus-visible:border-brand-600 focus-visible:ring-brand-600/15 w-full rounded-sm border px-3 py-2 text-sm focus-visible:ring-3 focus-visible:outline-none';

function saveGlobal(): void {
    saving.value = true;
    router.patch(
        props.settings.saveUrl,
        {
            ...form,
            keyterms: keyterms.value
                .split(',')
                .map((term) => term.trim())
                .filter((term) => term !== ''),
        },
        {
            preserveScroll: true,
            onError: (bag) => {
                errors.value = bag;
            },
            onSuccess: () => {
                if (props.scenario === null) open.value = false;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function saveScenario(): void {
    if (props.scenario === null) return;

    router.patch(
        props.scenario.saveUrl,
        {
            speakModel:
                scenarioVoice.value === GLOBAL ? null : scenarioVoice.value,
            elevenVoiceId: scenarioVoiceId.value.trim() || null,
            greeting: scenarioGreeting.value.trim() || null,
        },
        {
            preserveScroll: true,
            onError: (bag) => {
                errors.value = bag;
            },
        },
    );
}
</script>

<template>
    <LessonsModal
        v-model:open="open"
        title="Voice call settings"
        description="Control how the live AI guest listens, thinks and speaks in every voice call."
        size="lg"
    >
        <form class="mt-5 grid gap-5" @submit.prevent="saveGlobal">
            <p
                v-if="!settings.apiConfigured"
                class="bg-warning-tint text-warning-text flex gap-2 rounded-md p-3 text-xs leading-5"
                role="status"
            >
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                No Deepgram key is set on the server (DEEPGRAM_API_KEY), so
                learners will not be offered voice calls yet.
            </p>

            <!-- Listening -->
            <fieldset class="border-line grid gap-3 rounded-lg border p-4">
                <legend
                    class="text-brand-900 flex items-center gap-2 px-1 text-sm font-semibold"
                >
                    <Ear class="text-brand-600 size-4" aria-hidden="true" />
                    Listening
                </legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">Speech model</Label>
                        <Select v-model="form.listenModel">
                            <SelectTrigger :class="fieldClass">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="model in settings.options
                                        .listenModels"
                                    :key="model"
                                    :value="model"
                                >
                                    {{ model }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="va-eot" :class="labelClass">
                            End-of-turn confidence ({{ form.eotThreshold }})
                        </Label>
                        <input
                            id="va-eot"
                            v-model.number="form.eotThreshold"
                            type="range"
                            min="0.5"
                            max="0.9"
                            step="0.05"
                            class="accent-brand-600 h-11 w-full"
                            :disabled="form.listenModel !== 'flux-general-en'"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="va-keyterms" :class="labelClass">
                        Key terms (comma separated)
                    </Label>
                    <Input
                        id="va-keyterms"
                        v-model="keyterms"
                        placeholder="check-in, reservation, passport"
                        :class="fieldClass"
                    />
                    <p class="text-ink-slate text-[11.5px]">
                        Words the listener should expect, like room types or the
                        hotel's name.
                    </p>
                </div>
            </fieldset>

            <!-- Voice -->
            <fieldset class="border-line grid gap-3 rounded-lg border p-4">
                <legend
                    class="text-brand-900 flex items-center gap-2 px-1 text-sm font-semibold"
                >
                    <AudioLines
                        class="text-brand-600 size-4"
                        aria-hidden="true"
                    />
                    Guest voice
                </legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">Voice provider</Label>
                        <Select v-model="form.speakProvider">
                            <SelectTrigger :class="fieldClass">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="deepgram">
                                    Deepgram Aura-2
                                </SelectItem>
                                <SelectItem value="eleven_labs">
                                    ElevenLabs
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div
                        v-if="form.speakProvider === 'deepgram'"
                        class="grid gap-1.5"
                    >
                        <Label :class="labelClass">Voice</Label>
                        <Select v-model="form.speakModel">
                            <SelectTrigger
                                :class="fieldClass"
                                data-test="voice-agent-voice-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="voice in settings.options.voices"
                                    :key="voice.value"
                                    :value="voice.value"
                                >
                                    {{ voice.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div
                    v-if="form.speakProvider === 'eleven_labs'"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <div class="grid gap-1.5">
                        <Label for="va-eleven-model" :class="labelClass">
                            ElevenLabs model id
                        </Label>
                        <Input
                            id="va-eleven-model"
                            v-model="form.elevenModelId"
                            :class="fieldClass"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="va-eleven-voice" :class="labelClass">
                            ElevenLabs voice id
                        </Label>
                        <Input
                            id="va-eleven-voice"
                            v-model="form.elevenVoiceId"
                            :class="fieldClass"
                        />
                    </div>
                </div>
            </fieldset>

            <!-- Brain -->
            <fieldset class="border-line grid gap-3 rounded-lg border p-4">
                <legend
                    class="text-brand-900 flex items-center gap-2 px-1 text-sm font-semibold"
                >
                    <Brain class="text-brand-600 size-4" aria-hidden="true" />
                    Guest brain
                </legend>
                <div class="grid gap-1.5">
                    <Label :class="labelClass">Language model</Label>
                    <Select v-model="form.thinkMode">
                        <SelectTrigger :class="fieldClass">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="managed">
                                Deepgram-managed model
                            </SelectItem>
                            <SelectItem
                                value="qwen_proxy"
                                :disabled="!settings.qwenProxyAvailable"
                            >
                                Qwen ({{
                                    settings.qwenModel ?? 'server model'
                                }}) through Guesvia
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="!settings.qwenProxyAvailable"
                        class="text-ink-slate text-[11.5px]"
                    >
                        Qwen is unavailable: {{ settings.qwenProxyReason }}
                    </p>
                    <p
                        v-if="errors.thinkMode"
                        class="text-danger-text text-[12px]"
                    >
                        {{ errors.thinkMode }}
                    </p>
                </div>
                <div
                    v-if="form.thinkMode === 'managed'"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">Provider</Label>
                        <Select v-model="form.thinkProvider">
                            <SelectTrigger :class="fieldClass">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="provider in settings.options
                                        .thinkProviders"
                                    :key="provider"
                                    :value="provider"
                                >
                                    {{ thinkLabels[provider] ?? provider }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="va-think-model" :class="labelClass">
                            Model
                        </Label>
                        <Input
                            id="va-think-model"
                            v-model="form.thinkModel"
                            list="va-think-models"
                            :class="fieldClass"
                        />
                        <datalist id="va-think-models">
                            <option
                                v-for="model in modelSuggestions"
                                :key="model"
                                :value="model"
                            />
                        </datalist>
                        <p
                            v-if="errors.thinkModel"
                            class="text-danger-text text-[12px]"
                        >
                            {{ errors.thinkModel }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="va-temperature" :class="labelClass">
                        Creativity ({{ form.temperature }})
                    </Label>
                    <input
                        id="va-temperature"
                        v-model.number="form.temperature"
                        type="range"
                        min="0"
                        max="1.5"
                        step="0.1"
                        class="accent-brand-600 h-11 w-full"
                    />
                </div>
            </fieldset>

            <!-- Conversation -->
            <fieldset class="border-line grid gap-3 rounded-lg border p-4">
                <legend
                    class="text-brand-900 flex items-center gap-2 px-1 text-sm font-semibold"
                >
                    <Settings2
                        class="text-brand-600 size-4"
                        aria-hidden="true"
                    />
                    Conversation
                </legend>
                <div class="grid gap-1.5">
                    <Label for="va-greeting" :class="labelClass">
                        Default greeting (the guest's first line)
                    </Label>
                    <Input
                        id="va-greeting"
                        v-model="form.greeting"
                        maxlength="300"
                        :class="fieldClass"
                    />
                    <p
                        v-if="errors.greeting"
                        class="text-danger-text text-[12px]"
                    >
                        {{ errors.greeting }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="va-prompt" :class="labelClass">
                        Extra instructions for voice calls
                    </Label>
                    <textarea
                        id="va-prompt"
                        v-model="form.prompt"
                        rows="3"
                        maxlength="3000"
                        :class="textareaClass"
                        placeholder="e.g. Speak a little slower and use simple words."
                    />
                    <p class="text-ink-slate text-[11.5px]">
                        Added after the scenario brief and the AI Instructions
                        tab. The role-play guard always applies.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="va-max" :class="labelClass">
                            Max call length (seconds)
                        </Label>
                        <Input
                            id="va-max"
                            v-model.number="form.maxCallSeconds"
                            type="number"
                            min="60"
                            max="1800"
                            step="30"
                            :class="fieldClass"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">Mic sample rate</Label>
                        <Select v-model="form.inputSampleRate">
                            <SelectTrigger :class="fieldClass">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="rate in settings.options
                                        .inputSampleRates"
                                    :key="rate"
                                    :value="rate"
                                >
                                    {{ rate }} Hz
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label :class="labelClass">Voice sample rate</Label>
                        <Select v-model="form.outputSampleRate">
                            <SelectTrigger :class="fieldClass">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="rate in settings.options
                                        .outputSampleRates"
                                    :key="rate"
                                    :value="rate"
                                >
                                    {{ rate }} Hz
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="border-line text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-xs font-semibold sm:h-10"
                    @click="open = false"
                >
                    Close
                </Button>
                <Button
                    type="submit"
                    :disabled="saving"
                    class="bg-brand-600 shadow-btn hover:bg-brand-700 h-11 rounded-md px-4 text-xs font-semibold text-white sm:h-10"
                    data-test="save-voice-agent-settings-button"
                >
                    Save voice call settings
                </Button>
            </div>
        </form>

        <!-- This scenario only -->
        <form
            v-if="scenario"
            class="border-line bg-brand-50/35 mt-5 grid gap-3 rounded-lg border p-4"
            @submit.prevent="saveScenario"
        >
            <p class="text-brand-900 text-sm font-semibold">
                Only for “{{ scenario.title }}”
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label :class="labelClass">Aura-2 voice</Label>
                    <Select v-model="scenarioVoice">
                        <SelectTrigger :class="fieldClass">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="GLOBAL">
                                Use the global voice
                            </SelectItem>
                            <SelectItem
                                v-for="voice in settings.options.voices"
                                :key="voice.value"
                                :value="voice.value"
                            >
                                {{ voice.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="va-scenario-eleven" :class="labelClass">
                        ElevenLabs voice id (optional)
                    </Label>
                    <Input
                        id="va-scenario-eleven"
                        v-model="scenarioVoiceId"
                        :class="fieldClass"
                    />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="va-scenario-greeting" :class="labelClass">
                    Greeting (leave empty for the default)
                </Label>
                <Input
                    id="va-scenario-greeting"
                    v-model="scenarioGreeting"
                    maxlength="300"
                    :placeholder="form.greeting"
                    :class="fieldClass"
                />
            </div>
            <div class="flex justify-end">
                <Button
                    type="submit"
                    variant="outline"
                    class="border-brand-600 text-brand-700 hover:bg-brand-50 h-11 rounded-md px-4 text-xs font-semibold sm:h-10"
                    data-test="save-scenario-voice-button"
                >
                    Save scenario voice
                </Button>
            </div>
        </form>
    </LessonsModal>
</template>
