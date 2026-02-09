<script setup>
import { ref, watch, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue'; 
import { SidebarProvider } from '@/components/ui/sidebar'; 
import { Search, AlertCircle, CheckCircle, Loader2 } from 'lucide-vue-next';
import { debounce } from 'lodash';

import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import TarjetaPdf from './TarjetaPdf.vue'; 
import { getSchedule } from '@/routes'; 

const props = defineProps({
    empleados: Object, 
    filters: Object,
    rollingMonths: Array, // Lista dinámica de 12 meses (id, year, name, label)
    year: Number
});

// --- ESTADO ---
const generatingPdf = ref(false);
const loadingCell = ref(null);
const pdfData = ref({
    schedule: { horario: '', registros: [] },
    selectedMonth: 1,
    selectedYear: props.year,
    daysInMonth: 30,
    firstFortnight: [],
    secondFortnight: []
});
const employeeForPdf = ref({}); 

const search = ref(props.filters.search || '');
const monthFilter = ref(props.filters.month || '');
const statusFilter = ref(props.filters.status || '');

// --- LÓGICA PDF ---
const getDayFromDateString = (dateString) => {
    if (!dateString) return '';
    return parseInt(dateString.split('-')[2], 10);
};

const processDataForPdf = (apiData, monthId, year) => {
    const registrosRaw = apiData.registros || [];
    const processedRegistros = registrosRaw.map(registro => {
        const hasObservation = registro.observaciones && registro.observaciones.trim().length > 0;
        let displayCalificacion = registro.calificacion;
        if (registro.calificacion === 'DESC' && hasObservation) displayCalificacion = 'J';
        
        return {
            ...registro,
            checkin: registro.checkin ? registro.checkin.substring(0, 5) : '',
            checkout: registro.checkout ? registro.checkout.substring(0, 5) : '',
            calificacion: displayCalificacion
        };
    });

    pdfData.value.schedule = apiData;
    pdfData.value.selectedMonth = monthId;
    pdfData.value.selectedYear = year; 
    pdfData.value.daysInMonth = new Date(year, monthId, 0).getDate();
    pdfData.value.firstFortnight = processedRegistros.filter(r => getDayFromDateString(r.dia) <= 15);
    pdfData.value.secondFortnight = processedRegistros.filter(r => getDayFromDateString(r.dia) > 15);
};

const downloadCard = async (emp, monthIndex) => {
    const monthObj = props.rollingMonths[monthIndex];
    const cellId = `${emp.id}-${monthIndex}`;
    
    if (loadingCell.value) return;
    loadingCell.value = cellId;
    
    try {
        employeeForPdf.value = {
            ...emp,
            emp_code: emp.emp_code || emp.id, 
            department_name: emp.department_name 
        };

        const response = await axios.post(getSchedule().url, {
            emp_id: emp.id, 
            month: monthObj.id,
            year: monthObj.year // Mandamos el año real de la columna
        });

        processDataForPdf(response.data, monthObj.id, monthObj.year);
        generatingPdf.value = true;
        await nextTick(); 

        const element = document.getElementById('pdf-content');
        if (!element) throw new Error("Render error");

        const canvas = await html2canvas(element, { scale: 2, useCORS: true, logging: false });
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pdf = new jsPDF('p', 'mm', 'letter');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Tarjeta_${emp.emp_code}_${monthObj.name}_${monthObj.year}.pdf`);

    } catch (e) {
        console.error(e);
    } finally {
        generatingPdf.value = false;
        loadingCell.value = null;
    }
};

const refreshData = debounce(() => {
    router.get('/reporte-disponibilidad', { 
        search: search.value,
        month: monthFilter.value,
        status: statusFilter.value,
        page: 1 
    }, { preserveState: true, replace: true, preserveScroll: true });
}, 500);

watch([search, monthFilter, statusFilter], () => { refreshData(); });

const getStatusClasses = (status) => {
    if (status === 'blocked') return 'bg-red-100 text-red-600 border-red-200 hover:bg-red-200 hover:border-red-300 cursor-pointer'; 
    if (status === 'ok') return 'bg-green-100 text-green-600 border-green-200 hover:bg-green-200 hover:border-green-300 cursor-pointer';
    return 'bg-gray-50 text-gray-300 border-gray-100 cursor-default'; 
};

const changePage = (url) => {
    if (!url) return;
    router.get(url, {
        search: search.value,
        month: monthFilter.value,
        status: statusFilter.value
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Disponibilidad de Tarjetas" />

    <SidebarProvider>
        <AppSidebar>
            <div class="flex flex-col h-screen max-h-screen bg-gray-50 p-6 overflow-hidden">
                <div class="flex flex-col w-full h-full max-w-full mx-auto">
                    
                    <!-- 1. ENCABEZADO Y FILTROS -->
                    <div class="flex-none flex flex-col gap-4 mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Disponibilidad de Tarjetas</h1>
                            <p class="text-sm text-gray-500">Haga clic en los círculos de color para descargar.</p>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3 bg-white p-3 rounded-lg shadow-sm border border-gray-200">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Search class="h-4 w-4 text-gray-400" />
                                </div>
                                <input v-model="search" type="text" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10" placeholder="Buscar por nombre o ID..." />
                            </div>

                            <div class="w-full sm:w-48">
                                <select v-model="monthFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50">
                                    <option value="">Cualquier Mes</option>
                                    <!-- Usamos rollingMonths para que el filtro incluya el año y funcione -->
                                    <option v-for="m in rollingMonths" :key="`${m.id}-${m.year}`" :value="m.id">{{ m.label }}</option>
                                </select>
                            </div>

                            <div class="w-full sm:w-48">
                                <select v-model="statusFilter" :disabled="!monthFilter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm h-10 bg-gray-50 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                                    <option value="">Tipo de Incidencia</option>
                                    <option value="blocked">🔴 Con Incidencias</option>
                                    <option value="ok">🟢 Sin Incidencias</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. TABLA -->
                    <div class="flex-1 bg-white shadow border-b border-gray-200 sm:rounded-lg flex flex-col min-h-0 overflow-hidden">
                        <div class="flex-1 overflow-auto relative">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 sticky top-0 z-20 shadow-sm">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 top-0 bg-gray-50 z-30 shadow-sm min-w-[250px] border-b border-gray-200">
                                            Empleado
                                        </th>
                                        <!-- CABECERAS CON COLOR POR AÑO (Azul 2025, Naranja 2026) -->
                                        <th v-for="m in rollingMonths" :key="`${m.id}-${m.year}`" 
                                            class="px-2 py-3 text-center text-[10px] font-black uppercase tracking-wider w-16 sticky top-0 z-20 border-b border-gray-200 transition-colors"
                                            :class="m.year === 2025 ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700'"
                                        >
                                            {{ m.name.substring(0, 3) }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
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
                                            <button 
                                                class="mx-auto flex items-center justify-center h-8 w-8 rounded-full border text-xs transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 shadow-sm"
                                                :class="getStatusClasses(status)"
                                                :style="monthFilter && parseInt(monthFilter) === rollingMonths[index].id ? 'border-width: 2px; border-color: currentColor;' : (monthFilter ? 'opacity: 0.2' : '')"
                                                :disabled="status === 'future' || loadingCell"
                                                @click="status !== 'future' && downloadCard(emp, index)"
                                                :title="status === 'blocked' ? 'Descargar (Incidencias)' : (status === 'ok' ? 'Descargar (Limpio)' : 'Mes Futuro')"
                                            >
                                                <Loader2 v-if="loadingCell === `${emp.id}-${index}`" class="h-4 w-4 animate-spin" />
                                                <template v-else>
                                                    <AlertCircle v-if="status === 'blocked'" class="h-4 w-4" />
                                                    <CheckCircle v-else-if="status === 'ok'" class="h-4 w-4" />
                                                    <span v-else class="text-[10px] text-gray-300">•</span>
                                                </template>
                                            </button>
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

                    <!-- 3. PAGINACIÓN -->
                    <div class="flex-none mt-4 flex items-center justify-between" v-if="empleados.total > 0">
                        <div class="text-sm text-gray-700 hidden sm:block">
                            Mostrando {{ empleados.from }} a {{ empleados.to }} de {{ empleados.total }} resultados
                        </div>
                        <div class="flex gap-2">
                            <button @click="changePage(empleados.prev_page_url)" :disabled="!empleados.prev_page_url" class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700">Anterior</button>
                            <button @click="changePage(empleados.next_page_url)" :disabled="!empleados.next_page_url" class="px-4 py-2 border rounded bg-white hover:bg-gray-50 disabled:opacity-50 text-sm font-medium text-gray-700">Siguiente</button>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>

        <TarjetaPdf 
            v-if="generatingPdf"
            :employee="employeeForPdf"
            :schedule="pdfData.schedule"
            :months="['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']" 
            :first-fortnight="pdfData.firstFortnight"
            :second-fortnight="pdfData.secondFortnight"
            :days-in-month="pdfData.daysInMonth"
            :selected-month="pdfData.selectedMonth"
            :selected-year="pdfData.selectedYear"
        />
    </SidebarProvider>
</template>