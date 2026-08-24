<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

type Props = {
    class?: HTMLAttributes['class'];
    size?: 'sm' | 'md' | 'lg';
};

withDefaults(defineProps<Props>(), {
    size: 'md',
});

const sizeClasses = {
    sm: 'text-base',
    md: 'text-lg',
    lg: 'text-xl',
} as const;
</script>

<template>
    <span
        :class="[
            'font-[family-name:var(--font-display)] font-bold tracking-tight',
            sizeClasses[size],
            $props.class,
        ]"
    >
        <span class="text-[var(--text-primary)]">Repair</span
        ><span class="font-medium text-[var(--accent)]">Track</span>
    </span>
</template>
