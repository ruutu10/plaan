<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import { formatEstonianTimestamp } from '@/lib/date';
import { index } from '@/routes/admin/audit-log';
import type { AuditLogEntry } from '@/types';

defineProps<{ entries: AuditLogEntry[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tegevuslogi',
                href: index(),
            },
        ],
    },
});

/** How an event reads in Estonian, and how much it should stand out. */
const EVENT_LABELS: Record<
    string,
    { label: string; tone: 'muted' | 'neutral' | 'accent' | 'navy' }
> = {
    created: { label: 'Loodud', tone: 'navy' },
    updated: { label: 'Muudetud', tone: 'neutral' },
    deleted: { label: 'Kustutatud', tone: 'accent' },
    restored: { label: 'Taastatud', tone: 'neutral' },
    submitted: { label: 'Esitatud', tone: 'navy' },
    status_changed: { label: 'Oleku muutus', tone: 'neutral' },
};

function eventLabel(event: string | null): string {
    return event ? (EVENT_LABELS[event]?.label ?? event) : '—';
}

function eventTone(
    event: string | null,
): 'muted' | 'neutral' | 'accent' | 'navy' {
    return (event && EVENT_LABELS[event]?.tone) || 'muted';
}
</script>

<template>
    <Head title="Tegevuslogi" />

    <R10Page>
        <StepHeader
            eyebrow="Haldus"
            title="Tegevuslogi"
            lead="Maja tegevuslugu: kontode, formaatide, etenduste, tiimide ja tehnikaplaanide loomine ning olulised muudatused. Uuemad kirjed eespool."
        />

        <R10Table
            :columns="[
                { label: 'Aeg' },
                { label: 'Sündmus' },
                { label: 'Kirjeldus' },
                { label: 'Objekt' },
                { label: 'Tegija' },
            ]"
            :rows="entries"
            row-test-id="audit-log-row"
            empty-text="Tegevuslogis pole veel ühtegi kirjet."
            error-text="Tegevuslogi laadimine ebaõnnestus. Proovi lehte värskendada."
        >
            <template #row="{ row: entry }">
                <td
                    class="px-5 py-4 align-top font-medium whitespace-nowrap text-r10-ink"
                >
                    {{ formatEstonianTimestamp(entry.createdAt) }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <R10Pill :tone="eventTone(entry.event)">
                        {{ eventLabel(entry.event) }}
                    </R10Pill>
                </td>
                <td class="px-5 py-4 align-top text-r10-ink">
                    {{ entry.description }}
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <template v-if="entry.subjectType">
                        <span class="block text-r10-ink">
                            {{
                                entry.subjectLabel ??
                                (entry.subjectId
                                    ? `#${entry.subjectId}`
                                    : entry.subjectType)
                            }}
                        </span>
                        <span class="block text-r10-grey-500">
                            {{ entry.subjectType }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-r10-grey-500">—</span>
                    </template>
                </td>
                <td class="px-5 py-4 align-top whitespace-nowrap">
                    <span v-if="entry.causerName" class="text-r10-ink">
                        {{ entry.causerName }}
                    </span>
                    <R10Pill
                        v-else
                        tone="muted"
                        data-test="audit-log-system-actor"
                    >
                        Süsteem
                    </R10Pill>
                </td>
            </template>
        </R10Table>
    </R10Page>
</template>
