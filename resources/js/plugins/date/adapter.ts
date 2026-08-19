import { format, isValid, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

const empty = '—';

export function formatDate(value: string | null): string {
    if (!value) {
        return empty;
    }

    const date = parseISO(value.slice(0, 10));

    if (!isValid(date)) {
        return empty;
    }

    return format(date, 'PP', { locale: es });
}

export function formatDateTime(value: string): string {
    const date = parseISO(value);

    if (!isValid(date)) {
        return empty;
    }

    return format(date, 'PPp', { locale: es });
}
