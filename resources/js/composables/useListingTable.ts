import {
    columnFilteringFeature,
    createFilteredRowModel,
    createSortedRowModel,
    rowSortingFeature,
    tableFeatures,
    useTable,
} from '@tanstack/vue-table';
import type { RowData } from '@tanstack/vue-table';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { computed, toValue } from 'vue';

/** A value a column sorts by. Empty values always sort after the rest. */
export type SortValue = string | number | null | undefined;

/** What a column offers the search box, or the filter dropdown, for one row. */
export type ListingText =
    string | null | undefined | (string | null | undefined)[];

/**
 * One column of a listing table. Leaving out `sortBy`, `searchBy` and
 * `filterBy` gives a plain column, so a table that declares none of them reads
 * exactly as it did before sorting existed.
 */
export type ListingColumn<T> = {
    label: string;
    align?: 'left' | 'center' | 'right';
    /** Header text kept for screen readers only, e.g. an actions column. */
    srOnly?: boolean;
    /**
     * Extra classes for the column's header and skeleton cells, e.g.
     * `hidden lg:table-cell` to drop it on narrow screens. The page's own row
     * cells need the same classes.
     */
    class?: string;
    /** Names the column for `defaultSort`; its position stands in otherwise. */
    id?: string;
    /** The value the column sorts by. A column with one has a sortable header. */
    sortBy?: (row: T) => SortValue;
    /** Whether the first click sorts largest first, e.g. a date wanted newest first. */
    sortDescFirst?: boolean;
    /** The text the search box matches the row by in this column. */
    searchBy?: (row: T) => ListingText;
    /**
     * The value or values the row has for this column's filter dropdown. The
     * dropdown offers every distinct one across the rows.
     */
    filterBy?: (row: T) => ListingText;
};

/** Which column a table is sorted by before anybody clicks a header. */
export type ListingSort = { id: string; desc?: boolean };

/** The hidden column the search box filters by, holding every searchable text. */
const SEARCH_COLUMN_ID = '__search';

/** Folds case and diacritics, so «oolo» finds «Õõlo» and «Müür» finds «muur». */
export function foldForSearch(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('et');
}

function texts(value: ListingText): string[] {
    const values = Array.isArray(value) ? value : [value];

    return values.filter(
        (entry): entry is string => typeof entry === 'string' && entry !== '',
    );
}

/** Whether every word of the query appears somewhere in the row's text. */
export function matchesSearch(haystack: string, query: string): boolean {
    const words = foldForSearch(query).split(/\s+/).filter(Boolean);
    const folded = foldForSearch(haystack);

    return words.every((word) => folded.includes(word));
}

const collator = new Intl.Collator('et', {
    numeric: true,
    sensitivity: 'base',
});

/** An ISO 8601 instant as a sortable number; a missing one sorts last. */
export function instantSortValue(
    iso: string | null | undefined,
): number | undefined {
    return iso ? Date.parse(iso) : undefined;
}

/** Ascending comparison: numbers by size, text the way Estonian orders it. */
export function compareSortValues(left: SortValue, right: SortValue): number {
    if (typeof left === 'number' && typeof right === 'number') {
        return left - right;
    }

    return collator.compare(String(left ?? ''), String(right ?? ''));
}

/**
 * The state behind a listing table: what it is sorted by, what the search box
 * and the filter dropdowns hold, and the rows left to show. The rows arrive
 * whole from the server, so the narrowing all happens here in the browser.
 */
export function useListingTable<T>(options: {
    rows: MaybeRefOrGetter<T[] | null>;
    columns: MaybeRefOrGetter<ListingColumn<T>[]>;
    /** False sets the columns' `searchBy` and `filterBy` aside; sorting stays. */
    narrowable?: MaybeRefOrGetter<boolean>;
    defaultSort?: ListingSort;
}) {
    const features = tableFeatures({
        columnFilteringFeature,
        filteredRowModel: createFilteredRowModel(),
        rowSortingFeature,
        sortedRowModel: createSortedRowModel(),
    });

    const listingColumns = computed(() => {
        const narrowable = toValue(options.narrowable) ?? true;

        return toValue(options.columns).map((column, index) => ({
            ...column,
            // Its position stands in for an id the page did not give it.
            id: column.id ?? `column-${index}`,
            searchBy: narrowable ? column.searchBy : undefined,
            filterBy: narrowable ? column.filterBy : undefined,
        }));
    });

    const searchableColumns = computed(() =>
        listingColumns.value.filter((column) => column.searchBy),
    );

    const filterableColumns = computed(() =>
        listingColumns.value.filter((column) => column.filterBy),
    );

    const data = computed(() => toValue(options.rows) ?? []);

    /**
     * Every row's sort, filter and search values, worked out where Vue can see
     * them. A column function may read a ref of the page's own — the format's
     * team, say — and the table has to notice when that changes, which it
     * would not if it called the functions itself and cached their answers.
     */
    const rowValues = computed(() => {
        const values = new Map<
            T,
            {
                sort: Record<string, SortValue>;
                filter: Record<string, string[]>;
                search: string;
            }
        >();

        for (const row of data.value) {
            const sort: Record<string, SortValue> = {};
            const filter: Record<string, string[]> = {};

            for (const column of listingColumns.value) {
                sort[column.id] = column.sortBy?.(row) ?? undefined;

                if (column.filterBy) {
                    filter[column.id] = texts(column.filterBy(row));
                }
            }

            const search = searchableColumns.value
                .flatMap((column) => texts(column.searchBy?.(row)))
                .join(' ');

            values.set(row, { sort, filter, search });
        }

        return values;
    });

    /**
     * The rows as the table is handed them. The table caches each row's values
     * until it is given a new array — new columns are not enough — so the
     * array is drawn afresh from the values whenever any of them change.
     */
    const tableData = computed(() => [...rowValues.value.keys()]);

    const tableColumns = computed(() => {
        const values = rowValues.value;

        return [
            ...listingColumns.value.map((column) => ({
                id: column.id,
                accessorFn: (row: T) => values.get(row)?.sort[column.id],
                enableSorting: column.sortBy !== undefined,
                sortDescFirst: column.sortDescFirst ?? false,
                sortUndefined: 'last' as const,
                sortFn: (
                    left: { getValue: (id: string) => unknown },
                    right: { getValue: (id: string) => unknown },
                    id: string,
                ) =>
                    compareSortValues(
                        left.getValue(id) as SortValue,
                        right.getValue(id) as SortValue,
                    ),
                enableColumnFilter: column.filterBy !== undefined,
                filterFn: (
                    row: { original: T },
                    _id: string,
                    chosen: string,
                ): boolean =>
                    values
                        .get(row.original)
                        ?.filter[column.id]?.includes(chosen) ?? false,
            })),
            {
                id: SEARCH_COLUMN_ID,
                accessorFn: (row: T) => values.get(row)?.search ?? '',
                enableSorting: false,
                filterFn: (
                    row: { getValue: (id: string) => unknown },
                    id: string,
                    query: string,
                ): boolean => matchesSearch(String(row.getValue(id)), query),
            },
        ];
    });

    const table = useTable({
        features,
        // The column objects carry their own sort and filter functions, which
        // the inferred column-def type has no way to express.
        columns: tableColumns as never,
        // The adapter unwraps any ref, but types `data` as `MaybeRef`, which
        // leaves a read-only computed out.
        data: tableData as unknown as Ref<RowData[]>,
        enableSortingRemoval: true,
        initialState: {
            sorting: options.defaultSort
                ? [
                      {
                          id: options.defaultSort.id,
                          desc: options.defaultSort.desc ?? false,
                      },
                  ]
                : [],
        },
    });

    /** The rows that survive the search and filters, in sorted order. */
    const visibleRows = computed(() =>
        table.getRowModel().rows.map((row) => ({
            key: row.id,
            row: row.original as T,
        })),
    );

    const search = computed({
        get: () =>
            (table.getColumn(SEARCH_COLUMN_ID)?.getFilterValue() as
                string | undefined) ?? '',
        set: (query: string) =>
            table
                .getColumn(SEARCH_COLUMN_ID)
                ?.setFilterValue(query.trim() === '' ? undefined : query),
    });

    function filterChoice(id: string): string {
        return (
            (table.getColumn(id)?.getFilterValue() as string | undefined) ?? ''
        );
    }

    function chooseFilter(id: string, choice: string): void {
        table.getColumn(id)?.setFilterValue(choice === '' ? undefined : choice);
    }

    /** Every distinct value a filter column holds across all rows, in order. */
    function filterOptions(id: string): string[] {
        const values = new Set(
            [...rowValues.value.values()].flatMap(
                (row) => row.filter[id] ?? [],
            ),
        );

        return [...values].sort((left, right) => collator.compare(left, right));
    }

    function sortDirection(id: string): false | 'asc' | 'desc' {
        return table.getColumn(id)?.getIsSorted() ?? false;
    }

    /** Steps the column through its own first order, the other, then unsorted. */
    function toggleSort(id: string): void {
        table.getColumn(id)?.getToggleSortingHandler()?.({});
    }

    /** Whether the search or a filter is hiding any of the rows. */
    const isNarrowed = computed(
        () => table.atoms.columnFilters.get().length > 0,
    );

    function clearNarrowing(): void {
        table.resetColumnFilters(true);
    }

    return {
        listingColumns,
        visibleRows,
        isSearchable: computed(() => searchableColumns.value.length > 0),
        filterableColumns,
        search,
        filterChoice,
        chooseFilter,
        filterOptions,
        sortDirection,
        toggleSort,
        isNarrowed,
        clearNarrowing,
    };
}
