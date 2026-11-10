<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import R10Combobox from '@/components/technical-plan/R10Combobox.vue';
import R10FormDialog from '@/components/technical-plan/R10FormDialog.vue';
import R10Select from '@/components/technical-plan/R10Select.vue';
import { store } from '@/routes/api/formats/performances/staff';
import { search as searchUsers } from '@/routes/api/users';
import type {
    PerformanceStaffRole,
    PerformanceStaffRoleOption,
    R10ComboboxOption,
    UserSearchResult,
} from '@/types';

/**
 * Put somebody on a performance's staff by hand — a guest the Planka card
 * never named, a stand-in at the desk. They are then everything an imported
 * name is: written to, let in, listed. The import leaves them alone.
 *
 * The person is found by name as it is typed: the server answers a few
 * matches at a time and never the whole house, so nothing is fetched until
 * the search is long enough to narrow it down.
 */
const props = defineProps<{
    formatId: number;
    performanceId: number;
    roles: PerformanceStaffRoleOption[];
}>();

const emit = defineEmits<{ added: [] }>();

const open = defineModel<boolean>('open', { required: true });

/** Mirrors App\Http\Requests\Users\SearchUsersRequest::MIN_LENGTH. */
const MIN_SEARCH_LENGTH = 4;

const form = useHttp<{
    user_id: number | string | null;
    role: PerformanceStaffRole | string | null;
}>({
    user_id: null,
    role: null,
});

const chosenUser = ref<R10ComboboxOption | null>(null);
const searchTerm = ref('');
const matches = ref<R10ComboboxOption[]>([]);

/** The search has its own request, so it never fights the form's own. */
const searcher = useHttp();

/**
 * Bumped with every search sent, so an answer that comes back after a newer
 * one was asked for is dropped rather than shown over it.
 */
let latestSearch = 0;

const searchIsLongEnough = computed(
    () => searchTerm.value.trim().length >= MIN_SEARCH_LENGTH,
);

const emptyText = computed(() =>
    searchIsLongEnough.value
        ? 'Sellise nimega kasutajat ei leitud.'
        : `Sisesta vähemalt ${MIN_SEARCH_LENGTH} tähemärki.`,
);

watchDebounced(
    searchTerm,
    async (term) => {
        const query = term.trim();
        const thisSearch = ++latestSearch;

        if (query.length < MIN_SEARCH_LENGTH) {
            matches.value = [];

            return;
        }

        try {
            const { data } = (await searcher.submit(
                searchUsers({ query: { q: query } }),
            )) as { data: UserSearchResult[] };

            if (thisSearch === latestSearch) {
                matches.value = data.map((user) => ({
                    value: user.id,
                    label: user.name,
                }));
            }
        } catch {
            if (thisSearch === latestSearch) {
                matches.value = [];
            }
        }
    },
    { debounce: 250 },
);

/** A modal is mounted with the page and reused, so it starts clean each time. */
function reset(): void {
    form.resetAndClearErrors();
    chosenUser.value = null;
    searchTerm.value = '';
    matches.value = [];
}

async function save(): Promise<void> {
    form.user_id = chosenUser.value?.value ?? null;

    try {
        await form.submit(store([props.formatId, props.performanceId]));

        emit('added');
        open.value = false;

        toast.success('Meeskonnaliige lisatud.');
    } catch {
        // A refused save leaves its field errors on the form; anything else is
        // shown as a plain failure rather than passed on as a broken promise.
        if (!form.hasErrors) {
            toast.error('Lisamine ebaõnnestus. Proovi uuesti.');
        }
    }
}
</script>

<template>
    <R10FormDialog
        v-model:open="open"
        title="Lisa meeskonda"
        description="Lisatud inimene saab etenduse kohta samad teavitused ja ligipääsu nagu Planka kaardilt imporditud meeskond. Planka import käsitsi lisatuid ei muuda."
        submit-label="Lisa"
        :processing="form.processing"
        test-id-prefix="add-staff"
        @opened="reset"
        @submit="save"
    >
        <R10Combobox
            v-model="chosenUser"
            v-model:search-term="searchTerm"
            label="Kasutaja"
            required
            placeholder="Otsi nime järgi"
            :options="matches"
            :loading="searcher.processing"
            :empty-text="emptyText"
            :error="form.errors.user_id"
            error-test-id="add-staff-user-error"
            data-test="add-staff-user-search"
        />

        <R10Select
            v-model="form.role"
            label="Roll"
            required
            placeholder="Vali roll"
            :options="roles"
            :error="form.errors.role"
            error-test-id="add-staff-role-error"
            data-test="add-staff-role-select"
        />
    </R10FormDialog>
</template>
