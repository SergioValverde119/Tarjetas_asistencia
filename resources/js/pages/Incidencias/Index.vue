<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { PlusCircle, List, FileText, Calendar, CheckCircle, Clock, Search, Filter, FileUp, Download, X } from 'lucide-vue-next';
import { debounce } from 'lodash';

// Importamos la ruta de creación (Wayfinder)
import { create as createIncidencia } from '@/routes/incidencias';

const props = defineProps({
    incidencias: Object, // Paginator
    categorias: Array,   // Catálogo (Restaurado)
    flash: Object,
    filters: Object
});

// --- FILTROS ---
const search = ref(props.filters.search || '');
const dateApply = ref(props.filters.date_apply || '');
const dateIncidence = ref(props.filters.date_incidence || '');

// Función de recarga con Debounce
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

// Observamos cambios
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

if (props.flash?.error) {
    errorMessage.value = props.flash.error;
    showErrorModal.value = true;
}

const handleImportSubmit = async (event) => {
    const form = event.target;
    const formData = new FormData(form);
    
    isImporting.value = true;

    try {
        const response = await fetch('/incidencias/importar', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `resultado_importacion_${new Date().toISOString().slice(0,10)}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            
            showImportModal.value = false;
            router.reload(); 
        } else {
            const data = await response.json();
            errorMessage.value = data.message || "Error al procesar el archivo.";
            showErrorModal.value = true;
        }
    } catch (error) {
        errorMessage.value = "Error de conexión.";
        showErrorModal.value = true;
    } finally {
        isImporting.value = false;
        form.reset(); 
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
            <!-- CONTENEDOR PRINCIPAL AJUSTADO: w-full y flex-col para adaptarse al sidebar -->
            <div class="p-6 bg-gray-50 min-h-screen w-full flex flex-col">
                <div class="w-full max-w-full space-y-6 flex-grow flex flex-col">
                    
                    <!-- ENCABEZADO -->
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Bitácora de Incidencias</h1>
                            <p class="text-sm text-gray-500">Gestión y consulta del historial de permisos.</p>
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
                                <PlusCircle class="h-4 w-4 mr-2" /> Nueva
                            </Link>
                        </div>
                    </div>

                    <!-- BARRA DE FILTROS -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            
                            <!-- Buscador -->
                            <div class="md:col-span-6 lg:col-span-6">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Buscar Empleado</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search class="h-4 w-4 text-gray-400" />
                                    </div>
                                    <input 
                                        v-model="search"
                                        type="text"
                                        class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10"
                                        placeholder="Nombre, Apellido o ID..."
                                    />
                                </div>
                            </div>

                            <!-- Filtro Fecha Registro -->
                            <div class="md:col-span-3 lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Registro</label>
                                <input 
                                    v-model="dateApply"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10"
                                />
                            </div>

                            <!-- Filtro Fecha Incidencia -->
                            <div class="md:col-span-3 lg:col-span-3">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Incidencia</label>
                                <input 
                                    v-model="dateIncidence"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- MENSAJE ÉXITO -->
                    <div v-if="$page.props.flash.success" class="rounded-md bg-green-50 p-4 border border-green-200">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <CheckCircle class="h-5 w-5 text-green-400" aria-hidden="true" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA PRINCIPAL -->
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden flex flex-col">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider w-20">Folio</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Vigencia</th>
                                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="inc in incidencias.data" :key="inc.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">#{{ inc.id }}</td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ inc.first_name }} {{ inc.last_name }}</div>
                                            <div class="text-xs text-gray-400">ID: {{ inc.emp_code }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                {{ inc.tipo }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 font-mono text-xs">
                                            {{ formatDate(inc.apply_time) }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">
                                            <div class="flex flex-col text-xs space-y-1">
                                                <span class="flex items-center gap-1"><Calendar class="h-3 w-3 text-green-600"/> {{ formatDate(inc.start_time) }}</span>
                                                <span class="flex items-center gap-1"><Clock class="h-3 w-3 text-red-500"/> {{ formatDate(inc.end_time) }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" :title="inc.apply_reason">
                                            {{ inc.apply_reason || '-' }}
                                        </td>
                                    </tr>
                                    <tr v-if="incidencias.data.length === 0">
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 bg-gray-50">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <Filter class="h-8 w-8 text-gray-300" />
                                                <p class="text-sm">No se encontraron incidencias con los filtros actuales.</p>
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
                                        <button 
                                            @click="changePage(incidencias.prev_page_url)"
                                            :disabled="!incidencias.prev_page_url"
                                            class="relative inline-flex items-center px-4 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Anterior
                                        </button>
                                        <button 
                                            @click="changePage(incidencias.next_page_url)"
                                            :disabled="!incidencias.next_page_url"
                                            class="relative inline-flex items-center px-4 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Siguiente
                                        </button>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA 2: CATÁLOGO DE CATEGORÍAS (RESTAURADA) -->
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden mt-auto">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <FileText class="h-4 w-4 text-gray-500" />
                                <h2 class="text-sm font-bold text-gray-700 uppercase">Catálogo de Permisos Disponibles</h2>
                            </div>
                            <span class="text-xs text-gray-400">{{ categorias.length }} tipos registrados</span>
                        </div>
                        <div class="p-4 bg-gray-50/50 max-h-48 overflow-y-auto">
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="cat in categorias" 
                                    :key="cat.id"
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white text-gray-700 border border-gray-300 shadow-sm hover:bg-gray-50 transition-colors cursor-default"
                                >
                                    {{ cat.name }} 
                                    <span class="ml-1.5 text-[10px] text-gray-400 font-mono bg-gray-100 px-1 rounded">{{ cat.code }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>

        <!-- VENTANA EMERGENTE DE ERROR -->
        <ErrorModal 
            :show="showErrorModal" 
            :message="errorMessage" 
            title="¡Atención!"
            @close="showErrorModal = false" 
        />
        
        <!-- MODAL IMPORTACIÓN (Se mantiene oculto hasta click) -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative animate-in fade-in zoom-in duration-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Importación Masiva (Excel)</h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600"><X class="h-5 w-5" /></button>
                </div>
                
                <p class="text-sm text-gray-600 mb-4">
                    Suba un archivo Excel (.xlsx) con las columnas: Nómina, Nombre (Opcional), Código Permiso, Inicio, Fin, Motivo.
                </p>

                <a 
                    href="/incidencias/plantilla" 
                    target="_blank"
                    class="block w-full text-center py-2 px-4 mb-4 border border-green-300 text-green-700 rounded-md hover:bg-green-50 transition-colors text-sm font-semibold"
                >
                    <Download class="h-4 w-4 inline mr-1" /> Descargar Plantilla
                </a>

                <form @submit.prevent="handleImportSubmit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Archivo</label>
                        <input 
                            type="file" 
                            name="file" 
                            accept=".xlsx,.xls,.csv"
                            required
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                    </div>
                    
                    <div class="flex justify-end pt-2">
                        <button 
                            type="submit" 
                            :disabled="isImporting"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-sm disabled:opacity-50 flex justify-center items-center gap-2"
                        >
                            <span v-if="isImporting" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                            {{ isImporting ? 'Procesando...' : 'Subir y Procesar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </SidebarProvider>
</template>