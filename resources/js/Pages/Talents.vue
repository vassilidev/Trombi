<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TalentCard from '@/Components/TalentCard.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    talents: { type: Object, required: true },
    filter: { type: String, required: true },
    stats: { type: Object, required: true },
});

const analyzeForm = useForm({});

function analyzePending() {
    analyzeForm.post('/talents/analyze-pending', { preserveScroll: true });
}

const filters = [
    { key: 'all', label: 'Tous' },
    { key: 'unqualified', label: 'À qualifier' },
    { key: 'gold', label: 'Validés' },
    { key: 'analyzed', label: 'Décrits par l’IA' },
];

function setFilter(key) {
    router.get('/talents', { filter: key }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Talents" />
    <AppLayout>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow mb-2">Étape 2 — Qualifier</p>
                <h1 class="display text-3xl">Talents</h1>
                <p class="mt-2 text-sm" style="color: var(--color-stone)">
                    <strong style="color: var(--color-ink); font-weight: 600">{{ stats.total }}</strong> au total ·
                    <strong style="color: var(--color-klein); font-weight: 600">{{ stats.gold }}</strong> validés à la main ·
                    <strong style="color: var(--color-ink); font-weight: 600">{{ stats.analyzed }}</strong> décrits par l'IA
                </p>
                <div v-if="stats.pending > 0" class="mt-3 flex items-center gap-1.5">
                    <button class="btn btn-primary !py-2 !text-xs" :disabled="analyzeForm.processing" @click="analyzePending">
                        Décrire les {{ stats.pending }} restants avec l'IA
                    </button>
                    <HelpTip
                        title="Décrire avec l'IA"
                        detail="Pour chaque visage pas encore traité, l'IA remplit le portrait-robot et rédige une description, puis rend le profil recherchable. Le traitement tourne en arrière-plan (garde la file d'attente active)."
                        eyebrow="Que fait ce bouton ?"
                    />
                </div>
            </div>
            <div class="flex w-full flex-wrap gap-x-4 gap-y-1 sm:w-auto">
                <button
                    v-for="f in filters"
                    :key="f.key"
                    class="eyebrow relative py-1 transition-colors"
                    :style="{ color: filter === f.key ? 'var(--color-ink)' : 'var(--color-stone)' }"
                    @click="setFilter(f.key)"
                >
                    {{ f.label }}
                    <span
                        v-if="filter === f.key"
                        class="absolute -bottom-1 left-0 h-[2px] w-full"
                        style="background: var(--color-klein)"
                    />
                </button>
            </div>
        </div>

        <div
            v-if="talents.data.length"
            class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
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
        <p v-else class="mt-8 text-sm" style="color: var(--color-stone)">Aucun talent dans ce filtre.</p>

        <!-- Pagination -->
        <div v-if="talents.meta && talents.meta.last_page > 1" class="mt-10 flex justify-center gap-1">
            <Link
                v-for="link in talents.meta.links"
                :key="link.label"
                :href="link.url || ''"
                v-html="link.label"
                class="px-3 py-1.5 font-mono text-xs transition-colors"
                :style="{
                    color: link.active ? '#fff' : 'var(--color-stone)',
                    background: link.active ? 'var(--color-ink)' : 'transparent',
                    borderRadius: '2px',
                    pointerEvents: link.url ? 'auto' : 'none',
                    opacity: link.url ? 1 : 0.4,
                }"
            />
        </div>
    </AppLayout>
</template>
