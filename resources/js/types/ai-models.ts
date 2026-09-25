/**
 * Settings → AI models (API-04): the Super Admin's model overrides over
 * .env, a fake/real switch per paid capability, and the last connection
 * test per capability. No key or endpoint ever travels here (SEC-03).
 */

/** '' = follow .env, 'fake' = off, 'real' = on. */
export type AiCapabilityMode = '' | 'fake' | 'real';

export type AiSwitchCapability = 'ai' | 'image' | 'tts' | 'stt';

export type AiCheckCapability = 'ai' | 'fast' | 'image' | 'tts' | 'stt';

export type AiModelValues = {
    aiMode: AiCapabilityMode;
    aiModel: string;
    aiFastModel: string;
    imageMode: AiCapabilityMode;
    imageModel: string;
    imageSizeLandscape: string;
    imageSizeSquare: string;
    ttsMode: AiCapabilityMode;
    ttsVoice: string;
    ttsExpressivity: number | null;
    sttMode: AiCapabilityMode;
    sttModel: string;
};

export type AiModelEnv = {
    aiProvider: string;
    aiModel: string;
    aiFastModel: string;
    imageProvider: string;
    imageModel: string;
    imageSizeLandscape: string;
    imageSizeSquare: string;
    ttsProvider: string;
    ttsVoice: string;
    ttsExpressivity: number;
    sttProvider: string;
    sttModel: string;
};

export type AiEffectiveCapability = {
    provider: string;
    model: string;
    keyConfigured: boolean;
};

export type AiModelSettingsPayload = {
    values: AiModelValues;
    env: AiModelEnv;
    effective: Record<AiCheckCapability, AiEffectiveCapability>;
    realDefaults: Record<AiSwitchCapability, string>;
    presets: {
        text: string[];
        image: string[];
        imageLandscape: string[];
        imageSquare: string[];
        ttsVoices: string[];
        stt: string[];
    };
};

export type AiCheckStatus = 'pending' | 'running' | 'ok' | 'failed';

export type AiCheckState = {
    status: AiCheckStatus;
    latency_ms: number | null;
    detail: string | null;
    error: string | null;
    checked_at: string | null;
    model: string | null;
    provider: string | null;
};

export type AiChecks = Record<AiCheckCapability, AiCheckState | null>;
