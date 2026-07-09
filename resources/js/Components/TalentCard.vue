<script setup>
defineProps({
    talent: { type: Object, required: true },
    score: { type: Number, default: null },
});
</script>

<template>
    <figure class="card group overflow-hidden">
        <div class="relative aspect-[4/5] overflow-hidden" style="background: var(--color-paper-deep)">
            <img
                v-if="talent.photo_url"
                :src="talent.photo_url"
                :alt="talent.code"
                class="size-full object-cover transition duration-500 ease-out group-hover:scale-[1.03]"
                style="filter: grayscale(0.35) contrast(1.02)"
                loading="lazy"
                onmouseover="this.style.filter='grayscale(0) contrast(1)'"
                onmouseout="this.style.filter='grayscale(0.35) contrast(1.02)'"
            />
            <div v-else class="eyebrow grid size-full place-items-center" style="color: var(--color-stone-soft)">
                sans photo
            </div>

            <span
                v-if="score !== null"
                class="absolute right-2 top-2 font-mono text-xs font-bold tabular-nums"
                style="background: var(--color-ink); color: var(--color-paper); padding: 0.15rem 0.4rem; border-radius: 2px"
            >
                {{ Math.round(score * 100) }}
            </span>
            <span
                v-if="talent.photos_count > 1"
                class="absolute left-2 top-2 font-mono text-[10px] font-bold"
                style="background: rgba(9, 11, 32, 0.8); color: var(--color-ink); padding: 0 0.3rem; border-radius: 2px"
                >×{{ talent.photos_count }}</span
            >
        </div>

        <figcaption
            class="flex items-center justify-between gap-2 border-t px-2.5 py-1.5"
            style="border-color: var(--color-line)"
        >
            <span class="min-w-0">
                <span v-if="talent.name" class="block truncate text-xs font-semibold" style="color: var(--color-ink)" :title="talent.name">
                    {{ talent.name }}
                </span>
                <span class="block truncate font-mono text-[11px]" style="color: var(--color-stone)" :title="talent.location || talent.code">
                    {{ talent.location || talent.code }}
                </span>
            </span>
            <div class="flex shrink-0 items-center gap-1">
                <span
                    v-if="talent.is_gold"
                    class="tag"
                    style="background: var(--color-ink); color: var(--color-paper)"
                    title="Qualifié à la main — sert de référence"
                    >validé</span
                >
                <span
                    v-if="talent.is_analyzed"
                    class="tag"
                    style="background: var(--color-klein-wash); color: var(--color-klein-deep)"
                    >IA</span
                >
            </div>
        </figcaption>
    </figure>
</template>
