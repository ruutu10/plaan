<script setup lang="ts" generic="T">
/**
 * The listing table every management screen uses, together with the four states
 * a listing is ever in: rows, still loading, failed to load, or nothing to
 * show. Each screen had its own copy of all four, which is how the wording of
 * an empty state came to differ from page to page.
 *
 * `rows` is null until the first response lands — that is what the skeleton
 * keys off, so a page passes its ref straight through.
 */
withDefaults(
    defineProps<{
        columns: {
            label: string;
            align?: 'left' | 'center' | 'right';
            /** Header text kept for screen readers only, e.g. an actions column. */
            srOnly?: boolean;
        }[];
        rows: T[] | null;
        loadFailed?: boolean;
        emptyText: string;
        errorText: string;
        /** Stand-in rows shown while the first response is in flight. */
        skeletonRows?: number;
        /**
         * Width of the stand-in bar per column, so the skeleton is the shape of
         * the real rows. The last entry repeats for any remaining columns.
         */
        skeletonWidths?: string[];
        rowTestId?: string;
        skeletonTestId?: string;
    }>(),
    {
        loadFailed: false,
        skeletonRows: 3,
        skeletonWidths: () => ['w-48', 'w-16'],
    },
);

const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};
</script>

<template>
    <div
        class="overflow-x-auto rounded-xl border-2 border-r10-grey-200 bg-white"
    >
        <table class="w-full border-collapse text-left text-sm">
            <thead class="border-b-2 border-r10-navy">
                <tr
                    class="font-r10-body text-[11px] font-bold tracking-[0.12em] text-r10-navy uppercase"
                >
                    <th
                        v-for="(column, index) in columns"
                        :key="index"
                        class="px-5 py-3.5"
                        :class="alignClass[column.align ?? 'left']"
                    >
                        <span v-if="column.srOnly" class="sr-only">{{
                            column.label
                        }}</span>
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in rows ?? []"
                    :key="index"
                    :data-test="rowTestId"
                    class="border-b border-r10-grey-200 transition-colors last:border-0 hover:bg-r10-grey-100"
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
                        v-for="(column, index) in columns"
                        :key="index"
                        class="px-5 py-4"
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
            </tbody>
        </table>
    </div>
</template>
