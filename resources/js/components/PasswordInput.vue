<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A password field wearing {@see R10Input}'s styling with a show/hide toggle.
 * The markup is repeated rather than wrapping `R10Input`, which has no slot for
 * a control inside the box.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
    label?: string;
    required?: boolean;
    error?: string;
    errorTestId?: string;
}>();

const showPassword = ref(false);
const inputRef = useTemplateRef<HTMLInputElement>('inputRef');

defineExpose({
    $el: inputRef,
    focus: () => inputRef.value?.focus(),
});
</script>

<template>
    <label :class="cn('flex flex-col gap-1.5', props.class)">
        <span
            v-if="label"
            class="font-r10-body text-xs font-bold tracking-[0.12em] text-r10-ink uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-r10-orange">*</span>
        </span>

        <span class="relative block">
            <input
                ref="inputRef"
                :type="showPassword ? 'text' : 'password'"
                :required="required"
                class="w-full rounded-lg border-2 border-r10-grey-200 bg-white py-3 pr-12 pl-4 font-r10-body text-[15px] text-r10-ink outline-none focus:border-r10-orange"
                v-bind="$attrs"
            />
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex cursor-pointer items-center rounded-r-lg px-3.5 text-r10-grey-500 transition hover:text-r10-orange"
                :aria-label="showPassword ? 'Peida parool' : 'Näita parooli'"
                :tabindex="-1"
                @click="showPassword = !showPassword"
            >
                <EyeOff v-if="showPassword" class="size-4" />
                <Eye v-else class="size-4" />
            </button>
        </span>

        <span
            v-if="error"
            :data-test="errorTestId"
            class="text-xs font-medium text-r10-orange-700"
        >
            {{ error }}
        </span>
    </label>
</template>
