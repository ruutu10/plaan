<script setup lang="ts" generic="T">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from '@lucide/vue';
import { computed, useSlots } from 'vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Input from '@/components/technical-plan/R10Input.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import type { ListingColumn, ListingSort } from '@/composables/useListingTable';
import { useListingTable } from '@/composables/useListingTable';

/**
 * The listing table every management screen uses, together with the four states
 * a listing is ever in: rows, still loading, failed to load, or nothing to
 * show. Each screen had its own copy of all four, which is how the wording of
 * an empty state came to differ from page to page.
 *
 * `rows` is null until the first response lands — that is what the skeleton
 * keys off, so a page passes its ref straight through.
 *
 * A column that declares `sortBy` gets a sortable header, `searchBy` feeds the
 * search box above the table, and `filterBy` a dropdown in a header row beneath
 * the column's label. A table declaring none of them reads as it always did.
 */
const props = withDefaults(
    defineProps<{
        columns: ListingColumn<T>[];
        rows: T[] | null;
        loadFailed?: boolean;
        emptyText: string;
        errorText: string;
        /** Shown when there are rows, but the search and filters hide them all. */
        noMatchesText?: string;
        searchPlaceholder?: string;
        /** The column sorted by before anybody clicks a header. */
        defaultSort?: ListingSort;
        /**
         * Whether the columns' search and filters are offered. Off, the
         * headers still sort — for a listing too short to need narrowing.
         */
        narrowable?: boolean;
        /** Stand-in rows shown while the first response is in flight. */
        skeletonRows?: number;
        /**
         * Width of the stand-in bar per column, so the skeleton is the shape of
         * the real rows. The last entry repeats for any remaining columns.
         */
        skeletonWidths?: string[];
        rowTestId?: string;
        skeletonTestId?: string;
        /**
         * Where clicking a row leads. A row it answers null for is not a link,
         * so a page can keep the way through to those allowed in.
         */
        rowHref?: (row: T) => string | null;
    }>(),
    {
        loadFailed: false,
        narrowable: true,
        noMatchesText: 'Otsingule vastavaid ridu pole.',
        searchPlaceholder: 'Otsi…',
        skeletonRows: 3,
        skeletonWidths: () => ['w-48', 'w-16'],
    },
);

defineSlots<{
    row(props: { row: T; index: number }): unknown;
    /** Extra controls for the toolbar, beside the search and filters. */
    toolbar?(): unknown;
}>();

const slots = useSlots();

const listing = useListingTable<T>({
    rows: () => props.rows,
    columns: () => props.columns,
    narrowable: () => props.narrowable,
    defaultSort: props.defaultSort,
});

const hasToolbar = computed(
    () => listing.isSearchable.value || slots.toolbar !== undefined,
);

const totalCount = computed(() => props.rows?.length ?? 0);

function filterSelectOptions(id: string): { value: string; label: string }[] {
    return [
        { value: '', label: 'Kõik' },
        ...listing
            .filterOptions(id)
            .map((option) => ({ value: option, label: option })),
    ];
}

/** How a sortable header shows its order, and how it reads to a screen reader. */
const SORT_STATES = {
    asc: { icon: ArrowUp, ariaSort: 'ascending', iconClass: '' },
    desc: { icon: ArrowDown, ariaSort: 'descending', iconClass: '' },
    none: {
        icon: ChevronsUpDown,
        ariaSort: 'none',
        iconClass: 'text-r10-grey-500',
    },
} as const;

function sortState(id: string): (typeof SORT_STATES)[keyof typeof SORT_STATES] {
    return SORT_STATES[listing.sortDirection(id) || 'none'];
}

function visitRow(
    href: string | null,
    event: MouseEvent | KeyboardEvent,
): void {
    if (href === null) {
        return;
    }

    // A click that lands on a button or link inside the row is that control's own.
    if ((event.target as HTMLElement).closest('a, button, input, label')) {
        return;
    }

    router.visit(href);
}

const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

const justifyClass = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
};
</script>

<template>
    <div>
        <div
            v-if="hasToolbar"
            class="mb-4 flex flex-wrap items-end gap-3"
            data-test="listing-toolbar"
        >
            <div
                v-if="listing.isSearchable.value"
                class="relative w-full sm:w-72"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-r10-grey-500"
                />
                <R10Input
                    v-model="listing.search.value"
                    type="search"
                    :placeholder="searchPlaceholder"
                    :aria-label="searchPlaceholder"
                    class="pl-10!"
                    data-test="listing-search"
                />
            </div>

            <span
                v-if="listing.isNarrowed.value && rows !== null"
                class="text-sm text-r10-grey-500 tabular-nums"
                data-test="listing-count"
            >
                {{ listing.visibleRows.value.length }} / {{ totalCount }}
            </span>

            <div v-if="$slots.toolbar" class="ml-auto flex items-center gap-3">
                <slot name="toolbar" />
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border-2 border-r10-grey-200 bg-white"
        >
            <table class="w-full border-collapse text-left text-sm">
                <thead class="border-b-2 border-r10-navy">
                    <tr
                        class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-navy uppercase"
                    >
                        <th
                            v-for="column in listing.listingColumns.value"
                            :key="column.id"
                            class="px-5 py-3.5"
                            :class="[
                                alignClass[column.align ?? 'left'],
                                column.class,
                            ]"
                            :aria-sort="
                                column.sortBy
                                    ? sortState(column.id).ariaSort
                                    : undefined
                            "
                        >
                            <span v-if="column.srOnly" class="sr-only">{{
                                column.label
                            }}</span>
                            <button
                                v-else-if="column.sortBy"
                                type="button"
                                class="-mx-1 inline-flex w-full cursor-pointer items-center gap-1.5 rounded px-1 tracking-[0.12em] uppercase hover:text-r10-orange-700 focus-visible:outline-2 focus-visible:outline-r10-orange"
                                :class="justifyClass[column.align ?? 'left']"
                                :data-test="`listing-sort-${column.id}`"
                                @click="listing.toggleSort(column.id)"
                            >
                                {{ column.label }}
                                <component
                                    :is="sortState(column.id).icon"
                                    class="h-3.5 w-3.5 shrink-0"
                                    :class="sortState(column.id).iconClass"
                                />
                            </button>
                            <template v-else>{{ column.label }}</template>
                        </th>
                    </tr>

                    <!-- Each dropdown sits in the column it filters, so it
                         shares that column's width and padding. -->
                    <tr
                        v-if="listing.filterableColumns.value.length > 0"
                        data-test="listing-filter-row"
                    >
                        <td
                            v-for="column in listing.listingColumns.value"
                            :key="`filter-${column.id}`"
                            class="px-5 pb-3 align-top"
                            :class="column.class"
                        >
                            <R10Select
                                v-if="column.filterBy"
                                :model-value="listing.filterChoice(column.id)"
                                :options="filterSelectOptions(column.id)"
                                :aria-label="`Filtreeri: ${column.label}`"
                                class="px-2.5! py-1.5! text-[13px]!"
                                :data-test="`listing-filter-${column.id}`"
                                @update:model-value="
                                    listing.chooseFilter(
                                        column.id,
                                        String($event),
                                    )
                                "
                            />
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="({ key, row }, index) in listing.visibleRows
                            .value"
                        :key="key"
                        :data-test="rowTestId"
                        class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
                        :class="{
                            'cursor-pointer focus-visible:bg-r10-grey-100 focus-visible:outline-none':
                                rowHref?.(row),
                        }"
                        :role="rowHref?.(row) ? 'link' : undefined"
                        :tabindex="rowHref?.(row) ? 0 : undefined"
                        @click="visitRow(rowHref?.(row) ?? null, $event)"
                        @keydown.enter="
                            visitRow(rowHref?.(row) ?? null, $event)
                        "
                    >
                        <slot name="row" :row="row" :index="index" />
                    </tr>

                    <!-- Nothing has arrived yet: stand-in rows the width of the
                         real ones, so the table does not jump once it does. -->
                    <tr
                        v-for="skeleton in rows === null && !loadFailed
                            ? skeletonRows
                            : 0"
                        :key="`skeleton-${skeleton}`"
                        :data-test="skeletonTestId"
                        class="border-b border-r10-grey-200 last:border-0"
                    >
                        <td
                            v-for="(column, index) in listing.listingColumns
                                .value"
                            :key="index"
                            class="px-5 py-4"
                            :class="column.class"
                        >
                            <span
                                class="block h-4 animate-pulse rounded-full bg-r10-grey-200"
                                :class="
                                    skeletonWidths[index] ??
                                    skeletonWidths[skeletonWidths.length - 1]
                                "
                            />
                        </td>
                    </tr>

                    <tr v-if="loadFailed">
                        <td
                            :colspan="columns.length"
                            class="px-5 py-12 text-center text-[15px] text-r10-orange-700"
                        >
                            {{ errorText }}
                        </td>
                    </tr>

                    <tr v-else-if="rows?.length === 0">
                        <td
                            :colspan="columns.length"
                            class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                        >
                            {{ emptyText }}
                        </td>
                    </tr>

                    <!-- There are rows, but the search and filters hide every
                         one: say so, and offer the way back to all of them. -->
                    <tr
                        v-else-if="
                            rows !== null &&
                            listing.visibleRows.value.length === 0
                        "
                        data-test="listing-no-matches"
                    >
                        <td
                            :colspan="columns.length"
                            class="px-5 py-12 text-center text-[15px] text-r10-grey-500"
                        >
                            <p>{{ noMatchesText }}</p>
                            <R10Button
                                v-if="listing.isNarrowed.value"
                                variant="outline"
                                size="sm"
                                class="mt-4"
                                data-test="listing-clear-filters"
                                @click="listing.clearNarrowing()"
                            >
                                Tühjenda otsing ja filtrid
                            </R10Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
