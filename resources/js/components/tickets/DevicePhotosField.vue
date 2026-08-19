<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const MAX_PHOTOS = 5;

defineProps<{
    error?: string;
}>();

const photos = ref<File[]>([]);
const previews = ref<string[]>([]);
const cameraInput = ref<HTMLInputElement | null>(null);
const galleryInput = ref<HTMLInputElement | null>(null);
const photosField = ref<HTMLInputElement | null>(null);

const remaining = computed(() => MAX_PHOTOS - photos.value.length);

const syncNamedInput = (): void => {
    const input = photosField.value;

    if (!input) {
        return;
    }

    const transfer = new DataTransfer();

    photos.value.forEach((file) => {
        transfer.items.add(file);
    });

    input.files = transfer.files;
};

watch(photos, syncNamedInput, { flush: 'post' });

const addFiles = (fileList: FileList | null): void => {
    if (!fileList || fileList.length === 0) {
        return;
    }

    const incoming = Array.from(fileList).filter((file) =>
        file.type.startsWith('image/'),
    );

    if (incoming.length === 0) {
        toast.error('Solo se aceptan imágenes.');

        return;
    }

    const room = remaining.value;

    if (room <= 0) {
        toast.error('Puedes subir hasta 5 fotos.');

        return;
    }

    const accepted = incoming.slice(0, room);

    if (incoming.length > room) {
        toast.error('Puedes subir hasta 5 fotos.');
    }

    photos.value = [...photos.value, ...accepted];
    previews.value = [
        ...previews.value,
        ...accepted.map((file) => URL.createObjectURL(file)),
    ];
};

const removeAt = (index: number): void => {
    const preview = previews.value[index];

    if (preview) {
        URL.revokeObjectURL(preview);
    }

    photos.value = photos.value.filter((_, current) => current !== index);
    previews.value = previews.value.filter((_, current) => current !== index);
};

const onPick = (event: Event): void => {
    const input = event.target as HTMLInputElement;

    addFiles(input.files);
    input.value = '';
};

onUnmounted(() => {
    previews.value.forEach((url) => {
        URL.revokeObjectURL(url);
    });
});
</script>

<template>
    <div class="grid gap-2 sm:col-span-2">
        <Label>Fotos del equipo</Label>
        <p class="text-sm text-muted-foreground">
            Opcional, hasta {{ MAX_PHOTOS }}. En el celular puedes abrir la
            cámara.
        </p>
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="remaining <= 0"
                @click="cameraInput?.click()"
            >
                <Camera />
                Tomar foto
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="remaining <= 0"
                @click="galleryInput?.click()"
            >
                <ImagePlus />
                Elegir de galería
            </Button>
        </div>
        <input
            ref="cameraInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="sr-only"
            tabindex="-1"
            @change="onPick"
        />
        <input
            ref="galleryInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="sr-only"
            tabindex="-1"
            @change="onPick"
        />
        <input
            v-if="photos.length > 0"
            ref="photosField"
            type="file"
            name="photos[]"
            multiple
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
        />
        <ul
            v-if="previews.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-5"
        >
            <li
                v-for="(preview, index) in previews"
                :key="preview"
                class="relative"
            >
                <img
                    :src="preview"
                    alt=""
                    class="aspect-square w-full rounded-md border object-cover"
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="icon-sm"
                    class="absolute top-1 right-1 size-6"
                    :aria-label="`Quitar foto ${index + 1}`"
                    @click="removeAt(index)"
                >
                    <X class="size-3" />
                </Button>
            </li>
        </ul>
        <InputError :message="error" />
    </div>
</template>
