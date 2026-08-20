<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/plugins/date';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/tickets';
import type {
    DashboardMonth,
    DashboardStats,
    TicketListItem,
    TicketStatus,
} from '@/types';
import { deviceTypeLabel, ticketStatusVariant } from '@/types';

const props = defineProps<{
    month: DashboardMonth;
    recentTickets: TicketListItem[];
    stats: DashboardStats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const statusLabel = (status: TicketStatus): string =>
    props.stats.by_status.find((item) => item.value === status)?.label ??
    status;

const equipmentLabel = (ticket: TicketListItem): string => {
    const type = deviceTypeLabel(ticket.device_type);
    const parts = [ticket.brand, ticket.model].filter(Boolean);

    if (parts.length === 0) {
        return type;
    }

    return `${type} · ${parts.join(' · ')}`;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading title="Resumen" :description="month.label" />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()">Ver todos</Link>
                </Button>
                <Button as-child>
                    <Link :href="create()">Nuevo ticket</Link>
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <section class="flex flex-col gap-3">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Últimos tickets
                </h2>

                <div
                    v-if="recentTickets.length === 0"
                    class="flex flex-col items-start gap-3 rounded-xl border border-dashed p-6"
                >
                    <p class="text-muted-foreground">Aún no hay tickets</p>
                    <Button as-child>
                        <Link :href="create()">Nuevo ticket</Link>
                    </Button>
                </div>

                <div v-else class="flex flex-col gap-3">
                    <Link
                        v-for="ticket in recentTickets"
                        :key="ticket.id"
                        :href="show(ticket.id)"
                        class="rounded-xl border bg-card p-4 hover:bg-white/50"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ ticket.customer.name }}
                                </p>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ equipmentLabel(ticket) }}
                                </p>
                            </div>
                            <Badge
                                :variant="ticketStatusVariant(ticket.status)"
                            >
                                {{ statusLabel(ticket.status) }}
                            </Badge>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Recibido {{ formatDate(ticket.received_at) }}
                        </p>
                    </Link>
                </div>
            </section>

            <section class="flex flex-col gap-3 lg:col-span-2">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Este mes
                </h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Card class="gap-3 py-5 sm:col-span-2">
                        <CardHeader class="px-5">
                            <CardTitle>Tickets y clientes del mes</CardTitle>
                            <CardDescription>
                                Registrados en {{ month.label }}, cualquier
                                estado
                            </CardDescription>
                        </CardHeader>
                        <CardContent
                            class="grid grid-cols-2 gap-4 px-5 tabular-nums"
                        >
                            <div>
                                <p class="text-3xl font-semibold">
                                    {{ stats.tickets_count }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Tickets
                                </p>
                            </div>
                            <div>
                                <p class="text-3xl font-semibold">
                                    {{ stats.customers_count }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Clientes
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="gap-3 py-5">
                        <CardHeader class="px-5">
                            <CardDescription>Terminados</CardDescription>
                            <CardTitle
                                class="text-3xl font-semibold tabular-nums"
                            >
                                {{ stats.completed_count }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5">
                            <p class="text-sm text-muted-foreground">
                                Entregados este mes
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="gap-3 py-5">
                        <CardHeader class="px-5">
                            <CardDescription>Por terminar</CardDescription>
                            <CardTitle
                                class="text-3xl font-semibold tabular-nums"
                            >
                                {{ stats.pending_count }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-5">
                            <p class="text-sm text-muted-foreground">
                                Distintos de Entregado
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Card
                        v-for="item in stats.by_status"
                        :key="item.value"
                        class="gap-2 py-4"
                    >
                        <CardHeader class="px-4">
                            <CardDescription>{{ item.label }}</CardDescription>
                            <CardTitle class="text-2xl tabular-nums">
                                {{ item.count }}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>
            </section>
        </div>
    </div>
</template>
