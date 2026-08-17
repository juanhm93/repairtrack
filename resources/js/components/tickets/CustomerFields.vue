<script setup lang="ts">
import { Check } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Customer } from '@/types';

const props = defineProps<{
    customers: Customer[];
    errors: Record<string, string>;
}>();

const email = ref('');
const name = ref('');
const phone = ref('');
const open = ref(false);
const highlightedIndex = ref(0);
const root = useTemplateRef<HTMLElement>('root');

const normalizedEmail = computed(() => email.value.trim().toLowerCase());

const selectedCustomer = computed(
    () =>
        props.customers.find(
            (customer) =>
                customer.email.toLowerCase() === normalizedEmail.value,
        ) ?? null,
);

const suggestions = computed(() => {
    const query = email.value.trim().toLowerCase();

    const matches = query
        ? props.customers.filter((customer) => {
              const haystack = [
                  customer.email,
                  customer.name,
                  customer.phone ?? '',
              ]
                  .join(' ')
                  .toLowerCase();

              return haystack.includes(query);
          })
        : props.customers;

    return matches.slice(0, 8);
});

const showSuggestions = computed(
    () =>
        open.value &&
        props.customers.length > 0 &&
        suggestions.value.length > 0,
);

onClickOutside(root, () => {
    open.value = false;
});

watch(suggestions, () => {
    highlightedIndex.value = 0;
});

const selectCustomer = (customer: Customer): void => {
    email.value = customer.email;
    name.value = customer.name;
    phone.value = customer.phone ?? '';
    open.value = false;
};

const onEmailKeydown = (event: KeyboardEvent): void => {
    if (!showSuggestions.value) {
        if (event.key === 'ArrowDown' && suggestions.value.length > 0) {
            open.value = true;
            event.preventDefault();
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value =
            (highlightedIndex.value + 1) % suggestions.value.length;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value =
            (highlightedIndex.value - 1 + suggestions.value.length) %
            suggestions.value.length;
    }

    if (event.key === 'Enter') {
        const highlighted = suggestions.value[highlightedIndex.value];

        if (highlighted) {
            event.preventDefault();
            selectCustomer(highlighted);
        }
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
};
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2 sm:col-span-2">
            <Label for="customer_email">Correo</Label>
            <div ref="root" class="relative">
                <Input
                    id="customer_email"
                    v-model="email"
                    type="email"
                    name="customer_email"
                    required
                    autofocus
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Busca por correo o nombre"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="showSuggestions"
                    aria-controls="customer-email-list"
                    :aria-activedescendant="
                        showSuggestions
                            ? `customer-option-${highlightedIndex}`
                            : undefined
                    "
                    @focus="open = true"
                    @keydown="onEmailKeydown"
                />
                <ul
                    v-if="showSuggestions"
                    id="customer-email-list"
                    role="listbox"
                    class="absolute top-full z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    <li
                        v-for="(customer, index) in suggestions"
                        :id="`customer-option-${index}`"
                        :key="customer.id"
                        role="option"
                        :aria-selected="selectedCustomer?.id === customer.id"
                        :class="
                            cn(
                                'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm',
                                index === highlightedIndex && 'bg-accent',
                            )
                        "
                        @mousedown.prevent="selectCustomer(customer)"
                        @mouseenter="highlightedIndex = index"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ customer.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ customer.email }}
                                <span v-if="customer.phone">
                                    · {{ customer.phone }}
                                </span>
                            </p>
                        </div>
                        <Check
                            v-if="selectedCustomer?.id === customer.id"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                    </li>
                </ul>
            </div>
            <p v-if="selectedCustomer" class="text-sm text-muted-foreground">
                Cliente existente: {{ selectedCustomer.name }}
                <span v-if="selectedCustomer.phone">
                    · {{ selectedCustomer.phone }}
                </span>
            </p>
            <InputError :message="errors.customer_email" />
        </div>

        <template v-if="!selectedCustomer">
            <div class="grid gap-2">
                <Label for="customer_name">Nombre</Label>
                <Input
                    id="customer_name"
                    v-model="name"
                    name="customer_name"
                    required
                    autocomplete="name"
                    placeholder="Nombre del cliente"
                />
                <InputError :message="errors.customer_name" />
            </div>
            <div class="grid gap-2">
                <Label for="customer_phone">Teléfono</Label>
                <Input
                    id="customer_phone"
                    v-model="phone"
                    name="customer_phone"
                    autocomplete="tel"
                    placeholder="Opcional"
                />
                <InputError :message="errors.customer_phone" />
            </div>
        </template>
    </div>
</template>
