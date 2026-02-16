<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    PlusCircle, List, FileText, Calendar, CheckCircle, Clock, 
    Search, Filter, FileUp, Download, X, Loader2, AlertTriangle, 
    Info, BookOpen, Edit2 // Se agregó Edit2 para el botón
} from 'lucide-vue-next';
import { debounce } from 'lodash';
import axios from 'axios';

// Importamos las rutas (Wayfinder) - Se agregó editIncidencia
import { create as createIncidencia, edit as editIncidencia } from '@/routes/incidencias';

const props = defineProps({
    incidencias: Object, 
    categorias: Array,   
    flash: Object,
    filters: Object
});

// --- GESTIÓN DE VENTANA DE ÉXITO (MODAL) ---
const showSuccessModal = ref(false); 
const successMessage = ref(''); 
const page = usePage(); // Necesario para detectar el mensaje flash del controlador

/**
 * Escuchamos los mensajes flash del servidor.
 * Si llega un mensaje de éxito (como al editar), se activa la ventana modal.
 */
watch(() => page.props.flash.success, (val) => {
    if (val) {
        successMessage.value = val;
        showSuccessModal.value = true;
    }
}, { immediate: true });

// --- FILTROS ---
const search = ref(props.filters.search || '');
const dateApply = ref(props.filters.date_apply || '');
const dateIncidence = ref(props.filters.date_incidence || '');

const refreshData = debounce(() => {
    router.get('/incidencias', { 
        search: search.value,
        date_apply: dateApply.value,
        date_incidence: dateIncidence.value,
        page: 1 
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 500);

watch([search, dateApply, dateIncidence], () => {
    refreshData();
});

// --- PAGINACIÓN ---
const changePage = (url) => {
    if (url) {
        router.get(url, {
            search: search.value,
            date_apply: dateApply.value,
            date_incidence: dateIncidence.value
        }, {
            preserveState: true,
            preserveScroll: true
        });
    }
};

// --- IMPORTACIÓN EXCEL ---
const showImportModal = ref(false);
const isImporting = ref(false);
const showErrorModal = ref(false);
const errorMessage = ref('');

const handleImportSubmit = async (event) => {
    const formElement = event.target;
    const formData = new FormData(formElement);
    
    isImporting.value = true;

    try {
        const response = await axios.post('/incidencias/importar', formData, {
            responseType: 'blob', 
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `resultado_importacion_${new Date().toISOString().slice(0,10)}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        successMessage.value = "El proceso ha finalizado. Se ha descargado un archivo de Excel con los resultados. Por favor, verifique la columna 'ESTATUS' en el archivo descargado para confirmar si hubo filas con errores.";
        showSuccessModal.value = true;
        
        formElement.reset(); 
        showImportModal.value = false;
        router.reload(); 

    } catch (error) {
        if (error.response && error.response.data instanceof Blob) {
            const reader = new FileReader();
            reader.onload = () => {
                try {
                    const errorData = JSON.parse(reader.result);
                    errorMessage.value = errorData.message || "Error al procesar el archivo.";
                } catch (e) {
                    errorMessage.value = "Error crítico en el servidor. Esto ocurre usualmente si el archivo tiene un formato de fecha irreconocible o si no se eliminaron las filas de instrucciones de la plantilla.";
                }
                showErrorModal.value = true;
            };
            reader.readAsText(error.response.data);
        } else {
            errorMessage.value = error.response?.data?.message || "No se pudo conectar con el servidor. Verifique el formato del archivo.";
            showErrorModal.value = true;
        }
    } finally {
        isImporting.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return dateString.substring(0, 16).replace('T', ' ');
};
</script>

<template>
    <Head title="Listado de Incidencias" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 bg-gray-50 min-h-screen w-full flex flex-col">
                <div class="w-full max-w-full space-y-6 flex-grow flex flex-col">
                    
                    <!-- ENCABEZADO -->
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Bitácora de Incidencias</h1>
                            <p class="text-sm text-gray-500">Gestión y consulta del historial de permisos registrados en BioTime.</p>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="showImportModal = true"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors"
                            >
                                <FileUp class="h-4 w-4 mr-2" /> Importar Excel
                            </button>
                            <Link 
                                :href="createIncidencia ? createIncidencia().url : '#'" 
                                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors"
                            >
                                <PlusCircle class="h-4 w-4 mr-2" /> Nueva Individual
                            </Link>
                        </div>
                    </div>

                    <!-- BARRA DE FILTROS -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            <div class="md:col-span-6 lg:col-span-6">
                                <label class="block text-xs font-medium text-gray-700 mb-1 uppercase tracking-wider">Buscar Empleado</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search class="h-4 w-4 text-gray-400" />
                                    </div>
                                    <input v-model="search" type="text" class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10" placeholder="Nombre, Apellido o ID..." />
                                </div>
                            </div>
                            <div class="md:col-span-3 lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1 uppercase tracking-wider">Fecha Registro</label>
                                <input v-model="dateApply" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10" />
                            </div>
                            <div class="md:col-span-3 lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1 uppercase tracking-wider">Fecha Incidencia</label>
                                <input v-model="dateIncidence" type="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10" />
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS PRINCIPAL -->
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden flex flex-col">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider w-20">Folio</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Empleado</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Vigencia</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Motivo</th>
                                        <th class="px-6 py-3 text-right font-semibold text-gray-500 uppercase tracking-wider">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="inc in incidencias.data" :key="inc.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">#{{ inc.id }}</td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ inc.first_name }} {{ inc.last_name }}</div>
                                            <div class="text-xs text-gray-400">Nómina: {{ inc.emp_code }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                {{ inc.tipo }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 font-mono text-xs">{{ formatDate(inc.apply_time) }}</td>
                                        <td class="px-6 py-4 text-gray-600">
                                            <div class="flex flex-col text-xs space-y-1">
                                                <span class="flex items-center gap-1 font-medium text-green-700">
                                                    <Calendar class="h-3 w-3" /> {{ formatDate(inc.start_time) }}
                                                </span>
                                                <span class="flex items-center gap-1 font-medium text-red-600">
                                                    <Clock class="h-3 w-3" /> {{ formatDate(inc.end_time) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" :title="inc.apply_reason">
                                            {{ inc.apply_reason || '-' }}
                                        </td>
                                        <!-- BOTÓN EDITAR -->
                                        <td class="px-6 py-4 text-right">
                                            <Link 
                                                v-if="editIncidencia"
                                                :href="editIncidencia(inc.id).url" 
                                                :data="{ 
                                                    search: search, 
                                                    date_apply: dateApply, 
                                                    date_incidence: dateIncidence, 
                                                    page: incidencias.current_page 
                                                }"
                                                class="text-blue-600 hover:text-blue-900 inline-flex items-center gap-1 font-bold uppercase text-[10px]"
                                            >
                                                <Edit2 class="h-3 w-3" /> Editar
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="incidencias.data.length === 0">
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 bg-gray-50">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <Filter class="h-8 w-8 text-gray-300" />
                                                <p class="text-sm font-medium">No se encontraron incidencias con esos filtros.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINACIÓN -->
                        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6" v-if="incidencias.total > 0">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Mostrando <span class="font-medium">{{ incidencias.from }}</span> a <span class="font-medium">{{ incidencias.to }}</span> de <span class="font-medium">{{ incidencias.total }}</span> resultados
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <button @click="changePage(incidencias.prev_page_url)" :disabled="!incidencias.prev_page_url" class="relative inline-flex items-center px-4 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors">Anterior</button>
                                        <button @click="changePage(incidencias.next_page_url)" :disabled="!incidencias.next_page_url" class="relative inline-flex items-center px-4 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors">Siguiente</button>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LISTADO DE TIPOS DE JUSTIFICANTE (CATEGORÍAS) -->
                    <div class="space-y-4 pt-6">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5 text-gray-400" />
                            <h2 class="text-lg font-bold text-gray-800">Catálogo de Tipos de Permiso</h2>
                        </div>
                        <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider w-24">ID</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Nombre del Permiso</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Código (Símbolo)</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-500 uppercase tracking-wider">Unidad</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="cat in categorias" :key="cat.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ cat.id }}</td>
                                        <td class="px-6 py-4 font-medium text-gray-900">{{ cat.name }}</td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded font-bold text-xs border border-gray-200 uppercase">
                                                {{ cat.code }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            {{ cat.unit === 3 ? 'Días' : (cat.unit === 2 ? 'Horas' : 'Minutos') }}
                                        </td>
                                    </tr>
                                    <tr v-if="categorias.length === 0">
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-400">No hay categorías registradas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>

        <!-- MODALES (Error, Éxito, Importación) -->
        <ErrorModal :show="showErrorModal" :message="errorMessage" title="Atención en Importación" @close="showErrorModal = false" />
        
        <div v-if="showSuccessModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 text-center animate-in fade-in zoom-in">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <CheckCircle class="h-6 w-6 text-green-600" />
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Proceso Terminado</h3>
                <p class="text-sm text-gray-600 mb-6">{{ successMessage }}</p>
                <button @click="showSuccessModal = false" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Entendido
                </button>
            </div>
        </div>

        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 relative">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Importación Masiva</h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600"><X class="h-5 w-5" /></button>
                </div>

                <div class="mb-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg flex items-start gap-3">
                    <AlertTriangle class="h-6 w-6 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-xs text-amber-900 font-bold mb-1 uppercase">Instrucción Crucial</p>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Asegúrese de que el archivo <strong>SOLO tenga los datos</strong> a partir de la fila 2. Elimine cualquier fila de ejemplo o instrucciones de la plantilla original.
                        </p>
                    </div>
                </div>

                <div class="mb-4 flex items-center gap-2 p-2 bg-blue-50 text-blue-700 rounded-md border border-blue-100">
                    <Info class="h-4 w-4 shrink-0" />
                    <p class="text-[10px] font-medium leading-tight italic">
                        Nota técnica: El sistema saltará automáticamente las primeras 2 filas (Encabezados y Ejemplo de la plantilla).
                    </p>
                </div>

                <form @submit.prevent="handleImportSubmit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Archivo Excel (.xlsx)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    </div>
                    
                    <div class="flex justify-center mb-4">
                        <a href="/incidencias/plantilla" target="_blank" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                            <Download class="h-3 w-3" /> Descargar plantilla oficial
                        </a>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="isImporting" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-md shadow-sm disabled:opacity-50 flex justify-center items-center gap-2 transition-all">
                            <Loader2 v-if="isImporting" class="h-4 w-4 animate-spin" />
                            {{ isImporting ? 'Procesando archivo...' : 'Subir y Procesar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SidebarProvider>
</template>