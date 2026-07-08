<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
});

const emit = defineEmits(['close']);

function onKey(e) {
    if (e.key === 'Escape' && props.open) emit('close');
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(23, 21, 15, 0.35)"
                @mousedown.self="emit('close')"
            >
                <transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="open"
                        class="card w-full max-w-md overflow-hidden"
                        style="box-shadow: 0 24px 60px -20px rgba(23, 21, 15, 0.35)"
                    >
                        <div
                            class="flex items-start justify-between gap-4 border-b px-5 py-4"
                            style="border-color: var(--color-line)"
                        >
                            <div>
                                <p v-if="eyebrow" class="eyebrow mb-1.5">{{ eyebrow }}</p>
                                <h2 class="display text-xl">{{ title }}</h2>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 text-lg leading-none transition-colors"
                                style="color: var(--color-stone)"
                                aria-label="Fermer"
                                @click="emit('close')"
                            >
                                ✕
                            </button>
                        </div>
                        <div class="px-5 py-4">
                            <slot />
                        </div>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>
