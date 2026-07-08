<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    query: { type: String, default: '' },
    search: { type: [Object, null], default: null },
    demo: { type: [Object, null], default: null },
});

const input = ref(props.query);
const loading = ref(false);

function submit(value) {
    const q = (value ?? input.value).trim();
    if (!q) return;
    input.value = q;
    loading.value = true;
    router.get(
        '/',
        { q },
        { preserveState: true, preserveScroll: true, onFinish: () => (loading.value = false) },
    );
}

// Colore un objet en JSON lisible (données internes, pas d'entrée utilisateur).
function highlight(obj) {
    const json = JSON.stringify(obj, null, 2)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return json
        .replace(/"([^"]+)":/g, '<span class="jk">"$1"</span>:')
        .replace(/: "([^"]*)"/g, ': <span class="js">"$1"</span>')
        .replace(/\b(\d+)\b/g, '<span class="jn">$1</span>');
}

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
        <!-- Hero -->
        <div class="mx-auto max-w-3xl">
            <p class="eyebrow text-center">Moteur de casting en langage naturel</p>
            <h1 class="display mt-3 text-center text-4xl sm:text-5xl">
                Décris le profil,<br /><span class="italic" style="color: var(--color-klein)">l'IA trouve le visage.</span>
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-center text-sm" style="color: var(--color-stone)">
                Écris ton besoin en français, comme un message. L'outil
                <strong style="color: var(--color-klein); font-weight: 600">comprend le sens</strong>
                — pas juste les mots-clés — et classe tes profils par pertinence.
            </p>

            <div class="mt-8">
                <textarea
                    v-model="input"
                    rows="3"
                    placeholder="ex : une femme la trentaine, cheveux bruns, air méditerranéen, sourire naturel…"
                    class="field resize-none !text-base"
                    @keydown.enter.exact.prevent="submit()"
                />
                <div class="mt-2 flex items-center justify-between">
                    <span class="eyebrow">Entrée pour chercher</span>
                    <button class="btn btn-primary" :disabled="loading || !input.trim()" @click="submit()">
                        {{ loading ? 'Recherche…' : 'Chercher' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Résultats de recherche -->
        <div v-if="search" class="mx-auto mt-10 max-w-3xl">
            <div class="rule flex flex-wrap items-center gap-2 pt-4">
                <span class="eyebrow" style="color: var(--color-ink)">
                    {{ search.results.length }} résultat{{ search.results.length > 1 ? 's' : '' }}
                </span>
                <span v-if="search.relaxed" class="inline-flex items-center gap-1">
                    <span class="tag" style="background: var(--color-flag); color: #fff">recherche élargie</span>
                    <HelpTip
                        title="Recherche élargie"
                        detail="Trop peu de profils collaient à tous tes critères : l'outil a assoupli les contraintes pour ne pas te laisser sans résultat. Ce sont les plus proches, pas des correspondances exactes."
                        eyebrow="Info"
                    />
                </span>
                <span class="grow"></span>
                <span
                    v-for="chip in filterChips(search.filters)"
                    :key="chip"
                    class="tag"
                    style="background: var(--color-paper-deep); color: var(--color-stone)"
                    >{{ chip }}</span
                >
            </div>

            <div v-if="search.results.length" class="mt-4 space-y-3">
                <article v-for="(r, i) in search.results" :key="r.id" class="card flex gap-4 p-3">
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
                            <span class="font-mono text-sm font-bold tabular-nums" style="color: var(--color-klein)"
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

        <!-- Landing : comment ça marche -->
        <template v-else-if="demo">
            <div class="mt-20">
                <p class="eyebrow text-center">Comment ça marche</p>
                <h2 class="display mt-2 text-center text-2xl sm:text-3xl">
                    Du langage humain au profil, en trois temps
                </h2>

                <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- 01 : la requête -->
                    <div class="card flex flex-col p-5">
                        <p class="eyebrow" style="color: var(--color-klein)">01 — Tu décris</p>
                        <p class="mt-3 text-sm leading-relaxed" style="color: var(--color-ink)">
                            « {{ demo.query }} »
                        </p>
                        <button
                            class="mt-4 self-start text-xs transition-opacity hover:opacity-70"
                            style="color: var(--color-klein)"
                            @click="submit(demo.query)"
                        >
                            Essayer cet exemple →
                        </button>
                    </div>

                    <!-- 02 : les filtres -->
                    <div class="card flex flex-col p-5">
                        <div class="flex items-center gap-1.5">
                            <p class="eyebrow" style="color: var(--color-klein)">02 — L'IA structure</p>
                            <HelpTip
                                title="Ce que fait l'IA"
                                detail="Elle lit ta phrase et la découpe en deux : des filtres exacts (genre, âge, cheveux…) piochés dans un vocabulaire fixe, et le reste — l'ambiance, le style — transformé en « sens » comparable. Elle ne peut inventer aucune valeur qui n'existe pas dans la base."
                                eyebrow="La magie"
                            />
                        </div>
                        <pre class="json-block mt-3 grow" v-html="highlight(demo.filters)" />
                    </div>

                    <!-- 03 : les résultats -->
                    <div class="card flex flex-col p-5">
                        <p class="eyebrow" style="color: var(--color-klein)">03 — Tu obtiens</p>
                        <div class="mt-3 space-y-2">
                            <div v-for="(r, i) in demo.results" :key="i" class="flex items-center gap-3">
                                <div class="size-12 shrink-0 overflow-hidden" style="border-radius: 2px; background: var(--color-paper-deep)">
                                    <img v-if="r.photo_url" :src="r.photo_url" class="size-full object-cover" />
                                </div>
                                <span class="font-mono text-xs" style="color: var(--color-stone)">{{ r.code }}</span>
                                <span class="grow"></span>
                                <span class="font-mono text-sm font-bold tabular-nums" style="color: var(--color-klein)"
                                    >{{ Math.round(r.score * 100) }}%</span
                                >
                            </div>
                            <p v-if="!demo.results.length" class="text-xs" style="color: var(--color-stone-soft)">
                                Importe des visages pour voir des résultats réels.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- La magie : chaque visage → fiche structurée -->
            <div class="mt-20">
                <p class="eyebrow text-center">Sous le capot</p>
                <h2 class="display mt-2 text-center text-2xl sm:text-3xl">
                    Chaque visage devient une fiche exploitable
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-center text-sm" style="color: var(--color-stone)">
                    À l'import, l'IA regarde chaque photo et en tire un portrait-robot structuré
                    <span style="color: var(--color-klein)">plus</span> une description — c'est ça qui rend la recherche possible.
                </p>

                <div class="mx-auto mt-8 grid max-w-4xl grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="card overflow-hidden">
                        <div class="aspect-[4/5] w-full" style="background: var(--color-paper-deep)">
                            <img
                                v-if="demo.portrait.photo_url"
                                :src="demo.portrait.photo_url"
                                class="size-full object-cover"
                                style="filter: grayscale(0.3)"
                            />
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <div class="flex items-center justify-between border-b px-4 py-2" style="border-color: var(--color-line)">
                            <span class="eyebrow">portrait-robot.json</span>
                            <span v-if="demo.portrait.is_example" class="tag" style="background: var(--color-paper-deep); color: var(--color-stone)">exemple</span>
                        </div>
                        <pre class="json-block max-h-[420px] overflow-auto p-4" v-html="highlight(demo.portrait.json)" />
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
