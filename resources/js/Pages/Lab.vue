<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    pipeline: { type: Object, required: true },
    embedding: { type: Object, required: true },
    dataset: { type: Array, default: () => [] },
    projection: { type: Object, required: true },
    pairs: { type: [Object, null], default: null },
    anatomy: { type: [Object, null], default: null },
});

// --- Couleurs par genre (cohérentes avec la palette) ---
const GENRE_COLOR = {
    femme: 'var(--color-klein)',
    homme: 'var(--color-pine)',
    non_binaire: 'var(--color-flag)',
    androgyne: 'var(--color-rust)',
};
const genreColor = (g) => GENRE_COLOR[g] ?? 'var(--color-stone)';

const genresPresent = computed(() => {
    const set = new Map();
    props.projection.points.forEach((p) => p.genre && set.set(p.genre, true));
    return [...set.keys()];
});

// --- Scatter : x,y ∈ [-1,1] → pourcentages, y inversé (haut = positif) ---
const dots = computed(() =>
    props.projection.points.map((p) => ({
        ...p,
        left: ((p.x + 1) / 2) * 100,
        top: (1 - (p.y + 1) / 2) * 100,
    })),
);

const hovered = ref(null);

// --- Anatomie : échelle des barres ---
const anatomyMax = computed(() => {
    if (!props.anatomy) return 1;
    return Math.max(...props.anatomy.values.map((v) => Math.abs(v)), 0.0001);
});

// --- Cosinus → angle en degrés (pour les mini-diagrammes) ---
const toDeg = (cos) => (Math.acos(Math.max(-1, Math.min(1, cos))) * 180) / Math.PI;

// Coordonnée d'un vecteur unité à un angle donné (repère SVG, centre 50/50, rayon 38).
function tip(angleDeg) {
    const a = (angleDeg * Math.PI) / 180;
    return { x: 50 + 38 * Math.cos(a), y: 50 - 38 * Math.sin(a) };
}

function open(id) {
    router.get(`/talents/${id}/qualify`);
}

const pct = (v) => Math.round(v * 100);
</script>

<template>
    <Head title="Le Labo — comprendre la machine" />
    <AppLayout>
        <!-- ═══ HERO ═══ -->
        <header class="max-w-3xl">
            <p class="eyebrow">Le Labo · sous le capot</p>
            <h1 class="display mt-2 text-4xl sm:text-5xl">
                Un visage devient<br />
                <span class="italic" style="color: var(--color-klein-deep)">un point dans l’espace.</span>
            </h1>
            <p class="mt-4 text-sm leading-relaxed" style="color: var(--color-stone)">
                Trombi ne compare pas des mots-clés. Il lit chaque visage, en écrit un portrait, puis le transforme
                en <strong style="color: var(--color-ink)">{{ embedding.dims }} nombres</strong> — un point dans un
                espace à {{ embedding.dims }} dimensions. Chercher un profil, c’est trouver ses voisins dans cet espace.
                Cette page rend cette mécanique visible.
            </p>
        </header>

        <!-- ═══ PIPELINE (séquence réelle → numérotation légitime) ═══ -->
        <section class="mt-12">
            <p class="eyebrow mb-4">De la photo au vecteur</p>
            <ol class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                <li
                    v-for="(step, i) in [
                        { t: 'Photo', d: 'Le portrait importé du talent.' },
                        { t: 'Vision IA', d: 'Un modèle lit le visage et remplit le portrait-robot.' },
                        { t: 'Texte', d: 'Attributs + vibe + description → une phrase recherchable.' },
                        { t: 'Embedding', d: `La phrase devient ${embedding.dims} nombres (un vecteur).` },
                        { t: 'pgvector', d: 'Le vecteur est stocké dans Postgres, indexé.' },
                        { t: 'Cosinus', d: 'La recherche classe par angle entre vecteurs.' },
                    ]"
                    :key="step.t"
                    class="card p-3"
                >
                    <span class="font-mono text-xs" style="color: var(--color-klein-deep)">{{ String(i + 1).padStart(2, '0') }}</span>
                    <p class="mt-1 text-sm font-semibold" style="color: var(--color-ink)">{{ step.t }}</p>
                    <p class="mt-1 leading-snug" style="color: var(--color-stone); font-size: 0.6875rem">{{ step.d }}</p>
                </li>
            </ol>
        </section>

        <!-- ═══ ÉTAT DU DATASET ═══ -->
        <section class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="stat in [
                { k: 'talents', label: 'Talents' },
                { k: 'analyzed', label: 'Analysés IA' },
                { k: 'searchable', label: 'Cherchables' },
                { k: 'gold', label: 'Validés' },
            ]" :key="stat.k" class="card p-4">
                <p class="font-mono text-3xl tabular-nums" style="color: var(--color-ink)">{{ pipeline[stat.k] }}</p>
                <p class="eyebrow mt-1">{{ stat.label }}</p>
            </div>
        </section>

        <!-- ═══ LA CARTE DES VISAGES (signature) ═══ -->
        <section class="mt-14">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <div class="flex items-center gap-1.5">
                        <p class="eyebrow" style="color: var(--color-ink)">La carte des visages</p>
                        <HelpTip
                            title="Projection 2D (PCA)"
                            detail="Impossible de dessiner 1536 dimensions. On les écrase sur les 2 axes qui portent le plus de variation (analyse en composantes principales). Deux points proches ici = deux profils au portrait proche. Les axes n'ont pas de nom : seule la proximité compte."
                            eyebrow="Méthode"
                        />
                    </div>
                    <p class="mt-1 text-sm" style="color: var(--color-stone)">
                        Chaque point est un talent. Survole pour voir qui, clique pour ouvrir.
                    </p>
                </div>
                <p v-if="projection.count >= 2" class="font-mono text-xs" style="color: var(--color-stone)">
                    2 axes · {{ pct(projection.variance[0] + projection.variance[1]) }}% de la variance ·
                    {{ projection.count }} points
                </p>
            </div>

            <!-- Légende genres -->
            <div v-if="genresPresent.length" class="mt-3 flex flex-wrap gap-3">
                <span v-for="g in genresPresent" :key="g" class="flex items-center gap-1.5 text-xs" style="color: var(--color-stone)">
                    <span class="inline-block size-2.5 rounded-full" :style="{ background: genreColor(g) }" />
                    {{ g.replace('_', ' ') }}
                </span>
            </div>

            <div
                v-if="dots.length >= 2"
                class="card relative mt-3 overflow-hidden"
                style="aspect-ratio: 16 / 10"
                @mouseleave="hovered = null"
            >
                <!-- Grille repère -->
                <div class="pointer-events-none absolute inset-0" style="background-image: linear-gradient(var(--color-line) 1px, transparent 1px), linear-gradient(90deg, var(--color-line) 1px, transparent 1px); background-size: 12.5% 20%; opacity: 0.4" />
                <div class="pointer-events-none absolute left-1/2 top-0 h-full" style="width: 1px; background: var(--color-line-strong); opacity: 0.5" />
                <div class="pointer-events-none absolute left-0 top-1/2 w-full" style="height: 1px; background: var(--color-line-strong); opacity: 0.5" />

                <button
                    v-for="p in dots"
                    :key="p.id"
                    class="absolute -translate-x-1/2 -translate-y-1/2 rounded-full transition-transform hover:scale-150"
                    :style="{
                        left: p.left + '%',
                        top: p.top + '%',
                        width: hovered?.id === p.id ? '14px' : '10px',
                        height: hovered?.id === p.id ? '14px' : '10px',
                        background: genreColor(p.genre),
                        boxShadow: hovered?.id === p.id ? '0 0 0 3px var(--color-klein-wash)' : 'none',
                        zIndex: hovered?.id === p.id ? 20 : 1,
                    }"
                    :title="`${p.name || p.code} — ouvrir`"
                    @mouseenter="hovered = p"
                    @click="open(p.id)"
                />

                <!-- Carte du point survolé -->
                <div
                    v-if="hovered"
                    class="pointer-events-none absolute bottom-3 left-3 flex items-center gap-2 border p-2"
                    style="background: var(--color-paper-deep); border-color: var(--color-line-strong); border-radius: var(--radius-frame); max-width: 60%"
                >
                    <img v-if="hovered.photo" :src="hovered.photo" class="size-12 shrink-0 object-cover" style="border-radius: 2px" />
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-semibold" style="color: var(--color-ink)">{{ hovered.name || hovered.code }}</span>
                        <span class="block truncate font-mono text-[11px]" style="color: var(--color-stone)">{{ hovered.code }} · {{ (hovered.genre || '—').replace('_', ' ') }}</span>
                    </span>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="card mt-3 p-8 text-center">
                <p class="text-sm" style="color: var(--color-stone)">
                    Il faut au moins 2 talents cherchables pour dessiner la carte.
                    <Link href="/talents" class="underline" style="color: var(--color-klein)">Analyser des talents →</Link>
                </p>
            </div>
        </section>

        <!-- ═══ ANATOMIE D'UN VECTEUR ═══ -->
        <section v-if="anatomy" class="mt-14">
            <div class="flex items-center gap-1.5">
                <p class="eyebrow" style="color: var(--color-ink)">Anatomie d’un vecteur</p>
                <HelpTip
                    title="Lire un vecteur"
                    detail="Un vecteur n'a pas de valeurs 'lisibles' une par une. Chacune est une coordonnée minuscule sur un axe abstrait. Ce qui porte le sens, c'est la COMBINAISON — la direction d'ensemble. La norme (longueur) vaut ≈ 1 : tous les profils sont sur la même sphère, seule leur orientation les distingue."
                    eyebrow="Comment lire"
                />
            </div>
            <p class="mt-1 text-sm" style="color: var(--color-stone)">
                Exemple : {{ anatomy.code }} · {{ embedding.dims }} dimensions · norme {{ anatomy.norm }} · voici les
                64 premières coordonnées (bleu positif, corail négatif).
            </p>

            <div class="card mt-3 flex items-end gap-[2px] p-4" style="height: 120px">
                <div v-for="(v, i) in anatomy.values" :key="i" class="flex-1" style="height: 100%; display: flex; flex-direction: column; justify-content: center">
                    <div
                        :style="{
                            height: (Math.abs(v) / anatomyMax) * 50 + '%',
                            alignSelf: v >= 0 ? 'flex-end' : 'flex-start',
                            background: v >= 0 ? 'var(--color-klein)' : 'var(--color-rust)',
                            transform: v >= 0 ? 'translateY(0)' : 'translateY(0)',
                        }"
                        :title="v"
                    />
                </div>
            </div>
        </section>

        <!-- ═══ SIMILARITÉ COSINUS ═══ -->
        <section v-if="pairs" class="mt-14">
            <div class="flex items-center gap-1.5">
                <p class="eyebrow" style="color: var(--color-ink)">La mesure : le cosinus</p>
                <HelpTip
                    title="Similarité cosinus"
                    detail="On compare deux profils par l'ANGLE entre leurs vecteurs, pas par leur distance. Angle nul → cosinus 1 → identiques. Angle droit → cosinus 0 → sans rapport. C'est le pourcentage affiché dans la recherche. Il atteint rarement 100 % car deux portraits ne sont jamais formulés à l'identique."
                    eyebrow="Maths"
                />
            </div>
            <p class="mt-1 text-sm" style="color: var(--color-stone)">
                Deux exemples réels de ton dataset : la paire la plus proche et la plus éloignée.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <article
                    v-for="ex in [
                        { key: 'closest', label: 'Paire la plus proche', accent: 'var(--color-pine)', data: pairs.closest },
                        { key: 'farthest', label: 'Paire la plus éloignée', accent: 'var(--color-rust)', data: pairs.farthest },
                    ]"
                    :key="ex.key"
                    class="card p-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="eyebrow">{{ ex.label }}</span>
                        <span class="font-mono text-lg font-bold tabular-nums" :style="{ color: ex.accent }">
                            {{ pct(ex.data.score) }}%
                        </span>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <!-- Diagramme d'angle -->
                        <svg viewBox="0 0 100 100" class="size-24 shrink-0">
                            <circle cx="50" cy="50" r="38" fill="none" :stroke="'var(--color-line)'" stroke-width="1" />
                            <line x1="50" y1="50" :x2="tip(0).x" :y2="tip(0).y" :stroke="ex.accent" stroke-width="2" />
                            <line x1="50" y1="50" :x2="tip(toDeg(ex.data.score)).x" :y2="tip(toDeg(ex.data.score)).y" :stroke="ex.accent" stroke-width="2" />
                            <circle cx="50" cy="50" r="2" :fill="ex.accent" />
                            <text x="50" y="94" text-anchor="middle" style="font-size: 9px; fill: var(--color-stone); font-family: var(--font-mono)">
                                {{ Math.round(toDeg(ex.data.score)) }}°
                            </text>
                        </svg>

                        <!-- Les deux profils -->
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <button
                                v-for="who in [ex.data.a, ex.data.b]"
                                :key="who.id"
                                class="min-w-0 flex-1 text-left transition-opacity hover:opacity-80"
                                @click="open(who.id)"
                            >
                                <img v-if="who.photo" :src="who.photo" class="aspect-square w-full object-cover" style="border-radius: 2px" />
                                <span class="mt-1 block truncate font-mono text-[11px]" style="color: var(--color-stone)" :title="who.name || who.code">
                                    {{ who.name || who.code }}
                                </span>
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- ═══ COMPOSITION DU DATASET ═══ -->
        <section v-if="dataset.length" class="mt-14">
            <p class="eyebrow" style="color: var(--color-ink)">Composition du dataset</p>
            <p class="mt-1 text-sm" style="color: var(--color-stone)">
                Ce que l’IA a observé sur l’ensemble des visages analysés — la matière première de la recherche.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                <div v-for="attr in dataset" :key="attr.key">
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm font-semibold" style="color: var(--color-ink)">{{ attr.label }}</span>
                        <span class="font-mono text-[11px]" style="color: var(--color-stone)">{{ attr.total }}</span>
                    </div>
                    <div class="mt-2 space-y-1.5">
                        <div v-for="bar in attr.bars" :key="bar.label" class="flex items-center gap-2">
                            <span class="w-24 shrink-0 truncate text-xs" style="color: var(--color-stone)" :title="bar.label">{{ bar.label }}</span>
                            <div class="h-2.5 flex-1 overflow-hidden" style="background: var(--color-paper-deep); border-radius: 2px">
                                <div class="h-full" :style="{ width: (bar.count / attr.bars[0].count) * 100 + '%', background: 'var(--color-klein)' }" />
                            </div>
                            <span class="w-6 shrink-0 text-right font-mono text-[11px] tabular-nums" style="color: var(--color-stone)">{{ bar.count }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <p class="mt-14 text-xs" style="color: var(--color-stone-soft)">
            Modèle d’embedding : <span class="font-mono">{{ embedding.model }}</span> ·
            <Link href="/usage" class="underline" style="color: var(--color-klein)">voir la consommation OpenRouter →</Link>
        </p>
    </AppLayout>
</template>
