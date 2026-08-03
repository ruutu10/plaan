<script setup lang="ts">
import { computed } from 'vue';
import { STEP_LABELS } from './plan';

const props = withDefaults(
    defineProps<{
        /** Current wizard step index (0-based over STEP_LABELS). */
        step: number;
        optionalSteps?: number[];
        /** On the login screen: step 0 is active and every wizard step is disabled. */
        loginActive?: boolean;
        /** Whether the wizard step nodes navigate when clicked. */
        wizardClickable?: boolean;
        /** Whether step 0 opens the login screen when clicked. */
        loginClickable?: boolean;
        /** Whether to show the "start over" link. */
        showReset?: boolean;
    }>(),
    {
        optionalSteps: () => [],
        loginActive: false,
        wizardClickable: true,
        loginClickable: false,
        showReset: true,
    },
);

const emit = defineEmits<{ go: [index: number]; login: []; reset: [] }>();

const LOGIN_LABEL = 'Plaani koostaja';

// The login step sits at position 0; the wizard steps follow at 1…N.
const TOTAL_POSITIONS = STEP_LABELS.length + 1;

type StepNode = {
    pos: number;
    label: string;
    display: string;
    kind: 'login' | 'wizard';
    wizardIndex: number | null;
    clickable: boolean;
};

const nodes = computed<StepNode[]>(() => {
    const list: StepNode[] = [
        {
            pos: 0,
            label: LOGIN_LABEL,
            display: '0',
            kind: 'login',
            wizardIndex: null,
            clickable: props.loginClickable && !props.loginActive,
        },
    ];

    STEP_LABELS.forEach((label, index) => {
        list.push({
            pos: index + 1,
            label,
            display: String(index + 1),
            kind: 'wizard',
            wizardIndex: index,
            clickable: props.wizardClickable && !props.loginActive,
        });
    });

    return list;
});

// The furthest position the user has reached. Login active pins it to 0.
const activePosition = computed(() => (props.loginActive ? 0 : props.step + 1));

const progress = computed(() =>
    Math.round((activePosition.value / (TOTAL_POSITIONS - 1)) * 100),
);

function stateOf(pos: number): 'active' | 'done' | 'todo' {
    if (pos === activePosition.value) {
        return 'active';
    }

    return pos < activePosition.value ? 'done' : 'todo';
}

function isOptional(wizardIndex: number | null): boolean {
    return (
        wizardIndex !== null && props.optionalSteps.includes(wizardIndex + 1)
    );
}

function circleClasses(
    node: StepNode,
): Array<string | Record<string, boolean>> {
    const state = stateOf(node.pos);

    if (node.kind === 'login') {
        // Once logged in, step 0 stays visible but greyed out.
        return [
            state === 'active'
                ? 'border-r10-orange bg-r10-orange text-r10-navy'
                : 'border-r10-grey-200 bg-r10-grey-100 text-r10-grey-500',
        ];
    }

    return [
        {
            'border-r10-orange bg-r10-orange text-r10-navy': state === 'active',
            'border-r10-navy bg-r10-navy text-white': state === 'done',
            'border-r10-grey-200 bg-white text-r10-grey-500': state === 'todo',
            'border-dashed': isOptional(node.wizardIndex),
        },
    ];
}

function labelClasses(node: StepNode): string {
    const state = stateOf(node.pos);

    if (node.kind === 'login') {
        return state === 'active' ? 'text-r10-ink' : 'text-r10-grey-500';
    }

    return state === 'todo' ? 'text-r10-grey-500' : 'text-r10-ink';
}

/** The step the compact phone strip names beneath its row of numbers. */
const activeLabel = computed(
    () => nodes.value[activePosition.value]?.label ?? '',
);

const resetClass =
    'cursor-pointer border-none bg-transparent py-1.5 font-r10-body text-xs font-bold tracking-[0.06em] text-r10-grey-500 uppercase underline';

function onNodeClick(node: StepNode): void {
    if (!node.clickable) {
        return;
    }

    if (node.wizardIndex === null) {
        emit('login');

        return;
    }

    emit('go', node.wizardIndex);
}
</script>

<template>
    <aside
        class="r10-no-print flex w-full flex-col gap-0 lg:sticky lg:top-24 lg:w-[190px] lg:shrink-0"
    >
        <div class="mb-3.5">
            <div class="h-[5px] overflow-hidden rounded-full bg-r10-grey-200">
                <div
                    class="h-full rounded-full bg-r10-orange transition-[width] duration-300"
                    :style="{ width: progress + '%' }"
                />
            </div>
            <div
                class="mt-2 text-xs font-bold tracking-[0.1em] text-r10-grey-500 uppercase"
            >
                {{ progress }}% täidetud
            </div>
        </div>

        <!-- On a phone the rail would push the step itself off the screen, so
             the same nodes become a row of numbers with the current step named
             underneath. Every node still navigates. -->
        <div class="lg:hidden">
            <div class="flex items-center overflow-x-auto pb-1">
                <template v-for="node in nodes" :key="node.pos">
                    <span
                        v-if="node.pos > 0"
                        class="h-0.5 w-2 shrink-0 sm:w-4"
                        :class="
                            node.pos <= activePosition
                                ? 'bg-r10-navy'
                                : 'bg-r10-grey-200'
                        "
                    />
                    <button
                        type="button"
                        :disabled="!node.clickable"
                        :title="node.label"
                        :aria-label="node.label"
                        :aria-current="
                            node.pos === activePosition ? 'step' : undefined
                        "
                        :class="[
                            'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 font-r10-display text-[13px] font-extrabold transition-colors',
                            node.clickable
                                ? 'cursor-pointer'
                                : 'cursor-default',
                            circleClasses(node),
                        ]"
                        @click="onNodeClick(node)"
                    >
                        {{ node.display }}
                    </button>
                </template>
            </div>

            <div class="mt-2 flex items-center justify-between gap-3">
                <span
                    class="min-w-0 font-r10-body text-sm font-bold tracking-[0.02em] text-r10-ink"
                >
                    {{ activeLabel }}
                </span>
                <button
                    v-if="showReset"
                    type="button"
                    :class="[resetClass, 'shrink-0 px-0']"
                    @click="emit('reset')"
                >
                    Alusta otsast
                </button>
            </div>
        </div>

        <button
            v-for="node in nodes"
            :key="node.pos"
            type="button"
            :disabled="!node.clickable"
            :class="[
                'hidden min-h-[46px] w-full items-stretch gap-3 rounded-[10px] border-none px-2.5 py-0.5 text-left transition-colors lg:flex',
                stateOf(node.pos) === 'active'
                    ? 'bg-r10-orange-100'
                    : 'bg-transparent',
                node.clickable ? 'cursor-pointer' : 'cursor-default',
            ]"
            @click="onNodeClick(node)"
        >
            <span class="flex w-7 shrink-0 flex-col items-center self-stretch">
                <span
                    class="w-0.5 flex-1"
                    :class="
                        node.pos <= activePosition
                            ? 'bg-r10-navy'
                            : 'bg-r10-grey-200'
                    "
                    :style="{
                        visibility: node.pos === 0 ? 'hidden' : 'visible',
                    }"
                />
                <span
                    class="z-[1] inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 font-r10-display text-[13px] font-extrabold"
                    :class="circleClasses(node)"
                >
                    {{ node.display }}
                </span>
                <span
                    class="w-0.5 flex-1"
                    :class="
                        node.pos < activePosition
                            ? 'bg-r10-navy'
                            : 'bg-r10-grey-200'
                    "
                    :style="{
                        visibility:
                            node.pos === TOTAL_POSITIONS - 1
                                ? 'hidden'
                                : 'visible',
                    }"
                />
            </span>
            <span
                class="flex items-center font-r10-body text-sm font-bold tracking-[0.02em]"
                :class="labelClasses(node)"
            >
                {{ node.label }}
            </span>
        </button>

        <button
            v-if="showReset"
            type="button"
            :class="[resetClass, 'mt-4 hidden self-start px-0 lg:block']"
            @click="emit('reset')"
        >
            Alusta otsast
        </button>
    </aside>
</template>
