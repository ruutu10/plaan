# ADR 0003: Sortable, searchable and filterable listing tables

- **Status:** Accepted
- **Date:** 2026-11-20

## Context

Every management screen draws its listing through `R10Table`. That component owned the four states a listing can be in: rows, still loading, failed to load, or empty. It could not sort, search or filter. With the house's bill of performances, plans, accounts and the audit feed growing, people needed to find a row without scrolling.

Before this change the frontend had no table library. The UI is shadcn-vue on reka-ui, styled with the R10 tokens on Tailwind 4. The candidates were:

- **TanStack Table.** Headless: it handles sorting and filtering, and our own markup draws the table. shadcn-vue's data table guide is built on it.
- **PrimeVue DataTable.** Complete, but a second component system next to reka-ui, with its own look to override.
- **AG Grid Community.** Powerful, but a spreadsheet-style grid that is heavy for lists of a few hundred rows.
- **Hand-written sorting and filtering in `R10Table`.** Enough for one search box. It grows awkwardly once filters per column are wanted.

Every listing involved loads its whole collection in one response: performances, users, teams, formats, a format's performances, and plans. The one exception is the audit log, which is capped at the latest `AuditLogController::FEED_SIZE` (200) entries.

## Decision

### TanStack Table v9, behind our own composable

We use `@tanstack/vue-table` v9 (`useTable`, explicit `tableFeatures`). It is headless, so `R10Table` keeps its R10 markup, its four states, the clickable rows and the narrow-screen column hiding. Pages do not touch TanStack directly. They go through `useListingTable` (`resources/js/composables/useListingTable.ts`). That keeps the v9 API in one file, and the sorting and filtering can be unit-tested without a DOM.

v9 renamed much of v8's API. shadcn-vue's guide still shows v8 code, so the v9 skills bundled in `node_modules/@tanstack/vue-table/skills` are the reference.

### Columns opt in

A column in `ListingColumn<T>` gains behaviour only from what it declares:

- `sortBy(row)` makes the header sortable. Numbers compare by size, and text compares with Estonian collation (`Intl.Collator('et', { numeric: true })`). Empty values always sort last. `sortDescFirst` makes the first click sort largest or newest first. Clicking cycles through ascending, descending, then back to the server's own order.
- `searchBy(row)` adds the column's text to one search box above the table. The query matches when every word appears somewhere in the row, ignoring case and diacritics.
- `filterBy(row)` gives the column a dropdown. It offers every distinct value across the rows, and a row may have several (a user's roles, a night's technicians).

A table that declares none of these looks and behaves exactly as before. `R10Table` also takes a `narrowable` prop to switch off search and filters while keeping sorting. A performance's own page uses it for its handful of plans.

### Search is one hidden column

The search box filters a hidden `__search` column whose value joins every `searchBy` text. TanStack's global filter checks each column separately, so "mari tehnik" could never match a name in one column and a role in another.

### Filters sit in the column they control

The dropdowns are a second row inside `<thead>`, one cell per column. So each dropdown shares the width and padding of the column it filters. Only the search box and a page's own controls (the `toolbar` slot) sit above the table.

### Values are worked out where Vue can see them

`useListingTable` calls the column functions inside a Vue `computed`, not inside TanStack's accessors. A column function may read a ref of the page's own. A format's performances, for instance, fall back to the format's team, which can load after them. TanStack caches row values until it is handed a new `data` array, and new columns are not enough. So the table is fed a fresh array whenever any row's values change.

### All in the browser

Sorting, search and filtering run in the browser on the loaded rows. There is no server round trip and no new endpoint.

## Consequences

- **The audit log only searches its latest 200 entries.** Its placeholder and its no-match message say so. Searching the full history would need server-side filtering: query parameters, a controller that filters, and TanStack's `manualFiltering`/`manualSorting`.
- **Sort and filter state is not kept.** It resets when the reader opens a row and comes back. Keeping it in the query string is a natural next step.
- **Each recomputation walks every row.** A change of data, columns or a ref that a column reads recomputes every row's values. That is negligible for hundreds of rows. Lists in the many thousands would want paging, or server-side processing.
- **Each page owns its column definitions.** Pages define `ListingColumn` arrays in `<script setup>`, not inline in the template. Shared ordering is not repeated per page: `performanceStatusRank()` in `lib/performanceStatus.ts`, and `instantSortValue()` in the composable.
- **New dependency:** `@tanstack/vue-table` (and through it `@tanstack/table-core`). There is no CSS, and only the features registered in `useListingTable` are bundled.
