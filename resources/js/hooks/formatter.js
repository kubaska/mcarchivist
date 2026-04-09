const numberFormatter = Intl.NumberFormat('en', { notation: 'compact' });

export const useNumberFormatter = () => numberFormatter;
