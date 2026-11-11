<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import R10Page from '@/components/technical-plan/R10Page.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import R10Table from '@/components/technical-plan/R10Table.vue';
import StepHeader from '@/components/technical-plan/StepHeader.vue';
import TextLink from '@/components/TextLink.vue';
import { formatLocalTimestamp } from '@/lib/date';
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
    mail_sent: { label: 'E-kiri saadetud', tone: 'muted' },
    role_granted: { label: 'Roll antud', tone: 'navy' },
    role_revoked: { label: 'Roll eemaldatud', tone: 'accent' },
    staff_added: { label: 'Koosseisu lisatud', tone: 'neutral' },
    staff_removed: { label: 'Koosseisust eemaldatud', tone: 'neutral' },
    staff_synced: { label: 'Koosseis Plankast', tone: 'muted' },
    commented: { label: 'Kommenteeritud', tone: 'muted' },
    comment_deleted: { label: 'Kommentaar kustutatud', tone: 'neutral' },
    planka_reimport_requested: { label: 'Planka import', tone: 'muted' },
    copied: { label: 'Kopeeritud', tone: 'muted' },
    plot_summary_generated: {
        label: 'Sisukokkuvõte kirjutatud',
        tone: 'muted',
    },
    plot_summary_edited: { label: 'Sisukokkuvõte muudetud', tone: 'neutral' },
    plot_summary_abandoned: {
        label: 'Sisukokkuvõttest loobutud',
        tone: 'muted',
    },
    plot_summary_failed: { label: 'Sisukokkuvõte ebaõnnestus', tone: 'accent' },
    plot_summary_regeneration_requested: {
        label: 'Uus sisukokkuvõte tellitud',
        tone: 'muted',
    },
    password_changed: { label: 'Parool muudetud', tone: 'accent' },
    password_reset: { label: 'Parool lähtestatud', tone: 'accent' },
    two_factor_enabled: { label: '2FA sisse lülitatud', tone: 'neutral' },
    two_factor_confirmed: { label: '2FA kinnitatud', tone: 'neutral' },
    two_factor_disabled: { label: '2FA välja lülitatud', tone: 'accent' },
    recovery_codes_regenerated: {
        label: 'Taastekoodid uuendatud',
        tone: 'neutral',
    },
    passkey_added: { label: 'Pääsuvõti lisatud', tone: 'neutral' },
    passkey_removed: { label: 'Pääsuvõti eemaldatud', tone: 'accent' },
};

function eventLabel(event: string | null): string {
    return event ? (EVENT_LABELS[event]?.label ?? event) : '—';
}

function eventTone(
    event: string | null,
): 'muted' | 'neutral' | 'accent' | 'navy' {
    return (event && EVENT_LABELS[event]?.tone) || 'muted';
}

/**
 * What the row's record reads by: its own name, or its bare id when the record
 * is one the feed does not name — or one since deleted.
 */
function subjectName(entry: AuditLogEntry): string {
    return (
        entry.subjectLabel ??
        (entry.subjectId ? `#${entry.subjectId}` : (entry.subjectType ?? '—'))
    );
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
                { label: 'Päritolu' },
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
                    {{ formatLocalTimestamp(entry.createdAt) }}
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
                        <!-- Through to the record itself, for a reader who may
                             open it; the ones who may not read the same name as
                             plain text. -->
                        <TextLink
                            v-if="entry.subjectUrl"
                            :href="entry.subjectUrl"
                            class="block"
                            data-test="audit-log-subject-link"
                        >
                            {{ subjectName(entry) }}
                        </TextLink>
                        <span v-else class="block text-r10-ink">
                            {{ subjectName(entry) }}
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
                <td class="px-5 py-4 align-top" data-test="audit-log-origin">
                    <template v-if="entry.ipAddress || entry.userAgent">
                        <span
                            class="block font-mono whitespace-nowrap text-r10-ink"
                        >
                            {{ entry.ipAddress ?? '—' }}
                        </span>
                        <!-- A user-agent runs long; the row shows its start and
                             the whole of it on hover. -->
                        <span
                            v-if="entry.userAgent"
                            class="block max-w-56 truncate text-r10-grey-500"
                            :title="entry.userAgent"
                        >
                            {{ entry.userAgent }}
                        </span>
                    </template>
                    <span v-else class="text-r10-grey-500">—</span>
                </td>
            </template>
        </R10Table>
    </R10Page>
</template>
