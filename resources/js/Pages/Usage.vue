<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    credits: { type: [Object, null], default: null },
    models: { type: Object, required: true },
    activity: { type: Object, required: true },
    benchmark: { type: Object, required: true },
});

const usedPct = computed(() => {
    if (!props.credits || props.credits.total <= 0) return 0;
    return Math.min(100, Math.round((props.credits.used / props.credits.total) * 100));
});

const money = (v) => `$${Number(v).toFixed(v < 1 ? 4 : 2)}`;
</script>

<template>
    <Head title="Usage OpenRouter" />
    <AppLayout>
        <header class="max-w-3xl">
            <p class="eyebrow">Consommation · OpenRouter</p>
            <h1 class="display mt-2 text-4xl sm:text-5xl">Ce que l’IA coûte.</h1>
            <p class="mt-4 text-sm leading-relaxed" style="color: var(--color-stone)">
                Chaque analyse de visage, chaque embedding et chaque recherche appelle un modèle via OpenRouter.
                Voici tes crédits restants et l’activité du moteur.
            </p>
        </header>

        <!-- ═══ CRÉDITS ═══ -->
        <section class="mt-10">
            <div v-if="credits" class="card p-6">
                <div class="flex items-center gap-1.5">
                    <span class="eyebrow">Crédits OpenRouter</span>
                    <HelpTip
                        title="Crédits"
                        detail="Chiffres lus en direct depuis ton compte OpenRouter (achat total moins consommation cumulée). C'est la source de vérité pour ce qu'il te reste à dépenser."
                        eyebrow="Source"
                    />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-[1.2fr_1fr] sm:items-center">
                    <div>
                        <p class="font-mono text-5xl tabular-nums" style="color: var(--color-ink)">{{ money(credits.remaining) }}</p>
                        <p class="eyebrow mt-1">restant</p>

                        <div class="mt-4 h-2.5 overflow-hidden" style="background: var(--color-paper-deep); border-radius: 999px">
                            <div class="h-full" :style="{ width: usedPct + '%', background: usedPct > 85 ? 'var(--color-rust)' : 'var(--color-klein)' }" />
                        </div>
                        <p class="mt-1.5 font-mono text-[11px]" style="color: var(--color-stone)">
                            {{ money(credits.used) }} consommés sur {{ money(credits.total) }} ({{ usedPct }}%)
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="border p-3" style="border-color: var(--color-line); border-radius: var(--radius-frame)">
                            <p class="font-mono text-2xl tabular-nums" style="color: var(--color-ink)">{{ money(credits.total) }}</p>
                            <p class="eyebrow mt-1">Acheté</p>
                        </div>
                        <div class="border p-3" style="border-color: var(--color-line); border-radius: var(--radius-frame)">
                            <p class="font-mono text-2xl tabular-nums" style="color: var(--color-ink)">{{ money(credits.used) }}</p>
                            <p class="eyebrow mt-1">Consommé</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card p-6">
                <p class="text-sm" style="color: var(--color-stone)">
                    Impossible de lire les crédits OpenRouter. Vérifie que <span class="font-mono">OPENROUTER_API_KEY</span>
                    est configurée et que le compte est joignable.
                </p>
            </div>
        </section>

        <!-- ═══ ACTIVITÉ ═══ -->
        <section class="mt-8">
            <p class="eyebrow mb-3">Activité du moteur</p>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="stat in [
                    { k: 'analyses', label: 'Analyses vision', tip: 'Talents lus par un modèle vision.' },
                    { k: 'embeddings', label: 'Embeddings', tip: 'Profils transformés en vecteurs cherchables.' },
                    { k: 'searches', label: 'Recherches', tip: 'Requêtes lancées (chaque requête = 1 parsing + 1 embedding).' },
                    { k: 'benchmark_runs', label: 'Runs benchmark', tip: 'Comparaisons de modèles sur le set de calibration.' },
                ]" :key="stat.k" class="card p-4">
                    <p class="font-mono text-3xl tabular-nums" style="color: var(--color-ink)">{{ activity[stat.k] }}</p>
                    <div class="mt-1 flex items-center gap-1">
                        <p class="eyebrow">{{ stat.label }}</p>
                        <HelpTip :title="stat.label" :detail="stat.tip" eyebrow="Info" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ DÉPENSE MESURÉE (benchmarks) ═══ -->
        <section class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="card p-4">
                <p class="font-mono text-2xl tabular-nums" style="color: var(--color-ink)">${{ benchmark.cost.toFixed(4) }}</p>
                <div class="mt-1 flex items-center gap-1">
                    <p class="eyebrow">Coût benchmarks</p>
                    <HelpTip
                        title="Coût mesuré"
                        detail="Seuls les runs de benchmark enregistrent leur coût réel ligne à ligne. Les analyses et embeddings courants ne sont pas encore chiffrés localement : la vérité $ reste les crédits OpenRouter ci-dessus."
                        eyebrow="Portée"
                    />
                </div>
            </div>
            <div class="card p-4">
                <p class="font-mono text-2xl tabular-nums" style="color: var(--color-ink)">{{ benchmark.prompt_tokens.toLocaleString('fr-FR') }}</p>
                <p class="eyebrow mt-1">Tokens entrée (bench)</p>
            </div>
            <div class="card p-4">
                <p class="font-mono text-2xl tabular-nums" style="color: var(--color-ink)">{{ benchmark.completion_tokens.toLocaleString('fr-FR') }}</p>
                <p class="eyebrow mt-1">Tokens sortie (bench)</p>
            </div>
        </section>

        <!-- ═══ MODÈLES EN JEU ═══ -->
        <section class="mt-8">
            <p class="eyebrow mb-3">Modèles en service</p>
            <div class="card divide-y" style="--tw-divide-opacity: 1">
                <div
                    v-for="role in [
                        { k: 'vision', label: 'Vision', d: 'Lit le visage et remplit le portrait-robot.' },
                        { k: 'embedding', label: 'Embedding', d: 'Transforme le texte en vecteur cherchable.' },
                        { k: 'parsing', label: 'Parsing requête', d: 'Découpe ta demande en filtres + sémantique.' },
                    ]"
                    :key="role.k"
                    class="flex items-center justify-between gap-4 p-4"
                    style="border-color: var(--color-line)"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold" style="color: var(--color-ink)">{{ role.label }}</p>
                        <p class="text-xs" style="color: var(--color-stone)">{{ role.d }}</p>
                    </div>
                    <span class="shrink-0 font-mono text-xs" style="color: var(--color-klein-deep)">{{ models[role.k] }}</span>
                </div>
            </div>
        </section>

        <p class="mt-10 text-xs" style="color: var(--color-stone-soft)">
            <Link href="/labo" class="underline" style="color: var(--color-klein)">← Comprendre la méthode (Le Labo)</Link>
        </p>
    </AppLayout>
</template>
