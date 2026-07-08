<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    runs: { type: Array, required: true },
    availableModels: { type: Array, required: true },
    goldReady: { type: Number, required: true },
});

const form = useForm({
    models: props.availableModels.slice(0, 3),
    label: '',
    gold_limit: null,
});

function toggle(model) {
    const set = new Set(form.models);
    set.has(model) ? set.delete(model) : set.add(model);
    form.models = [...set];
}

function launch() {
    form.post('/benchmark/run');
}
</script>

<template>
    <Head title="Benchmark" />
    <AppLayout>
        <p class="eyebrow mb-2">Étape 3b — Choisir le modèle</p>
        <div class="flex items-center gap-2">
            <h1 class="display text-3xl">Comparatif de modèles</h1>
            <HelpTip
                title="À quoi sert le comparatif"
                detail="Tu fais analyser tes images validées par plusieurs IA différentes, avec le même énoncé, et tu compares : laquelle voit le plus juste, laquelle coûte le moins, laquelle répond le plus vite. Objectif : choisir le modèle sur preuve, pas au feeling."
                eyebrow="Concept"
            />
        </div>
        <p class="mt-2 max-w-xl text-sm" style="color: var(--color-stone)">
            Même énoncé, plusieurs modèles, tes images de référence. On compare
            <strong style="color: var(--color-klein); font-weight: 600">justesse, coût et vitesse</strong>.
        </p>

        <div
            v-if="goldReady === 0"
            class="mt-5 flex items-center gap-3 border px-4 py-3"
            style="border-color: var(--color-line-strong); background: var(--color-paper-deep); border-radius: var(--radius-frame)"
        >
            <span class="tag" style="background: var(--color-flag); color: #fff">à faire</span>
            <span class="text-sm">Aucune image validée à la main. Qualifie d’abord quelques talents.</span>
        </div>

        <!-- Lancement -->
        <div class="card mt-6 p-5">
            <p class="eyebrow mb-3">Nouveau comparatif</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="model in availableModels"
                    :key="model"
                    type="button"
                    class="chip font-mono"
                    :class="{ 'chip-on': form.models.includes(model) }"
                    @click="toggle(model)"
                >
                    {{ model.split('/').pop() }}
                </button>
            </div>
            <div class="mt-5 flex flex-wrap items-end gap-3">
                <div>
                    <label class="eyebrow mb-1.5 block">Nom (optionnel)</label>
                    <input v-model="form.label" type="text" placeholder="ex : test énoncé v1" class="field w-56" />
                </div>
                <div>
                    <div class="mb-1.5 flex items-center gap-1">
                        <label class="eyebrow">Images à tester</label>
                        <HelpTip
                            title="Images à tester"
                            detail="Combien de tes images validées passer dans le comparatif. Laisse vide pour toutes les utiliser. En mettre moins = comparatif plus rapide et moins coûteux, mais moins représentatif."
                            eyebrow="Champ"
                        />
                    </div>
                    <input v-model.number="form.gold_limit" type="number" min="1" placeholder="toutes" class="field w-28" />
                </div>
                <button
                    class="btn btn-primary"
                    :disabled="form.processing || !form.models.length || goldReady === 0"
                    @click="launch"
                >
                    {{ form.processing ? 'Comparatif en cours…' : 'Lancer' }}
                </button>
            </div>
            <p v-if="form.processing" class="mt-2 text-xs" style="color: var(--color-stone)">
                Un appel par image × modèle — ça peut prendre un moment.
            </p>
        </div>

        <!-- Historique -->
        <div class="rule mt-10 pt-4">
            <p class="eyebrow">Comparatifs précédents</p>
        </div>
        <div class="card mt-4 overflow-hidden">
            <table v-if="runs.length" class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="run in runs"
                        :key="run.id"
                        class="border-b last:border-0"
                        style="border-color: var(--color-line)"
                    >
                        <td class="px-4 py-3">
                            <Link :href="`/benchmark/${run.id}`" class="font-mono text-xs font-bold" style="color: var(--color-klein)">
                                #{{ String(run.id).padStart(3, '0') }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ run.label || '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-stone)">
                            {{ run.models.map((m) => m.split('/').pop()).join(' · ') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-xs" style="color: var(--color-stone)">
                            {{ run.gold_count }} img · {{ run.created_at }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="p-8 text-sm" style="color: var(--color-stone)">Aucun comparatif pour l’instant.</div>
        </div>
    </AppLayout>
</template>
