<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Mic2, Save, Sparkles, Volume2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { generateAll as generateAllAudio } from '@/routes/audio';
import { update } from '@/routes/tts/settings';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { cn } from '@/lib/utils';
import type { TtsSettings, TtsVoice } from '@/types';

type Props = {
    settings: TtsSettings;
    compact?: boolean;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { compact: false });

const voice = ref(props.settings.voice);
const expressivity = ref(props.settings.expressivity);
const gender = ref('');
const accent = ref('');
const age = ref('');
const useCase = ref('');
const characteristic = ref('');
const saving = ref(false);
const generatingAll = ref(false);
const { can } = useCan();
const editable = computed(() => can('scenarios.manage'));

watch(
    () => props.settings,
    (settings) => {
        voice.value = settings.voice;
        expressivity.value = settings.expressivity;
    },
);

const selected = computed<TtsVoice | undefined>(() =>
    props.settings.voices.find((item) => item.model === voice.value),
);

const settingsDirty = computed(
    () =>
        voice.value !== props.settings.voice ||
        expressivity.value !== props.settings.expressivity,
);

const filteredVoices = computed(() =>
    props.settings.voices.filter((item) => {
        if (gender.value !== '' && item.gender !== gender.value) return false;
        if (accent.value !== '' && item.accent !== accent.value) return false;
        if (age.value !== '' && item.age !== age.value) return false;
        if (useCase.value !== '' && !item.use_cases.includes(useCase.value)) {
            return false;
        }
        if (
            characteristic.value !== '' &&
            !item.characteristics.includes(characteristic.value)
        ) {
            return false;
        }

        return true;
    }),
);

const statusLabel = computed(() =>
    props.settings.provider === 'deepgram'
        ? props.settings.apiConfigured
            ? 'Deepgram connected'
            : 'Deepgram key missing'
        : `Provider: ${props.settings.provider}`,
);

const title = computed(() =>
    props.compact ? 'AI Role-play Voice' : 'Deepgram Voice Studio',
);

const subtitle = computed(() =>
    props.compact
        ? `${statusLabel.value} · guest replies and lesson audio`
        : `${statusLabel.value} · stored lesson audio`,
);

function save(): void {
    saving.value = true;
    router.post(
        update.url(),
        { voice: voice.value, expressivity: expressivity.value },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function selectVoice(model: string): void {
    voice.value = model;
}

function generateAllLessons(): void {
    if (!editable.value || settingsDirty.value) {
        return;
    }

    generatingAll.value = true;
    router.post(
        generateAllAudio.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                generatingAll.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        :class="
            cn(
                'border-line bg-surface shadow-card min-w-0 rounded-lg border p-3',
                props.class,
            )
        "
        data-test="tts-voice-studio"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span
                        class="bg-ai-tint text-ai grid size-8 shrink-0 place-items-center rounded-xl"
                    >
                        <Mic2 class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <h2
                            class="font-heading text-brand-800 truncate text-base font-semibold"
                        >
                            {{ title }}
                        </h2>
                        <p class="text-ink-slate text-[11px]">
                            {{ subtitle }}
                        </p>
                    </div>
                </div>
            </div>
            <span
                class="bg-success-tint text-success-text rounded-pill shrink-0 px-2 py-1 text-[10px] font-semibold"
            >
                {{ props.settings.voices.length }} voices
            </span>
        </div>

        <div
            class="mt-3 grid gap-2"
            :class="compact ? 'sm:grid-cols-2' : 'md:grid-cols-3'"
        >
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Gender</span
                >
                <select
                    v-model="gender"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-9 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option value="">All genders</option>
                    <option
                        v-for="item in props.settings.filters.genders"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Accent</span
                >
                <select
                    v-model="accent"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-9 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option value="">All accents</option>
                    <option
                        v-for="item in props.settings.filters.accents"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Age</span
                >
                <select
                    v-model="age"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-9 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option value="">All ages</option>
                    <option
                        v-for="item in props.settings.filters.ages"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Use case</span
                >
                <select
                    v-model="useCase"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-9 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option value="">All use cases</option>
                    <option
                        v-for="item in props.settings.filters.use_cases"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Character</span
                >
                <select
                    v-model="characteristic"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-9 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option value="">All characteristics</option>
                    <option
                        v-for="item in props.settings.filters.characteristics"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
            </label>
        </div>

        <div
            class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
        >
            <label class="grid min-w-0 gap-1">
                <span class="text-brand-900 text-[11px] font-semibold"
                    >Selected voice</span
                >
                <select
                    v-model="voice"
                    :disabled="!editable"
                    class="border-line text-ink bg-surface h-10 w-full min-w-0 rounded-md border px-2 text-[12px]"
                >
                    <option
                        v-for="item in filteredVoices"
                        :key="item.model"
                        :value="item.model"
                    >
                        {{ item.name }} · {{ item.gender }} · {{ item.accent }}
                    </option>
                </select>
            </label>
            <Button
                v-if="editable"
                type="button"
                :disabled="saving || selected === undefined"
                class="bg-brand-600 hover:bg-brand-700 shadow-btn h-10 gap-1.5 rounded-md px-3 text-[12px] font-semibold text-white"
                @click="save"
            >
                <Save class="size-3.5" aria-hidden="true" />
                {{ saving ? 'Saving…' : 'Save voice' }}
            </Button>
        </div>

        <div
            v-if="selected"
            class="border-line bg-brand-50/45 mt-3 rounded-md border px-3 py-2"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-brand-900 text-[12px] font-semibold">
                        {{ selected.name }}
                    </p>
                    <p class="text-ink-slate text-[11px]">
                        {{ selected.gender }} · {{ selected.accent }} ·
                        {{ selected.age }}
                    </p>
                </div>
                <Sparkles class="text-ai size-4 shrink-0" aria-hidden="true" />
            </div>
            <div class="mt-2 flex flex-wrap gap-1">
                <span
                    v-for="tag in selected.characteristics"
                    :key="tag"
                    class="bg-surface text-ink-muted rounded-pill px-2 py-0.5 text-[10px]"
                    >{{ tag }}</span
                >
            </div>
        </div>

        <div class="mt-3">
            <div class="flex items-center justify-between gap-3">
                <label
                    class="text-brand-900 text-[11px] font-semibold"
                    for="tts-expressivity"
                    >Expressivity</label
                >
                <span class="text-ink-slate text-[11px]"
                    >{{
                        expressivity === 0
                            ? 'Default'
                            : expressivity < 0
                              ? 'Calm'
                              : 'Animated'
                    }}
                    ({{ expressivity }})</span
                >
            </div>
            <input
                id="tts-expressivity"
                v-model.number="expressivity"
                :disabled="!editable"
                type="range"
                min="-2"
                max="2"
                step="1"
                class="accent-brand-600 mt-1 w-full"
            />
            <div class="text-ink-faint flex justify-between text-[10px]">
                <span>Calm</span><span>Production default</span
                ><span>Animated</span>
            </div>
        </div>

        <div
            v-if="editable"
            class="border-line bg-brand-50/45 mt-3 flex flex-col gap-2 rounded-md border px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
            data-test="tts-generate-all-lessons"
        >
            <div class="min-w-0">
                <p class="text-brand-900 text-[11px] font-semibold">
                    Generate audio for all lessons
                </p>
                <p class="text-ink-slate text-[10.5px] leading-4">
                    Saves Normal and Slow clips for every existing lesson using
                    the saved voice and expressivity.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="
                    generatingAll ||
                    saving ||
                    selected === undefined ||
                    settingsDirty
                "
                class="border-brand-200 text-brand-700 hover:bg-brand-100/70 h-9 shrink-0 gap-1.5 rounded-md px-3 text-[11px] font-semibold shadow-none"
                data-test="generate-all-lesson-audio"
                @click="generateAllLessons"
            >
                <Volume2 class="size-3.5" aria-hidden="true" />
                {{
                    settingsDirty
                        ? 'Save voice first'
                        : generatingAll
                          ? 'Queueing…'
                          : 'Generate all audio'
                }}
            </Button>
        </div>

        <div v-if="!compact" class="mt-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-brand-900 text-[11px] font-semibold">
                    Voice catalog
                </p>
                <span class="text-ink-faint text-[10px]"
                    >{{ filteredVoices.length }} matching</span
                >
            </div>
            <div
                class="grid max-h-56 gap-2 overflow-y-auto pr-1 sm:grid-cols-2"
            >
                <button
                    v-for="item in filteredVoices"
                    :key="item.model"
                    type="button"
                    :class="
                        cn(
                            'border-line flex items-start gap-2 rounded-md border p-2 text-start',
                            item.model === voice
                                ? 'border-brand-600 bg-brand-100/60'
                                : 'bg-surface hover:bg-brand-50',
                        )
                    "
                    :disabled="!editable"
                    @click="selectVoice(item.model)"
                >
                    <span
                        :class="
                            cn(
                                'grid size-6 shrink-0 place-items-center rounded-md',
                                item.model === voice
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-brand-100 text-brand-700',
                            )
                        "
                    >
                        <Check
                            v-if="item.model === voice"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        <Mic2 v-else class="size-3.5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="text-brand-900 block truncate text-[11px] font-semibold"
                            >{{ item.name }}</span
                        >
                        <span class="text-ink-faint block truncate text-[10px]"
                            >{{ item.gender }} · {{ item.accent }} ·
                            {{ item.age }}</span
                        >
                    </span>
                </button>
            </div>
        </div>

        <p class="text-ink-faint mt-3 text-[10.5px] leading-4">
            Normal and Slow clips are generated and stored server-side. Changing
            the voice does not delete old audio; regenerate a lesson's missing
            clips to render it with this voice.
        </p>
    </section>
</template>
