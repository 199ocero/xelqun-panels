import { ref } from 'vue';

const messages = ref<Record<string, string>>({});

export function setMessages(value: Record<string, string>): void {
    messages.value = value;
}

/**
 * The panel's text in the app's language. The English is the key, so it's also
 * what shows if a language leaves one out.
 */
export function t(text: string, replacements: Record<string, string | number> = {}): string {
    return Object.entries(replacements).reduce(
        (result, [name, value]) => result.replaceAll(`:${name}`, String(value)),
        messages.value[text] ?? text,
    );
}
