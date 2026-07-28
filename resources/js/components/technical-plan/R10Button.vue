<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'outline' | 'danger';
        /** `sm` is slim enough for an icon-only button. */
        size?: 'sm' | 'md' | 'lg';
        type?: 'button' | 'submit';
        disabled?: boolean;
        /**
         * Renders as a link instead of a button. An Inertia `<Link>` by
         * default, because most destinations are pages of this app; pass
         * `external` for a plain `<a>` that leaves it.
         */
        href?: string;
        external?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        disabled: false,
        external: false,
    },
);

const component = computed(() => {
    if (!props.href) {
        return 'button';
    }

    return props.external ? 'a' : Link;
});

/** A link takes neither `type` nor `disabled`, and a button takes no `href`. */
const attrs = computed(() =>
    props.href
        ? { href: props.href }
        : { type: props.type, disabled: props.disabled },
);

const classes = computed(() => {
    const variant = {
        primary: 'bg-r10-orange text-r10-navy hover:bg-r10-orange-600',
        outline:
            'bg-white text-r10-navy border-2 border-r10-navy hover:bg-r10-navy hover:text-white',
        danger: 'bg-r10-error text-white hover:opacity-90',
    }[props.variant];

    const size = {
        sm: 'text-xs px-3 py-2',
        md: 'text-sm px-6 py-3',
        lg: 'text-[15px] px-7 py-[15px]',
    }[props.size];

    return `${variant} ${size}`;
});
</script>

<template>
    <component
        :is="component"
        v-bind="attrs"
        :class="[
            'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full font-r10-body font-bold tracking-[0.04em] uppercase transition disabled:pointer-events-none disabled:opacity-50',
            classes,
        ]"
    >
        <slot />
    </component>
</template>
