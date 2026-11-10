export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/** One choice in an R10Combobox: what is kept, and what is shown. */
export type R10ComboboxOption = {
    value: string | number;
    label: string;
};
