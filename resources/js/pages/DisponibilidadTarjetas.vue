<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue'; 
import { SidebarProvider } from '@/components/ui/sidebar'; 
import { Search, AlertCircle, CheckCircle } from 'lucide-vue-next';
import { debounce } from 'lodash';

const props = defineProps({
    empleados: Object, 
    filters: Object,
    year: Number
});

// --- FILTROS REACTIVOS ---
// Se inicializan con lo que responde el servidor (para mantener el estado)
const search = ref(props.filters.search || '');
const monthFilter = ref(props.filters.month || '');
const statusFilter = ref(props.filters.status || '');

const monthsFull = [
    { id: 1, name: 'Enero' }, { id: 2, name: 'Febrero' }, { id: 3, name: 'Marzo' },
    { id: 4, name: 'Abril' }, { id: 5, name: 'Mayo' }, { id: 6, name: 'Junio' },
    { id: 7, name: 'Julio' }, { id: 8, name: 'Agosto' }, { id: 9, name: 'Septiembre' },
    { id: 10, name: 'Octubre' }, { id: 11, name: 'Noviembre' }, { id: 12, name: 'Diciembre' }
];

// --- REGLA: Si quitan el mes, quitamos el estatus ---
watch(monthFilter, (val) => {
    if (!val) statusFilter.value = '';
});

// --- RECARGA DE DATOS (BACKEND) ---
// Esta función manda la petición al servidor cada que algo cambia
const refreshData = debounce(() => {
    router.get('/reporte-disponibilidad', { 
        search: search.value,
        month: monthFilter.value,
        status: statusFilter.value,
        page: 1 // Importante: Reiniciar a página 1 al filtrar
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 500); // 500ms de espera para no saturar

// Observamos cualquier cambio en los filtros
watch([search, monthFilter, statusFilter], () => {
    refreshData();
});

const monthsHeader = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const getStatusClasses = (status) => {
    if (status === 'blocked') return 'bg-red-100 text-red-600 border-red-200'; 
    if (status === 'ok') return 'bg-green-100 text-green-600 border-green-200';
    return 'bg-gray-50 text-gray-300 border-gray-100'; 
};

// Paginación
const changePage = (url) => {
    if (!url) return;
    // Al cambiar de página, mantenemos los filtros actuales
    router.get(url, {
        search: search.value,
        month: monthFilter.value,
        status: statusFilter.value
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Disponibilidad Anual" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 min-h-screen bg-gray-50 flex flex-col">
                <div class="max-w-full mx-auto w-full flex-grow flex flex-col">
                    
                    <!-- Encabezado y Filtros -->
                    <div class="flex flex-col gap-4 mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Disponibilidad de Tarjetas {{ year }}</h1>
                            <p class="text-sm text-gray-500">Los filtros se aplican en toda la base de datos (Backend).</p>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3 bg-white p-3 rounded-lg shadow-sm border border-gray-200">
                            <!-- Buscador -->
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Search class="h-4 w-4 text-gray-400" />
                                </div>
                                <input v-model="search" type="text" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10" placeholder="Buscar por nombre o ID..." />
                            </div>

                            <!-- Filtro Mes -->
                            <div class="w-full sm:w-48">
                                <select v-model="monthFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50">
                                    <option value="">Seleccionar Mes</option>
                                    <option v-for="m in monthsFull" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>

                            <!-- Filtro Estatus -->
                            <div class="w-full sm:w-48">
                                <select 
                                    v-model="statusFilter" 
                                    :disabled="!monthFilter"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                                >
                                    <option value="">Tipo de Incidencia</option>
                                    <option value="blocked">🔴 Con Incidencias</option>
                                    <option value="ok">🟢 Sin Incidencias</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="bg-white shadow overflow-hidden border-b border-gray-200 sm:rounded-lg flex flex-col flex-grow h-[70vh]">
                        <div class="overflow-auto flex-grow relative">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 sticky top-0 z-20 shadow-sm">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 top-0 bg-gray-50 z-30 shadow-sm min-w-[250px] border-b border-gray-200">
                                            Empleado
                                        </th>
                                        <th v-for="m in monthsHeader" :key="m" class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16 sticky top-0 bg-gray-50 z-20 border-b border-gray-200">
                                            {{ m }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Iteramos DIRECTAMENTE sobre los datos del servidor (empleados.data) -->
                                    <tr v-for="emp in empleados.data" :key="emp.id" class="hover:bg-gray-50">
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
                                        
                                        <td v-for="(status, index) in emp.semaforo" :key="index" class="px-2 py-4 text-center whitespace-nowrap">
                                            <div 
                                                class="mx-auto flex items-center justify-center h-8 w-8 rounded-full border text-xs transition-all cursor-default"
                                                :class="getStatusClasses(status)"
                                                :style="monthFilter && parseInt(monthFilter) !== (index + 1) ? 'opacity: 0.2' : ''"
                                            >
                                                <AlertCircle v-if="status === 'blocked'" class="h-4 w-4" />
                                                <CheckCircle v-else-if="status === 'ok'" class="h-4 w-4" />
                                                <span v-else class="text-[10px] text-gray-300">•</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="empleados.data.length === 0">
                                        <td colspan="13" class="px-6 py-10 text-center text-gray-500">
                                            No se encontraron registros.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4 flex items-center justify-between" v-if="empleados.total > 0">
                        <div class="text-sm text-gray-700 hidden sm:block">
                            Mostrando {{ empleados.from }} a {{ empleados.to }} de {{ empleados.total }}
                        </div>
                        <div class="flex gap-2">
                            <button @click="changePage(empleados.prev_page_url)" :disabled="!empleados.prev_page_url" class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700">Anterior</button>
                            <button @click="changePage(empleados.next_page_url)" :disabled="!empleados.next_page_url" class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700">Siguiente</button>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>