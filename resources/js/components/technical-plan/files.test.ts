import { describe, expect, it } from 'vitest';
import { isReady, storedFile } from './files';

describe('storedFile', () => {
    it('marks a handle the server stored as ready to show', () => {
        expect(
            storedFile({ id: 'media-1', name: 'lugu.mp3', size: 1 }),
        ).toEqual({
            id: 'media-1',
            name: 'lugu.mp3',
            size: 1,
            status: 'ready',
        });
    });

    it('drops a handle that never made it server-side', () => {
        expect(storedFile({ id: '', name: 'lugu.mp3', size: 1 })).toBeNull();
        expect(storedFile(null)).toBeNull();
        expect(storedFile(undefined)).toBeNull();
    });
});

describe('isReady', () => {
    const file = { id: 'media-1', name: 'lugu.mp3', size: 1 };

    it('refuses only the states that mean the file is not there yet', () => {
        expect(isReady({ ...file, status: 'uploading' })).toBe(false);
        expect(isReady({ ...file, status: 'error' })).toBe(false);
        expect(isReady({ ...file, status: 'ready' })).toBe(true);
    });

    it('has nothing to show for a missing file', () => {
        expect(isReady(null)).toBe(false);
        expect(isReady(undefined)).toBe(false);
    });
});
