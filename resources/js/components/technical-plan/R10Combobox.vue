<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import type { R10ComboboxOption } from '@/types';

/**
 * A searchable dropdown wearing the R10 field styling, for a choice too long
 * to hand to the page in full. It does no filtering of its own: whatever is
 * typed comes back out through `searchTerm`, the caller fetches the matches,
 * and `options` is what is shown — so the list is only ever as long as one
 * answer from the server.
 */

withDefaults(
    defineProps<{
        label?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
        /** Validation message for this field, shown beneath it. */
        error?: string;
        errorTestId?: string;
        /** The matches for what has been typed so far. */
        options: R10ComboboxOption[];
        /** Whether the matches are still being fetched. */
        loading?: boolean;
        /** Shown in place of the list when there is nothing to show. */
        emptyText?: string;
    }>(),
    { required: false, loading: false, emptyText: 'Midagi ei leitud.' },
);

defineOptions({ inheritAttrs: false });

const model = defineModel<R10ComboboxOption | null>({ required: true });

const searchTerm = defineModel<string>('searchTerm', { default: '' });
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span
            v-if="label"
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-r10-orange">*</span>
        </span>
        <span v-if="hint" class="-mt-0.5 text-xs text-r10-grey-500">{{
            hint
        }}</span>

        <ComboboxRoot
            v-model="model"
            by="value"
            ignore-filter
            :reset-search-term-on-blur="false"
            :reset-search-term-on-select="false"
        >
            <ComboboxAnchor
                class="flex w-full items-center rounded-lg border-2 border-r10-grey-200 bg-white pr-2 focus-within:border-r10-orange"
            >
                <ComboboxInput
                    v-bind="$attrs"
                    v-model="searchTerm"
                    :display-value="
                        (option: R10ComboboxOption | null) =>
                            option?.label ?? ''
                    "
                    :placeholder="placeholder"
                    class="w-full bg-transparent px-4 py-3 font-r10-body text-[15px] text-r10-ink outline-none placeholder:text-r10-grey-500"
                />
                <LoaderCircle
                    v-if="loading"
                    class="h-4 w-4 shrink-0 animate-spin text-r10-grey-500"
                />
                <ComboboxTrigger
                    v-else
                    class="shrink-0 text-r10-grey-500"
                    aria-label="Ava valikud"
                >
                    <ChevronsUpDown class="h-4 w-4" />
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 max-h-64 w-(--reka-combobox-trigger-width) overflow-hidden rounded-lg border-2 border-r10-grey-200 bg-white font-r10-body shadow-md"
                >
                    <ComboboxViewport class="p-1">
                        <ComboboxEmpty
                            class="px-3 py-2 text-[13px] text-r10-grey-500"
                            data-test="combobox-empty"
                        >
                            {{ emptyText }}
                        </ComboboxEmpty>

                        <ComboboxItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option"
                            :text-value="option.label"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-3 py-2 text-[15px] text-r10-ink outline-none data-highlighted:bg-r10-grey-100"
                            data-test="combobox-option"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator>
                                <Check class="h-4 w-4 text-r10-orange" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>

        <span
            v-if="error"
            :data-test="errorTestId"
            class="text-xs font-medium text-r10-orange-700"
        >
            {{ error }}
        </span>
    </div>
</template>
