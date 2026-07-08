<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, required: true }, // [{value,label,hint?}]
});

const emit = defineEmits(['update:modelValue']);

function toggle(value) {
    const set = new Set(props.modelValue ?? []);
    set.has(value) ? set.delete(value) : set.add(value);
    emit('update:modelValue', [...set]);
}

const isOn = (value) => (props.modelValue ?? []).includes(value);
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            class="chip"
            :class="{ 'chip-on': isOn(opt.value) }"
            :title="opt.hint || opt.label"
            @click="toggle(opt.value)"
        >
            {{ opt.label }}
        </button>
    </div>
</template>
