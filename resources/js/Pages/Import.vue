<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TalentCard from '@/Components/TalentCard.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    talents: { type: Object, required: true },
    stats: { type: Object, required: true },
});

const dragging = ref(false);
const uploadForm = useForm({ photos: [] });
const pullForm = useForm({ count: 10 });

function onFiles(fileList) {
    uploadForm.photos = Array.from(fileList);
    if (uploadForm.photos.length) {
        uploadForm.post('/import/upload', {
            forceFormData: true,
            onSuccess: () => uploadForm.reset('photos'),
        });
    }
}

function onDrop(e) {
    dragging.value = false;
    onFiles(e.dataTransfer.files);
}

function pull() {
    pullForm.post('/import/pull', { preserveScroll: true });
}

const figures = [
    { key: 'total', label: 'visages' },
    { key: 'analyzed', label: 'décrits par l’IA' },
    { key: 'gold', label: 'validés à la main' },
];
</script>

<template>
    <Head title="Import" />
    <AppLayout>
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="eyebrow mb-2">Étape 1 — Peupler la base</p>
                <h1 class="display text-3xl">Import des visages</h1>
                <p class="mt-2 max-w-lg text-sm" style="color: var(--color-stone)">
                    Charge tes propres photos ou récupère des visages synthétiques. Les doublons sont
                    <strong style="color: var(--color-klein); font-weight: 600">écartés automatiquement</strong>.
                </p>
            </div>
            <div class="flex gap-6">
                <div v-for="f in figures" :key="f.key" class="text-right">
                    <div class="display text-3xl" :style="{ color: f.key === 'total' ? 'var(--color-klein)' : 'var(--color-ink)' }">
                        {{ stats[f.key] }}
                    </div>
                    <div class="eyebrow mt-1">{{ f.label }}</div>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <!-- Upload -->
            <label
                class="card flex cursor-pointer flex-col items-center justify-center p-10 text-center transition-colors"
                :style="{
                    borderStyle: 'dashed',
                    borderColor: dragging ? 'var(--color-klein)' : 'var(--color-line-strong)',
                    background: dragging ? 'var(--color-klein-wash)' : 'var(--color-card)',
                }"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <input type="file" class="hidden" multiple accept="image/*" @change="onFiles($event.target.files)" />
                <p class="eyebrow">Depuis ton ordinateur</p>
                <p class="mt-3 text-sm font-medium">Glisse tes images ici, ou clique pour choisir</p>
                <p class="mt-1 text-xs" style="color: var(--color-stone)">jpg, png, webp — plusieurs à la fois</p>
                <p v-if="uploadForm.processing" class="mt-3 text-xs" style="color: var(--color-klein)">Import…</p>
            </label>

            <!-- Pull API -->
            <div class="card flex flex-col justify-center p-10">
                <div class="flex items-center gap-1.5">
                    <p class="eyebrow">Visages synthétiques</p>
                    <HelpTip
                        title="Visages synthétiques"
                        detail="Des visages générés par IA (thispersondoesnotexist.com) : des personnes qui n'existent pas. Pratique pour remplir la base sans aucune question de droit à l'image."
                        eyebrow="C'est quoi ?"
                    />
                </div>
                <p class="mt-3 text-sm font-medium">Récupérer des visages générés</p>
                <p class="mt-1 text-xs" style="color: var(--color-stone)">
                    Un court délai entre chaque pour éviter les doublons.
                </p>
                <div class="mt-4 flex items-center gap-2">
                    <input v-model.number="pullForm.count" type="number" min="1" max="50" class="field w-24" />
                    <button class="btn btn-ink" :disabled="pullForm.processing" @click="pull">
                        {{ pullForm.processing ? 'Récupération…' : `Récupérer ${pullForm.count}` }}
                    </button>
                </div>
                <p v-if="pullForm.processing" class="mt-2 text-xs" style="color: var(--color-stone)">
                    ≈ {{ pullForm.count * 2 }}s (délai anti-doublon).
                </p>
            </div>
        </div>

        <!-- Récents -->
        <div class="rule mt-12 flex items-center justify-between pt-4">
            <p class="eyebrow">Derniers imports</p>
            <span class="eyebrow" style="color: var(--color-stone-soft)">Clique un visage pour le qualifier</span>
        </div>
        <div
            v-if="talents.data.length"
            class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
        >
            <Link
                v-for="t in talents.data"
                :key="t.id"
                :href="`/talents/${t.id}/qualify`"
                class="block transition-opacity hover:opacity-90"
            >
                <TalentCard :talent="t" />
            </Link>
        </div>
        <p v-else class="mt-4 text-sm" style="color: var(--color-stone)">
            Rien encore. Récupère une poignée de visages pour commencer.
        </p>
    </AppLayout>
</template>
