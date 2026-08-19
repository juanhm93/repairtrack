<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/Heading.vue';
import DeleteTicketDialog from '@/components/tickets/DeleteTicketDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/plugins/date';
import { dashboard } from '@/routes';
import { create, edit, index, show } from '@/routes/tickets';
import type {
    Paginated,
    TicketIndexFilters,
    TicketListItem,
    TicketStatus,
    TicketStatusOption,
} from '@/types';
import { deviceTypeLabel, ticketStatusVariant } from '@/types';

const props = defineProps<{
    tickets: Paginated<TicketListItem>;
    filters: TicketIndexFilters;
    statusOptions: TicketStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Tickets',
                href: index(),
            },
        ],
    },
});

const hasFilters = computed(
    () => Boolean(props.filters.q) || Boolean(props.filters.status),
);

const q = ref(props.filters.q ?? '');
const statusFilter = ref(props.filters.status ?? '');

watch(
    () => props.filters,
    (filters) => {
        q.value = filters.q ?? '';
        statusFilter.value = filters.status ?? '';
    },
);

const statusLabel = (status: TicketStatus): string =>
    props.statusOptions.find((option) => option.value === status)?.label ??
    status;

const equipmentLabel = (ticket: TicketListItem): string => {
    const type = deviceTypeLabel(ticket.device_type);
    const parts = [ticket.brand, ticket.model].filter(Boolean);

    if (parts.length === 0) {
        return type;
    }

    return `${type} · ${parts.join(' · ')}`;
};

const visitTicket = (ticketId: number): void => {
    router.visit(show(ticketId));
};

const selectClass =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-card px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';
</script>

<template>
    <Head title="Tickets" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Tickets"
                description="Todos tus tickets, en cualquier estado."
            />
            <Button as-child>
                <Link :href="create()">Nuevo ticket</Link>
            </Button>
        </div>

        <Form
            v-bind="TicketController.index.form()"
            :options="{ preserveState: true, replace: true }"
            class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-end"
        >
            <div class="grid w-full gap-2 sm:min-w-56 sm:flex-1">
                <Label for="q">Cliente</Label>
                <Input
                    id="q"
                    v-model="q"
                    name="q"
                    class="!bg-card"
                    placeholder="Nombre, correo o teléfono"
                    autocomplete="off"
                />
            </div>
            <div class="grid w-full gap-2 sm:min-w-48 sm:w-auto">
                <Label for="status">Estado</Label>
                <select
                    id="status"
                    v-model="statusFilter"
                    name="status"
                    :class="selectClass"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div class="flex w-full gap-2 sm:w-auto">
                <Button type="submit" variant="secondary">Filtrar</Button>
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="ghost"
                    as-child
                >
                    <Link :href="index()">Limpiar filtros</Link>
                </Button>
            </div>
        </Form>

        <div
            v-if="tickets.data.length === 0"
            class="flex flex-col items-start gap-3 rounded-xl border border-dashed p-8"
        >
            <p class="text-muted-foreground">
                {{
                    hasFilters
                        ? 'Nada coincide con los filtros'
                        : 'No hay tickets'
                }}
            </p>
            <Button v-if="!hasFilters" as-child>
                <Link :href="create()">Nuevo ticket</Link>
            </Button>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="border-b bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Equipo</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Recibido</th>
                        <th class="px-4 py-3 font-medium">Entrega estimada</th>
                        <th class="px-4 py-3 font-medium">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        class="cursor-pointer border-b bg-card last:border-0 hover:bg-white/50"
                        @click="visitTicket(ticket.id)"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">
                                {{ ticket.customer.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ ticket.customer.email }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            {{ equipmentLabel(ticket) }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="ticketStatusVariant(ticket.status)"
                            >
                                {{ statusLabel(ticket.status) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(ticket.received_at) }}
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(ticket.estimated_delivery_at) }}
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div
                                class="flex flex-wrap items-center justify-end gap-1"
                            >
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="show(ticket.id)">Ver</Link>
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="edit(ticket.id)">Editar</Link>
                                </Button>
                                <DeleteTicketDialog :ticket-id="ticket.id">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                    >
                                        Eliminar
                                    </Button>
                                </DeleteTicketDialog>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="tickets.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                {{ tickets.from }}–{{ tickets.to }} de {{ tickets.total }}
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!tickets.prev_page_url"
                    as-child
                >
                    <Link
                        v-if="tickets.prev_page_url"
                        :href="tickets.prev_page_url"
                        preserve-state
                    >
                        Anterior
                    </Link>
                    <span v-else>Anterior</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!tickets.next_page_url"
                    as-child
                >
                    <Link
                        v-if="tickets.next_page_url"
                        :href="tickets.next_page_url"
                        preserve-state
                    >
                        Siguiente
                    </Link>
                    <span v-else>Siguiente</span>
                </Button>
            </div>
        </div>
    </div>
</template>
