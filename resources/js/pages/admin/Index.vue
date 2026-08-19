<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import OwnerController from '@/actions/App/Http/Controllers/Admin/OwnerController';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as admin } from '@/routes/admin';

type ClientUser = {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    is_admin?: boolean;
};

defineProps<{
    users: ClientUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Administración',
                href: admin(),
            },
        ],
    },
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Administración" />

    <div class="flex flex-col p-4">
        <Heading
            title="Administración"
            description="Mantenimiento del servidor y listado de clientes."
        />

        <div class="mt-12 flex flex-col space-y-8">
            <Card>
                <CardHeader>
                    <CardTitle>Mantenimiento</CardTitle>
                    <CardDescription>
                        Corre las migraciones pendientes o borra la caché de la
                        aplicación en este servidor.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-3">
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button data-test="run-migrations-button">
                                Correr migraciones
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="OwnerController.migrate.form()"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle
                                        >Correr migraciones</DialogTitle
                                    >
                                    <DialogDescription>
                                        Se van a aplicar las migraciones
                                        pendientes en este servidor. Confirma
                                        que quieres continuar.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">
                                            Cancelar
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        data-test="confirm-migrations-button"
                                    >
                                        Correr migraciones
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="outline"
                                data-test="clear-cache-button"
                            >
                                Borrar caché
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="OwnerController.clearCache.form()"
                                class="space-y-6"
                                v-slot="{ processing }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>Borrar caché</DialogTitle>
                                    <DialogDescription>
                                        Se va a borrar la caché de
                                        configuración, rutas, vistas y datos de
                                        la aplicación.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">
                                            Cancelar
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        data-test="confirm-clear-cache-button"
                                    >
                                        Borrar caché
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Clientes</CardTitle>
                    <CardDescription>
                        Usuarios registrados en RepairTrack.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="users.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No hay clientes registrados.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-muted-foreground">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">
                                        Nombre
                                    </th>
                                    <th class="py-2 pr-4 font-medium">
                                        Correo
                                    </th>
                                    <th class="py-2 pr-4 font-medium">Alta</th>
                                    <th class="py-2 font-medium">Rol</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4 font-medium">
                                        {{ user.name }}
                                    </td>
                                    <td class="py-3 pr-4">{{ user.email }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="py-3">
                                        <Badge v-if="user.is_admin">
                                            Admin
                                        </Badge>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            Técnico
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
