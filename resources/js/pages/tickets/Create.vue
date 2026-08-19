<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mic, Square } from '@lucide/vue';
import { computed, ref } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CustomerFields from '@/components/tickets/CustomerFields.vue';
import DevicePhotosField from '@/components/tickets/DevicePhotosField.vue';
import SuggestInput from '@/components/tickets/SuggestInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useSpeechToText } from '@/composables/useSpeechToText';
import { dashboard } from '@/routes';
import { create } from '@/routes/tickets';
import type { Customer, DeviceCatalog, DeviceHistoryItem } from '@/types';
import { deviceTypeOptions } from '@/types';

const props = defineProps<{
    customers: Customer[];
    deviceCatalog: DeviceCatalog;
    deviceHistory: DeviceHistoryItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Nuevo ticket',
                href: create(),
            },
        ],
    },
});

const today = new Date().toISOString().slice(0, 10);
const deviceType = ref<(typeof deviceTypeOptions)[number]['value']>('celular');
const brand = ref('');
const model = ref('');
const reportedIssue = ref('');

const { listening, toggle: toggleDictation } = useSpeechToText(
    () => reportedIssue.value,
    (value) => {
        reportedIssue.value = value;
    },
);

const uniquePreserve = (items: string[]): string[] => {
    const seen = new Set<string>();
    const result: string[] = [];

    for (const item of items) {
        const key = item.trim();

        if (key === '') {
            continue;
        }

        const lower = key.toLowerCase();

        if (seen.has(lower)) {
            continue;
        }

        seen.add(lower);
        result.push(item);
    }

    return result;
};

const catalogBrandKey = (
    type: string,
    brandValue: string,
): string | undefined => {
    const brands = props.deviceCatalog[type] ?? {};
    const lower = brandValue.trim().toLowerCase();

    return Object.keys(brands).find((name) => name.toLowerCase() === lower);
};

const brandSuggestions = computed(() => {
    const type = deviceType.value;
    const catalogBrands = Object.keys(props.deviceCatalog[type] ?? {});
    const historyBrands = props.deviceHistory
        .filter((item) => item.device_type === type && item.brand)
        .map((item) => item.brand as string);

    return uniquePreserve([...historyBrands, ...catalogBrands]);
});

const modelSuggestions = computed(() => {
    const type = deviceType.value;
    const brandValue = brand.value.trim();
    const catalogKey = catalogBrandKey(type, brandValue);
    const catalogModels = catalogKey
        ? (props.deviceCatalog[type]?.[catalogKey] ?? [])
        : [];
    const historyModels = props.deviceHistory
        .filter(
            (item) =>
                item.device_type === type &&
                Boolean(item.model) &&
                (item.brand ?? '').toLowerCase() === brandValue.toLowerCase(),
        )
        .map((item) => item.model as string);

    return uniquePreserve([...historyModels, ...catalogModels]);
});

const selectClass =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px]';
</script>

<template>
    <Head title="Nuevo ticket" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Nuevo ticket"
            description="Registra el cliente y el equipo que recibiste a reparación."
        />

        <Form
            v-bind="TicketController.store.form()"
            class="mx-auto w-full max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Cliente</CardTitle>
                    <CardDescription>
                        Empieza por el correo. Si eliges un cliente existente,
                        no hace falta nombre ni teléfono.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <CustomerFields :customers="customers" :errors="errors" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Equipo</CardTitle>
                    <CardDescription>
                        Datos del aparato y de la recepción.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="device_type">Tipo</Label>
                        <select
                            id="device_type"
                            v-model="deviceType"
                            name="device_type"
                            required
                            :class="selectClass"
                        >
                            <option
                                v-for="option in deviceTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.device_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="brand">Marca</Label>
                        <SuggestInput
                            id="brand"
                            v-model="brand"
                            name="brand"
                            :suggestions="brandSuggestions"
                            placeholder="Opcional"
                        />
                        <InputError :message="errors.brand" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="model">Modelo</Label>
                        <SuggestInput
                            id="model"
                            v-model="model"
                            name="model"
                            :suggestions="modelSuggestions"
                            placeholder="Opcional"
                        />
                        <InputError :message="errors.model" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="serial_number">Número de serie</Label>
                        <Input
                            id="serial_number"
                            name="serial_number"
                            placeholder="Opcional"
                            autocomplete="off"
                        />
                        <InputError :message="errors.serial_number" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="reported_issue"
                                >Problema reportado</Label
                            >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :aria-pressed="listening"
                                @click="toggleDictation"
                            >
                                <Square v-if="listening" />
                                <Mic v-else />
                                {{ listening ? 'Detener' : 'Dictar' }}
                            </Button>
                        </div>
                        <Textarea
                            id="reported_issue"
                            v-model="reportedIssue"
                            name="reported_issue"
                            required
                            placeholder="¿Qué le pasa al equipo?"
                        />
                        <p
                            v-if="listening"
                            class="text-sm text-muted-foreground"
                        >
                            Escuchando… habla y el texto se añade al campo.
                        </p>
                        <InputError :message="errors.reported_issue" />
                    </div>
                    <DevicePhotosField
                        :error="errors.photos ?? errors['photos.0']"
                    />
                    <div class="grid gap-2">
                        <Label for="estimated_cost">Costo estimado</Label>
                        <Input
                            id="estimated_cost"
                            type="number"
                            name="estimated_cost"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <InputError :message="errors.estimated_cost" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="received_at">Fecha de recepción</Label>
                        <Input
                            id="received_at"
                            type="date"
                            name="received_at"
                            required
                            :default-value="today"
                        />
                        <InputError :message="errors.received_at" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="estimated_delivery_at">
                            Fecha estimada de entrega
                        </Label>
                        <Input
                            id="estimated_delivery_at"
                            type="date"
                            name="estimated_delivery_at"
                        />
                        <InputError :message="errors.estimated_delivery_at" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="processing">
                    Crear ticket
                </Button>
            </div>
        </Form>
    </div>
</template>
