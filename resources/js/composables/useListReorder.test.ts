import { describe, expect, it, vi } from 'vitest';
import {
    dropGap,
    isStayingPut,
    moveToGap,
    useListReorder,
} from './useListReorder';

describe('dropGap', () => {
    const rect = { top: 100, height: 40 };

    it('lands above an entry over its top half', () => {
        expect(dropGap(2, 101, rect)).toBe(2);
        expect(dropGap(2, 119, rect)).toBe(2);
    });

    it('lands below an entry over its bottom half', () => {
        expect(dropGap(2, 120, rect)).toBe(3);
        expect(dropGap(2, 139, rect)).toBe(3);
    });
});

describe('isStayingPut', () => {
    it('treats the gaps either side of the entry as no move at all', () => {
        expect(isStayingPut(2, 2)).toBe(true);
        expect(isStayingPut(2, 3)).toBe(true);
        expect(isStayingPut(2, 1)).toBe(false);
        expect(isStayingPut(2, 4)).toBe(false);
    });
});

describe('moveToGap', () => {
    const order = (): string[] => ['a', 'b', 'c', 'd'];

    it('moves an entry up into an earlier gap', () => {
        const list = order();
        moveToGap(list, 2, 0);
        expect(list).toEqual(['c', 'a', 'b', 'd']);
    });

    it('moves an entry down, allowing for its own place closing up', () => {
        const list = order();
        moveToGap(list, 0, 2);
        expect(list).toEqual(['b', 'a', 'c', 'd']);
    });

    it('moves an entry to the very end', () => {
        const list = order();
        moveToGap(list, 1, 4);
        expect(list).toEqual(['a', 'c', 'd', 'b']);
    });

    it('leaves the order alone for the gaps either side of the entry', () => {
        const list = order();
        moveToGap(list, 1, 1);
        moveToGap(list, 1, 2);
        expect(list).toEqual(order());
    });

    it('ignores an entry or gap that is not in the list', () => {
        const list = order();
        moveToGap(list, -1, 0);
        moveToGap(list, 0, 5);
        expect(list).toEqual(order());
    });
});

describe('useListReorder', () => {
    const entries = () => [{ id: 'a' }, { id: 'b' }, { id: 'c' }];

    /** An entry 40px tall whose top half ends 20px below its top. */
    function over(clientY: number): DragEvent {
        return {
            clientY,
            currentTarget: {
                getBoundingClientRect: () => ({ top: 0, height: 40 }),
            },
        } as unknown as DragEvent;
    }

    function drop(): DragEvent {
        return { stopPropagation: vi.fn() } as unknown as DragEvent;
    }

    it('moves the dragged entry into the gap the pointer was last at', () => {
        const list = entries();
        const reorder = useListReorder(() => list);

        reorder.startDrag('a');
        reorder.onDragOver(2, over(30));
        expect(reorder.dropAt.value).toBe(3);
        expect(reorder.showsGap(3)).toBe(true);

        const event = drop();
        reorder.onDrop(event);

        expect(list.map((entry) => entry.id)).toEqual(['b', 'c', 'a']);
        expect(event.stopPropagation).toHaveBeenCalled();
        expect(reorder.dragId.value).toBeNull();
        expect(reorder.dropAt.value).toBeNull();
    });

    it('never opens a gap on either side of the dragged entry', () => {
        const reorder = useListReorder(entries);

        reorder.startDrag('b');
        reorder.onDragOver(1, over(5));
        expect(reorder.showsGap(1)).toBe(false);
        reorder.onDragOver(1, over(35));
        expect(reorder.showsGap(2)).toBe(false);
    });

    it('ignores a drag it did not start, and lets its drop bubble on', () => {
        const list = entries();
        const reorder = useListReorder(() => list);

        reorder.onDragOver(0, over(5));
        expect(reorder.dropAt.value).toBeNull();

        const event = drop();
        reorder.onDrop(event);

        expect(event.stopPropagation).not.toHaveBeenCalled();
        expect(list).toEqual(entries());
    });

    it('closes the gap only when the pointer leaves the list itself', () => {
        const reorder = useListReorder(entries);
        const inside = {};
        const container = { contains: (node: unknown) => node === inside };

        reorder.startDrag('a');
        reorder.onDragOver(2, over(5));

        reorder.onDragLeave({
            currentTarget: container,
            relatedTarget: inside,
        } as unknown as DragEvent);
        expect(reorder.dropAt.value).toBe(2);

        reorder.onDragLeave({
            currentTarget: container,
            relatedTarget: null,
        } as unknown as DragEvent);
        expect(reorder.dropAt.value).toBeNull();
    });

    it('lets go without moving anything when no gap was reached', () => {
        const list = entries();
        const reorder = useListReorder(() => list);

        reorder.startDrag('a');
        reorder.onDrop(drop());

        expect(list).toEqual(entries());
        expect(reorder.dragId.value).toBeNull();
    });
});
