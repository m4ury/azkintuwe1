<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    auth: Object,
    comunas: Array,
    establecimientos: Array,
    user: Object,
    servers: Object,
});

const selectedServer = ref('');
const selectedComunaValue = ref('');
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const csvPath = ref(null);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const isDssm = computed(() => props.user?.establecimiento?.codigo === '01000');
const userComuna = computed(() => props.user?.establecimiento?.comuna ?? null);

const serverOptions = computed(() =>
    Object.values(props.servers ?? {}).map((server) => ({
        value: String(server.url),
        label: server.label,
    })),
);

const comunaOptions = computed(() =>
    (props.comunas ?? []).map((comuna) => ({
        value: String(comuna.codigo ?? comuna.id),
        label: comuna.nombre,
    })),
);

const selectedComuna = computed(() => {
    if (!isDssm.value) {
        return userComuna.value;
    }

    return (props.comunas ?? []).find((comuna) => String(comuna.codigo) === selectedComunaValue.value) ?? null;
});

const validateForm = () => {
    if (!selectedServer.value) {
        error.value = 'Debe seleccionar un servidor';

        return false;
    }

    if (!selectedComuna.value) {
        error.value = isDssm.value
            ? 'Debe seleccionar una comuna'
            : 'No se encontró una comuna asociada al usuario';

        return false;
    }

    return true;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    loading.value = true;
    error.value = null;
    success.value = null;
    csvPath.value = null;

    try {
        const comuna = selectedComuna.value;
        const params = new URLSearchParams({
            server_url: selectedServer.value,
            comuna: comuna.codigo,
        });

        console.log('Enviando petición a:', route('sismaule.paciente-grupo-prioritario'));
        console.log('Query params:', params.toString());

        const response = await fetch(
            `${route('sismaule.paciente-grupo-prioritario')}?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'usuario': 'salud',
                    'Modulo': 'SALUD',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
            },
        );

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(data?.message ?? `Error ${response.status}: ${response.statusText}`);
        }

        console.log('Respuesta del servicio:', data);
        success.value = 'Datos obtenidos correctamente';

        if (data.csv_path) {
            csvPath.value = data.csv_path;
        }
    } catch (err) {
        error.value = `Error al consumir el servicio: ${err.message}`;
        console.error(err);
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Grupos prioritarios Emergencia y Desastre
            </h2>
            <p class="mt-3"> {{ props.user.establecimiento.nombre }}</p>
        </template>

        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Servidor:</label>
                            <SelectInput
                                v-model="selectedServer"
                                :options="serverOptions"
                                placeholder="Seleccione un servidor"
                                class="w-full"
                            />
                        </div>

                        <div v-if="isDssm">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Comuna:</label>
                            <SelectInput
                                v-model="selectedComunaValue"
                                :options="comunaOptions"
                                placeholder="Seleccione una comuna"
                                class="w-full"
                            />
                        </div>

                        <div v-else>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Comuna:</h3>
                            <p class="min-h-[42px] rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-gray-700">
                                {{ props.user.establecimiento.comuna.nombre }}
                            </p>
                        </div>
                    </div>

                    <div v-if="error" class="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-red-700">{{ error }}</p>
                    </div>

                    <div v-if="success" class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-green-700">{{ success }}</p>
                    </div>

                    <div v-if="csvPath" class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-blue-700 text-sm font-medium mb-2">
                            Archivo CSV guardado:
                        </p>
                        <code class="block text-xs text-blue-600 bg-blue-100 rounded px-2 py-1 break-all">
                            {{ csvPath }}
                        </code>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <PrimaryButton
                            type="button"
                            @click="handleSubmit"
                            :disabled="loading"
                            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded"
                        >
                            {{ loading ? 'Cargando...' : 'Enviar' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
