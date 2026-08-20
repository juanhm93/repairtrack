<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/plugins/date';
import { home } from '@/routes';
import type { PublicTicketPage } from '@/types';
import { deviceTypeLabel, ticketStatusVariant } from '@/types';

const { app, ticket, customer } = defineProps<PublicTicketPage>();

const equipmentTitle = computed(() => {
    const type = deviceTypeLabel(ticket.device_type);
    const parts = [ticket.brand, ticket.model].filter(Boolean);

    if (parts.length === 0) {
        return type;
    }

    return `${type} · ${parts.join(' ')}`;
});
</script>

<template>
    <Head :title="`Status de tu equipo — ${app.name}`" />

    <div class="flex min-h-svh flex-col bg-background">
        <header class="border-b border-border bg-card">
            <div
                class="mx-auto flex w-full max-w-2xl items-center justify-between px-4 py-4"
            >
                <p
                    class="font-display text-lg font-semibold tracking-tight text-foreground"
                >
                    {{ app.name }}
                </p>
                <Link
                    :href="home()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Inicio
                </Link>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
            <div class="space-y-2">
                <p class="text-sm text-muted-foreground">
                    Hola, {{ customer.name }}
                </p>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Así va tu equipo
                </h1>
            </div>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="space-y-1">
                            <CardTitle>{{ equipmentTitle }}</CardTitle>
                            <CardDescription>
                                Estado actual de tu reparación
                            </CardDescription>
                        </div>
                        <Badge :variant="ticketStatusVariant(ticket.status)">
                            {{ ticket.status_label }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Tipo</p>
                        <p class="font-medium">
                            {{ deviceTypeLabel(ticket.device_type) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Equipo</p>
                        <p class="font-medium">
                            {{
                                [ticket.brand, ticket.model]
                                    .filter(Boolean)
                                    .join(' ') || '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Recepción</p>
                        <p class="font-medium">
                            {{ formatDate(ticket.received_at) }}
                        </p>
                    </div>
                    <div v-if="ticket.estimated_delivery_at">
                        <p class="text-sm text-muted-foreground">
                            Entrega estimada
                        </p>
                        <p class="font-medium">
                            {{ formatDate(ticket.estimated_delivery_at) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Historial</CardTitle>
                    <CardDescription>
                        Cada cambio de estado, del más antiguo al más reciente.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ol
                        v-if="ticket.history.length"
                        class="relative space-y-6 border-l border-border pl-6"
                    >
                        <li
                            v-for="(item, index) in ticket.history"
                            :key="`${item.to_status}-${item.created_at}-${index}`"
                            class="relative"
                        >
                            <span
                                class="absolute top-1.5 -left-[1.45rem] size-2.5 rounded-full bg-primary"
                            />
                            <p class="font-medium">
                                {{ item.to_status_label }}
                            </p>
                            <p
                                v-if="item.note"
                                class="mt-1 text-sm whitespace-pre-wrap"
                            >
                                {{ item.note }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatDateTime(item.created_at) }}
                            </p>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        Aún no hay historial.
                    </p>
                </CardContent>
            </Card>
        </main>

        <footer class="border-t border-border">
            <p
                class="mx-auto max-w-2xl px-4 py-4 text-center text-xs text-muted-foreground"
            >
                Consulta generada por {{ app.name }}
            </p>
        </footer>
    </div>
</template>
