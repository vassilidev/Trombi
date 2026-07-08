<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    options: { type: Array, required: true }, // [{value,label,hint?}]
    placeholder: { type: String, default: 'Choisir…' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const searchInput = ref(null);

const selectedLabel = computed(
    () => props.options.find((o) => o.value === props.modelValue)?.label ?? '',
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(q) ||
            o.value.toLowerCase().includes(q) ||
            (o.hint || '').toLowerCase().includes(q),
    );
});

function toggle() {
    open.value = !open.value;
    if (open.value) nextTick(() => searchInput.value?.focus());
}

function pick(value) {
    emit('update:modelValue', value);
    open.value = false;
    query.value = '';
}

function clear() {
    emit('update:modelValue', null);
}
</script>

<template>
    <div v-click-outside="() => (open = false)" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 border px-3 py-2 text-left text-sm transition-colors"
            style="border-color: var(--color-line-strong); background: var(--color-card); border-radius: var(--radius-frame)"
            :style="{ color: modelValue ? 'var(--color-ink)' : 'var(--color-stone-soft)' }"
            @click="toggle"
        >
            <span class="truncate">{{ selectedLabel || placeholder }}</span>
            <span class="flex items-center gap-1.5">
                <span
                    v-if="modelValue"
                    role="button"
                    class="text-[10px]"
                    style="color: var(--color-stone-soft)"
                    @click.stop="clear"
                    >✕</span
                >
                <span style="color: var(--color-stone-soft)">▾</span>
            </span>
        </button>

        <div
            v-if="open"
            class="card absolute z-30 mt-1 w-full overflow-hidden"
            style="box-shadow: 0 18px 44px -18px rgba(4, 6, 20, 0.7)"
        >
            <input
                ref="searchInput"
                v-model="query"
                type="text"
                placeholder="Filtrer…"
                class="w-full border-b px-3 py-2 text-sm focus:outline-none"
                style="border-color: var(--color-line)"
                @keydown.enter.prevent="filtered[0] && pick(filtered[0].value)"
                @keydown.esc="open = false"
            />
            <ul class="max-h-60 overflow-auto py-1">
                <li v-for="opt in filtered" :key="opt.value">
                    <button
                        type="button"
                        class="w-full px-3 py-1.5 text-left transition-colors hover:bg-[var(--color-klein-wash)]"
                        :style="{
                            background: opt.value === modelValue ? 'var(--color-klein-wash)' : 'transparent',
                        }"
                        @click="pick(opt.value)"
                    >
                        <span
                            class="text-sm"
                            :style="{ fontWeight: opt.value === modelValue ? 600 : 400 }"
                            >{{ opt.label }}</span
                        >
                        <span
                            v-if="opt.hint"
                            class="mt-0.5 block truncate text-xs"
                            style="color: var(--color-stone)"
                            >{{ opt.hint }}</span
                        >
                    </button>
                </li>
                <li v-if="!filtered.length" class="px-3 py-2 text-sm" style="color: var(--color-stone-soft)">
                    Aucun résultat
                </li>
            </ul>
        </div>
    </div>
</template>
