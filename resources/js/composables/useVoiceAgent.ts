import { router } from '@inertiajs/vue3';
import { tryOnScopeDispose } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { t, tk } from '@/lib/i18n';
import type {
    VoiceCallCaption,
    VoiceCallStartResponse,
    VoiceCallState,
} from '@/types';

export type UseVoiceAgentOptions = {
    /** POST here to open the attempt and get a short-lived session. */
    startUrl: () => string;
};

export type UseVoiceAgentReturn = {
    state: Ref<VoiceCallState>;
    captions: Ref<VoiceCallCaption[]>;
    errorMessage: Ref<string | null>;
    elapsedSeconds: Ref<number>;
    maxSeconds: Ref<number>;
    muted: Ref<boolean>;
    /** Explained to the learner before the browser prompt (RESP-05). */
    explanation: string;
    start: () => Promise<void>;
    end: () => Promise<void>;
    toggleMute: () => void;
};

type AgentEvent = {
    type?: string;
    role?: string;
    content?: string;
    description?: string;
    message?: string;
};

const KEEP_ALIVE_MS = 8000;

/*
 * Converts the microphone to linear16 PCM at the call's input rate inside
 * an AudioWorklet (no dependency). The context runs at the device rate; the
 * worklet resamples by linear interpolation and posts ~40 ms chunks.
 */
const WORKLET_SOURCE = `
class GuesviaPcm16Writer extends AudioWorkletProcessor {
    constructor(options) {
        super();
        this.target = options.processorOptions.targetRate;
        this.ratio = sampleRate / this.target;
        this.t = 0;
        this.size = Math.max(160, Math.round(this.target * 0.04));
        this.out = new Int16Array(this.size);
        this.len = 0;
    }

    process(inputs) {
        const input = inputs[0] && inputs[0][0];
        if (!input) return true;
        let t = this.t;
        while (t < input.length) {
            const i = Math.floor(t);
            const f = t - i;
            const a = input[i];
            const b = i + 1 < input.length ? input[i + 1] : a;
            const s = Math.max(-1, Math.min(1, a + (b - a) * f));
            this.out[this.len++] = s < 0 ? s * 0x8000 : s * 0x7fff;
            if (this.len === this.size) {
                this.port.postMessage(this.out.buffer, [this.out.buffer]);
                this.out = new Int16Array(this.size);
                this.len = 0;
            }
            t += this.ratio;
        }
        this.t = t - input.length;
        return true;
    }
}
registerProcessor('guesvia-pcm16-writer', GuesviaPcm16Writer);
`;

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson(url: string, body: unknown): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(body),
    });
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

/*
 * One live spoken role-play over the Deepgram Voice Agent (RP-03, RP-11,
 * RESP-05; spec 0004).
 *
 * The server opens the attempt and builds the Settings message (RP-04);
 * this composable only carries audio. Every caption is posted to the server
 * as it happens, with a sequence number so a retry can never duplicate it
 * (PROG-03, PROG-04). The microphone is requested on an explicit tap, never
 * on mount; nothing but the text transcript leaves the browser for our
 * server, and the camera is never touched here (spec 0004 decision 3).
 */
export function useVoiceAgent(
    options: UseVoiceAgentOptions,
): UseVoiceAgentReturn {
    const state = ref<VoiceCallState>('idle');
    const captions = ref<VoiceCallCaption[]>([]);
    const errorMessage = ref<string | null>(null);
    const elapsedSeconds = ref(0);
    const maxSeconds = ref(300);
    const muted = ref(false);

    let socket: WebSocket | null = null;
    let micStream: MediaStream | null = null;
    let context: AudioContext | null = null;
    let worklet: AudioWorkletNode | null = null;
    let workletUrl: string | null = null;
    let keepAlive: ReturnType<typeof setInterval> | null = null;
    let clock: ReturnType<typeof setInterval> | null = null;
    let settingsApplied = false;
    let playhead = 0;
    let outputRate = 24000;
    let seq = 0;
    let turnUrl = '';
    let endUrl = '';
    let ended = false;
    const playing = new Set<AudioBufferSourceNode>();
    const pendingTurns = new Set<Promise<void>>();

    function stopPlayback(): void {
        playing.forEach((source) => {
            try {
                source.stop();
            } catch {
                // Already stopped.
            }
        });
        playing.clear();
        playhead = context?.currentTime ?? 0;
    }

    function playPcm(data: ArrayBuffer): void {
        if (context === null || data.byteLength < 2) return;

        const samples = new Int16Array(
            data,
            0,
            Math.floor(data.byteLength / 2),
        );
        const buffer = context.createBuffer(1, samples.length, outputRate);
        const channel = buffer.getChannelData(0);

        for (let i = 0; i < samples.length; i++) {
            channel[i] = samples[i] / 0x8000;
        }

        const source = context.createBufferSource();
        source.buffer = buffer;
        source.connect(context.destination);
        playhead = Math.max(playhead, context.currentTime);
        source.start(playhead);
        playhead += buffer.duration;
        playing.add(source);
        source.onended = () => {
            playing.delete(source);

            if (playing.size === 0 && state.value === 'speaking') {
                state.value = 'listening';
            }
        };
    }

    async function sendTurn(caption: VoiceCallCaption): Promise<void> {
        for (let attempt = 0; attempt < 4; attempt++) {
            try {
                const response = await postJson(turnUrl, {
                    seq: caption.seq,
                    role: caption.role,
                    content: caption.text,
                });

                if (response.ok) {
                    const result = (await response.json()) as {
                        limitReached?: boolean;
                    };

                    if (result.limitReached === true && !ended) {
                        toast.warning(
                            t(
                                'You have reached today’s AI practice limit. The call will end now.',
                            ),
                        );
                        void end();
                    }

                    return;
                }

                if (response.status < 500 && response.status !== 429) {
                    return;
                }
            } catch {
                // Offline for a moment: retry below.
            }

            await sleep(500 * 2 ** attempt);
        }
    }

    function recordCaption(role: 'user' | 'agent', text: string): void {
        const clean = text.trim();
        if (clean === '') return;

        const caption: VoiceCallCaption = { seq: seq++, role, text: clean };
        captions.value = [...captions.value, caption];

        const pending = sendTurn(caption);
        pendingTurns.add(pending);
        void pending.finally(() => pendingTurns.delete(pending));
    }

    function handleEvent(event: AgentEvent): void {
        switch (event.type) {
            case 'SettingsApplied':
                settingsApplied = true;
                state.value = 'listening';
                break;
            case 'ConversationText':
                recordCaption(
                    event.role === 'user' ? 'user' : 'agent',
                    event.content ?? '',
                );
                break;
            case 'UserStartedSpeaking':
                // Barge-in: the guest stops talking the moment you speak.
                stopPlayback();
                state.value = 'listening';
                break;
            case 'AgentThinking':
                state.value = 'thinking';
                break;
            case 'AgentStartedSpeaking':
                state.value = 'speaking';
                break;
            case 'AgentAudioDone':
                if (playing.size === 0) state.value = 'listening';
                break;
            case 'Warning':
                toast.warning(
                    event.description ??
                        event.message ??
                        t('Voice call warning.'),
                );
                break;
            case 'Error':
                errorMessage.value =
                    event.description ??
                    event.message ??
                    tk('The voice call hit a problem.');
                toast.error(t(errorMessage.value));
                break;
            default:
                break;
        }
    }

    async function startMicrophone(targetRate: number): Promise<void> {
        if (context === null || micStream === null) return;

        workletUrl = URL.createObjectURL(
            new Blob([WORKLET_SOURCE], { type: 'application/javascript' }),
        );
        await context.audioWorklet.addModule(workletUrl);

        const source = context.createMediaStreamSource(micStream);
        worklet = new AudioWorkletNode(context, 'guesvia-pcm16-writer', {
            processorOptions: { targetRate },
        });
        worklet.port.onmessage = (message: MessageEvent<ArrayBuffer>) => {
            if (socket?.readyState === WebSocket.OPEN && settingsApplied) {
                socket.send(message.data);
            }
        };

        // A silent sink keeps the worklet pulled without echoing the mic.
        const sink = context.createGain();
        sink.gain.value = 0;
        source.connect(worklet);
        worklet.connect(sink);
        sink.connect(context.destination);
    }

    function teardown(): void {
        if (keepAlive !== null) clearInterval(keepAlive);
        if (clock !== null) clearInterval(clock);
        keepAlive = null;
        clock = null;

        if (socket !== null) {
            socket.onclose = null;
            socket.onmessage = null;
            socket.onerror = null;

            if (
                socket.readyState === WebSocket.OPEN ||
                socket.readyState === WebSocket.CONNECTING
            ) {
                socket.close();
            }
        }

        socket = null;
        stopPlayback();
        worklet?.disconnect();
        worklet = null;
        micStream?.getTracks().forEach((track) => track.stop());
        micStream = null;
        void context?.close();
        context = null;

        if (workletUrl !== null) URL.revokeObjectURL(workletUrl);
        workletUrl = null;
    }

    async function start(): Promise<void> {
        if (!['idle', 'error', 'denied'].includes(state.value)) return;

        errorMessage.value = null;
        elapsedSeconds.value = 0;
        endUrl = '';
        captions.value = [];
        seq = 0;
        ended = false;
        settingsApplied = false;
        state.value = 'requesting';

        if (
            typeof navigator === 'undefined' ||
            typeof navigator.mediaDevices?.getUserMedia !== 'function' ||
            typeof WebSocket === 'undefined' ||
            typeof AudioWorkletNode === 'undefined'
        ) {
            errorMessage.value = tk(
                'This browser cannot make voice calls. Please use the text chat instead.',
            );
            state.value = 'error';

            return;
        }

        try {
            micStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: 1,
                },
            });
        } catch {
            errorMessage.value = tk(
                'Microphone access was blocked. Allow the microphone in your browser settings, then try again, or use the text chat.',
            );
            state.value = 'denied';

            return;
        }

        state.value = 'connecting';

        let started: VoiceCallStartResponse;

        try {
            const response = await postJson(options.startUrl(), {});
            const body = (await response.json()) as Partial<
                VoiceCallStartResponse & { message: string }
            >;

            if (!response.ok || body.session === undefined) {
                throw new Error(
                    body.message ?? tk('The voice call could not start.'),
                );
            }

            started = body as VoiceCallStartResponse;
        } catch (error) {
            teardown();
            errorMessage.value =
                error instanceof Error
                    ? error.message
                    : tk('The voice call could not start.');
            state.value = 'error';

            return;
        }

        turnUrl = started.turnUrl;
        endUrl = started.endUrl;
        maxSeconds.value = started.session.maxCallSeconds;
        outputRate = started.session.outputSampleRate;
        context = new AudioContext();
        playhead = context.currentTime;

        try {
            await context.resume();
            await startMicrophone(started.session.inputSampleRate);
        } catch {
            errorMessage.value = tk('Your browser could not start the audio.');
            state.value = 'error';
            await end();

            return;
        }

        socket = new WebSocket(started.session.url, [
            'bearer',
            started.session.token,
        ]);
        socket.binaryType = 'arraybuffer';
        socket.onopen = () => {
            socket?.send(JSON.stringify(started.session.settings));
            keepAlive = setInterval(() => {
                if (socket?.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'KeepAlive' }));
                }
            }, KEEP_ALIVE_MS);
            clock = setInterval(() => {
                elapsedSeconds.value += 1;

                if (elapsedSeconds.value >= maxSeconds.value) {
                    toast.info(t('Time is up. The call has ended.'));
                    void end();
                }
            }, 1000);
        };
        socket.onmessage = (message: MessageEvent<ArrayBuffer | string>) => {
            if (typeof message.data === 'string') {
                try {
                    handleEvent(JSON.parse(message.data) as AgentEvent);
                } catch {
                    // Ignore a malformed frame.
                }

                return;
            }

            playPcm(message.data);
        };
        socket.onerror = () => {
            errorMessage.value = tk(
                'The connection to the voice service failed.',
            );
        };
        socket.onclose = () => {
            if (!ended) {
                toast.error(
                    t(
                        errorMessage.value ??
                            tk('The voice call was disconnected.'),
                    ),
                );
                void end();
            }
        };
    }

    /**
     * Hang up, wait for the last captions to reach the server, then let the
     * server decide where to go (feedback, or back to the brief).
     */
    async function end(): Promise<void> {
        if (ended) return;

        ended = true;
        state.value = 'ending';
        teardown();

        await Promise.allSettled(pendingTurns);

        if (endUrl !== '') {
            router.post(endUrl);
        } else {
            state.value = errorMessage.value === null ? 'idle' : 'error';
        }
    }

    function toggleMute(): void {
        muted.value = !muted.value;
        micStream?.getAudioTracks().forEach((track) => {
            track.enabled = !muted.value;
        });
    }

    tryOnScopeDispose(() => {
        ended = true;
        teardown();
    });

    return {
        state,
        captions,
        errorMessage,
        elapsedSeconds,
        maxSeconds,
        muted,
        explanation: tk(
            'We need your microphone so the guest can hear you. Your voice is sent live to the voice service and only the written transcript is saved.',
        ),
        start,
        end,
        toggleMute,
    };
}
