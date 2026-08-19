import { onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

type SpeechRecognitionResultLike = {
    isFinal: boolean;
    0: { transcript: string };
};

type SpeechRecognitionEventLike = {
    resultIndex: number;
    results: ArrayLike<SpeechRecognitionResultLike> & { length: number };
};

type SpeechRecognitionLike = {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    onresult: ((event: SpeechRecognitionEventLike) => void) | null;
    onerror: ((event: { error: string }) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
};

type SpeechRecognitionCtor = new () => SpeechRecognitionLike;

function getSpeechRecognition(): SpeechRecognitionCtor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const speechWindow = window as Window & {
        SpeechRecognition?: SpeechRecognitionCtor;
        webkitSpeechRecognition?: SpeechRecognitionCtor;
    };

    return (
        speechWindow.SpeechRecognition ??
        speechWindow.webkitSpeechRecognition ??
        null
    );
}

export function useSpeechToText(
    getText: () => string,
    setText: (value: string) => void,
): {
    supported: boolean;
    listening: ReturnType<typeof ref<boolean>>;
    toggle: () => void;
    stop: () => void;
} {
    const supported = getSpeechRecognition() !== null;
    const listening = ref(false);
    let recognition: SpeechRecognitionLike | null = null;
    let baseText = '';
    let finalTranscript = '';
    let stopping = false;

    const apply = (interim = ''): void => {
        const prefix = baseText.trim() === '' ? '' : `${baseText.trimEnd()} `;

        setText(`${prefix}${finalTranscript}${interim}`.trimStart());
    };

    const stop = (): void => {
        stopping = true;
        recognition?.stop();
        listening.value = false;
    };

    const start = (): void => {
        const SpeechRecognitionApi = getSpeechRecognition();

        if (!SpeechRecognitionApi) {
            toast.error('Tu navegador no soporta dictado por voz.');

            return;
        }

        recognition = new SpeechRecognitionApi();
        recognition.lang = 'es-DO';
        recognition.interimResults = true;
        recognition.continuous = true;
        baseText = getText();
        finalTranscript = '';
        stopping = false;
        listening.value = true;

        recognition.onresult = (event) => {
            let interim = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const transcript = result?.[0]?.transcript ?? '';

                if (result?.isFinal) {
                    finalTranscript += transcript;
                } else {
                    interim += transcript;
                }
            }

            apply(interim);
        };

        recognition.onerror = (event) => {
            if (
                event.error === 'not-allowed' ||
                event.error === 'service-not-allowed'
            ) {
                toast.error('No hay permiso para usar el micrófono.');
            } else if (
                event.error !== 'aborted' &&
                event.error !== 'no-speech'
            ) {
                toast.error('No se pudo dictar. Inténtalo de nuevo.');
            }

            stop();
        };

        recognition.onend = () => {
            listening.value = false;

            if (!stopping) {
                return;
            }
        };

        try {
            recognition.start();
        } catch {
            toast.error('No se pudo iniciar el dictado.');
            listening.value = false;
        }
    };

    const toggle = (): void => {
        if (listening.value) {
            stop();

            return;
        }

        start();
    };

    onUnmounted(() => {
        stop();
    });

    return { supported, listening, toggle, stop };
}
