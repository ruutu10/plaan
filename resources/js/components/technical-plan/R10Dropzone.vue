<script setup lang="ts">
import Diamond from './Diamond.vue';

/**
 * The dashed area a file is dropped on or picked through. `compact` is the
 * version that sits inside a scene card, where there is less room than on the
 * plan's own attachments.
 */
withDefaults(
    defineProps<{
        label: string;
        hint: string;
        accept: string;
        multiple?: boolean;
        compact?: boolean;
    }>(),
    { multiple: false, compact: false },
);

defineEmits<{ files: [files: FileList] }>();
</script>

<template>
    <label
        class="flex cursor-pointer flex-col items-center justify-center rounded-[14px] border-2 border-dashed border-r10-navy-300 bg-r10-grey-100 text-center transition hover:border-r10-orange hover:bg-r10-orange-100"
        :class="compact ? 'gap-1.5 px-4 py-5' : 'gap-2 p-8'"
    >
        <Diamond :size="compact ? 12 : 14" />
        <span
            class="font-r10-body font-bold text-r10-navy"
            :class="compact ? 'text-sm' : 'text-[15px]'"
        >
            {{ label }}
        </span>
        <span class="text-xs text-r10-grey-500">{{ hint }}</span>
        <input
            type="file"
            class="hidden"
            :multiple="multiple"
            :accept="accept"
            @change="
                ($event.target as HTMLInputElement).files &&
                $emit(
                    'files',
                    ($event.target as HTMLInputElement).files as FileList,
                )
            "
        />
    </label>
</template>
