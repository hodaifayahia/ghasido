/*
 * Live spoken role-play through the Deepgram Voice Agent (spec 0004).
 * Shapes from App\Services\VoiceAgent\VoiceAgentSettings and the
 * voice-call controllers. No key ever appears here (API-02, SEC-03).
 */

export type VoiceAgentSpeakProvider = 'deepgram' | 'eleven_labs';

export type VoiceAgentThinkMode = 'managed' | 'qwen_proxy';

export type VoiceAgentThinkProvider = 'open_ai' | 'anthropic' | 'google';

/** `pipeline` = fast engine with stored voices (spec 0009). */
export type VoiceEngine = 'pipeline' | 'agent';

export type VoiceAgentValues = {
    engine: VoiceEngine;
    reuseStoredLines: boolean;
    listenModel: string;
    eotThreshold: number;
    eagerEotThreshold: number;
    eotTimeoutMs: number;
    keyterms: string[];
    language: string;
    speakProvider: VoiceAgentSpeakProvider;
    speakModel: string;
    elevenModelId: string;
    elevenVoiceId: string;
    thinkMode: VoiceAgentThinkMode;
    thinkProvider: VoiceAgentThinkProvider;
    thinkModel: string;
    temperature: number;
    greeting: string;
    prompt: string;
    maxCallSeconds: number;
    inputSampleRate: number;
    outputSampleRate: number;
};

export type VoiceAgentSettingsPayload = {
    values: VoiceAgentValues;
    defaults: VoiceAgentValues;
    apiConfigured: boolean;
    qwenProxyAvailable: boolean;
    qwenProxyReason: string | null;
    qwenModel: string | null;
    pipelineAvailable: boolean;
    pipelineReason: string | null;
    /** What the stored-voice bank has saved so far (spec 0009). */
    bank: {
        lines: number;
        reusable: number;
        reuses: number;
        charactersSaved: number;
    };
    options: {
        engines: VoiceEngine[];
        listenModels: string[];
        speakProviders: VoiceAgentSpeakProvider[];
        voices: { value: string; label: string }[];
        thinkModes: VoiceAgentThinkMode[];
        thinkProviders: VoiceAgentThinkProvider[];
        thinkModelSuggestions: Record<VoiceAgentThinkProvider, string[]>;
        inputSampleRates: number[];
        outputSampleRates: number[];
    };
    saveUrl: string;
};

export type VoiceAgentScenarioOverrides = {
    speakModel: string | null;
    elevenVoiceId: string | null;
    greeting: string | null;
};

export type VoiceAgentScenario = {
    id: number;
    title: string;
    guestRole: string;
    overrides: VoiceAgentScenarioOverrides;
    saveUrl: string;
    startUrl: string;
};

export type VoiceAgentPagePayload = {
    settings: VoiceAgentSettingsPayload;
    scenario: VoiceAgentScenario | null;
};

/** A Deepgram Voice Agent call (spec 0004). */
export type VoiceAgentSession = {
    engine: 'agent';
    url: string;
    token: string;
    expiresIn: number;
    settings: Record<string, unknown>;
    maxCallSeconds: number;
    inputSampleRate: number;
    outputSampleRate: number;
    thinkMode: VoiceAgentThinkMode;
};

/** A fast-engine call: Flux in the browser, stored voices (spec 0009). */
export type VoicePipelineSession = {
    engine: 'pipeline';
    token: string;
    expiresIn: number;
    sttUrl: string;
    /** Deepgram's streaming voice for a line not recorded yet. */
    ttsUrl: string;
    inputSampleRate: number;
    outputSampleRate: number;
    chunkMs: number;
    greeting: { text: string; audioUrl: string | null };
    maxCallSeconds: number;
};

export type VoiceCallSession = VoiceAgentSession | VoicePipelineSession;

export type VoiceCallStartResponse = {
    attemptId: number;
    turnUrl: string;
    replyUrl: string;
    endUrl: string;
    session: VoiceCallSession;
};

/** VoiceReplyService::reply() */
export type VoiceReplyResponse = {
    turn: number;
    rev: number;
    text: string;
    audioUrl: string | null;
    source: 'new' | 'reused';
    limitReached: boolean;
    /** Why the guest closed the call: the learner's limit or the AI quota. */
    endReason?: 'limit' | 'quota' | null;
    stale: boolean;
};

export type VoiceCallCaption = {
    seq: number;
    role: 'user' | 'agent';
    text: string;
};

export type VoiceCallState =
    | 'idle'
    | 'requesting'
    | 'denied'
    | 'connecting'
    | 'listening'
    | 'thinking'
    | 'speaking'
    | 'ending'
    | 'error';

/** The learner Get Ready page's voice option (RoleplayController::ready). */
export type VoiceCallOffer = {
    startUrl: string;
    guestRole: string;
};
