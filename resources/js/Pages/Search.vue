<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    query: { type: String, default: '' },
    search: { type: [Object, null], default: null },
});

const input = ref(props.query);
const loading = ref(false);

function submit() {
    if (!input.value.trim()) return;
    loading.value = true;
    router.get(
        '/',
        { q: input.value },
        { preserveState: true, preserveScroll: true, onFinish: () => (loading.value = false) },
    );
}

const examples = [
    'une femme la trentaine, cheveux bruns, air méditerranéen, sourire naturel, plutôt pub que haute couture',
    'homme barbu, look streetwear, énergie affirmée',
    'profil corporate, cheveux courts, regard doux',
];

function filterChips(filters) {
    if (!filters) return [];
    const chips = [];
    for (const [key, values] of Object.entries(filters.attributes || {})) {
        chips.push(`${key} · ${values.join(' / ')}`);
    }
    if (filters.age_min || filters.age_max) chips.push(`âge · ${filters.age_min ?? '?'}–${filters.age_max ?? '?'}`);
    (filters.tags_requis || []).forEach((t) => chips.push(`+${t}`));
    (filters.tags_exclus || []).forEach((t) => chips.push(`−${t}`));
    return chips;
}
</script>

<template>
    <Head title="Recherche" />
    <AppLayout>
        <div class="mx-auto max-w-3xl">
            <p class="eyebrow text-center">{{ stats.searchable }} profils recherchables</p>
            <h1 class="display mt-3 text-center text-4xl sm:text-5xl">
                Décris le profil<br /><span class="italic" style="color: var(--color-klein)">que tu cherches.</span>
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-center text-sm" style="color: var(--color-stone)">
                En français, comme un message. L'outil
                <strong style="color: var(--color-klein); font-weight: 600">comprend le sens</strong>
                (« air méditerranéen », « plutôt pub que mode ») et te remonte les meilleurs profils.
            </p>

            <!-- Champ chat -->
            <div class="mt-8">
                <textarea
                    v-model="input"
                    rows="3"
                    placeholder="ex : une femme la trentaine, cheveux bruns, air méditerranéen, sourire naturel…"
                    class="field resize-none !text-base"
                    @keydown.enter.exact.prevent="submit"
                />
                <div class="mt-2 flex items-center justify-between">
                    <span class="eyebrow">Entrée pour chercher</span>
                    <button class="btn btn-primary" :disabled="loading || !input.trim()" @click="submit">
                        {{ loading ? 'Recherche…' : 'Chercher' }}
                    </button>
                </div>
            </div>

            <!-- Exemples -->
            <div v-if="!search" class="mt-8">
                <p class="eyebrow mb-3">Essaie</p>
                <div class="space-y-2">
                    <button
                        v-for="ex in examples"
                        :key="ex"
                        class="card block w-full px-4 py-3 text-left text-sm transition-colors hover:border-[var(--color-klein)]"
                        style="color: var(--color-stone)"
                        @click="((input = ex), submit())"
                    >
                        {{ ex }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Résultats -->
        <div v-if="search" class="mx-auto mt-10 max-w-3xl">
            <div class="rule flex flex-wrap items-center gap-2 pt-4">
                <span class="eyebrow" style="color: var(--color-ink)">
                    {{ search.results.length }} résultat{{ search.results.length > 1 ? 's' : '' }}
                </span>
                <span v-if="search.relaxed" class="inline-flex items-center gap-1">
                    <span class="tag" style="background: var(--color-flag); color: #fff">recherche élargie</span>
                    <HelpTip
                        title="Recherche élargie"
                        detail="Trop peu de profils collaient à tous tes critères, donc l'outil a assoupli les contraintes pour ne pas te laisser sans résultat. Les profils ci-dessous sont les plus proches, pas des correspondances exactes."
                        eyebrow="Info"
                    />
                </span>
                <span class="grow"></span>
                <span
                    v-for="chip in filterChips(search.filters)"
                    :key="chip"
                    class="tag"
                    style="background: var(--color-paper-deep); color: var(--color-stone)"
                >
                    {{ chip }}
                </span>
            </div>

            <div v-if="search.results.length" class="mt-4 space-y-3">
                <article
                    v-for="(r, i) in search.results"
                    :key="r.id"
                    class="card flex gap-4 p-3"
                >
                    <div class="relative w-24 shrink-0 overflow-hidden" style="border-radius: 2px; background: var(--color-paper-deep)">
                        <img v-if="r.photo_url" :src="r.photo_url" class="aspect-[4/5] size-full object-cover" />
                        <span
                            class="absolute left-1 top-1 font-mono text-[10px] font-bold"
                            style="background: var(--color-ink); color: var(--color-paper); padding: 0 0.25rem; border-radius: 2px"
                            >{{ String(i + 1).padStart(2, '0') }}</span
                        >
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs" style="color: var(--color-stone)">{{ r.code }}</span>
                            <span
                                class="font-mono text-sm font-bold tabular-nums"
                                style="color: var(--color-klein)"
                                >{{ Math.round(r.score * 100) }}%</span
                            >
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed" style="color: var(--color-ink)">
                            {{ r.description || 'Pas encore de description.' }}
                        </p>
                    </div>
                </article>
            </div>
            <p v-else class="card mt-4 p-8 text-center text-sm" style="color: var(--color-stone)">
                Aucun profil ne correspond. Essaie une formulation plus large.
            </p>
        </div>
    </AppLayout>
</template>
