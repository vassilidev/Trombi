<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    summary: { type: Object, required: true },
    perAttribute: { type: Array, required: true },
});

const form = useForm({});

function analyzeGold() {
    form.post('/calibration/analyze-gold', { preserveScroll: true });
}

function rateColor(rate) {
    if (rate === null) return 'var(--color-line-strong)';
    if (rate >= 0.8) return 'var(--color-pine)';
    if (rate >= 0.5) return 'var(--color-flag)';
    return 'var(--color-rust)';
}

const cards = [
    { key: 'gold', label: 'images validées', help: null },
    {
        key: 'pairs',
        label: 'comparées à l’IA',
        help: 'Images à la fois validées par toi et analysées par l’IA : les seules qu’on peut comparer.',
    },
    { key: 'overall', label: 'accord global', percent: true, help: 'Sur l’ensemble des critères, à quel point l’IA voit comme toi.' },
    { key: 'pending', label: 'validées, pas encore analysées', help: null },
];
</script>

<template>
    <Head title="Calibration" />
    <AppLayout>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow mb-2">Étape 3 — Mesurer l’IA</p>
                <div class="flex items-center gap-2">
                    <h1 class="display text-3xl">Calibration</h1>
                    <HelpTip
                        title="À quoi sert la calibration"
                        detail="Tu qualifies des images à la main, l’IA fait les mêmes, et on compare critère par critère. Ça te dit lesquels sont fiables et lesquels demandent prudence. On n’entraîne aucun modèle : on améliore le prompt et le vocabulaire en boucle."
                        eyebrow="Concept"
                    />
                </div>
                <p class="mt-2 max-w-lg text-sm" style="color: var(--color-stone)">
                    Où l’IA <strong style="color: var(--color-klein); font-weight: 600">voit juste</strong>, où elle
                    se trompe. Ta boussole de fiabilité, critère par critère.
                </p>
            </div>
            <button class="btn btn-ink" :disabled="form.processing" @click="analyzeGold">
                {{ form.processing ? 'Analyse…' : 'Analyser les images validées' }}
            </button>
        </div>

        <!-- Résumé -->
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="c in cards" :key="c.key" class="card p-4">
                <div class="display text-3xl" :style="{ color: c.percent ? 'var(--color-klein)' : 'var(--color-ink)' }">
                    <template v-if="c.percent">
                        {{ summary[c.key] !== null ? Math.round(summary[c.key] * 100) + '%' : '—' }}
                    </template>
                    <template v-else>{{ summary[c.key] }}</template>
                </div>
                <div class="mt-1.5 flex items-center gap-1">
                    <span class="eyebrow">{{ c.label }}</span>
                    <HelpTip v-if="c.help" :title="c.label" :detail="c.help" eyebrow="Info" />
                </div>
            </div>
        </div>

        <!-- Par attribut -->
        <div class="rule mt-10 flex items-center gap-1.5 pt-4">
            <p class="eyebrow">Accord par critère</p>
            <HelpTip
                title="Accord par critère"
                detail="Pour chaque critère, le pourcentage d’images où l’IA a dit la même chose que toi. Vert = fiable, orange = à surveiller, rouge = peu fiable (souvent les critères subjectifs comme l’âge ou le type perçu)."
                eyebrow="Lecture"
            />
        </div>
        <div class="card mt-4">
            <div v-if="summary.pairs === 0" class="p-8 text-sm" style="color: var(--color-stone)">
                Rien à comparer pour l’instant. Qualifie des images, puis lance l’analyse IA ci-dessus.
            </div>
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="row in perAttribute"
                        :key="row.attribute"
                        class="border-b last:border-0"
                        style="border-color: var(--color-line)"
                    >
                        <td class="px-4 py-2.5 font-mono text-xs" style="color: var(--color-stone); width: 30%">
                            {{ row.attribute }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="h-1.5 w-full overflow-hidden" style="background: var(--color-paper-deep); border-radius: 2px">
                                <div
                                    class="h-full"
                                    :style="{ width: `${(row.rate ?? 0) * 100}%`, background: rateColor(row.rate) }"
                                />
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-xs tabular-nums" style="width: 90px">
                            {{ row.rate !== null ? Math.round(row.rate * 100) + '%' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
