<script setup>
import { computed, ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    auth: Object,
    comunas: Array,
    establecimientos: Array,
    user: Object,
    servers: Object,
    grupos: Object,
});

const selectedServer = ref('');
const selectedGrupo = ref('');
const selectedComunaValues = ref([]);
const loading = ref(false);
const error = ref(null);
const success = ref(null);
const csvPaths = ref([]);
const archivos = ref([]);
const cargandoArchivos = ref(false);
const procesandoConsolidado = ref(false);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const isDssm = computed(() => props.user?.establecimiento?.codigo === '01000');
const userComuna = computed(() => props.user?.establecimiento?.comuna ?? null);

const serverOptions = computed(() =>
    Object.values(props.servers ?? {}).map((server) => ({
        value: String(server.url),
        label: server.label,
    })),
);

const grupoOptions = computed(() =>
    Object.values(props.grupos ?? []).map((grupo) => ({
        value: String(grupo.idgrupo),
        label: grupo.nombregrupo,
    })),
);

const selectedComunaCodes = computed(() => {
    if (!isDssm.value) {
        return userComuna.value?.codigo ? [String(userComuna.value.codigo)] : [];
    }
    return selectedComunaValues.value;
});

const validateForm = () => {
    if (!selectedServer.value) {
        error.value = 'Debe seleccionar un servidor';
        return false;
    }

    if (selectedComunaCodes.value.length === 0) {
        error.value = isDssm.value
            ? 'Debe seleccionar al menos una comuna'
            : 'No se encontró una comuna asociada al usuario';
        return false;
    }
    return true;
};

const selectAllComunas = () => {
    selectedComunaValues.value = (props.comunas ?? []).map((comuna) => String(comuna.codigo));
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    loading.value = true;
    procesandoConsolidado.value = true;
    error.value = null;
    success.value = null;
    csvPaths.value = [];

    try {
        const params = new URLSearchParams({
            server_url: selectedServer.value,
            grupos: selectedGrupo.value,
        });

        for (const comunaCode of selectedComunaCodes.value) {
            params.append('comunas[]', comunaCode);
        }

        const response = await fetch(
            `${route('sismaule.paciente-grupo-prioritario')}?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'usuario': props.user?.name ?? 'salud',
                    'Modulo': 'SALUD',
                    'HTTP_ESREPORTE': 'S',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
            },
        );

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(data?.message ?? `Error ${response.status}: ${response.statusText}`);
        }

        if (data?.csv_path) {
            csvPaths.value.push(data.csv_path);
            success.value = `Se generó 1 archivo CSV consolidado`;
            await cargarArchivos();
        } else {
            error.value = 'El servicio no generó un archivo CSV consolidado para las comunas seleccionadas';
        }
    } catch (err) {
        error.value = `Error al consumir el servicio: ${err.message}`;
        console.error(err);
    } finally {
        loading.value = false;
        procesandoConsolidado.value = false;
    }
};

async function cargarArchivos() {
    cargandoArchivos.value = true;
    try {
        const response = await fetch(route('sismaule.archivos-csv'), {
            headers: { 'Accept': 'application/json' },
        });
        archivos.value = await response.json();
    } catch (err) {
        console.error('Error al listar archivos', err);
    } finally {
        cargandoArchivos.value = false;
    }
}
onMounted(cargarArchivos);

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

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Grupo Prioritario:</label>
                            <SelectInput
                                v-model="selectedGrupo"
                                :options="grupoOptions"
                                placeholder="Seleccione grupo prioritaio"
                                class="w-full"
                            />
                        </div>

                        <div v-if="isDssm">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="comunas" class="block text-sm font-medium text-gray-700">Comunas:</label>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-blue-700 hover:underline"
                                    @click="selectAllComunas"
                                >
                                    Seleccionar todas
                                </button>
                            </div>
                            <select
                                id="comunas"
                                v-model="selectedComunaValues"
                                multiple
                                size="7"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="comuna in comunas"
                                    :key="comuna.codigo"
                                    :value="String(comuna.codigo)"
                                >
                                    {{ comuna.nombre }}
                                </option>
                            </select>
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

                    <div v-if="procesandoConsolidado" class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <p class="text-amber-800 font-medium">
                            Procesando consolidado de comunas seleccionadas… esto puede tardar unos segundos.
                        </p>
                    </div>

                    <div v-if="success" class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-green-700">{{ success }}</p>
                    </div>

                    <div v-if="csvPaths.length" class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-blue-700 text-sm font-medium mb-2">
                            Archivos CSV generados:
                        </p>
                        <ul class="space-y-1">
                            <li v-for="path in csvPaths" :key="path">
                                <a
                                    :href="route('sismaule.descargar-csv', { path })"
                                    class="text-blue-600 hover:underline text-sm"
                                >
                                    Descargar {{ path.split('/').pop() }}
                                </a>
                            </li>
                        </ul>
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
