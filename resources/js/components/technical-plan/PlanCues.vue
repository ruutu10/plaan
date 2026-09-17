<script setup lang="ts">
import type { PlanDocumentSound } from '@/types/technicalPlan';

/**
 * A scene's sound as the reader sees it: its cues, then whatever the performer
 * wrote about them. Shared by the two layouts of the document — the table on
 * screen and the scene blocks on paper — so a cue stays clickable and reads the
 * same in both.
 */
defineProps<{ sounds: PlanDocumentSound[]; text: string }>();

const linkClass =
    'text-r10-navy underline decoration-r10-navy/30 transition hover:text-r10-orange hover:decoration-r10-orange';
</script>

<template>
    <span>
        <!-- Each cue gets its own line, in the order it is played, so they all
             stay clickable. -->
        <span
            v-for="(sound, position) in sounds"
            :key="position"
            class="block break-words"
        >
            <template v-if="sound.file">
                <a
                    :href="sound.file.url ?? undefined"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="linkClass"
                >
                    {{ sound.file.name }}
                </a>
                ({{ sound.file.sizeLabel }})
            </template>
            <a
                v-else
                :href="sound.url"
                target="_blank"
                rel="noopener noreferrer"
                :class="[linkClass, 'break-all']"
            >
                {{ sound.url }}
            </a>
        </span>
        <span v-if="text" class="block break-words whitespace-pre-line">
            {{ text }}
        </span>
    </span>
</template>
