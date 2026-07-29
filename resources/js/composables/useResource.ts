import { onMounted, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * A listing fetched once the page mounts, in the three states the screens care
 * about: not here yet (`null`, which is what the skeleton keys off), failed, or
 * loaded.
 *
 * The loader returns the rows rather than being handed a route, because most
 * endpoints send something alongside them — the teams a show may be assigned
 * to, the roles a member may be given — which the caller assigns itself.
 */
export function useResource<T>(load: () => Promise<T>): {
    data: Ref<T | null>;
    loadFailed: Ref<boolean>;
    reload: () => void;
} {
    const data = ref<T | null>(null) as Ref<T | null>;
    const loadFailed = ref(false);

    async function fetch(): Promise<void> {
        try {
            data.value = await load();
            loadFailed.value = false;
        } catch {
            loadFailed.value = true;
        }
    }

    onMounted(fetch);

    /**
     * Fetch afresh rather than splicing a row in or out: the server decides
     * both the order and the counts, and it has just moved.
     */
    function reload(): void {
        void fetch();
    }

    return { data, loadFailed, reload };
}
