<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    query: { type: String, default: '' },
    search: { type: [Object, null], default: null },
    vocab: { type: Array, default: () => [] },
});

const input = ref(props.query);
const loading = ref(false);

const FACE = '/landing/face.jpg';

const portrait = {
    genre: 'femme',
    age_min: 30,
    age_max: 38,
    type_percu: 'europeen',
    carnation: 'III',
    cheveux_couleur: 'brun',
    cheveux_longueur: 'mi_long',
    cheveux_texture: 'ondule',
    yeux_couleur: 'vert',
    forme_visage: 'ovale',
    expression: 'sourire',
    vibe: ['naturel', 'commercial', 'corporate'],
    signes_distinctifs: [],
    description_fr:
        'Allure naturelle et solaire, sourire franc et regard direct. Un profil très publicitaire, parfaitement à l’aise en registre corporate et grand public.',
};

const callouts = [
    { side: 'left', top: '20%', label: 'cheveux', value: 'brun · ondulé' },
    { side: 'right', top: '31%', label: 'yeux', value: 'vert' },
    { side: 'left', top: '46%', label: 'carnation', value: 'III' },
    { side: 'right', top: '55%', label: 'expression', value: 'sourire' },
    { side: 'left', top: '69%', label: 'type perçu', value: 'européen' },
];

// Bandeau : 3 lignes de paires « catégorie : valeur », défilement lent alterné.
const vocabRows = computed(() => {
    const v = props.vocab;
    const n = Math.ceil(v.length / 3);
    return [
        { items: v.slice(0, n), dir: 'marquee-l', dur: '120s' },
        { items: v.slice(n, n * 2), dir: 'marquee-r', dur: '150s' },
        { items: v.slice(n * 2), dir: 'marquee-l', dur: '135s' },
    ];
});

// Nom de l'app en ASCII art binaire (masque SVG rempli d'un champ de 0/1).
const binField = Array.from({ length: 16 }, () =>
    Array.from({ length: 220 }, () => (Math.random() < 0.5 ? '0' : '1')).join(''),
).join('\n');

const asciiStyle = computed(() => {
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 220'><text x='600' y='178' text-anchor='middle' font-family='monospace' font-weight='800' font-size='230' textLength='1150' lengthAdjust='spacingAndGlyphs'>TROMBI</text></svg>`;
    const url = `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
    return { WebkitMaskImage: url, maskImage: url };
});

function submit(value) {
    const q = (value ?? input.value).trim();
    if (!q) return;
    input.value = q;
    loading.value = true;
    router.get('/', { q }, { preserveState: true, preserveScroll: true, onFinish: () => (loading.value = false) });
}

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
    for (const [key, values] of Object.entries(filters.attributes || {})) chips.push(`${key} · ${values.join(' / ')}`);
    if (filters.age_min || filters.age_max) chips.push(`âge · ${filters.age_min ?? '?'}–${filters.age_max ?? '?'}`);
    (filters.tags_requis || []).forEach((t) => chips.push(`+${t}`));
    (filters.tags_exclus || []).forEach((t) => chips.push(`−${t}`));
    return chips;
}
</script>

<template>
    <Head title="Recherche" />
    <AppLayout>
        <!-- ============ MODE RÉSULTATS ============ -->
        <div v-if="search" class="mx-auto max-w-3xl">
            <textarea v-model="input" rows="2" class="field resize-none" @keydown.enter.exact.prevent="submit()" />
            <div class="mt-2 flex justify-end">
                <button class="btn btn-primary" :disabled="loading || !input.trim()" @click="submit()">
                    {{ loading ? 'Recherche…' : 'Chercher' }}
                </button>
            </div>

            <div class="rule mt-6 flex flex-wrap items-center gap-2 pt-4">
                <span class="inline-flex items-center gap-1">
                    <span class="eyebrow" style="color: var(--color-ink)">
                        {{ search.results.length }} résultat{{ search.results.length > 1 ? 's' : '' }}
                    </span>
                    <HelpTip
                        title="Comment lire le score"
                        detail="Le pourcentage = la part de TES critères que le profil satisfait (genre, cheveux, âge, vibe…). Tout satisfait = 100 %. Les puces ✓ montrent ce qui correspond, ✗ ce qui a été relâché pour ne pas te laisser sans résultat. À couverture égale, les profils sont départagés par proximité sémantique (le vecteur). Clique un profil pour l'ouvrir."
                        eyebrow="Score"
                    />
                </span>
                <span v-if="search.relaxed" class="inline-flex items-center gap-1">
                    <span class="tag" style="background: var(--color-flag); color: var(--color-paper)">recherche élargie</span>
                    <HelpTip
                        title="Recherche élargie"
                        detail="Trop peu de profils collaient à tous tes critères : l'outil a assoupli les contraintes pour ne pas te laisser sans résultat."
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
                <Link
                    v-for="(r, i) in search.results"
                    :key="r.id"
                    :href="`/talents/${r.id}/qualify`"
                    class="card flex gap-4 p-3 transition-colors hover:border-[var(--color-line-strong)]"
                >
                    <div class="relative w-24 shrink-0 overflow-hidden" style="border-radius: 2px; background: var(--color-paper-deep)">
                        <img v-if="r.photo_url" :src="r.photo_url" class="aspect-[4/5] size-full object-cover" />
                        <span class="absolute left-1 top-1 font-mono text-[10px] font-bold" style="background: var(--color-ink); color: var(--color-paper); padding: 0 0.25rem; border-radius: 2px">
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="min-w-0">
                                <span v-if="r.name" class="block truncate text-sm font-semibold" style="color: var(--color-ink)" :title="r.name">{{ r.name }}</span>
                                <span class="block truncate font-mono text-xs" style="color: var(--color-stone)" :title="r.location ? `${r.code} · ${r.location}` : r.code">
                                    {{ r.location ? `${r.code} · ${r.location}` : r.code }}
                                </span>
                            </span>
                            <span
                                class="shrink-0 font-mono text-sm font-bold tabular-nums"
                                :title="`Correspondance à tes critères : ${Math.round(r.score * 100)}%`"
                                :style="{ color: r.score >= 0.999 ? 'var(--color-pine)' : 'var(--color-klein)' }"
                                >{{ Math.round(r.score * 100) }}%</span
                            >
                        </div>

                        <!-- Critères correspondants / manquants (explication du score) -->
                        <div v-if="(r.matched && r.matched.length) || (r.missed && r.missed.length)" class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="m in r.matched"
                                :key="'ok-' + m"
                                class="tag"
                                style="background: var(--color-klein-wash); color: var(--color-pine)"
                                >✓ {{ m }}</span
                            >
                            <span
                                v-for="m in r.missed"
                                :key="'no-' + m"
                                class="tag"
                                style="background: var(--color-paper-deep); color: var(--color-stone-soft)"
                                >✗ {{ m }}</span
                            >
                        </div>

                        <p class="mt-1.5 text-sm leading-relaxed" style="color: var(--color-ink)">
                            {{ r.description || 'Pas encore de description.' }}
                        </p>
                    </div>
                </Link>
            </div>
            <p v-else class="card mt-4 p-8 text-center text-sm" style="color: var(--color-stone)">
                Aucun profil ne correspond. Essaie une formulation plus large.
            </p>
        </div>

        <!-- ============ LANDING HIGH-TECH ============ -->
        <section
            v-else
            class="hero-dark relative -mt-10 -mb-10 overflow-hidden"
            style="width: 100vw; margin-left: calc(50% - 50vw)"
        >
            <div class="hero-grid pointer-events-none absolute inset-0"></div>

            <!-- HERO -->
            <div class="relative mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:py-24">
                <div>
                    <h1 class="display text-4xl leading-[1.05] sm:text-5xl" style="color: #f4f2ec">
                        Décris un visage.<br />
                        <span class="italic" style="color: #aab0ff">L’IA le trouve.</span>
                    </h1>

                    <p class="mt-5 max-w-md text-sm leading-relaxed" style="color: rgba(244, 242, 236, 0.7)">
                        Écris ton besoin en langage naturel. Trombi lit chaque visage, en extrait une fiche
                        structurée, et classe tes profils par pertinence — pas par mots-clés.
                    </p>

                    <div class="mt-7 flex gap-2">
                        <input
                            v-model="input"
                            type="text"
                            placeholder="ex : femme trentaine, brune, air méditerranéen, sourire naturel…"
                            class="w-full rounded-lg border-0 px-4 py-3 text-sm shadow-lg focus:outline-none focus:ring-2"
                            style="background: #fff; color: #14173a"
                            @keydown.enter.prevent="submit()"
                        />
                        <button
                            class="btn btn-primary shrink-0 px-5"
                            style="background: #5060ff"
                            :disabled="loading || !input.trim()"
                            @click="submit()"
                        >
                            {{ loading ? '…' : 'Chercher' }}
                        </button>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2">
                        <span class="eyebrow" style="color: rgba(244, 242, 236, 0.45)">Vision par IA</span>
                        <span class="eyebrow" style="color: rgba(244, 242, 236, 0.45)">Recherche vectorielle</span>
                        <span class="eyebrow" style="color: rgba(244, 242, 236, 0.45)">{{ stats.searchable }} profils</span>
                    </div>
                </div>

                <!-- Le visage sous HUD d'analyse -->
                <div class="relative mx-auto w-full max-w-sm">
                    <div class="relative overflow-hidden rounded-xl" style="box-shadow: 0 30px 80px -30px rgba(80, 96, 255, 0.7)">
                        <img :src="FACE" alt="Analyse d'un visage" class="aspect-[3/4] w-full object-cover" />

                        <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(180deg, rgba(27,33,80,0.1), rgba(27,33,80,0.45))"></div>
                        <div class="scanline pointer-events-none absolute inset-x-0 top-0 h-16" style="background: linear-gradient(180deg, transparent, rgba(150,160,255,0.4), transparent)"></div>

                        <span class="pointer-events-none absolute left-3 top-3 size-5 border-l-2 border-t-2" style="border-color: rgba(170,176,255,0.85)"></span>
                        <span class="pointer-events-none absolute right-3 top-3 size-5 border-r-2 border-t-2" style="border-color: rgba(170,176,255,0.85)"></span>
                        <span class="pointer-events-none absolute bottom-3 left-3 size-5 border-b-2 border-l-2" style="border-color: rgba(170,176,255,0.85)"></span>
                        <span class="pointer-events-none absolute bottom-3 right-3 size-5 border-b-2 border-r-2" style="border-color: rgba(170,176,255,0.85)"></span>

                        <div class="hud-in absolute left-3 top-5 flex items-center gap-1.5" style="animation-delay: 0.1s">
                            <span class="pulse-dot size-1.5 rounded-full" style="background: #8b93ff"></span>
                            <span class="font-mono text-[10px] tracking-widest" style="color: #f4f2ec">ANALYSE</span>
                        </div>
                        <div class="hud-in absolute right-3 top-5 font-mono text-[10px] font-bold tabular-nums" style="color: #aab0ff; animation-delay: 0.2s">97%</div>

                        <div class="hud-in absolute inset-x-3 bottom-5 flex items-center justify-between rounded-md px-2.5 py-1.5" style="background: rgba(15,18,45,0.65); backdrop-filter: blur(4px); animation-delay: 0.9s">
                            <span class="font-mono text-[10px]" style="color: rgba(244,242,236,0.85)">âge 30–38</span>
                            <span class="font-mono text-[10px]" style="color: #aab0ff">vibe · naturel / commercial</span>
                        </div>
                    </div>

                    <template v-for="(c, i) in callouts" :key="c.label">
                        <div
                            class="hud-in absolute z-10 hidden items-center gap-1.5 rounded-md border px-2 py-1 sm:flex"
                            :class="c.side === 'left' ? '-left-4' : '-right-4'"
                            :style="`top:${c.top}; ${c.side === 'left' ? 'transform:translateX(-100%)' : 'transform:translateX(100%)'}; border-color:rgba(255,255,255,0.16); background:rgba(20,24,60,0.9); backdrop-filter:blur(6px); animation-delay:${0.3 + i * 0.15}s`"
                        >
                            <span class="size-1 rounded-full" style="background: #8b93ff"></span>
                            <span class="font-mono text-[9px] uppercase tracking-wider" style="color: rgba(244,242,236,0.55)">{{ c.label }}</span>
                            <span class="font-mono text-[10px]" style="color: #f4f2ec">{{ c.value }}</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- BANDEAU VOCABULAIRE (catégorie : valeur), défilement lent -->
            <div class="relative border-y py-8" style="border-color: rgba(255, 255, 255, 0.08)">
                <div class="mx-auto mb-5 max-w-6xl px-6">
                    <span class="eyebrow" style="color: rgba(244, 242, 236, 0.5)">
                        Un vocabulaire contrôlé — l'IA ne sort jamais de cette liste
                    </span>
                </div>
                <div class="space-y-3">
                    <div v-for="(row, ri) in vocabRows" :key="ri" class="marquee-mask overflow-hidden">
                        <div class="marquee-track" :class="row.dir" :style="`animation-duration:${row.dur}`">
                            <span
                                v-for="(item, i) in [...row.items, ...row.items]"
                                :key="i"
                                class="mr-2 shrink-0 rounded-full border px-3 py-1 font-mono text-xs"
                                style="border-color: rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.03)"
                            >
                                <span style="color: rgba(244, 242, 236, 0.45)">{{ item.cat }}</span>
                                <span style="color: rgba(244, 242, 236, 0.35)"> : </span>
                                <span style="color: #aab0ff">{{ item.val }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SORTIE STRUCTURÉE -->
            <div class="relative mx-auto max-w-6xl px-6 py-16">
                <div class="mb-6 flex items-center gap-2">
                    <span class="eyebrow" style="color: rgba(244, 242, 236, 0.5)">Sous le capot</span>
                    <span class="h-px flex-1" style="background: rgba(255, 255, 255, 0.1)"></span>
                    <HelpTip
                        title="La brique qui rend la recherche possible"
                        detail="Chaque visage importé passe par l'IA : elle remplit un portrait-robot dans un vocabulaire fixe (aucune valeur inventée) et rédige une description. C'est ce texte qui devient un vecteur comparable à ta requête."
                        eyebrow="Comment"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1.2fr]">
                    <div class="rounded-lg border p-5" style="border-color: rgba(255,255,255,0.1); background: rgba(255,255,255,0.03)">
                        <p class="eyebrow mb-4" style="color: rgba(244,242,236,0.5)">Fiche exploitable</p>
                        <p class="text-sm leading-relaxed" style="color: rgba(244,242,236,0.85)">{{ portrait.description_fr }}</p>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <span
                                v-for="v in ['femme', '30–38', 'européen', 'brun · ondulé', 'yeux verts', 'sourire', 'naturel', 'commercial']"
                                :key="v"
                                class="rounded-full px-2.5 py-1 font-mono text-[10px]"
                                style="background: rgba(100,112,255,0.15); color: #c4c8ff"
                                >{{ v }}</span
                            >
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border" style="border-color: rgba(255,255,255,0.1); background: rgba(15,18,45,0.4)">
                        <div class="flex items-center justify-between border-b px-4 py-2" style="border-color: rgba(255,255,255,0.08)">
                            <span class="font-mono text-[10px] tracking-widest" style="color: rgba(244,242,236,0.5)">portrait-robot.json</span>
                            <span class="font-mono text-[10px]" style="color: #63d2a4">✓ valide</span>
                        </div>
                        <pre class="json-block max-h-[360px] overflow-auto p-4" v-html="highlight(portrait)"></pre>
                    </div>
                </div>
            </div>

            <!-- FOOTER : TROMBI en ASCII art binaire -->
            <div class="relative border-t px-6 pb-8 pt-10" style="border-color: rgba(255, 255, 255, 0.08)">
                <pre class="ascii-art mx-auto max-w-5xl" :style="asciiStyle">{{ binField }}</pre>
                <p class="eyebrow mt-4 text-center" style="color: rgba(244, 242, 236, 0.3)">
                    Vision IA · recherche vectorielle · pgvector
                </p>
            </div>
        </section>
    </AppLayout>
</template>
