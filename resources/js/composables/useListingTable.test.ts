import { describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import type { ListingColumn } from './useListingTable';
import {
    compareSortValues,
    foldForSearch,
    instantSortValue,
    matchesSearch,
    useListingTable,
} from './useListingTable';

type Person = {
    name: string;
    email: string;
    roles: string[];
    teamCount: number | null;
    joinedAt: string;
};

const people = (): Person[] => [
    {
        name: 'Ülle Õun',
        email: 'ylle@example.test',
        roles: ['Admin'],
        teamCount: 2,
        joinedAt: '2026-03-01T10:00:00Z',
    },
    {
        name: 'Aadu Tamm',
        email: 'aadu@example.test',
        roles: [],
        teamCount: null,
        joinedAt: '2026-01-15T10:00:00Z',
    },
    {
        name: 'Mari Kask',
        email: 'mari@example.test',
        roles: ['Admin', 'Tehnik'],
        teamCount: 10,
        joinedAt: '2026-07-20T10:00:00Z',
    },
];

const columns: ListingColumn<Person>[] = [
    {
        id: 'name',
        label: 'Kasutaja',
        sortBy: (person) => person.name,
        searchBy: (person) => [person.name, person.email],
    },
    {
        id: 'roles',
        label: 'Rollid',
        filterBy: (person) => person.roles,
        searchBy: (person) => person.roles,
    },
    {
        id: 'teams',
        label: 'Tiime',
        sortBy: (person) => person.teamCount,
        sortDescFirst: true,
    },
    { label: 'Liitunud', sortBy: (person) => person.joinedAt },
    { label: 'Tegevused', srOnly: true },
];

function names(listing: ReturnType<typeof useListingTable<Person>>): string[] {
    return listing.visibleRows.value.map(({ row }) => row.name);
}

describe('foldForSearch', () => {
    it('folds case and Estonian diacritics', () => {
        expect(foldForSearch('Ülle ÕUN Šokk')).toBe('ulle oun sokk');
    });
});

describe('matchesSearch', () => {
    it('needs every word somewhere in the text', () => {
        expect(matchesSearch('Mari Kask mari@example.test', 'kask mari')).toBe(
            true,
        );
        expect(matchesSearch('Mari Kask', 'kask tamm')).toBe(false);
    });

    it('lets an empty query match everything', () => {
        expect(matchesSearch('anything', '   ')).toBe(true);
    });
});

describe('instantSortValue', () => {
    it('orders instants by time, and leaves a missing one empty', () => {
        expect(instantSortValue('2026-01-01T00:00:00Z')).toBeLessThan(
            instantSortValue('2026-01-01T00:00:01Z')!,
        );
        expect(instantSortValue(null)).toBeUndefined();
    });
});

describe('compareSortValues', () => {
    it('compares numbers by size, not as text', () => {
        expect(compareSortValues(2, 10)).toBeLessThan(0);
    });

    it('orders text the Estonian way, ignoring case', () => {
        // Estonian puts Õ, Ä, Ö and Ü after W, so Ü follows Z.
        expect(compareSortValues('Ülle', 'Zoja')).toBeGreaterThan(0);
        expect(compareSortValues('aadu', 'Aadu')).toBe(0);
    });

    it('compares numbers within text by size', () => {
        expect(compareSortValues('Etendus 2', 'Etendus 10')).toBeLessThan(0);
    });
});

describe('useListingTable', () => {
    it('keeps the server order until a column is sorted', () => {
        const listing = useListingTable({ rows: people(), columns });

        expect(names(listing)).toEqual(['Ülle Õun', 'Aadu Tamm', 'Mari Kask']);
    });

    it('shows nothing while the rows have not arrived', () => {
        const listing = useListingTable<Person>({ rows: null, columns });

        expect(listing.visibleRows.value).toEqual([]);
    });

    it('cycles a header through ascending, descending and unsorted', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.toggleSort('name');
        expect(listing.sortDirection('name')).toBe('asc');
        expect(names(listing)).toEqual(['Aadu Tamm', 'Mari Kask', 'Ülle Õun']);

        listing.toggleSort('name');
        expect(listing.sortDirection('name')).toBe('desc');
        expect(names(listing)).toEqual(['Ülle Õun', 'Mari Kask', 'Aadu Tamm']);

        listing.toggleSort('name');
        expect(listing.sortDirection('name')).toBe(false);
        expect(names(listing)).toEqual(['Ülle Õun', 'Aadu Tamm', 'Mari Kask']);
    });

    it('sorts largest first on the first click when the column asks for it', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.toggleSort('teams');

        expect(listing.sortDirection('teams')).toBe('desc');
        expect(names(listing)).toEqual(['Mari Kask', 'Ülle Õun', 'Aadu Tamm']);
    });

    it('puts empty values last in either direction', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.toggleSort('teams');
        expect(names(listing).at(-1)).toBe('Aadu Tamm');

        listing.toggleSort('teams');
        expect(names(listing).at(-1)).toBe('Aadu Tamm');
    });

    it('sorts only one column at a time', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.toggleSort('name');
        listing.toggleSort('column-3');

        expect(listing.sortDirection('name')).toBe(false);
        expect(listing.sortDirection('column-3')).toBe('asc');
        expect(names(listing)).toEqual(['Aadu Tamm', 'Ülle Õun', 'Mari Kask']);
    });

    it('starts from the default sort it is given', () => {
        const listing = useListingTable({
            rows: people(),
            columns,
            defaultSort: { id: 'name', desc: true },
        });

        expect(listing.sortDirection('name')).toBe('desc');
        expect(names(listing)[0]).toBe('Ülle Õun');
    });

    it('leaves a column without sortBy unsortable', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.toggleSort('column-4');

        expect(listing.sortDirection('column-4')).toBe(false);
    });

    it('searches across every searchable column at once', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.search.value = 'mari tehnik';
        expect(names(listing)).toEqual(['Mari Kask']);

        listing.search.value = 'aadu@';
        expect(names(listing)).toEqual(['Aadu Tamm']);

        listing.search.value = 'ulle';
        expect(names(listing)).toEqual(['Ülle Õun']);
    });

    it('ignores what a column does not offer to the search', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.search.value = '2026';

        expect(names(listing)).toEqual([]);
    });

    it('offers every distinct filter value, and filters by it', () => {
        const listing = useListingTable({ rows: people(), columns });

        expect(listing.filterOptions('roles')).toEqual(['Admin', 'Tehnik']);

        listing.chooseFilter('roles', 'Admin');
        expect(listing.filterChoice('roles')).toBe('Admin');
        expect(names(listing)).toEqual(['Ülle Õun', 'Mari Kask']);

        listing.chooseFilter('roles', '');
        expect(names(listing)).toHaveLength(3);
    });

    it('combines the search with the filters', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.chooseFilter('roles', 'Admin');
        listing.search.value = 'kask';

        expect(names(listing)).toEqual(['Mari Kask']);
    });

    it('tells when it is hiding rows, and clears all narrowing at once', () => {
        const listing = useListingTable({ rows: people(), columns });

        expect(listing.isNarrowed.value).toBe(false);

        listing.chooseFilter('roles', 'Tehnik');
        listing.search.value = 'mari';
        expect(listing.isNarrowed.value).toBe(true);

        listing.clearNarrowing();
        expect(listing.isNarrowed.value).toBe(false);
        expect(listing.search.value).toBe('');
        expect(names(listing)).toHaveLength(3);
    });

    it('treats a blank search as no search', () => {
        const listing = useListingTable({ rows: people(), columns });

        listing.search.value = '   ';

        expect(listing.isNarrowed.value).toBe(false);
    });

    it('keeps sorting and filtering rows that arrive later', async () => {
        const rows = ref<Person[] | null>(null);
        const listing = useListingTable({ rows, columns });

        listing.toggleSort('name');
        listing.chooseFilter('roles', 'Admin');

        rows.value = people();
        await nextTick();

        expect(names(listing)).toEqual(['Mari Kask', 'Ülle Õun']);
    });

    it('searches by what the columns offer now, when they are recomputed', async () => {
        const fallbackTeam = ref<string | null>(null);
        const listing = useListingTable({
            rows: people(),
            columns: () => [
                {
                    id: 'team',
                    label: 'Tiim',
                    searchBy: (person: Person) =>
                        person.roles.length > 0 ? null : fallbackTeam.value,
                },
            ],
        });

        listing.search.value = 'improgrupp';
        expect(names(listing)).toEqual([]);

        fallbackTeam.value = 'Improgrupp';
        await nextTick();

        expect(names(listing)).toEqual(['Aadu Tamm']);
    });

    it('offers no search or filters when not narrowable, but still sorts', () => {
        const listing = useListingTable({
            rows: people(),
            columns,
            narrowable: false,
        });

        expect(listing.isSearchable.value).toBe(false);
        expect(listing.filterableColumns.value).toEqual([]);

        listing.toggleSort('name');
        expect(names(listing)).toEqual(['Aadu Tamm', 'Mari Kask', 'Ülle Õun']);
    });

    it('is searchable only when a column offers something to search', () => {
        expect(useListingTable({ rows: [], columns }).isSearchable.value).toBe(
            true,
        );
        expect(
            useListingTable<Person>({
                rows: [],
                columns: [{ label: 'Kasutaja' }],
            }).isSearchable.value,
        ).toBe(false);
    });
});
