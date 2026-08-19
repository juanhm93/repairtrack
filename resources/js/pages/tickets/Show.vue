<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import DeleteTicketDialog from '@/components/tickets/DeleteTicketDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/plugins/date';
import { edit, index, show } from '@/routes/tickets';
import type { RepairTicket, TicketStatus, TicketStatusOption } from '@/types';
import {
    deviceTypeLabel,
    ticketNotificationStatusLabel,
    ticketStatusVariant,
} from '@/types';

const props = defineProps<{
    ticket: RepairTicket;
    statuses: TicketStatusOption[];
}>();

defineOptions({
    layout: (pageProps: { ticket: RepairTicket }) => ({
        breadcrumbs: [
            {
                title: 'Tickets',
                href: index(),
            },
            {
                title: `Ticket #${pageProps.ticket.id}`,
                href: show(pageProps.ticket.id),
            },
        ],
    }),
});

const statusLabel = (status: TicketStatus): string =>
    props.statuses.find((option) => option.value === status)?.label ?? status;

const nextStatuses = computed(() =>
    props.statuses.filter((option) => option.value !== props.ticket.status),
);

const equipmentTitle = computed(() => {
    const type = deviceTypeLabel(props.ticket.device_type);
    const parts = [props.ticket.brand, props.ticket.model].filter(Boolean);

    if (parts.length === 0) {
        return type;
    }

    return `${type} · ${parts.join(' ')}`;
});

const selectClass =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

const formatCost = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
    }).format(Number(value));
};
</script>

<template>
    <Head :title="`Ticket #${ticket.id}`" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="`Ticket #${ticket.id}`"
                :description="equipmentTitle"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="ticketStatusVariant(ticket.status)">
                    {{ statusLabel(ticket.status) }}
                </Badge>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="edit(ticket.id)">Editar</Link>
                </Button>
                <DeleteTicketDialog :ticket-id="ticket.id" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cliente y equipo</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Cliente</p>
                            <p class="font-medium">
                                {{ ticket.customer.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ ticket.customer.email }}
                            </p>
                            <p
                                v-if="ticket.customer.phone"
                                class="text-sm text-muted-foreground"
                            >
                                {{ ticket.customer.phone }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Equipo</p>
                            <p class="font-medium">{{ equipmentTitle }}</p>
                            <p
                                v-if="ticket.serial_number"
                                class="text-sm text-muted-foreground"
                            >
                                Serie: {{ ticket.serial_number }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-sm text-muted-foreground">
                                Problema reportado
                            </p>
                            <p class="whitespace-pre-wrap">
                                {{ ticket.reported_issue }}
                            </p>
                        </div>
                        <div v-if="ticket.photos.length" class="sm:col-span-2">
                            <p class="text-sm text-muted-foreground">Fotos</p>
                            <ul
                                class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4"
                            >
                                <li
                                    v-for="photo in ticket.photos"
                                    :key="photo.id"
                                >
                                    <a
                                        :href="photo.url"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            :src="photo.url"
                                            alt=""
                                            class="aspect-square w-full rounded-md border object-cover"
                                        />
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Costo estimado
                            </p>
                            <p>{{ formatCost(ticket.estimated_cost) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Recepción
                            </p>
                            <p>{{ formatDate(ticket.received_at) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Entrega estimada
                            </p>
                            <p>
                                {{ formatDate(ticket.estimated_delivery_at) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Historial</CardTitle>
                        <CardDescription>
                            Cambios de estado de este ticket, del más antiguo al
                            más reciente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol
                            v-if="ticket.history.length"
                            class="relative space-y-6 border-l border-border pl-6"
                        >
                            <li
                                v-for="item in ticket.history"
                                :key="item.id"
                                class="relative"
                            >
                                <span
                                    class="absolute top-1.5 -left-[1.45rem] size-2.5 rounded-full bg-primary"
                                />
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium">
                                        {{ statusLabel(item.to_status) }}
                                    </p>
                                    <span
                                        v-if="item.from_status"
                                        class="text-sm text-muted-foreground"
                                    >
                                        desde
                                        {{ statusLabel(item.from_status) }}
                                    </span>
                                </div>
                                <p
                                    v-if="item.note"
                                    class="mt-1 text-sm whitespace-pre-wrap"
                                >
                                    {{ item.note }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatDateTime(item.created_at) }}
                                    <span v-if="item.changed_by">
                                        · {{ item.changed_by.name }}
                                    </span>
                                </p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-muted-foreground">
                            Aún no hay historial.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cambiar estado</CardTitle>
                        <CardDescription>
                            Puedes pasar a cualquier estado distinto del actual.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="TicketController.updateStatus.form(ticket)"
                            :options="{ preserveScroll: true }"
                            class="space-y-4"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label for="status">Nuevo estado</Label>
                                <select
                                    id="status"
                                    name="status"
                                    required
                                    :class="selectClass"
                                >
                                    <option value="" disabled selected>
                                        Selecciona un estado
                                    </option>
                                    <option
                                        v-for="option in nextStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="note">Nota</Label>
                                <Textarea
                                    id="note"
                                    name="note"
                                    placeholder="Opcional, ej. esperando pantalla"
                                />
                                <InputError :message="errors.note" />
                            </div>
                            <Button type="submit" :disabled="processing">
                                Actualizar estado
                            </Button>
                        </Form>
                    </CardContent>
                </Card>

                <p
                    v-if="ticket.latest_notification"
                    class="text-sm text-muted-foreground"
                >
                    Último correo:
                    {{ ticket.latest_notification.to_email }}
                    ·
                    {{ formatDateTime(ticket.latest_notification.created_at) }}
                    ·
                    {{
                        ticketNotificationStatusLabel(
                            ticket.latest_notification.status,
                        )
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Link público (próximamente)
                </p>
            </div>
        </div>
    </div>
</template>
