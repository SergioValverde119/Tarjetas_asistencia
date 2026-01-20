<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { PlusCircle, List, FileText, Calendar, CheckCircle, Clock, Search, Filter } from 'lucide-vue-next';
import { debounce } from 'lodash';

import { create as createIncidencia } from '@/routes/incidencias';

const props = defineProps({
    incidencias: Object,
    categorias: Array,
    filters: Object,
    flash: Object
});

// --- FILTROS ---
const search = ref(props.filters.search || '');
const dateApply = ref(props.filters.date_apply || '');
const dateIncidence = ref(props.filters.date_incidence || '');

// Función de recarga (Usamos URL directa para evitar errores de 'route is not defined')
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

// Observamos cambios para búsqueda automática
watch([search, dateApply, dateIncidence], () => {
    refreshData();
});

// Función para el botón manual de buscar (fuerza la ejecución inmediata)
const handleSearchClick = () => {
    refreshData.cancel(); // Cancelamos el debounce pendiente
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
};

// Modal de Error
const showErrorModal = ref(false);
const errorMessage = ref('');

if (props.flash?.error) {
    errorMessage.value = props.flash.error;
    showErrorModal.value = true;
}

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return dateString.substring(0, 16).replace('T', ' ');
};

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
</script>

<template>
    <Head title="Listado de Incidencias" />

    <SidebarProvider>
        <AppSidebar>
            <!-- CONTENEDOR PRINCIPAL AJUSTADO: w-full y overflow-hidden para evitar scroll horizontal -->
            <div class="p-6 bg-gray-50 min-h-screen w-full overflow-hidden flex flex-col">
                <div class="w-full space-y-6 flex-grow">
                    
                    <!-- ENCABEZADO Y BOTÓN CREAR -->
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Bitácora de Incidencias</h1>
                            <p class="text-sm text-gray-500">Gestión y consulta del historial de permisos.</p>
                        </div>
                        <Link 
                            :href="createIncidencia ? createIncidencia().url : '#'" 
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                        >
                            <PlusCircle class="h-4 w-4 mr-2" />
                            Nueva Incidencia
                        </Link>
                    </div>

                    <!-- BARRA DE FILTROS -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            
                            <!-- Buscador -->
                            <div class="md:col-span-6 lg:col-span-5">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Buscar Empleado</label>
                                <div class="flex gap-2">
                                    <div class="relative flex-grow">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <Search class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <input 
                                            v-model="search"
                                            type="text"
                                            class="block w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-10"
                                            placeholder="Nombre, Apellido o ID..."
                                            @keyup.enter="handleSearchClick"
                                        />
                                    </div>
                                    <button 
                                        @click="handleSearchClick"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none"
                                    >
                                        Buscar
                                    </button>
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

                    <!-- MENSAJE DE ÉXITO -->
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

                    <!-- TABLA DE INCIDENCIAS (Con scroll horizontal interno si es necesario) -->
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                            <List class="h-4 w-4 text-gray-500" />
                            <h2 class="text-sm font-bold text-gray-700 uppercase">Últimos Registros</h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Folio</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vigencia</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="inc in incidencias.data" :key="inc.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                            #{{ inc.id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-xs">
                                                    {{ inc.first_name ? inc.first_name.charAt(0) : 'U' }}
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">{{ inc.first_name }} {{ inc.last_name }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ inc.emp_code }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                {{ inc.tipo }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(inc.apply_time) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col text-xs text-gray-500 space-y-1">
                                                <span class="flex items-center gap-1"><Calendar class="h-3 w-3 text-green-600"/> {{ formatDate(inc.start_time) }}</span>
                                                <span class="flex items-center gap-1"><Clock class="h-3 w-3 text-red-500"/> {{ formatDate(inc.end_time) }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" :title="inc.apply_reason">
                                            {{ inc.apply_reason || '-' }}
                                        </td>
                                    </tr>
                                    <tr v-if="!incidencias.data || incidencias.data.length === 0">
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

                        <!-- Paginación -->
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

                    <!-- TABLA 2: CATÁLOGO DE CATEGORÍAS (Con Scroll vertical) -->
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <FileText class="h-4 w-4 text-gray-500" />
                                <h2 class="text-sm font-bold text-gray-700 uppercase">Catálogo de Permisos</h2>
                            </div>
                            <span class="text-xs text-gray-400">{{ categorias.length }} tipos registrados</span>
                        </div>
                        <!-- Scroll Vertical para no ocupar tanto espacio -->
                        <div class="p-6 bg-gray-50/50 max-h-48 overflow-y-auto">
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="cat in categorias" 
                                    :key="cat.id"
                                    class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-white text-gray-700 border border-gray-300 shadow-sm"
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
    </SidebarProvider>
</template>