import { router } from '@inertiajs/vue3';
import { tryOnScopeDispose } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { t, tk } from '@/lib/i18n';
import type {
    VoiceAgentSession,
    VoiceCallCaption,
    VoiceCallStartResponse,
    VoiceCallState,
    VoicePipelineSession,
    VoiceReplyResponse,
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

/** A Deepgram Flux message (spec 0009). */
type FluxEvent = {
    type?: string;
    event?: string;
    turn_index?: number;
    transcript?: string;
    description?: string;
    message?: string;
};

/** One request for the guest's answer to one employee turn. */
type PendingReply = {
    turn: number;
    rev: number;
    text: string;
    confirmed: boolean;
    cancelled: boolean;
    delivered: boolean;
    result: VoiceReplyResponse | null;
};

const KEEP_ALIVE_MS = 8000;

/** Mic audio kept while the transcription socket opens (about 2 s). */
const MAX_BUFFERED_CHUNKS = 25;

/*
 * Converts the microphone to linear16 PCM at the call's input rate inside
 * an AudioWorklet (no dependency). The context runs at the device rate; the
 * worklet averages the samples each output sample covers when it
 * downsamples (so 48 kHz speech does not alias at 16 kHz), interpolates
 * when it upsamples, and posts chunks of `chunkMs`.
 */
const WORKLET_SOURCE = `
class GuesviaPcm16Writer extends AudioWorkletProcessor {
    constructor(options) {
        super();
        this.target = options.processorOptions.targetRate;
        this.ratio = sampleRate / this.target;
        this.t = 0;
        this.acc = 0;
        this.n = 0;
        this.pos = 0;
        const ms = options.processorOptions.chunkMs || 40;
        this.size = Math.max(160, Math.round(this.target * ms / 1000));
        this.out = new Int16Array(this.size);
        this.len = 0;
    }

    push(value) {
        const s = Math.max(-1, Math.min(1, value));
        this.out[this.len++] = s < 0 ? s * 0x8000 : s * 0x7fff;
        if (this.len === this.size) {
            this.port.postMessage(this.out.buffer, [this.out.buffer]);
            this.out = new Int16Array(this.size);
            this.len = 0;
        }
    }

    process(inputs) {
        const input = inputs[0] && inputs[0][0];
        if (!input) return true;
        if (this.ratio > 1) {
            for (let i = 0; i < input.length; i++) {
                this.acc += input[i];
                this.n++;
                this.pos += 1;
                if (this.pos >= this.ratio) {
                    this.pos -= this.ratio;
                    this.push(this.acc / this.n);
                    this.acc = 0;
                    this.n = 0;
                }
            }
            return true;
        }
        let t = this.t;
        while (t < input.length) {
            const i = Math.floor(t);
            const f = t - i;
            const a = input[i];
            const b = i + 1 < input.length ? input[i + 1] : a;
            this.push(a + (b - a) * f);
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

function sameWords(a: string, b: string): boolean {
    const words = (value: string): string =>
        value
            .toLowerCase()
            .replace(/[^\p{L}\p{N}\s']/gu, '')
            .replace(/\s+/gu, ' ')
            .trim();

    return words(a) === words(b);
}

/*
 * One live spoken role-play (RP-03, RP-11, RESP-05; specs 0004 and 0009).
 *
 * The server opens the attempt and picks the engine:
 * - `pipeline` (fast engine): the microphone streams to Deepgram Flux; each
 *   finished sentence goes to our server, which answers with the guest's
 *   line and, when it has one, a stored recording. A line not recorded yet
 *   is voiced live on Deepgram's streaming speech socket while the server
 *   records it. The reply is requested at Flux's early end-of-turn and
 *   played the moment the turn is confirmed.
 * - `agent`: the Deepgram Voice Agent listens, thinks and speaks; every
 *   caption is posted to the server as it happens.
 * Lines carry sequence numbers so a retry can never duplicate them
 * (PROG-03, PROG-04). The microphone is requested on an explicit tap, never
 * on mount; only the text transcript reaches our server, and the camera is
 * never touched here (spec 0004 decision 3).
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
    let replyUrl = '';
    let endUrl = '';
    let ended = false;
    let engine: 'agent' | 'pipeline' = 'agent';
    let buffered: ArrayBuffer[] = [];
    let pending: PendingReply | null = null;
    let userTurn = -1;
    let userSpeaking = false;
    // The fast engine's streamed voice for lines not recorded yet.
    let tts: WebSocket | null = null;
    let streaming = false;
    let streamFlushed = false;
    let streamDone: (() => void) | null = null;
    let dropStream = false;
    let dropTimer: ReturnType<typeof setTimeout> | null = null;
    const revs = new Map<number, number>();
    const decoded = new Map<string, Promise<AudioBuffer>>();
    const playing = new Set<AudioBufferSourceNode>();
    const pendingTurns = new Set<Promise<void>>();

    function track(promise: Promise<void>): void {
        pendingTurns.add(promise);
        void promise.finally(() => pendingTurns.delete(promise));
    }

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

        if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }

        // Stop Deepgram voicing the interrupted line; frames already on the
        // way are dropped until it confirms.
        if (streaming && tts?.readyState === WebSocket.OPEN) {
            tts.send(JSON.stringify({ type: 'Clear' }));
            dropStream = true;

            // Never stay muted if the confirmation is lost.
            if (dropTimer !== null) clearTimeout(dropTimer);
            dropTimer = setTimeout(() => {
                dropStream = false;
                dropTimer = null;
            }, 1500);
        }

        streaming = false;
        streamFlushed = false;
        streamDone = null;
    }

    function finishStream(): void {
        if (!streamFlushed || playing.size > 0) return;

        const done = streamDone;
        streamFlushed = false;
        streamDone = null;

        if (state.value === 'speaking') state.value = 'listening';
        done?.();
    }

    function playBuffer(buffer: AudioBuffer, onDone?: () => void): void {
        if (context === null) return;

        const source = context.createBufferSource();
        source.buffer = buffer;
        source.connect(context.destination);
        playhead = Math.max(playhead, context.currentTime);
        source.start(playhead);
        playhead += buffer.duration;
        playing.add(source);
        source.onended = () => {
            playing.delete(source);

            if (
                playing.size === 0 &&
                state.value === 'speaking' &&
                !streaming
            ) {
                state.value = 'listening';
            }

            onDone?.();
            finishStream();
        };
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

        playBuffer(buffer);
    }

    /** A stored recording, fetched and decoded once per call. */
    function recording(url: string): Promise<AudioBuffer> {
        const known = decoded.get(url);
        if (known !== undefined) return known;

        const promise = fetch(url, { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) throw new Error(String(response.status));

                return response.arrayBuffer();
            })
            .then(async (bytes) => {
                if (context === null) throw new Error('closed');

                return context.decodeAudioData(bytes);
            });

        decoded.set(url, promise);
        void promise.catch(() => decoded.delete(url));

        return promise;
    }

    /** The browser's own voice, when a line has no recording. */
    function speakLocally(text: string, onDone?: () => void): void {
        if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
            state.value = 'listening';
            onDone?.();

            return;
        }

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.onend = () => {
            if (state.value === 'speaking') state.value = 'listening';
            onDone?.();
        };
        window.speechSynthesis.speak(utterance);
    }

    async function speak(
        text: string,
        url: string | null,
        onDone?: () => void,
    ): Promise<void> {
        state.value = 'speaking';

        if (url === null) {
            if (tts?.readyState === WebSocket.OPEN) {
                // Not recorded yet: Deepgram voices it live (first sound in
                // about 0.2 s) while our server records it for next time.
                streaming = true;
                streamFlushed = false;
                streamDone = onDone ?? null;
                tts.send(JSON.stringify({ type: 'Speak', text }));
                tts.send(JSON.stringify({ type: 'Flush' }));

                return;
            }

            speakLocally(text, onDone);

            return;
        }

        try {
            playBuffer(await recording(url), onDone);
        } catch {
            if (!ended) speakLocally(text, onDone);
        }
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

    function showCaption(
        captionSeq: number,
        role: 'user' | 'agent',
        text: string,
    ): VoiceCallCaption | null {
        const clean = text.trim();
        if (clean === '') return null;

        const caption: VoiceCallCaption = {
            seq: captionSeq,
            role,
            text: clean,
        };
        captions.value = [
            ...captions.value.filter((existing) => existing.seq !== captionSeq),
            caption,
        ].sort((a, b) => a.seq - b.seq);

        return caption;
    }

    function recordCaption(role: 'user' | 'agent', text: string): void {
        const caption = showCaption(seq++, role, text);
        if (caption !== null) track(sendTurn(caption));
    }

    // ------------------------------------------------------------ agent

    function handleAgentEvent(event: AgentEvent): void {
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

    function openAgent(session: VoiceAgentSession): void {
        socket = new WebSocket(session.url, ['bearer', session.token]);
        socket.binaryType = 'arraybuffer';
        socket.onopen = () => {
            socket?.send(JSON.stringify(session.settings));
            keepAlive = setInterval(() => {
                if (socket?.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({ type: 'KeepAlive' }));
                }
            }, KEEP_ALIVE_MS);
            startClock();
        };
        socket.onmessage = (message: MessageEvent<ArrayBuffer | string>) => {
            if (typeof message.data === 'string') {
                try {
                    handleAgentEvent(JSON.parse(message.data) as AgentEvent);
                } catch {
                    // Ignore a malformed frame.
                }

                return;
            }

            playPcm(message.data);
        };
        watchSocket();
    }

    // --------------------------------------------------------- pipeline

    async function postReply(
        entry: PendingReply,
    ): Promise<VoiceReplyResponse | null> {
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                const response = await postJson(replyUrl, {
                    turn: entry.turn,
                    rev: entry.rev,
                    text: entry.text,
                });
                const body = (await response.json()) as Partial<
                    VoiceReplyResponse & { message: string }
                >;

                if (response.ok) return body as VoiceReplyResponse;

                if (response.status === 409) {
                    if (!ended) void end();

                    return null;
                }

                if (response.status !== 429 && response.status < 500) {
                    return null;
                }

                if (attempt === 2 && entry.confirmed && !entry.cancelled) {
                    toast.error(
                        body.message ??
                            t(
                                'The guest could not answer. Please say it again.',
                            ),
                    );
                }
            } catch {
                // Offline for a moment: retry below.
            }

            if (entry.cancelled || ended) return null;

            await sleep(400 * 2 ** attempt);
        }

        return null;
    }

    function deliver(entry: PendingReply): void {
        const result = entry.result;
        if (result === null || entry.delivered || entry.cancelled) return;
        if (result.stale) return;

        entry.delivered = true;
        showCaption(2 + 2 * entry.turn, 'agent', result.text);

        // The employee is already saying something new: show the line but
        // do not talk over them.
        if (userSpeaking && userTurn > entry.turn) {
            state.value = 'listening';

            return;
        }

        void speak(result.text, result.audioUrl, () => {
            if (result.limitReached && !ended) {
                toast.warning(
                    t(
                        'You have reached today’s AI practice limit. The call will end now.',
                    ),
                );
                void end();
            }
        });
    }

    function requestReply(
        turn: number,
        text: string,
        confirmed: boolean,
    ): void {
        const rev = (revs.get(turn) ?? -1) + 1;
        revs.set(turn, rev);

        const entry: PendingReply = {
            turn,
            rev,
            text,
            confirmed,
            cancelled: false,
            delivered: false,
            result: null,
        };
        pending = entry;

        track(
            postReply(entry).then((result) => {
                entry.result = result;

                if (result === null) {
                    if (entry.confirmed && pending === entry) {
                        state.value = 'listening';
                    }

                    return;
                }

                if (entry.confirmed) deliver(entry);
            }),
        );
    }

    function handleFluxEvent(event: FluxEvent): void {
        if (event.type === 'Error') {
            errorMessage.value =
                event.description ??
                event.message ??
                tk('The voice call hit a problem.');
            toast.error(t(errorMessage.value));

            return;
        }

        if (event.type !== 'TurnInfo') return;

        const turn = event.turn_index ?? 0;
        const text = (event.transcript ?? '').trim();

        switch (event.event) {
            case 'StartOfTurn':
                // Barge-in: the guest stops talking the moment you speak.
                stopPlayback();
                userSpeaking = true;
                userTurn = turn;
                state.value = 'listening';
                break;
            case 'EagerEndOfTurn':
                // Probably finished: prepare the answer now, play it only
                // once the turn is confirmed.
                if (text !== '') {
                    requestReply(turn, text, false);
                    state.value = 'thinking';
                }
                break;
            case 'TurnResumed':
                if (pending !== null && pending.turn === turn) {
                    pending.cancelled = !pending.confirmed;
                }
                state.value = 'listening';
                break;
            case 'EndOfTurn': {
                userSpeaking = false;

                if (text === '') {
                    state.value = 'listening';
                    break;
                }

                showCaption(1 + 2 * turn, 'user', text);
                const early = pending;

                if (
                    early !== null &&
                    early.turn === turn &&
                    !early.cancelled &&
                    sameWords(early.text, text)
                ) {
                    early.confirmed = true;
                    state.value = 'thinking';
                    deliver(early);
                } else {
                    requestReply(turn, text, true);
                    state.value = 'thinking';
                }
                break;
            }
            default:
                break;
        }
    }

    /**
     * Deepgram's streaming voice, opened once per call with the same
     * short-lived token. Resolves false when it cannot open; lines then fall
     * back to the browser's own voice rather than stall the call.
     */
    function openTts(session: VoicePipelineSession): Promise<boolean> {
        return new Promise((resolve) => {
            const timer = setTimeout(() => resolve(false), 3000);

            tts = new WebSocket(session.ttsUrl, ['bearer', session.token]);
            tts.binaryType = 'arraybuffer';
            tts.onopen = () => {
                clearTimeout(timer);
                resolve(true);
            };
            tts.onmessage = (message: MessageEvent<ArrayBuffer | string>) => {
                if (typeof message.data !== 'string') {
                    if (!dropStream) playPcm(message.data);

                    return;
                }

                let event: { type?: string } = {};

                try {
                    event = JSON.parse(message.data) as { type?: string };
                } catch {
                    return;
                }

                if (event.type === 'Flushed') {
                    streaming = false;
                    streamFlushed = true;
                    finishStream();
                } else if (event.type === 'Cleared') {
                    dropStream = false;
                }
            };
            tts.onerror = () => {
                clearTimeout(timer);
                resolve(false);
            };
            tts.onclose = () => {
                tts = null;
                resolve(false);

                if (streaming) {
                    streaming = false;
                    streamFlushed = true;
                    finishStream();
                }
            };
        });
    }

    function openPipeline(session: VoicePipelineSession): void {
        outputRate = session.outputSampleRate;
        const ttsReady = openTts(session);

        // The guest answers the moment the call opens: from storage, or
        // streamed as soon as the voice socket is up.
        const greeting = session.greeting;

        if (greeting.text.trim() !== '') {
            recordCaption('agent', greeting.text);

            if (greeting.audioUrl !== null) {
                void speak(greeting.text, greeting.audioUrl);
            } else {
                void ttsReady.then(() => speak(greeting.text, null));
            }
        }

        socket = new WebSocket(session.sttUrl, ['bearer', session.token]);
        socket.binaryType = 'arraybuffer';
        socket.onopen = () => {
            buffered.forEach((chunk) => socket?.send(chunk));
            buffered = [];
            startClock();

            if (state.value === 'connecting') state.value = 'listening';
        };
        socket.onmessage = (message: MessageEvent<ArrayBuffer | string>) => {
            if (typeof message.data !== 'string') return;

            try {
                handleFluxEvent(JSON.parse(message.data) as FluxEvent);
            } catch {
                // Ignore a malformed frame.
            }
        };
        watchSocket();
    }

    // ------------------------------------------------------------ shared

    function sendAudio(chunk: ArrayBuffer): void {
        if (socket?.readyState === WebSocket.OPEN) {
            if (engine === 'pipeline' || settingsApplied) socket.send(chunk);

            return;
        }

        if (engine === 'pipeline' && !ended) {
            buffered.push(chunk);
            if (buffered.length > MAX_BUFFERED_CHUNKS) buffered.shift();
        }
    }

    function startClock(): void {
        if (clock !== null) return;

        clock = setInterval(() => {
            elapsedSeconds.value += 1;

            if (elapsedSeconds.value >= maxSeconds.value) {
                toast.info(t('Time is up. The call has ended.'));
                void end();
            }
        }, 1000);
    }

    function watchSocket(): void {
        if (socket === null) return;

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

    async function startMicrophone(
        targetRate: number,
        chunkMs: number,
    ): Promise<void> {
        if (context === null || micStream === null) return;

        workletUrl = URL.createObjectURL(
            new Blob([WORKLET_SOURCE], { type: 'application/javascript' }),
        );
        await context.audioWorklet.addModule(workletUrl);

        const source = context.createMediaStreamSource(micStream);
        worklet = new AudioWorkletNode(context, 'guesvia-pcm16-writer', {
            processorOptions: { targetRate, chunkMs },
        });
        worklet.port.onmessage = (message: MessageEvent<ArrayBuffer>) => {
            sendAudio(message.data);
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

            if (socket.readyState === WebSocket.OPEN && engine === 'pipeline') {
                socket.send(JSON.stringify({ type: 'CloseStream' }));
            }

            if (
                socket.readyState === WebSocket.OPEN ||
                socket.readyState === WebSocket.CONNECTING
            ) {
                socket.close();
            }
        }

        socket = null;
        buffered = [];
        pending = null;
        stopPlayback();

        if (tts !== null) {
            tts.onclose = null;
            tts.onmessage = null;
            tts.onerror = null;

            if (tts.readyState === WebSocket.OPEN) {
                tts.send(JSON.stringify({ type: 'Close' }));
            }

            tts.close();
            tts = null;
        }

        if (dropTimer !== null) clearTimeout(dropTimer);
        dropTimer = null;
        dropStream = false;
        worklet?.disconnect();
        worklet = null;
        micStream?.getTracks().forEach((stream) => stream.stop());
        micStream = null;
        void context?.close();
        context = null;
        decoded.clear();

        if (workletUrl !== null) URL.revokeObjectURL(workletUrl);
        workletUrl = null;
    }

    async function start(): Promise<void> {
        if (!['idle', 'error', 'denied'].includes(state.value)) return;

        errorMessage.value = null;
        elapsedSeconds.value = 0;
        endUrl = '';
        replyUrl = '';
        captions.value = [];
        seq = 0;
        ended = false;
        settingsApplied = false;
        userTurn = -1;
        userSpeaking = false;
        revs.clear();
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

        // Created inside the tap, so iOS lets it play later.
        context = new AudioContext();
        void context.resume();

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
            teardown();
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

        if (context === null || ended) {
            // Closed while the session was being opened.
            return;
        }

        const session = started.session;
        engine = session.engine;
        turnUrl = started.turnUrl;
        replyUrl = started.replyUrl;
        endUrl = started.endUrl;
        maxSeconds.value = session.maxCallSeconds;
        playhead = context.currentTime;

        if (session.engine === 'agent') {
            outputRate = session.outputSampleRate;
        } else if (session.greeting.audioUrl !== null) {
            // Start fetching the greeting while the microphone starts.
            void recording(session.greeting.audioUrl).catch(() => undefined);
        }

        try {
            await context.resume();
            await startMicrophone(
                session.inputSampleRate,
                session.engine === 'pipeline' ? session.chunkMs : 40,
            );
        } catch {
            errorMessage.value = tk('Your browser could not start the audio.');
            state.value = 'error';
            await end();

            return;
        }

        if (session.engine === 'pipeline') {
            openPipeline(session);
        } else {
            openAgent(session);
        }
    }

    /**
     * Hang up, wait for the last lines to reach the server, then let the
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
        micStream?.getAudioTracks().forEach((audio) => {
            audio.enabled = !muted.value;
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
