import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';

/**
 * Which gap of a list a dragged entry would land in when let go over
 * the entry at `index`: the gap above it in its top half, the one below it in
 * its bottom half. Gaps are counted from zero (above the first entry) to the
 * length of the list (below the last).
 */
export function dropGap(
    index: number,
    pointerY: number,
    rect: { top: number; height: number },
): number {
    return pointerY < rect.top + rect.height / 2 ? index : index + 1;
}

/** Whether dropping the entry at `from` into `gap` would leave it where it is. */
export function isStayingPut(from: number, gap: number): boolean {
    return gap === from || gap === from + 1;
}

/** Move the entry at `from` into `gap`, as {@see dropGap} counts them. */
export function moveToGap<T>(list: T[], from: number, gap: number): void {
    if (from < 0 || from >= list.length || gap < 0 || gap > list.length) {
        return;
    }

    if (isStayingPut(from, gap)) {
        return;
    }

    const [moved] = list.splice(from, 1);
    // Taking the entry out shifts every gap below it up by one.
    list.splice(gap > from ? gap - 1 : gap, 0, moved);
}

export type UseListReorderReturn = {
    /** The id of the entry being dragged, or null while nothing of this list is. */
    dragId: Ref<string | null>;
    /** The gap the dragged entry would land in if let go now, or null. */
    dropAt: Ref<number | null>;
    dragFrom: ComputedRef<number>;
    startDrag: (id: string) => void;
    endDrag: () => void;
    onDragOver: (index: number, event: DragEvent) => void;
    onDragLeave: (event: DragEvent) => void;
    showsGap: (gap: number) => boolean;
    onDrop: (event: DragEvent) => void;
};

/**
 * Drag-and-drop reordering of a list of entries keyed by id, with the gap the
 * dragged entry would land in tracked as it moves, so the list can open a
 * space there (see `DropGap.vue`).
 *
 * The list's container takes the drop, so letting go between two entries works
 * as well as letting go over one; each entry only reports, from its
 * `dragover`, which gap the pointer is at.
 *
 * Lists nest — a scene's cues sit inside a scene card that is itself an entry
 * of the running order — so every handler ignores a drag this list did not
 * start. A foreign drop is left to bubble on to the list that owns it, and
 * only a drop this list handled is stopped there.
 */
export function useListReorder<T extends { id: string }>(
    list: () => T[],
): UseListReorderReturn {
    const dragId = ref<string | null>(null);
    const dropAt = ref<number | null>(null);

    const dragFrom = computed(() =>
        list().findIndex((entry) => entry.id === dragId.value),
    );

    function startDrag(id: string): void {
        dragId.value = id;
        dropAt.value = null;
    }

    function endDrag(): void {
        dragId.value = null;
        dropAt.value = null;
    }

    /** Follow the pointer over the entry at `index`, picking the gap above or below it. */
    function onDragOver(index: number, event: DragEvent): void {
        if (dragId.value === null) {
            return;
        }

        dropAt.value = dropGap(
            index,
            event.clientY,
            (event.currentTarget as HTMLElement).getBoundingClientRect(),
        );
    }

    /** Close the gap once the pointer leaves the list altogether. */
    function onDragLeave(event: DragEvent): void {
        const container = event.currentTarget as HTMLElement;

        if (!container.contains(event.relatedTarget as Node | null)) {
            dropAt.value = null;
        }
    }

    /** Whether to open a gap here — never on either side of the entry itself. */
    function showsGap(gap: number): boolean {
        return (
            dropAt.value === gap &&
            dragFrom.value >= 0 &&
            !isStayingPut(dragFrom.value, gap)
        );
    }

    function onDrop(event: DragEvent): void {
        if (dragId.value === null) {
            return;
        }

        event.stopPropagation();

        if (dropAt.value !== null) {
            moveToGap(list(), dragFrom.value, dropAt.value);
        }

        endDrag();
    }

    return {
        dragId,
        dropAt,
        dragFrom,
        startDrag,
        endDrag,
        onDragOver,
        onDragLeave,
        showsGap,
        onDrop,
    };
}
