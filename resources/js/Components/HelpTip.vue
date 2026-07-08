<script setup>
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    detail: { type: String, default: '' },
    note: { type: String, default: null },
    // Liste optionnelle de définitions : [{ label, hint }]
    items: { type: Array, default: () => [] },
    eyebrow: { type: String, default: 'Aide' },
});

const open = ref(false);
</script>

<template>
    <span class="inline-flex">
        <button
            type="button"
            class="grid size-4 place-items-center rounded-full border text-[10px] font-bold leading-none transition-colors"
            style="border-color: var(--color-line-strong); color: var(--color-stone)"
            :aria-label="`Aide : ${title}`"
            @click.stop.prevent="open = true"
            @mouseover="$event.currentTarget.style.color = 'var(--color-klein)'"
            @mouseout="$event.currentTarget.style.color = 'var(--color-stone)'"
        >
            i
        </button>

        <Modal :open="open" :eyebrow="eyebrow" :title="title" @close="open = false">
            <p v-if="detail" class="text-sm leading-relaxed" style="color: var(--color-ink)">
                {{ detail }}
            </p>

            <ul v-if="items.length" class="mt-4 space-y-2">
                <li
                    v-for="item in items"
                    :key="item.label"
                    class="flex gap-3 border-b pb-2 last:border-0"
                    style="border-color: var(--color-line)"
                >
                    <span
                        class="tag mt-0.5 shrink-0"
                        style="background: var(--color-paper-deep); color: var(--color-ink)"
                        >{{ item.label }}</span
                    >
                    <span class="text-sm" style="color: var(--color-stone)">{{ item.hint }}</span>
                </li>
            </ul>

            <p
                v-if="note"
                class="mt-4 border-l-2 pl-3 text-xs italic leading-relaxed"
                style="border-color: var(--color-klein); color: var(--color-stone)"
            >
                {{ note }}
            </p>
        </Modal>
    </span>
</template>
