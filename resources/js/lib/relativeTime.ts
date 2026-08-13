const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

/**
 * Format an ISO 8601 timestamp as a short relative label ("3h ago"),
 * falling back to "just now" for anything under a minute.
 */
export function formatRelativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = (Date.now() - new Date(iso).getTime()) / 1000;

    if (seconds < 60) {
        return 'just now';
    }

    for (const [unit, secondsInUnit] of UNITS) {
        if (seconds >= secondsInUnit) {
            return formatter.format(-Math.floor(seconds / secondsInUnit), unit);
        }
    }

    return formatter.format(-Math.floor(seconds / 60), 'minute');
}
