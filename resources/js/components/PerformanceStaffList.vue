<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AddPerformanceStaffModal from '@/components/AddPerformanceStaffModal.vue';
import RemovePerformanceStaffModal from '@/components/RemovePerformanceStaffModal.vue';
import R10Button from '@/components/technical-plan/R10Button.vue';
import R10Pill from '@/components/technical-plan/R10Pill.vue';
import type {
    PerformanceStaffMember,
    PerformanceStaffRoleOption,
} from '@/types';

/**
 * Who staffs a performance — read off the Planka card by the import, or added
 * here by hand. The import rewrites only its own rows (see
 * App\Services\PerformanceStaffSync), so only the hand-added ones offer a way
 * off the list. Adding is offered only when the server hands over the jobs to
 * choose from, which it does only for a reader who may change the night.
 */
const props = defineProps<{
    formatId: number;
    performanceId: number;
    staff: PerformanceStaffMember[];
    roles: PerformanceStaffRoleOption[];
}>();

const emit = defineEmits<{ changed: [] }>();

const addModalOpen = ref(false);
const removeModalOpen = ref(false);
const removing = ref<PerformanceStaffMember | null>(null);

const canManage = computed(() => props.roles.length > 0);

function confirmRemoval(member: PerformanceStaffMember): void {
    removing.value = member;
    removeModalOpen.value = true;
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span
                class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
            >
                Meeskond
            </span>
            <R10Button
                v-if="canManage"
                variant="outline"
                size="sm"
                data-test="add-staff-button"
                @click="addModalOpen = true"
            >
                <Plus class="h-3.5 w-3.5" />
                Lisa meeskonnaliige
            </R10Button>
        </div>
        <span class="-mt-0.5 text-xs text-r10-grey-500">
            Kes seda etendust teevad, laval ja lava taga. Planka kaardilt
            imporditud meeskonda uuendab ainult uus import; käsitsi lisatud
            inimesi import ei muuda.
        </span>

        <p
            v-if="staff.length === 0"
            data-test="performance-staff-empty"
            class="rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 px-4 py-3 text-[13px] text-r10-grey-700"
        >
            Selle etenduse meeskonda pole kedagi lisatud.
        </p>

        <ul
            v-else
            class="flex flex-col gap-2 rounded-lg border-2 border-r10-grey-200 bg-r10-grey-100 p-3"
        >
            <li
                v-for="member in staff"
                :key="member.staffingId"
                data-test="performance-staff-row"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-2.5"
            >
                <span class="font-medium text-r10-ink">{{ member.name }}</span>
                <span class="flex items-center gap-2">
                    <R10Pill tone="neutral">{{ member.roleLabel }}</R10Pill>
                    <span
                        class="text-[11px] text-r10-grey-500"
                        data-test="performance-staff-origin"
                    >
                        {{ member.manuallyAdded ? 'Käsitsi' : 'Planka' }}
                    </span>
                    <button
                        v-if="member.manuallyAdded && canManage"
                        type="button"
                        class="rounded p-1 text-r10-grey-500 hover:bg-r10-grey-100 hover:text-r10-orange-700"
                        :aria-label="`Eemalda ${member.name}`"
                        data-test="remove-staff-button"
                        @click="confirmRemoval(member)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </span>
            </li>
        </ul>

        <AddPerformanceStaffModal
            v-model:open="addModalOpen"
            :format-id="formatId"
            :performance-id="performanceId"
            :roles="roles"
            @added="emit('changed')"
        />

        <RemovePerformanceStaffModal
            v-model:open="removeModalOpen"
            :format-id="formatId"
            :performance-id="performanceId"
            :member="removing"
            @removed="emit('changed')"
        />
    </div>
</template>
