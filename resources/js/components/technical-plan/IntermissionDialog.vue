<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Scene } from '@/types/technicalPlan';
import {
    actMinutes,
    intermissionMinutes,
    readIntermissionForm,
} from './intermissions';
import { usePlan, useWizardConfig } from './planKey';
import R10FormDialog from './R10FormDialog.vue';
import R10Input from './R10Input.vue';

/**
 * Writing an interval's two lengths. Open while `editing` holds an interval:
 * either one already in the running order, or a fresh one held aside until it
 * is saved, so cancelling leaves the running order untouched.
 */
const editing = defineModel<Scene | null>({ required: true });

const plan = usePlan();
const config = useWizardConfig();

const minutesInput = ref('15');

/** How long the part of the show this interval ends runs, as typed. */
const actInput = ref('');

const minutesError = ref('');
const actError = ref('');

/**
 * The show's own running time, which the closing part is worked out from. Read
 * off the performance rather than the plan, so it is only missing on a night
 * nobody has timed.
 */
const showMinutes = computed(() => plan.meta.duration ?? 0);

const open = computed({
    get: () => editing.value !== null,
    set: (isOpen: boolean) => {
        if (!isOpen) {
            editing.value = null;
        }
    },
});

/** Start each opening from the interval's own lengths, not the last attempt. */
watch(editing, (scene) => {
    if (!scene) {
        return;
    }

    const act = actMinutes(scene);

    minutesInput.value = String(intermissionMinutes(scene));
    actInput.value = act === null ? '' : String(act);
    minutesError.value = '';
    actError.value = '';
});

function save(): void {
    const scene = editing.value;

    if (!scene) {
        return;
    }

    const { minutes, act, errors } = readIntermissionForm(
        minutesInput.value,
        actInput.value,
        config,
    );

    minutesError.value = errors.minutes;
    actError.value = errors.act;

    if (minutes === null || act === null) {
        return;
    }

    scene.intermission = minutes;
    scene.actMinutes = act;

    // A new interval joins the running order at the end, where "lisa stseen"
    // puts a scene too; from there it is dragged into place.
    if (!plan.scenes.includes(scene)) {
        plan.scenes.push(scene);
    }

    editing.value = null;
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Vaheaeg"
        description="Kui pikk on vaheaeg? Vaheaeg jagab etenduse kaheks pooleks; lisatud vaheaja saad lohistades õigesse kohta tõsta."
        submit-label="Salvesta"
        test-id-prefix="intermission"
        @submit="save"
    >
        <R10Input
            v-model="minutesInput"
            type="number"
            min="1"
            :max="config.maxIntermissionMinutes"
            required
            label="Vaheaja pikkus minutites"
            placeholder="15"
            :error="minutesError"
            error-test-id="intermission-minutes-error"
        />
        <!-- Required: the length of the part an interval ends is the one
             the reader cannot work out afterwards, so the performer is
             asked for it here rather than chased for it later. -->
        <R10Input
            v-model="actInput"
            type="number"
            min="1"
            :max="config.maxActMinutes"
            required
            label="Eelneva osa pikkus minutites"
            :hint="
                showMinutes > 0
                    ? `Kui pikk on etenduse osa, mis selle vaheajaga lõpeb? Kogu etendus kestab ${showMinutes} min; viimase osa pikkuse arvutame ise.`
                    : 'Kui pikk on etenduse osa, mis selle vaheajaga lõpeb?'
            "
            placeholder="nt 25"
            :error="actError"
            error-test-id="intermission-act-error"
        />
    </R10FormDialog>
</template>
