<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, FileText, Download, Calendar, Monitor } from 'lucide-vue-next';
import debounce from 'lodash/debounce';

// LAYOUT
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { home } from '@/routes';
import { index as logsIndex } from '@/routes/logs';

const props = defineProps({
    logs: Object, 
    filters: Object
});

const search = ref(props.filters.search);

// Breadcrumbs
const breadcrumbs = [
    { title: 'Dashboard', href: home() },
    { title: 'Bitácora Descargas', href: logsIndex().url },
];

watch(search, debounce((value) => {
    router.get(route('logs.index'), { search: value }, { preserveState: true, replace: true });
}, 300));

const formatDate = (dateString) => {
    if (!dateString) return '-';
    let isoString = dateString.replace(' ', 'T');
    if (!isoString.endsWith('Z')) isoString += 'Z';
    const date = new Date(isoString);
    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    }).format(date);
};

const getMonthName = (monthNumber) => {
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return months[monthNumber - 1] || monthNumber;
};
</script>

<template>
    <Head title="Bitácora de Descargas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- CORRECCIÓN: Eliminado 'min-h-full' para evitar el doble scroll -->
        <div class="p-4 md:p-6 bg-gray-50 w-full">
            
            <div class="max-w-7xl mx-auto w-full">
                <!-- Encabezado -->
                <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <FileText class="h-6 w-6 text-gray-600" />
                            Bitácora de Descargas
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">Historial de tarjetas generadas por los usuarios.</p>
                    </div>

                    <!-- Buscador -->
                    <div class="relative max-w-md w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-gray-400" />
                        </div>
                        <input 
                            v-model="search"
                            type="text" 
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out shadow-sm"
                            placeholder="Buscar por Nombre o No. Empleado..."
                        >
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
                    <div class="overflow-x-auto w-full">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarjeta Solicitada</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de Descarga</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalles Técnicos</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs uppercase">
                                                {{ log.user.name.charAt(0) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ log.user.name }}</div>
                                                <div class="text-xs text-gray-500">No. Empleado: {{ log.user.emp_code || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-900">
                                            <Calendar class="mr-2 h-4 w-4 text-gray-400" />
                                            <span class="font-medium bg-gray-100 px-2 py-0.5 rounded text-gray-700">
                                                {{ getMonthName(log.month) }} {{ log.year }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <Download class="mr-2 h-4 w-4 text-green-500" />
                                            {{ formatDate(log.downloaded_at) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center" title="Dirección IP">
                                            <Monitor class="mr-2 h-4 w-4 text-gray-400" />
                                            {{ log.ip_address }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">No hay registros que coincidan con la búsqueda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="logs.links.length > 3" class="bg-white px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Anterior</Link>
                            <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Siguiente</Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <p class="text-sm text-gray-700">Mostrando {{ logs.from }} a {{ logs.to }} de {{ logs.total }}</p>
                            <div class="flex gap-1">
                                <template v-for="(link, key) in logs.links" :key="key">
                                    <Link v-if="link.url" :href="link.url" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium" :class="link.active ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'" v-html="link.label" />
                                    <span v-else v-html="link.label" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>