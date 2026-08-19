<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import { Button } from '@/components/ui/button';
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

defineProps<{
    ticketId: number;
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <slot>
                <Button variant="destructive" size="sm">Eliminar</Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="TicketController.destroy.form(ticketId)"
                class="space-y-6"
                v-slot="{ processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>¿Eliminar este ticket?</DialogTitle>
                    <DialogDescription>
                        Se perderá el historial y las fotos. El cliente no se
                        borra. Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        Eliminar ticket
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
