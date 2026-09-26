<script setup lang="ts">
import {
    Bot,
    Mic,
    MicOff,
    Phone,
    PhoneOff,
    ShieldCheck,
    Video,
    VideoOff,
    X,
} from '@lucide/vue';
import { useScrollLock } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from '@/composables/useI18n';
import { useVoiceAgent } from '@/composables/useVoiceAgent';
import { tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { VoiceCallState } from '@/types';

/*
 * The live spoken role-play as a video call (RP-03, RP-11, RP-13, RESP-05,
 * ACC-03; spec 0004). A large AI-guest tile, a small self-view that is LOCAL
 * ONLY (the camera stream never leaves this component and is never
 * uploaded), live captions, mute, camera and hang-up. The microphone is
 * explained first and requested only when "Start call" is tapped; a denied
 * camera never blocks the call.
 */
type Props = {
    startUrl: string;
    title: string;
    guestRole?: string | null;
    thumbnail?: string | null;
    /** An admin test call (RP-13): shown as such, never an attempt. */
    preview?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    guestRole: null,
    thumbnail: null,
    preview: false,
});

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const call = useVoiceAgent({ startUrl: () => props.startUrl });
const { state, captions, errorMessage, elapsedSeconds, maxSeconds, muted } =
    call;

const selfVideo = ref<HTMLVideoElement | null>(null);
const cameraOn = ref(false);
let cameraStream: MediaStream | null = null;

const inCall = computed(() =>
    ['connecting', 'listening', 'thinking', 'speaking', 'ending'].includes(
        state.value,
    ),
);

const stateLabel: Record<VoiceCallState, string> = {
    idle: tk('Ready'),
    requesting: tk('Waiting for your microphone…'),
    denied: tk('Microphone blocked'),
    connecting: tk('Connecting…'),
    listening: tk('Listening'),
    thinking: tk('The guest is thinking…'),
    speaking: tk('The guest is speaking'),
    ending: tk('Ending the call…'),
    error: tk('Call failed'),
};

const dotTone = computed(() => {
    if (state.value === 'error' || state.value === 'denied') {
        return 'bg-danger';
    }

    return inCall.value ? 'bg-success' : 'bg-brand-300';
});

const recentCaptions = computed(() => captions.value.slice(-3));

function clock(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}

async function attachCamera(): Promise<void> {
    await nextTick();

    if (selfVideo.value !== null) {
        selfVideo.value.srcObject = cameraStream;
    }
}

async function startCamera(): Promise<void> {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });
        cameraOn.value = true;
        await attachCamera();
    } catch {
        cameraOn.value = false;
        toast.info(
            t('Camera unavailable. The call continues with audio only.'),
        );
    }
}

function stopCamera(): void {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    cameraOn.value = false;
}

async function toggleCamera(): Promise<void> {
    if (cameraOn.value) {
        stopCamera();

        return;
    }

    await startCamera();
}

/** Turn the self-view on only when the camera is already allowed. */
async function cameraIfAllowed(): Promise<void> {
    try {
        const status = await navigator.permissions.query({
            name: 'camera' as PermissionName,
        });

        if (status.state === 'granted') {
            await startCamera();
        }
    } catch {
        // Permissions API without "camera" (Firefox, older Safari): stay off.
    }
}

async function startCall(): Promise<void> {
    await call.start();

    if (inCall.value) {
        void cameraIfAllowed();
    }
}

function hangUp(): void {
    stopCamera();
    void call.end();
}

function close(): void {
    if (inCall.value) return;

    stopCamera();
    open.value = false;
}

// The call covers the screen: the page behind it must not scroll.
const pageLocked = useScrollLock(
    typeof document === 'undefined' ? null : document.body,
);

watch(
    open,
    (isOpen) => {
        pageLocked.value = isOpen;
    },
    { immediate: true },
);

watch(inCall, (active) => {
    if (!active) stopCamera();
});

onBeforeUnmount(stopCamera);
</script>

<template>
    <div
        v-if="open"
        class="bg-ink fixed inset-0 z-50 flex flex-col text-white"
        role="dialog"
        aria-modal="true"
        aria-labelledby="voice-call-title"
        data-test="voice-call"
    >
        <header
            class="flex min-h-14 items-center justify-between gap-3 px-4 py-2 md:px-6"
        >
            <div class="grid min-w-0 gap-0.5">
                <h2
                    id="voice-call-title"
                    class="font-heading truncate text-[16px] font-semibold text-white"
                >
                    {{ title }}
                </h2>
                <p
                    class="text-brand-100 flex items-center gap-2 text-[12.5px]"
                    aria-live="polite"
                >
                    <span
                        :class="cn('size-2 shrink-0 rounded-full', dotTone)"
                        aria-hidden="true"
                    />
                    {{ $t(stateLabel[state]) }}
                    <span
                        v-if="preview"
                        class="bg-ai-tint text-ai rounded-pill px-2 py-0.5 text-[11px] font-semibold"
                    >
                        {{ $t('Test call') }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    v-if="inCall"
                    class="rounded-pill bg-brand-900 px-3 py-1 font-mono text-[13px] tabular-nums"
                >
                    {{ clock(elapsedSeconds) }} / {{ clock(maxSeconds) }}
                </span>
                <button
                    v-if="!inCall"
                    type="button"
                    class="hover:bg-brand-900 focus-visible:ring-brand-300 grid size-11 place-items-center rounded-md focus-visible:ring-3 focus-visible:outline-none"
                    :aria-label="$t('Close')"
                    data-test="voice-call-close"
                    @click="close"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </div>
        </header>

        <!-- Before the call: explain the microphone, then ask on tap. -->
        <div
            v-if="!inCall"
            class="flex flex-1 items-center justify-center overflow-y-auto p-4"
        >
            <div
                class="bg-surface text-ink grid w-full max-w-md gap-4 rounded-xl p-6"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="bg-brand-100 text-brand-700 grid size-12 shrink-0 place-items-center rounded-xl"
                    >
                        <Phone class="size-6" aria-hidden="true" />
                    </span>
                    <div class="grid gap-0.5">
                        <p
                            class="font-heading text-ink-royal text-[18px] font-semibold"
                        >
                            {{ $t('Voice call with the guest') }}
                        </p>
                        <p class="text-ink-slate text-[13px]">
                            {{ $t('Speak naturally, as you would at work.') }}
                        </p>
                    </div>
                </div>

                <p
                    class="bg-brand-50 text-brand-800 flex gap-2 rounded-md p-3 text-[13.5px] leading-5"
                >
                    <ShieldCheck
                        class="text-brand-600 mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ $t(call.explanation) }}
                    {{
                        $t(
                            'Your camera, if you turn it on, is only shown to you and is never recorded.',
                        )
                    }}
                </p>

                <p
                    v-if="errorMessage"
                    class="bg-danger-tint text-danger-text rounded-md p-3 text-[13px]"
                    role="alert"
                >
                    {{ $t(errorMessage) }}
                </p>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        class="border-line text-brand-700 hover:bg-brand-50 bg-surface focus-visible:border-brand-600 focus-visible:ring-brand-600/15 inline-flex h-11 items-center justify-center rounded-md border px-5 text-[14px] font-semibold focus-visible:ring-3 focus-visible:outline-none"
                        @click="close"
                    >
                        {{ $t('Not now') }}
                    </button>
                    <button
                        type="button"
                        :disabled="state === 'requesting'"
                        class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-[14px] font-semibold text-white focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                        data-test="voice-call-start-button"
                        @click="startCall"
                    >
                        <Mic class="size-4" aria-hidden="true" />
                        {{
                            state === 'requesting'
                                ? $t('Allow the microphone…')
                                : errorMessage
                                  ? $t('Try again')
                                  : $t('Start call')
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- In the call. -->
        <template v-else>
            <main class="relative min-h-0 flex-1 px-3 pb-2 md:px-6">
                <div
                    class="bg-brand-900 relative flex h-full flex-col overflow-hidden rounded-xl"
                >
                    <!-- The guest tile shrinks; captions stay in flow below
                         it so they can never cover the guest's name. -->
                    <div
                        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 py-4"
                    >
                        <!-- The speaking ring is an outline, so the pulse (a
                             box-shadow) cannot wipe it out. -->
                        <div
                            :class="
                                cn(
                                    'rounded-pill grid size-32 place-items-center overflow-hidden md:size-40',
                                    'bg-brand-700 outline-brand-300 outline-offset-2 transition-[outline-width] duration-300 motion-reduce:transition-none',
                                    state === 'speaking'
                                        ? 'motion-safe:animate-pulse-ring outline-4'
                                        : 'outline-0',
                                )
                            "
                        >
                            <img
                                v-if="thumbnail"
                                :src="thumbnail"
                                alt=""
                                aria-hidden="true"
                                class="size-full object-cover"
                            />
                            <Bot v-else class="size-16" aria-hidden="true" />
                        </div>
                        <div class="grid max-w-lg gap-1 px-6 text-center">
                            <p class="font-heading text-[18px] font-semibold">
                                {{ $t('Guest (AI)') }}
                            </p>
                            <p
                                v-if="guestRole"
                                class="text-brand-100 line-clamp-2 text-[13px]"
                            >
                                {{ guestRole }}
                            </p>
                        </div>
                    </div>

                    <!-- Live captions -->
                    <ol
                        class="grid shrink-0 gap-1.5 px-3 pb-3 empty:hidden md:ps-6 md:pe-60 md:pb-5"
                        aria-live="polite"
                        :aria-label="$t('Live captions')"
                    >
                        <li
                            v-for="caption in recentCaptions"
                            :key="caption.seq"
                            :class="
                                cn(
                                    'w-fit max-w-full rounded-md px-3 py-1.5 text-[14px] leading-5',
                                    caption.role === 'agent'
                                        ? 'bg-surface text-ink'
                                        : 'bg-brand-600 ms-auto text-white md:ms-0',
                                )
                            "
                        >
                            <span class="font-semibold">
                                {{
                                    caption.role === 'agent'
                                        ? $t('Guest')
                                        : $t('You')
                                }}:
                            </span>
                            {{ caption.text }}
                        </li>
                    </ol>

                    <!-- Self view: local only, never uploaded. -->
                    <div
                        class="border-brand-700 bg-ink absolute end-3 top-3 grid h-32 w-24 place-items-center overflow-hidden rounded-lg border md:end-5 md:top-auto md:bottom-5 md:h-32 md:w-48"
                    >
                        <video
                            v-show="cameraOn"
                            ref="selfVideo"
                            autoplay
                            muted
                            playsinline
                            class="size-full -scale-x-100 object-cover"
                            :aria-label="
                                $t('Your camera (only visible to you)')
                            "
                        />
                        <VideoOff
                            v-if="!cameraOn"
                            class="text-brand-300 size-6"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </main>

            <footer
                class="flex items-center justify-center gap-4 px-4 pt-2 pb-[max(1rem,env(safe-area-inset-bottom))]"
            >
                <button
                    type="button"
                    :aria-pressed="muted"
                    :aria-label="
                        muted ? $t('Unmute microphone') : $t('Mute microphone')
                    "
                    :class="
                        cn(
                            'rounded-pill focus-visible:ring-brand-300 grid size-14 place-items-center focus-visible:ring-3 focus-visible:outline-none',
                            muted
                                ? 'bg-surface text-ink'
                                : 'bg-brand-800 hover:bg-brand-700 text-white',
                        )
                    "
                    data-test="voice-call-mute-button"
                    @click="call.toggleMute"
                >
                    <MicOff v-if="muted" class="size-6" aria-hidden="true" />
                    <Mic v-else class="size-6" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :aria-pressed="cameraOn"
                    :aria-label="
                        cameraOn ? $t('Turn camera off') : $t('Turn camera on')
                    "
                    :class="
                        cn(
                            'rounded-pill focus-visible:ring-brand-300 grid size-14 place-items-center focus-visible:ring-3 focus-visible:outline-none',
                            cameraOn
                                ? 'bg-brand-800 hover:bg-brand-700 text-white'
                                : 'bg-surface text-ink',
                        )
                    "
                    data-test="voice-call-camera-button"
                    @click="toggleCamera"
                >
                    <Video v-if="cameraOn" class="size-6" aria-hidden="true" />
                    <VideoOff v-else class="size-6" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :disabled="state === 'ending'"
                    class="border-danger text-danger bg-surface hover:bg-danger-tint focus-visible:ring-danger/30 rounded-pill inline-flex h-14 items-center gap-2 border-2 px-6 text-[15px] font-semibold focus-visible:ring-3 focus-visible:outline-none active:scale-[.97] disabled:opacity-60"
                    data-test="voice-call-end-button"
                    @click="hangUp"
                >
                    <PhoneOff class="size-5" aria-hidden="true" />
                    {{ $t('End call') }}
                </button>
            </footer>
        </template>
    </div>
</template>
