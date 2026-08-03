<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileClock, Pencil } from '@lucide/vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianDate } from '@/lib/date';
import { index } from '@/routes/admin/performances';
import { edit } from '@/routes/shows';
import type { AdminPerformanceRow } from '@/types';

defineProps<{ performances: AdminPerformanceRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Etendused',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Etendused" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            title="Etendused"
            lead="Kõik maja etendused, olenemata lavastusest ja tiimist. Muutmiseks ava lavastus."
        />

        <R10Table
            :columns="[
                { label: 'Algus' },
                { label: 'Lavastus' },
                { label: 'Etteaste' },
                { label: 'Kestus' },
                { label: 'Olek' },
                { label: 'Tehnikaplaane' },
                { label: 'Tegevused', align: 'right', srOnly: true },
            ]"
            :rows="performances"
            row-test-id="admin-performance-row"
            empty-text="Ühtegi etendust pole veel sisestatud."
            error-text="Etenduste laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: performance }">
                <td
                    class="px-5 py-4 align-top font-medium whitespace-nowrap text-r10-ink"
                >
                    {{ formatEstonianDate(performance.date) }}
                    <span class="text-r10-grey-500">
                        {{ performance.startTime }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top">
                    <span
                        class="font-r10-display text-base font-semibold text-r10-ink"
                    >
                        {{ performance.showName }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top">
                    <span
                        v-if="performance.title"
                        class="block text-r10-ink"
                        data-test="admin-performance-title"
                    >
                        {{ performance.title }}
                    </span>
                    <span
                        class="block text-r10-grey-500"
                        data-test="admin-performance-team"
                    >
                        {{ performance.teamName ?? '—' }}
                    </span>
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    {{
                        performance.duration
                            ? `${performance.duration} min`
                            : '—'
                    }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <R10Pill
                        v-if="performance.isDraft"
                        tone="accent"
                        data-test="admin-performance-draft-badge"
                        title="Ülevaatamata etendust ei pakuta tehnikaplaani koostajale."
                        class="border-transparent"
                    >
                        <FileClock class="h-3.5 w-3.5" />
                        Ülevaatamata
                    </R10Pill>
                    <span
                        v-else
                        class="font-r10-body text-[11px] font-bold tracking-[0.08em] text-r10-grey-500 uppercase"
                    >
                        Kinnitatud
                    </span>
                </td>
                <td class="px-5 py-4 align-top tabular-nums">
                    {{ performance.technicalPlanCount ?? 0 }}
                </td>
                <td class="px-5 py-4 text-right align-top">
                    <!-- A performance is corrected on the show it hangs off,
                         which is the one page that knows the whole bill. -->
                    <R10Button
                        variant="outline"
                        size="sm"
                        :href="edit(performance.showId).url"
                        data-test="admin-performance-edit-link"
                        class="px-4 py-2"
                    >
                        Muuda
                        <Pencil class="h-3.5 w-3.5" />
                    </R10Button>
                </td>
            </template>
        </R10Table>
    </R10Page>
</template>
