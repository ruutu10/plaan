<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * A name that leads back to the record behind it — a format's screen, a
 * performance's. The name is rendered as plain text when there is no link, so a
 * reader who may not open the screen still reads what is playing rather than
 * following a link into a refusal; the server decides which is which.
 *
 * Carries no typography of its own: it is wrapped around a name already styled
 * by the row it sits in, and only marks it as leading somewhere.
 */
const props = defineProps<{ href?: string | null }>();

const component = computed(() => (props.href ? Link : 'span'));

/** A plain name takes no href, and neither does an `<a>` without a target. */
const attrs = computed(() => (props.href ? { href: props.href } : {}));
</script>

<template>
    <component
        :is="component"
        v-bind="attrs"
        :class="
            href
                ? 'underline decoration-r10-grey-200 decoration-2 underline-offset-4 transition hover:text-r10-orange hover:decoration-r10-orange'
                : undefined
        "
    >
        <slot />
    </component>
</template>
