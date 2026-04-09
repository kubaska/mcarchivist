import {computed} from "vue";

const units = [
    { unit: "year", ms: 31536000000 },
    { unit: "month", ms: 2628000000 },
    { unit: "day", ms: 86400000 },
    { unit: "hour", ms: 3600000 },
    { unit: "minute", ms: 60000 },
    { unit: "second", ms: 1000 },
];

const formatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'short',
    timeStyle: 'medium',
});
const relativeFormatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

export const useDateFormatter = () => formatter;

export const useRelativeDate = (date) => {
    const d = computed(() => new Date(date));
    const now = Date.now();

    const diff = now - d.value.getTime();

    // Date in future
    if (diff < 0) return { formatted: formatter.format(d.value), relative: '' };

    for (const {unit, ms} of units) {
        if (Math.abs(diff) >= ms || unit === 'second') {
            return {
                formatted: formatter.format(d.value),
                relative: relativeFormatter.format(-Math.round(diff / ms), unit)
            };
        }
    }

    return { formatted: formatter.format(d.value), relative: '' };
}
