<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import { updatePerformance } from '@/routes/technical-plans';
import type { PerformanceOption } from '@/types/technicalPlan';

/**
 * Files a plan under a different night. Written for the plan that came in
 * before its evening was on the books — under the stand-in performance — and is
 * moved to the real one once it is, but it serves a plan filed under the wrong
 * night just as well.
 */
const props = defineProps<{
    /** The plan being moved, by the key its own routes are keyed on. */
    planToken: string;
    /** The night it is filed under now, or null once that has been put aside. */
    performanceId: number | null;
    /** The nights it may be moved to. */
    performances: PerformanceOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ performance_id: number | null }>({
    performance_id: null,
});

/**
 * Start from the night the plan is filed under every time the dialog opens: it
 * is mounted with the page rather than with the plan, so a choice made and
 * abandoned once must not still be showing the next time it is opened.
 */
function selectTheCurrentNight(): void {
    form.resetAndClearErrors();
    form.performance_id =
        props.performanceId ?? props.performances[0]?.value ?? null;
}

function save(): void {
    form.submit(updatePerformance(props.planToken), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            toast.success('Plaan tõsteti teise etenduse alla.');
        },
        onError: () => {
            // A refused move leaves its field error on the form; anything else
            // is shown as a plain failure rather than passing silently.
            if (!form.errors.performance_id) {
                toast.error('Etenduse muutmine ebaõnnestus. Proovi uuesti.');
            }
        },
    });
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Muuda etendust"
        description="Vali etendus, mille alla see tehnikaplaan kuulub. Nimekirjas on tulevased etendused ja viimane kuu."
        submit-label="Salvesta"
        :processing="form.processing"
        test-id-prefix="plan-performance"
        @opened="selectTheCurrentNight"
        @submit="save"
    >
        <R10Select
            v-model="form.performance_id"
            label="Etendus"
            required
            :options="performances"
            :error="form.errors.performance_id"
            error-test-id="plan-performance-error"
            data-test="plan-performance-select"
        />
    </R10FormDialog>
</template>
