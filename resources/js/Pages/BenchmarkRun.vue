<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const run = props.summary.run;
const models = run.models;
const showImages = ref(false);

const short = (m) => m.split('/').pop();
const pct = (v) => (v === null || v === undefined ? '—' : Math.round(v * 100) + '%');
const usd = (v) => (v === null || v === undefined ? '—' : '$' + Number(v).toFixed(4));

function rateColor(rate) {
    if (rate === null || rate === undefined) return 'var(--color-stone-soft)';
    if (rate >= 0.8) return 'var(--color-pine)';
    if (rate >= 0.5) return 'var(--color-flag)';
    return 'var(--color-rust)';
}
</script>

<template>
    <Head :title="`Comparatif #${run.id}`" />
    <AppLayout>
        <Link href="/benchmark" class="eyebrow transition-colors hover:text-[var(--color-ink)]">
            ← Comparatifs
        </Link>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="display text-3xl">
                    Comparatif #{{ String(run.id).padStart(3, '0') }}
                    <span v-if="run.label" class="italic" style="color: var(--color-stone)">· {{ run.label }}</span>
                </h1>
                <p class="mt-2 font-mono text-xs" style="color: var(--color-stone)">
                    {{ run.gold_count }} images · énoncé {{ run.prompt_version }} · {{ run.created_at }}
                </p>
            </div>
            <div
                v-if="summary.bestValue"
                class="border px-4 py-2.5"
                style="border-color: var(--color-klein); background: var(--color-klein-wash); border-radius: var(--radius-frame)"
            >
                <div class="flex items-center gap-1">
                    <span class="eyebrow" style="color: var(--color-klein-deep)">Meilleur rapport qualité / coût</span>
                    <HelpTip
                        title="Meilleur rapport qualité / coût"
                        detail="Le modèle qui offre le meilleur compromis entre justesse et prix. Le plus juste n’est pas toujours le plus cher — c’est là qu’on gagne de l’argent."
                        eyebrow="Info"
                    />
                </div>
                <div class="mt-0.5 font-mono text-sm font-bold" style="color: var(--color-klein-deep)">
                    {{ short(summary.bestValue) }}
                </div>
            </div>
        </div>

        <!-- Récap par modèle -->
        <div class="rule mt-8 flex items-center gap-1.5 pt-4">
            <p class="eyebrow">Récapitulatif</p>
        </div>
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-b text-left" style="border-color: var(--color-line)">
                        <th class="px-4 py-2.5 font-normal"><span class="eyebrow">Modèle</span></th>
                        <th class="px-4 py-2.5 font-normal">
                            <span class="inline-flex items-center gap-1">
                                <span class="eyebrow">Justesse</span>
                                <HelpTip title="Justesse" detail="Part des critères où le modèle a répondu comme toi (ton label de référence). Plus c’est haut, mieux le modèle voit comme un humain." eyebrow="Colonne" />
                            </span>
                        </th>
                        <th class="px-4 py-2.5 font-normal">
                            <span class="inline-flex items-center gap-1">
                                <span class="eyebrow">Réponses propres</span>
                                <HelpTip title="Réponses propres" detail="Part des réponses bien formées, qui n’utilisent que le vocabulaire autorisé. Un modèle peut avoir l’air juste mais inventer des valeurs : cette colonne le démasque." eyebrow="Colonne" />
                            </span>
                        </th>
                        <th class="px-4 py-2.5 font-normal"><span class="eyebrow">Vitesse (méd. / p95)</span></th>
                        <th class="px-4 py-2.5 font-normal"><span class="eyebrow">Coût total</span></th>
                        <th class="px-4 py-2.5 font-normal"><span class="eyebrow">Coût / image</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in summary.perModel" :key="m.model" class="border-b last:border-0" style="border-color: var(--color-line)">
                        <td class="px-4 py-2.5 font-mono text-xs">
                            {{ short(m.model) }}
                            <span v-if="m.model === summary.bestValue" style="color: var(--color-klein)">★</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono font-bold tabular-nums" :style="{ color: rateColor(m.justesse) }">{{ pct(m.justesse) }}</td>
                        <td class="px-4 py-2.5 font-mono tabular-nums" :style="{ color: rateColor(m.valid_rate) }">{{ pct(m.valid_rate) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs tabular-nums" style="color: var(--color-stone)">
                            {{ m.latency_p50 ?? '—' }} / {{ m.latency_p95 ?? '—' }} ms
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs tabular-nums">{{ usd(m.cost_total) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs tabular-nums">{{ usd(m.cost_per_image) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Delta par attribut -->
        <div class="rule mt-8 flex items-center gap-1.5 pt-4">
            <p class="eyebrow">Justesse par critère</p>
            <HelpTip title="Justesse par critère" detail="Sur quel critère chaque modèle se trompe le plus. Si tous ratent le même critère (souvent la carnation ou le type perçu), c’est le signal pour reformuler l’énoncé." eyebrow="Lecture" />
        </div>
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead>
                    <tr class="border-b text-left" style="border-color: var(--color-line)">
                        <th class="px-4 py-2.5 font-normal"><span class="eyebrow">Critère</span></th>
                        <th v-for="m in models" :key="m" class="px-4 py-2.5 font-normal">
                            <span class="eyebrow">{{ short(m) }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in summary.deltaByAttribute" :key="row.attribute" class="border-b last:border-0" style="border-color: var(--color-line)">
                        <td class="px-4 py-2 font-mono text-xs" style="color: var(--color-stone)">{{ row.attribute }}</td>
                        <td v-for="m in models" :key="m" class="px-4 py-2 font-mono text-xs font-bold tabular-nums" :style="{ color: rateColor(row.models[m]) }">
                            {{ pct(row.models[m]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Delta par image -->
        <div class="rule mt-8 flex items-center justify-between pt-4">
            <div class="flex items-center gap-1.5">
                <p class="eyebrow">Image par image ({{ summary.deltaByImage.length }})</p>
                <HelpTip title="Image par image" detail="Ton label à gauche, chaque modèle à côté, critère par critère. Vert = le modèle a vu juste, rouge = faux. Le détail concret de ce qui marche et ce qui coince." eyebrow="Lecture" />
            </div>
            <button class="eyebrow transition-colors hover:text-[var(--color-ink)]" @click="showImages = !showImages">
                {{ showImages ? 'Masquer' : 'Afficher' }}
            </button>
        </div>
        <div v-if="showImages" class="mt-4 space-y-5">
            <div v-for="img in summary.deltaByImage" :key="img.talent.id" class="card overflow-hidden">
                <div class="flex items-center gap-3 border-b p-3" style="border-color: var(--color-line)">
                    <img v-if="img.talent.photo_url" :src="img.talent.photo_url" class="size-12 object-cover" style="border-radius: 2px" />
                    <span class="font-mono text-xs" style="color: var(--color-stone)">{{ img.talent.code }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-xs">
                        <thead>
                            <tr class="border-b text-left" style="border-color: var(--color-line)">
                                <th class="px-3 py-2 font-normal"><span class="eyebrow">Critère</span></th>
                                <th class="px-3 py-2 font-normal"><span class="eyebrow">Toi</span></th>
                                <th v-for="m in models" :key="m" class="px-3 py-2 font-normal"><span class="eyebrow">{{ short(m) }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in img.fields" :key="f.attribute" class="border-b last:border-0" style="border-color: var(--color-line)">
                                <td class="px-3 py-1.5 font-mono" style="color: var(--color-stone)">{{ f.attribute }}</td>
                                <td class="px-3 py-1.5 font-medium">{{ f.human || '—' }}</td>
                                <td
                                    v-for="m in models"
                                    :key="m"
                                    class="px-3 py-1.5"
                                    :style="{ color: f.models[m].agree ? 'var(--color-pine)' : 'var(--color-rust)' }"
                                >
                                    {{ f.models[m].value || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
