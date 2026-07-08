<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const nav = [
    { label: 'Recherche', href: '/' },
    { label: 'Import', href: '/import' },
    { label: 'Talents', href: '/talents' },
    { label: 'Calibration', href: '/calibration' },
    { label: 'Benchmark', href: '/benchmark' },
    { label: 'Prompts', href: '/prompts' },
];

const isActive = (href) =>
    href === '/' ? currentPath.value === '/' : currentPath.value.startsWith(href);

const flash = computed(() => page.props.flash?.message);
</script>

<template>
    <div class="min-h-full" style="overflow-x: clip">
        <!-- Liseré Klein : la colonne vertébrale de l'app -->
        <div class="h-[3px] w-full" style="background: var(--color-klein)" />

        <header class="border-b" style="border-color: var(--color-line)">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <Link href="/" class="group flex items-baseline gap-2.5">
                    <span class="display text-2xl leading-none">Trombi</span>
                    <span class="eyebrow hidden sm:inline">Moteur de casting</span>
                </Link>

                <nav class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="eyebrow relative py-1 transition-colors"
                        :style="{ color: isActive(item.href) ? 'var(--color-ink)' : 'var(--color-stone)' }"
                    >
                        {{ item.label }}
                        <span
                            v-if="isActive(item.href)"
                            class="absolute -bottom-[13px] left-0 h-[2px] w-full"
                            style="background: var(--color-klein)"
                        />
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-1 opacity-0"
            >
                <div
                    v-if="flash"
                    class="mb-8 flex items-center gap-3 border px-4 py-3"
                    style="border-color: var(--color-line-strong); background: var(--color-klein-wash); border-radius: var(--radius-frame)"
                >
                    <span class="tag" style="background: var(--color-klein); color: #fff">OK</span>
                    <span class="text-sm" style="color: var(--color-ink)">{{ flash }}</span>
                </div>
            </transition>

            <slot />
        </main>
    </div>
</template>
