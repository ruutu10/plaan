<script setup lang="ts">
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import type { PerformanceStaffMember } from '@/types';

/**
 * Who staffs a performance, as the Planka import last read it off the card.
 * Read-only by nature — see App\Services\PerformanceStaffSync, the only place
 * a row here is ever written — so there is nothing to edit, only to show.
 */
defineProps<{ staff: PerformanceStaffMember[] }>();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            Meeskond
        </span>
        <span class="-mt-0.5 text-xs text-r10-grey-500">
            Kes seda etendust teevad, laval ja lava taga. Loetud Planka kaardilt
            viimase impordi käigus — seda ei saa siin muuta, ainult uus import
            uuendab seda.
        </span>

        <p
            v-if="staff.length === 0"
            data-test="performance-staff-empty"
            class="rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 px-4 py-3 text-[13px] text-r10-grey-700"
        >
            Plankast pole selle etenduse kohta meeskonda imporditud.
        </p>

        <ul
            v-else
            class="flex flex-col gap-2 rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 p-3"
        >
            <li
                v-for="member in staff"
                :key="`${member.id}-${member.role}`"
                data-test="performance-staff-row"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-2.5"
            >
                <span class="font-medium text-r10-ink">{{ member.name }}</span>
                <R10Pill tone="neutral">{{ member.roleLabel }}</R10Pill>
            </li>
        </ul>
    </div>
</template>
