<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    PlusCircle, List, FileText, Calendar, CheckCircle, Clock, 
    Search, Filter, FileUp, Download, X, Loader2, AlertTriangle, 
    Info, BookOpen, Edit2, RotateCw, ChartSpline
} from 'lucide-vue-next';
import { debounce } from 'lodash';
import axios from 'axios';
import { statistics } from '@/routes/incidencias';
// Importamos las rutas (Wayfinder)
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
const page = usePage(); 

watch(() => page.props.flash.success, (val) => {
    if (val) {
        successMessage.value = val;
        showSuccessModal.value = true;
    }
}, { immediate: true });

// --- FILTROS ACTUALIZADOS ---
const search = ref(props.filters.search || '');
const dateApply = ref(props.filters.date_apply || '');
const dateIncidence = ref(props.filters.date_incidence || '');
// NUEVOS FILTROS DE RANGO
const dateStart = ref(props.filters.date_start || '');
const dateEnd = ref(props.filters.date_end || '');

const refreshData = debounce(() => {
    router.get('/incidencias', { 
        search: search.value,
        date_apply: dateApply.value,
        date_incidence: dateIncidence.value,
        date_start: dateStart.value, 
        date_end: dateEnd.value,     
        page: 1 
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 500);

// Observamos todos los filtros al mismo tiempo
watch([search, dateApply, dateIncidence, dateStart, dateEnd], () => {
    refreshData();
});

// Funciones para limpiar mutuamente sin bloquear visualmente las celdas
const onIncidenceChange = () => {
    if (dateIncidence.value) {
        dateStart.value = '';
        dateEnd.value = '';
    }
};

const onRangeChange = () => {
    if (dateStart.value || dateEnd.value) {
        dateIncidence.value = '';
    }
};

// Función para reiniciar rápidamente la tabla
const limpiarFiltros = () => {
    search.value = '';
    dateApply.value = '';
    dateIncidence.value = '';
    dateStart.value = '';
    dateEnd.value = '';
};

// --- PAGINACIÓN ---
const changePage = (url) => {
    if (url) {
        router.get(url, {
            search: search.value,
            date_apply: dateApply.value,
            date_incidence: dateIncidence.value,
            date_start: dateStart.value,
            date_end: dateEnd.value
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
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none transition-all active:scale-95"
                            >
                                <FileUp class="h-4 w-4 mr-2" /> Importar Excel
                            </button>
                            <!-- CORRECCIÓN: Se agregan los filtros al botón Nueva Individual -->
                            <Link 
                                :href="createIncidencia ? createIncidencia().url : '#'" 
                                :data="{ 
                                    search: search, 
                                    date_apply: dateApply, 
                                    date_incidence: dateIncidence,
                                    date_start: dateStart,
                                    date_end: dateEnd, 
                                    page: incidencias?.current_page || 1 
                                }"
                                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none transition-colors"
                            >
                                <PlusCircle class="h-4 w-4 mr-2" /> Nueva Individual
                            </Link>
                            

                            <!-- MODIFICACIÓN: Botón de estadísticas ahora es naranja con el icono ChartSpline -->
                            <Link 
                                v-if="statistics"
                                :href="statistics().url" 
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-orange-500 hover:bg-orange-600 focus:outline-none transition-all active:scale-95"
                            >
                                <ChartSpline class="h-4 w-4 mr-2" /> 
                                <span>Estadísticas</span>
                            </Link>
                        </div>
                    </div>

                    <!-- NUEVA BARRA DE FILTROS -->
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                <Filter class="h-4 w-4 text-blue-500" /> Búsqueda Avanzada
                            </h3>
                            <button @click="limpiarFiltros" class="text-xs text-gray-500 hover:text-red-600 flex items-center gap-1 font-bold transition-colors">
                                <RotateCw class="h-3 w-3" /> Limpiar
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            
                            <!-- Búsqueda General -->
                            <div class="md:col-span-3">
                                <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Empleado</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search class="h-4 w-4 text-gray-400" />
                                    </div>
                                    <input v-model="search" type="text" class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-gray-50" placeholder="Nombre, Apellido o ID..." />
                                </div>
                            </div>
                            
                            <!-- Fecha de Captura -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest" title="Fecha en que se registró en el sistema">Día Registro</label>
                                <input v-model="dateApply" type="date" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-gray-50" />
                            </div>

                            <!-- Separador -->
                            <div class="hidden md:flex md:col-span-1 justify-center pb-2">
                                <div class="h-6 w-px bg-gray-200"></div>
                            </div>

                            <!-- Opciones de Incidencia (Día Exacto) -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-blue-600 mb-1 uppercase tracking-widest">Día Exacto</label>
                                <input v-model="dateIncidence" @input="onIncidenceChange" type="date" class="block w-full rounded-lg border-blue-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-blue-50/30" />
                            </div>
                            
                            <!-- Opciones de Incidencia (Rango) -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-orange-600 mb-1 uppercase tracking-widest">Rango (Desde)</label>
                                <input v-model="dateStart" @input="onRangeChange" type="date" class="block w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-orange-600 mb-1 uppercase tracking-widest">Rango (Hasta)</label>
                                <input v-model="dateEnd" @input="onRangeChange" type="date" class="block w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS PRINCIPAL -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden flex flex-col flex-grow">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider w-20 text-[11px]">Folio</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Empleado</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Tipo</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Registro</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Vigencia</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Motivo</th>
                                        <th class="px-6 py-3 text-right font-bold text-gray-500 uppercase tracking-wider text-[11px]">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="inc in incidencias.data" :key="inc.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">#{{ inc.id }}</td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-gray-900">{{ inc.first_name }} {{ inc.last_name }}</div>
                                            <div class="text-[11px] text-gray-400 font-mono">ID: {{ inc.emp_code }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800 border border-blue-200">
                                                {{ inc.tipo }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ formatDate(inc.apply_time) }}</td>
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
                                        <td class="px-6 py-4 text-xs text-gray-600 max-w-xs truncate italic" :title="inc.apply_reason">
                                            {{ inc.apply_reason || '-' }}
                                        </td>
                                        <!-- BOTÓN EDITAR CON TODOS LOS FILTROS NUEVOS -->
                                        <td class="px-6 py-4 text-right">
                                            <Link 
                                                v-if="editIncidencia"
                                                :href="editIncidencia(inc.id).url" 
                                                :data="{ 
                                                    search: search, 
                                                    date_apply: dateApply, 
                                                    date_incidence: dateIncidence,
                                                    date_start: dateStart,
                                                    date_end: dateEnd, 
                                                    page: incidencias.current_page 
                                                }"
                                                class="text-blue-600 hover:text-blue-900 inline-flex items-center gap-1 font-black uppercase tracking-widest text-[10px] bg-white border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-all shadow-sm"
                                            >
                                                <Edit2 class="h-3 w-3" /> Editar
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="incidencias.data.length === 0">
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 bg-gray-50/50">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <Filter class="h-8 w-8 text-gray-300" />
                                                <p class="text-sm font-medium italic">No se encontraron incidencias con estos filtros.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINACIÓN -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between shrink-0" v-if="incidencias.total > 0">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">
                                        Mostrando <span class="text-gray-900">{{ incidencias.from }}</span> a <span class="text-gray-900">{{ incidencias.to }}</span> de <span class="text-gray-900">{{ incidencias.total }}</span> resultados
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-lg shadow-sm gap-2" aria-label="Pagination">
                                        <button @click="changePage(incidencias.prev_page_url)" :disabled="!incidencias.prev_page_url" class="px-4 py-2 border border-gray-300 bg-white text-xs font-black uppercase text-gray-600 disabled:opacity-50 rounded-lg hover:bg-gray-100 transition-colors">Anterior</button>
                                        <button @click="changePage(incidencias.next_page_url)" :disabled="!incidencias.next_page_url" class="px-4 py-2 border border-gray-300 bg-white text-xs font-black uppercase text-gray-600 disabled:opacity-50 rounded-lg hover:bg-gray-100 transition-colors">Siguiente</button>
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
                        <!-- NUEVO: Altura máxima (max-h-64) y scroll (overflow-y-auto) -->
                        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden max-h-64 overflow-y-auto custom-scrollbar relative">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <!-- NUEVO: Encabezado pegajoso (sticky top-0) -->
                                <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider w-24 text-[11px] bg-gray-50">ID</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Nombre del Permiso</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Código (Símbolo)</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Unidad</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="cat in categorias" :key="cat.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ cat.id }}</td>
                                        <td class="px-6 py-4 font-bold text-gray-900">{{ cat.name }}</td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded font-black text-[10px] border border-gray-200 uppercase tracking-widest">
                                                {{ cat.code }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 text-xs font-medium">
                                            {{ cat.unit === 3 ? 'Días' : (cat.unit === 2 ? 'Horas' : 'Minutos') }}
                                        </td>
                                    </tr>
                                    <tr v-if="categorias.length === 0">
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-400 italic">No hay categorías registradas.</td>
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
        
        <div v-if="showSuccessModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-all">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center animate-in zoom-in-95 duration-200 border border-gray-100">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6 border-4 border-green-50">
                    <CheckCircle class="h-8 w-8 text-green-600" />
                </div>
                <h3 class="text-xl font-black text-gray-900 mb-2 tracking-tight">¡Proceso Terminado!</h3>
                <p class="text-sm text-gray-500 mb-8 font-medium leading-relaxed">{{ successMessage }}</p>
                <button @click="showSuccessModal = false" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-green-600/30 uppercase text-sm tracking-widest">
                    Entendido
                </button>
            </div>
        </div>

        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative animate-in zoom-in-95 duration-200">
                <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-lg font-black text-gray-900 flex items-center gap-2"><FileUp class="h-5 w-5 text-blue-600" /> Importación Masiva</h3>
                    <button @click="showImportModal = false" class="p-1 hover:bg-gray-100 rounded-full text-gray-400 hover:text-gray-600 transition-colors"><X class="h-5 w-5" /></button>
                </div>

                <div class="mb-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg flex items-start gap-3 shadow-sm">
                    <AlertTriangle class="h-6 w-6 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-xs text-amber-900 font-bold mb-1 uppercase tracking-widest">Instrucción Crucial</p>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Asegúrese de que el archivo <strong>SOLO tenga los datos</strong> a partir de la fila 2. Elimine cualquier fila de ejemplo o instrucciones de la plantilla original.
                        </p>
                    </div>
                </div>

                <div class="mb-6 flex items-center gap-3 p-3 bg-blue-50 text-blue-800 rounded-xl border border-blue-100 shadow-sm">
                    <Info class="h-5 w-5 shrink-0" />
                    <p class="text-[10px] font-medium leading-tight italic">
                        Nota técnica: El sistema saltará automáticamente las primeras 2 filas (Encabezados y Ejemplo de la plantilla).
                    </p>
                </div>

                <form @submit.prevent="handleImportSubmit" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Archivo Excel (.xlsx)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-xl p-1 cursor-pointer transition-colors" />
                    </div>
                    
                    <div class="flex justify-center mb-4">
                        <a href="/incidencias/plantilla" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1.5 transition-colors">
                            <Download class="h-4 w-4" /> Descargar plantilla oficial
                        </a>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <button type="submit" :disabled="isImporting" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 rounded-xl shadow-md shadow-blue-600/20 disabled:opacity-50 flex justify-center items-center gap-2 transition-all active:scale-95 uppercase tracking-widest text-xs">
                            <Loader2 v-if="isImporting" class="h-4 w-4 animate-spin" />
                            {{ isImporting ? 'Procesando archivo...' : 'Subir y Procesar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SidebarProvider>
</template>

<style scoped>
/* Estilos personalizados para que la barra de scroll se vea profesional y delgada */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f8fafc;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>