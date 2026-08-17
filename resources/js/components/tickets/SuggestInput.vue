<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const props = defineProps<{
    id: string;
    name: string;
    suggestions: string[];
    placeholder?: string;
}>();

const model = defineModel<string>({ default: '' });

const open = ref(false);
const highlightedIndex = ref(0);
const root = useTemplateRef<HTMLElement>('root');

const filtered = computed(() => {
    const query = model.value.trim().toLowerCase();

    const matches = query
        ? props.suggestions.filter((suggestion) =>
              suggestion.toLowerCase().includes(query),
          )
        : props.suggestions;

    return matches.slice(0, 8);
});

const showSuggestions = computed(() => open.value && filtered.value.length > 0);

onClickOutside(root, () => {
    open.value = false;
});

watch(filtered, () => {
    highlightedIndex.value = 0;
});

const selectSuggestion = (suggestion: string): void => {
    model.value = suggestion;
    open.value = false;
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!showSuggestions.value) {
        if (event.key === 'ArrowDown' && filtered.value.length > 0) {
            open.value = true;
            event.preventDefault();
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value =
            (highlightedIndex.value + 1) % filtered.value.length;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value =
            (highlightedIndex.value - 1 + filtered.value.length) %
            filtered.value.length;
    }

    if (event.key === 'Enter') {
        const highlighted = filtered.value[highlightedIndex.value];

        if (highlighted) {
            event.preventDefault();
            selectSuggestion(highlighted);
        }
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
};
</script>

<template>
    <div ref="root" class="relative">
        <Input
            :id="id"
            v-model="model"
            :name="name"
            autocomplete="off"
            spellcheck="false"
            :placeholder="placeholder"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="showSuggestions"
            :aria-controls="`${id}-list`"
            :aria-activedescendant="
                showSuggestions ? `${id}-option-${highlightedIndex}` : undefined
            "
            @focus="open = true"
            @keydown="onKeydown"
        />
        <ul
            v-if="showSuggestions"
            :id="`${id}-list`"
            role="listbox"
            class="absolute top-full z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <li
                v-for="(suggestion, index) in filtered"
                :id="`${id}-option-${index}`"
                :key="suggestion"
                role="option"
                :aria-selected="
                    model.trim().toLowerCase() === suggestion.toLowerCase()
                "
                :class="
                    cn(
                        'cursor-pointer rounded-sm px-2 py-1.5 text-sm',
                        index === highlightedIndex && 'bg-accent',
                    )
                "
                @mousedown.prevent="selectSuggestion(suggestion)"
                @mouseenter="highlightedIndex = index"
            >
                {{ suggestion }}
            </li>
        </ul>
    </div>
</template>
