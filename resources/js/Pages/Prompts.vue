<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpTip from '@/Components/HelpTip.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    prompts: { type: Array, required: true },
    defaults: { type: Array, required: true },
    placeholders: { type: Array, required: true },
});

// Aperçu des variables : quel token est déplié.
const openPreview = ref(null);
const togglePreview = (token) => (openPreview.value = openPreview.value === token ? null : token);

// Un formulaire d'édition par prompt.
const forms = reactive(
    Object.fromEntries(props.prompts.map((p) => [p.id, useForm({ content: p.content })])),
);

function save(prompt) {
    forms[prompt.id].put(`/prompts/${prompt.id}`, { preserveScroll: true });
}

function restore(prompt) {
    const def = props.defaults.find((d) => d.key === prompt.key);
    if (def) forms[prompt.id].content = def.content;
}

</script>

<template>
    <Head title="Prompts" />
    <AppLayout>
        <div class="flex items-center gap-2">
            <h1 class="display text-3xl">Prompts IA</h1>
            <HelpTip
                title="C'est quoi un prompt"
                detail="Les consignes qu'on donne à l'IA. L'un lui dit comment décrire un visage, l'autre comment transformer une recherche en filtres. Tu peux les ajuster ici : c'est le principal levier pour améliorer les résultats, sans jamais réentraîner de modèle."
                eyebrow="Concept"
            />
        </div>
        <p class="mt-2 max-w-xl text-sm" style="color: var(--color-stone)">
            Édite les consignes, enregistre, relance une analyse. Chaque enregistrement crée une
            <strong style="color: var(--color-klein); font-weight: 600">nouvelle version</strong>.
        </p>

        <!-- Placeholders : description + aperçu de la valeur injectée -->
        <div class="mt-5 space-y-2">
            <div v-for="p in placeholders" :key="p.token" class="card p-3">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <code
                        class="font-mono text-xs"
                        style="background: var(--color-klein-wash); color: var(--color-klein-deep); padding: 0.1rem 0.35rem; border-radius: 2px"
                        >{{ p.token }}</code
                    >
                    <span class="text-xs" style="color: var(--color-stone)">{{ p.desc }}</span>
                    <span class="grow"></span>
                    <button
                        type="button"
                        class="font-mono text-[11px] underline transition-opacity hover:opacity-70"
                        style="color: var(--color-klein)"
                        @click="togglePreview(p.token)"
                    >
                        {{ openPreview === p.token ? 'masquer l’aperçu' : 'voir l’aperçu' }}
                    </button>
                </div>
                <pre
                    v-if="openPreview === p.token"
                    class="mt-2 max-h-64 overflow-auto p-2 font-mono text-[11px] leading-relaxed whitespace-pre-wrap"
                    style="background: var(--color-paper-deep); border-radius: 2px; color: var(--color-stone)"
                    >{{ p.value }}</pre>
            </div>
        </div>

        <!-- Éditeurs -->
        <div class="mt-8 space-y-8">
            <div v-for="prompt in prompts" :key="prompt.id" class="card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">{{ prompt.label }}</p>
                        <p class="eyebrow mt-1">
                            {{ prompt.key }} · version {{ prompt.version }} · maj {{ prompt.updated_at }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="btn btn-ghost !py-2 !text-xs" @click="restore(prompt)">
                            Restaurer le défaut
                        </button>
                        <button
                            class="btn btn-primary !py-2 !text-xs"
                            :disabled="forms[prompt.id].processing"
                            @click="save(prompt)"
                        >
                            {{ forms[prompt.id].processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </button>
                    </div>
                </div>
                <textarea
                    v-model="forms[prompt.id].content"
                    rows="16"
                    class="field mt-4 resize-y font-mono !text-xs leading-relaxed"
                    spellcheck="false"
                />
                <p
                    v-if="forms[prompt.id].errors.content"
                    class="mt-1 text-xs"
                    style="color: var(--color-rust)"
                >
                    {{ forms[prompt.id].errors.content }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
