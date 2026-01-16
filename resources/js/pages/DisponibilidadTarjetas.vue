<script setup>
import { ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue'; 
import { SidebarProvider } from '@/components/ui/sidebar'; 
import { Search, AlertCircle, CheckCircle, Filter } from 'lucide-vue-next';
import { debounce } from 'lodash';

// import { disponibilidad } from '@/routes/tarjetas'; 

const props = defineProps({
    empleados: Object, 
    filters: Object,
    year: Number
});

// --- FILTROS REACTIVOS ---
const search = ref(props.filters.search || '');
const monthFilter = ref(props.filters.month || '');
const statusFilter = ref(props.filters.status || '');

// Lista completa para el select
const monthsFull = [
    { id: 1, name: 'Enero' }, { id: 2, name: 'Febrero' }, { id: 3, name: 'Marzo' },
    { id: 4, name: 'Abril' }, { id: 5, name: 'Mayo' }, { id: 6, name: 'Junio' },
    { id: 7, name: 'Julio' }, { id: 8, name: 'Agosto' }, { id: 9, name: 'Septiembre' },
    { id: 10, name: 'Octubre' }, { id: 11, name: 'Noviembre' }, { id: 12, name: 'Diciembre' }
];

// Función unificada para actualizar filtros
const updateFilters = debounce(() => {
    // CORRECCIÓN: Usamos la URL directa para asegurar que funcione siempre
    // Y agregamos page: 1 para reiniciar la paginación al filtrar
    router.get('/reporte-disponibilidad', { 
        search: search.value,
        month: monthFilter.value,
        status: statusFilter.value,
        page: 1 
    }, {
        preserveState: true,
        replace: true,
    });
}, 500);

// Observamos cambios en cualquiera de los 3 filtros
watch([search, monthFilter, statusFilter], () => {
    updateFilters();
});

const monthsHeader = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const getStatusClasses = (status) => {
    if (status === 'blocked') return 'bg-red-100 text-red-600 border-red-200'; 
    if (status === 'ok') return 'bg-green-100 text-green-600 border-green-200';
    return 'bg-gray-50 text-gray-300 border-gray-100'; 
};

const changePage = (url) => {
    if (url) router.get(url);
};
</script>

<template>
    <Head title="Disponibilidad Anual" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 min-h-screen bg-gray-50">
                <div class="max-w-full mx-auto">
                    
                    <!-- Encabezado y Barra de Filtros -->
                    <div class="flex flex-col gap-4 mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Disponibilidad de Tarjetas {{ year }}</h1>
                            <p class="text-sm text-gray-500">Semáforo de incidencias. Use los filtros para reportes específicos.</p>
                        </div>
                        
                        <!-- Barra de Herramientas (Buscador + Filtros) -->
                        <div class="flex flex-col sm:flex-row gap-3 bg-white p-3 rounded-lg shadow-sm border border-gray-200">
                            
                            <!-- Buscador -->
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Search class="h-4 w-4 text-gray-400" />
                                </div>
                                <input 
                                    v-model="search"
                                    type="text"
                                    class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10"
                                    placeholder="Buscar por nombre o ID..."
                                />
                            </div>

                            <!-- Filtro Mes -->
                            <div class="w-full sm:w-48">
                                <select v-model="monthFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50">
                                    <option value="">Todo el Año</option>
                                    <option v-for="m in monthsFull" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>

                            <!-- Filtro Estatus -->
                            <div class="w-full sm:w-48">
                                <select v-model="statusFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50">
                                    <option value="">Todos los estatus</option>
                                    <option value="blocked">🔴 Con Incidencias</option>
                                    <option value="ok">🟢 Disponibles</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla Semáforo -->
                    <div class="bg-white shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <!-- Columna Fija: Nombre -->
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-20 shadow-sm min-w-[250px]">
                                            Empleado
                                        </th>
                                        <!-- Meses -->
                                        <th v-for="m in monthsHeader" :key="m" class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                            {{ m }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="emp in empleados.data" :key="emp.id" class="hover:bg-gray-50">
                                        
                                        <!-- Datos del Empleado -->
                                        <td class="px-6 py-4 whitespace-nowrap sticky left-0 bg-white z-10 shadow-sm border-r border-gray-100">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold">
                                                    {{ emp.first_name ? emp.first_name.charAt(0) : 'U' }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ emp.first_name }} {{ emp.last_name }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ emp.emp_code }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Grid de Semáforo -->
                                        <td v-for="(status, index) in emp.semaforo" :key="index" class="px-2 py-4 text-center whitespace-nowrap">
                                            <div 
                                                class="mx-auto flex items-center justify-center h-8 w-8 rounded-full border text-xs transition-all cursor-default"
                                                :class="getStatusClasses(status)"
                                                :style="monthFilter && parseInt(monthFilter) !== (index + 1) ? 'opacity: 0.3' : ''"
                                                :title="status === 'blocked' ? 'Con Incidencias' : (status === 'ok' ? 'Disponible' : 'Mes Futuro')"
                                            >
                                                <AlertCircle v-if="status === 'blocked'" class="h-4 w-4" />
                                                <CheckCircle v-else-if="status === 'ok'" class="h-4 w-4" />
                                                <span v-else class="text-[10px] text-gray-300">•</span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="empleados.data.length === 0">
                                        <td colspan="13" class="px-6 py-10 text-center text-gray-500">
                                            No se encontraron empleados que coincidan con los filtros.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4 flex items-center justify-between" v-if="empleados.total > 0">
                        <div class="text-sm text-gray-700 hidden sm:block">
                            Mostrando <span class="font-medium">{{ empleados.from }}</span> a <span class="font-medium">{{ empleados.to }}</span> de <span class="font-medium">{{ empleados.total }}</span> resultados
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="changePage(empleados.prev_page_url)"
                                :disabled="!empleados.prev_page_url"
                                class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium text-gray-700"
                            >
                                Anterior
                            </button>
                            <button 
                                @click="changePage(empleados.next_page_url)"
                                :disabled="!empleados.next_page_url"
                                class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium text-gray-700"
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>
