/*
 * Live spoken role-play through the Deepgram Voice Agent (spec 0004).
 * Shapes from App\Services\VoiceAgent\VoiceAgentSettings and the
 * voice-call controllers. No key ever appears here (API-02, SEC-03).
 */

export type VoiceAgentSpeakProvider = 'deepgram' | 'eleven_labs';

export type VoiceAgentThinkMode = 'managed' | 'qwen_proxy';

export type VoiceAgentThinkProvider = 'open_ai' | 'anthropic' | 'google';

export type VoiceAgentValues = {
    listenModel: string;
    eotThreshold: number;
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
    options: {
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

export type VoiceCallSession = {
    url: string;
    token: string;
    expiresIn: number;
    settings: Record<string, unknown>;
    maxCallSeconds: number;
    inputSampleRate: number;
    outputSampleRate: number;
    thinkMode: VoiceAgentThinkMode;
};

export type VoiceCallStartResponse = {
    attemptId: number;
    turnUrl: string;
    endUrl: string;
    session: VoiceCallSession;
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
