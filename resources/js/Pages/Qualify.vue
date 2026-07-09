<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import MultiChips from '@/Components/MultiChips.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    talent: { type: Object, required: true },
    values: { type: Object, required: true },
    taxonomy: { type: Object, required: true }, // { single: [...], multi: [...] }
    diff: { type: [Object, null], default: null },
    meta: { type: Object, required: true },
    nextId: { type: [Number, null], default: null },
});

// --- Gestion des photos ---
const photoForm = useForm({ photos: [] });

function addPhotos(fileList) {
    photoForm.photos = Array.from(fileList);
    if (photoForm.photos.length) {
        photoForm.post(`/talents/${props.talent.id}/photos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => photoForm.reset('photos'),
        });
    }
}

function deletePhoto(photoId) {
    const isLast = (props.talent.photos?.length ?? 0) <= 1;
    const message = isLast
        ? 'C’est la dernière photo de ce talent. La supprimer le laissera sans image. Continuer ?'
        : 'Supprimer cette photo ?';
    if (!confirm(message)) return;
    router.delete(`/talents/${props.talent.id}/photos/${photoId}`, { preserveScroll: true });
}

// --- Identité (prénom, nom, localisation) ---
const identityForm = useForm({
    first_name: props.talent.first_name ?? '',
    last_name: props.talent.last_name ?? '',
    location: props.talent.location ?? '',
});

function saveIdentity() {
    identityForm.patch(`/talents/${props.talent.id}/identity`, { preserveScroll: true });
}

function deleteTalent() {
    if (!confirm(`Supprimer définitivement ${props.talent.code} et toutes ses données ?`)) return;
    router.delete(`/talents/${props.talent.id}`);
}

// --- Métadonnées ---
const showMeta = ref(false);

const single = props.taxonomy.single;
const multi = props.taxonomy.multi;

function initialForm() {
    const data = {
        age_min: props.values.age_min ?? null,
        age_max: props.values.age_max ?? null,
        description_fr: props.values.description_fr ?? '',
    };
    single.forEach((a) => (data[a.key] = props.values[a.key] ?? null));
    multi.forEach((a) => (data[a.key] = props.values[a.key] ?? []));
    return data;
}

const form = useForm(initialForm());

// --- Galerie photos (un talent peut en avoir plusieurs) ---
const mainPhoto = ref(props.talent.photos?.[0]?.url ?? props.talent.photo_url);

// Après ajout/suppression, garde une photo principale valide.
watch(
    () => props.talent.photos,
    (list) => {
        const urls = (list ?? []).map((p) => p.url);
        if (!urls.includes(mainPhoto.value)) {
            mainPhoto.value = urls[0] ?? null;
        }
    },
);

// --- Analyse IA + comparaison ---
const fewShot = ref(false);
const analyzing = ref(false);

function analyze() {
    analyzing.value = true;
    router.post(
        `/talents/${props.talent.id}/analyze`,
        { few_shot: fewShot.value },
        { preserveScroll: true, onFinish: () => (analyzing.value = false) },
    );
}

// --- Recherche globale : pose une valeur sur le bon attribut ---
const search = ref('');
const searchOpen = ref(false);

const searchIndex = computed(() => {
    const idx = [];
    single.forEach((a) =>
        a.options.forEach((o) => idx.push({ attr: a.key, attrLabel: a.label, kind: 'single', ...o })),
    );
    multi.forEach((a) =>
        a.options.forEach((o) => idx.push({ attr: a.key, attrLabel: a.label, kind: 'multi', ...o })),
    );
    return idx;
});

const suggestions = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return [];
    return searchIndex.value
        .filter(
            (e) =>
                e.label.toLowerCase().includes(q) ||
                e.value.toLowerCase().includes(q) ||
                (e.hint || '').toLowerCase().includes(q),
        )
        .slice(0, 8);
});

function applySuggestion(entry) {
    if (entry.kind === 'single') {
        form[entry.attr] = entry.value;
    } else {
        const set = new Set(form[entry.attr] ?? []);
        set.add(entry.value);
        form[entry.attr] = [...set];
    }
    search.value = '';
    searchOpen.value = false;
}

// --- Import des valeurs détectées par l'IA ---
// L'humain relit ce que l'IA propose et l'adopte dans le formulaire, champ par
// champ ou d'un bloc. Rien n'est enregistré tant qu'il n'a pas sauvegardé.
const refused = ref(new Set());
const imported = ref(new Set());

function importField(f) {
    if (f.kind === 'age') {
        form.age_min = f.ai_raw?.age_min ?? null;
        form.age_max = f.ai_raw?.age_max ?? null;
    } else if (f.kind === 'multi') {
        form[f.key] = [...(f.ai_raw ?? [])];
    } else {
        form[f.key] = f.ai_raw ?? null;
    }
    refused.value.delete(f.key);
    imported.value = new Set(imported.value).add(f.key);
}

function refuseField(f) {
    imported.value.delete(f.key);
    refused.value = new Set(refused.value).add(f.key);
}

function importAll() {
    (props.diff?.fields ?? []).forEach((f) => {
        if (!refused.value.has(f.key)) importField(f);
    });
}

// --- Sauvegarde ---
function save(stay = false) {
    form.transform((data) => ({ ...data, stay })).post(`/talents/${props.talent.id}/qualify`);
}

function onKey(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        save();
    }
}

function skip() {
    if (props.nextId) router.get(`/talents/${props.nextId}/qualify`);
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="`Qualifier ${talent.code}`" />
    <AppLayout>
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <!-- Image + comparaison IA -->
            <div class="lg:sticky lg:top-6 lg:self-start">
                <figure class="card overflow-hidden">
                    <img
                        v-if="mainPhoto"
                        :src="mainPhoto"
                        :alt="talent.code"
                        class="w-full object-cover"
                    />
                    <figcaption
                        class="flex items-center justify-between border-t px-3 py-2"
                        style="border-color: var(--color-line)"
                    >
                        <span class="min-w-0">
                            <span v-if="talent.first_name || talent.last_name" class="block truncate text-xs font-semibold" style="color: var(--color-ink)">
                                {{ [talent.first_name, talent.last_name].filter(Boolean).join(' ') }}
                            </span>
                            <span class="block truncate font-mono text-xs" style="color: var(--color-stone)">
                                {{ talent.location || talent.code }}
                            </span>
                        </span>
                        <span v-if="talent.is_gold" class="inline-flex shrink-0 items-center gap-1">
                            <span class="tag" style="background: var(--color-ink); color: var(--color-paper)">
                                validé
                            </span>
                            <HelpTip
                                title="Validé à la main"
                                detail="Ce talent a déjà été qualifié par toi. Il rejoint le set de référence : la « vérité terrain » qui sert à mesurer si l'IA voit juste."
                                eyebrow="Statut"
                            />
                        </span>
                    </figcaption>
                </figure>

                <!-- Photos du talent : sélection, suppression, ajout -->
                <div class="mt-2 flex flex-wrap gap-2">
                    <div v-for="p in talent.photos" :key="p.id" class="group/photo relative">
                        <button
                            type="button"
                            class="size-14 overflow-hidden border transition-all"
                            :style="{
                                borderColor: mainPhoto === p.url ? 'var(--color-klein)' : 'var(--color-line)',
                                borderRadius: '2px',
                                opacity: mainPhoto === p.url ? 1 : 0.7,
                            }"
                            @click="mainPhoto = p.url"
                        >
                            <img :src="p.url" class="size-full object-cover" />
                        </button>
                        <button
                            type="button"
                            class="absolute -right-1.5 -top-1.5 grid size-4 place-items-center rounded-full text-[9px] leading-none shadow-sm transition-transform hover:scale-110"
                            style="background: var(--color-rust); color: #fff"
                            title="Supprimer cette photo"
                            @click.stop="deletePhoto(p.id)"
                        >
                            ✕
                        </button>
                    </div>

                    <label
                        class="grid size-14 cursor-pointer place-items-center border text-lg"
                        style="border-color: var(--color-line-strong); border-style: dashed; border-radius: 2px; color: var(--color-stone)"
                        title="Ajouter des photos"
                    >
                        <input type="file" class="hidden" multiple accept="image/*" @change="addPhotos($event.target.files)" />
                        +
                    </label>
                </div>

                <!-- Identité -->
                <div class="card mt-4 p-4">
                    <div class="mb-3 flex items-center gap-1.5">
                        <span class="eyebrow" style="color: var(--color-ink)">Identité</span>
                        <HelpTip
                            title="Identité"
                            detail="Le prénom, le nom et la localisation qu'on attribue à ce profil. Purement descriptif : ces champs n'entrent pas dans l'analyse IA ni dans le portrait-robot."
                            eyebrow="Fiche"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="eyebrow mb-1 block">Prénom</span>
                            <input v-model="identityForm.first_name" type="text" class="field" placeholder="Prénom" />
                        </label>
                        <label class="block">
                            <span class="eyebrow mb-1 block">Nom</span>
                            <input v-model="identityForm.last_name" type="text" class="field" placeholder="Nom" />
                        </label>
                    </div>
                    <label class="mt-3 block">
                        <span class="eyebrow mb-1 block">Localisation</span>
                        <input v-model="identityForm.location" type="text" class="field" placeholder="Ville, pays…" />
                    </label>
                    <button
                        class="btn btn-ghost mt-3 w-full"
                        :disabled="identityForm.processing"
                        @click="saveIdentity"
                    >
                        {{ identityForm.processing ? 'Enregistrement…' : 'Enregistrer l’identité' }}
                    </button>
                </div>

                <!-- Comparaison IA -->
                <div class="card mt-4 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1.5">
                        <span class="eyebrow">Comparer avec l'IA</span>
                        <span class="flex items-center gap-1.5">
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs" style="color: var(--color-stone)">
                                <input v-model="fewShot" type="checkbox" />
                                s'inspirer de mes corrections
                            </label>
                            <HelpTip
                                title="S'inspirer de mes corrections"
                                detail="Avant d'analyser, l'IA regarde quelques images que tu as déjà qualifiées à la main et s'aligne sur ta façon de juger. Le modèle n'est pas réentraîné : on lui montre juste tes exemples. Plus tu qualifies, meilleur c'est."
                                eyebrow="Option"
                            />
                        </span>
                    </div>
                    <button class="btn btn-ghost mt-3 w-full" :disabled="analyzing" @click="analyze">
                        {{ analyzing ? 'Analyse en cours…' : 'Lancer l’analyse IA' }}
                    </button>

                    <div v-if="diff" class="mt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="font-mono text-[11px]" style="color: var(--color-stone)">{{ diff.model }}</span>
                            <span class="text-xs font-semibold">
                                Accord {{ Math.round(diff.overall * 100) }}%
                            </span>
                        </div>
                        <div class="mb-2 flex items-center gap-2">
                            <button
                                type="button"
                                class="btn btn-ghost"
                                style="padding: 0.35rem 0.7rem; font-size: 0.6875rem"
                                @click="importAll"
                            >
                                Tout importer depuis l'IA
                            </button>
                            <HelpTip
                                title="Importer, valider, refuser"
                                detail="« Importer » copie la valeur de l'IA dans ton formulaire (colonne de droite). « Refuser » écarte sa proposition et garde la tienne. Rien n'est enregistré tant que tu n'as pas cliqué sur Sauvegarder : c'est ta version relue qui devient la référence."
                                eyebrow="Aide"
                            />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[280px] text-xs">
                                <thead>
                                    <tr class="eyebrow text-left" style="font-size: 0.5625rem">
                                        <th class="pb-1.5 font-normal">Champ</th>
                                        <th class="pb-1.5 font-normal">Toi</th>
                                        <th class="pb-1.5 font-normal">IA</th>
                                        <th class="pb-1.5 font-normal"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="f in diff.fields"
                                        :key="f.key"
                                        class="border-t"
                                        style="border-color: var(--color-line)"
                                        :style="{
                                            color: imported.has(f.key)
                                                ? 'var(--color-pine)'
                                                : f.agree
                                                  ? 'var(--color-pine)'
                                                  : 'var(--color-rust)',
                                            opacity: refused.has(f.key) ? 0.4 : 1,
                                        }"
                                    >
                                        <td class="py-1 pr-2 font-mono" style="color: var(--color-stone)">{{ f.key }}</td>
                                        <td class="py-1 pr-2">{{ f.human || '—' }}</td>
                                        <td
                                            class="py-1 pr-2"
                                            :style="{ fontWeight: imported.has(f.key) ? 600 : 400 }"
                                        >
                                            {{ f.ai || '—' }}
                                        </td>
                                        <td class="py-1 text-right whitespace-nowrap">
                                            <span
                                                v-if="imported.has(f.key)"
                                                class="font-medium"
                                                style="color: var(--color-pine)"
                                                >importé ✓</span
                                            >
                                            <span
                                                v-else-if="refused.has(f.key)"
                                                class="transition-opacity"
                                                style="color: var(--color-stone)"
                                            >
                                                refusé ·
                                                <button
                                                    type="button"
                                                    class="underline transition-opacity hover:opacity-70"
                                                    @click="importField(f)"
                                                >
                                                    importer
                                                </button>
                                            </span>
                                            <template v-else-if="!f.agree">
                                                <button
                                                    type="button"
                                                    class="font-medium transition-opacity hover:opacity-70"
                                                    style="color: var(--color-klein)"
                                                    title="Importer la valeur de l'IA dans le formulaire"
                                                    @click="importField(f)"
                                                >
                                                    importer
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ml-2 transition-opacity hover:opacity-70"
                                                    style="color: var(--color-stone)"
                                                    title="Refuser : garder ta valeur"
                                                    @click="refuseField(f)"
                                                >
                                                    refuser
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Métadonnées -->
                <div class="card mt-4 p-4">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between"
                        @click="showMeta = !showMeta"
                    >
                        <span class="eyebrow">Métadonnées</span>
                        <span style="color: var(--color-stone)">{{ showMeta ? '−' : '+' }}</span>
                    </button>

                    <div v-if="showMeta" class="mt-4 space-y-4 text-xs">
                        <div>
                            <span class="eyebrow">Source</span>
                            <p class="mt-1 font-mono" style="color: var(--color-stone)">{{ meta.source }}</p>
                        </div>

                        <div v-if="meta.appearance">
                            <div class="flex items-center gap-1">
                                <span class="eyebrow">Portrait-robot retenu</span>
                                <HelpTip
                                    title="Portrait-robot retenu"
                                    detail="Les valeurs finales gardées pour ce talent. Elles viennent de toi (validé) ou de l'IA — ta version prime toujours."
                                    eyebrow="Info"
                                />
                            </div>
                            <p class="mt-1" style="color: var(--color-stone)">
                                origine : {{ meta.appearance.source_label }} ·
                                {{ meta.appearance.model_used || '—' }} ·
                                {{ meta.appearance.analyzed_at || '—' }}
                            </p>
                        </div>

                        <div v-if="meta.appearance && meta.appearance.raw_analysis">
                            <div class="flex items-center gap-1">
                                <span class="eyebrow">JSON brut de l'IA</span>
                                <HelpTip
                                    title="JSON brut"
                                    detail="La réponse complète et non filtrée du dernier passage. Un filet de sécurité : on n'y perd jamais rien, même les infos qu'on ne range pas encore dans un critère."
                                    eyebrow="Info"
                                />
                            </div>
                            <pre
                                class="mt-1 max-h-52 overflow-auto p-2 font-mono"
                                style="background: var(--color-paper-deep); border-radius: 2px; color: var(--color-ink)"
                            >{{ JSON.stringify(meta.appearance.raw_analysis, null, 2) }}</pre>
                        </div>

                        <div v-if="meta.tags.length">
                            <span class="eyebrow">Tags</span>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="t in meta.tags"
                                    :key="t.slug"
                                    class="tag"
                                    style="background: var(--color-paper-deep); color: var(--color-ink)"
                                    >{{ t.slug }}</span
                                >
                            </div>
                        </div>

                        <div v-if="meta.profile">
                            <div class="flex items-center gap-1">
                                <span class="eyebrow">Vecteur de recherche</span>
                                <HelpTip
                                    title="Vecteur (embedding)"
                                    detail="La description du talent est transformée en une liste de 1536 nombres qui capture son « sens ». La recherche compare ces vecteurs pour trouver les profils proches d'une demande, même sans les mots exacts."
                                    eyebrow="C'est quoi ?"
                                />
                            </div>
                            <p class="mt-1" style="color: var(--color-stone)">
                                {{
                                    meta.profile.embedding
                                        ? meta.profile.embedding.dims + ' dimensions · norme ' + meta.profile.embedding.norm
                                        : 'pas encore de vecteur'
                                }}
                            </p>
                            <p
                                v-if="meta.profile.embedding"
                                class="mt-1 truncate font-mono"
                                style="color: var(--color-stone-soft)"
                            >
                                [{{ meta.profile.embedding.preview.join(', ') }}, …]
                            </p>
                            <div class="mt-2">
                                <span class="eyebrow">Texte recherchable</span>
                                <p class="mt-1" style="color: var(--color-stone)">{{ meta.profile.searchable_text }}</p>
                            </div>
                        </div>

                        <div v-if="meta.annotations.length">
                            <div class="flex items-center gap-1">
                                <span class="eyebrow">Historique</span>
                                <HelpTip
                                    title="Historique d'annotations"
                                    detail="Chaque passage — le tien ou celui de l'IA — est archivé ici. C'est ce qui permet de comparer, de suivre l'accord dans le temps, et de nourrir l'IA de tes corrections."
                                    eyebrow="Info"
                                />
                            </div>
                            <ul class="mt-1 space-y-0.5 font-mono" style="color: var(--color-stone)">
                                <li v-for="(a, i) in meta.annotations" :key="i">
                                    {{ a.source }} · {{ a.annotator || '—' }} · {{ a.created_at }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Zone danger -->
                <button
                    type="button"
                    class="mt-4 text-xs transition-opacity hover:opacity-70"
                    style="color: var(--color-rust)"
                    @click="deleteTalent"
                >
                    Supprimer ce talent et toutes ses données
                </button>
            </div>

            <!-- Formulaire -->
            <div>
                <p class="eyebrow mb-2">Qualification manuelle</p>
                <h1 class="display text-3xl">Décris ce que tu vois</h1>
                <p class="mt-2 text-sm" style="color: var(--color-stone)">
                    Renseigne uniquement
                    <strong style="color: var(--color-klein); font-weight: 600">ce que la photo montre</strong>.
                    Le petit <span class="font-semibold" style="color: var(--color-ink)">i</span> explique chaque
                    critère. <span class="font-mono text-xs">⌘/Ctrl + ⏎</span> pour sauver et passer au suivant.
                </p>

                <!-- Recherche globale -->
                <div v-click-outside="() => (searchOpen = false)" class="relative mt-5">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Aller vite : tape une valeur (roux, editorial, bleu…)"
                        class="field"
                        @focus="searchOpen = true"
                    />
                    <ul
                        v-if="searchOpen && suggestions.length"
                        class="card absolute z-30 mt-1 w-full overflow-hidden"
                        style="box-shadow: 0 18px 44px -18px rgba(4, 6, 20, 0.7)"
                    >
                        <li v-for="s in suggestions" :key="`${s.attr}-${s.value}`">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors hover:bg-[var(--color-klein-wash)]"
                                @click="applySuggestion(s)"
                            >
                                <span class="text-sm">{{ s.label }}</span>
                                <span class="eyebrow">{{ s.attrLabel }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Âge -->
                <div class="mt-6">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span class="eyebrow" style="color: var(--color-ink)">Âge perçu</span>
                        <HelpTip
                            title="Âge perçu"
                            detail="La fourchette d'âge que la personne paraît avoir à l'écran — pas son âge réel. Donne une borne basse et une borne haute (ex. 28 à 35)."
                            eyebrow="Critère"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model.number="form.age_min" type="number" min="0" max="120" placeholder="min" class="field" />
                        <input v-model.number="form.age_max" type="number" min="0" max="120" placeholder="max" class="field" />
                    </div>
                </div>

                <!-- Attributs single -->
                <div class="mt-6 grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2">
                    <div v-for="attr in single" :key="attr.key">
                        <div class="mb-1.5 flex items-center gap-1.5">
                            <span class="eyebrow" style="color: var(--color-ink)">{{ attr.label }}</span>
                            <HelpTip
                                :title="attr.label"
                                :detail="attr.detail"
                                :note="attr.note"
                                :items="attr.options.filter((o) => o.hint)"
                                eyebrow="Critère"
                            />
                        </div>
                        <SearchableSelect v-model="form[attr.key]" :options="attr.options" />
                    </div>
                </div>

                <!-- Attributs multi -->
                <div class="mt-7 space-y-5">
                    <div v-for="attr in multi" :key="attr.key">
                        <div class="mb-2 flex items-center gap-1.5">
                            <span class="eyebrow" style="color: var(--color-ink)">{{ attr.label }}</span>
                            <HelpTip
                                :title="attr.label"
                                :detail="attr.detail"
                                :note="attr.note"
                                :items="attr.options.filter((o) => o.hint)"
                                eyebrow="Critère"
                            />
                        </div>
                        <MultiChips v-model="form[attr.key]" :options="attr.options" />
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-7">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span class="eyebrow" style="color: var(--color-ink)">Description libre</span>
                        <HelpTip
                            title="Description libre"
                            detail="Quelques phrases sur l'allure, le style, l'énergie — comme une note de book. Optionnel : l'IA sait la rédiger, mais ta version fait référence."
                            eyebrow="Optionnel"
                        />
                    </div>
                    <textarea v-model="form.description_fr" rows="3" class="field" placeholder="Allure, style, énergie…" />
                </div>

                <!-- Actions -->
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <button class="btn btn-primary" :disabled="form.processing" @click="save(true)">
                        Sauvegarder
                    </button>
                    <button v-if="nextId" class="btn btn-ghost" :disabled="form.processing" @click="save(false)">
                        Sauver &amp; suivant
                    </button>
                    <button v-if="nextId" class="btn btn-ghost" @click="skip">Passer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
