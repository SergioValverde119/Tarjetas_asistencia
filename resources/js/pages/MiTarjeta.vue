<script setup>
import { ref, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

import { home } from '@/routes';
import { getSchedule } from '@/routes'; 
import { download_pdf } from '@/routes/tarjetas'; 
import TarjetaPdf from './TarjetaPdf.vue'; 

const props = defineProps({
    empleado: Object,
    descargasPrevias: { type: Array, default: () => [] },
    // El resumen de faltas viene del controlador: { 1: [5, 12], 2: [1] }
    resumenFaltas: { type: Object, default: () => ({}) }
});

const year = 2025;
const loadingId = ref(null);
const generatingPdf = ref(false);
const downloadedMonths = ref(new Set(props.descargasPrevias));

const pdfData = ref({
    schedule: { horario: '', registros: [] },
    selectedMonth: 1,
    selectedYear: year,
    daysInMonth: 30,
    firstFortnight: [],
    secondFortnight: []
});

const months = [
    { id: 1, name: 'Enero' }, { id: 2, name: 'Febrero' }, { id: 3, name: 'Marzo' },
    { id: 4, name: 'Abril' }, { id: 5, name: 'Mayo' }, { id: 6, name: 'Junio' },
    { id: 7, name: 'Julio' }, { id: 8, name: 'Agosto' }, { id: 9, name: 'Septiembre' },
    { id: 10, name: 'Octubre' }, { id: 11, name: 'Noviembre' }, { id: 12, name: 'Diciembre' },
];

const employeeForPdf = computed(() => ({
    ...props.empleado,
    emp_code: props.empleado.emp_code || props.empleado.id, 
}));

const getDayFromDateString = (dateString) => {
    if (!dateString) return '';
    return parseInt(dateString.split('-')[2], 10);
};

const handleImageError = (e) => {
    e.target.src = "https://placehold.co/90x90/D1D5DB/4B5563?text=LOGO";
};

// --- ESTILOS DE FILA (ROJO SI HAY FALTA) ---
const getRowClass = (monthId) => {
    // Verificamos si existe el mes en el objeto y si tiene elementos
    if (props.resumenFaltas && props.resumenFaltas[monthId] && props.resumenFaltas[monthId].length > 0) {
        return 'bg-red-50 border-l-4 border-l-red-400 hover:bg-red-100'; 
    }
    return 'hover:bg-blue-50'; 
};

// --- PROCESAMIENTO PDF ---
const processDataForPdf = (apiData, monthId) => {
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

const registrarDescargaEnBackend = async (monthId) => {
    try {
        await axios.post(download_pdf().url, { month: monthId, year: year });
        downloadedMonths.value.add(monthId);
    } catch (e) { console.error(e); }
};

const descargarTarjeta = async (monthId) => {
    loadingId.value = monthId;
    try {
        const response = await axios.post(getSchedule().url, {
            emp_id: props.empleado.id, // ID Interno para la API
            month: monthId,
            year: year
        });

        processDataForPdf(response.data, monthId);
        generatingPdf.value = true;
        await nextTick(); 

        const element = document.getElementById('pdf-content');
        if (!element) throw new Error("Error render");

        const canvas = await html2canvas(element, { scale: 2, useCORS: true, logging: false });
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pdf = new jsPDF('p', 'mm', 'letter');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Tarjeta_${props.empleado.emp_code}_${months[monthId-1].name}_${year}.pdf`);
        
        await registrarDescargaEnBackend(monthId);
    } catch (e) {
        alert("Error al generar PDF.");
        console.error(e);
    } finally {
        generatingPdf.value = false;
        loadingId.value = null;
    }
};
</script>

<template>
    <Head title="Tarjetas de asistencia del año 2025" />

    <div class="schedule-card-wrapper">
        <div class="nav-bar">
            <Link :href="home().url" class="back-link">← Volver al Menú</Link>
        </div>

        <div class="schedule-card">
            <div class="header">
                <img src="/images/logo_cdmx.jpeg" alt="Logo CDMX" class="logo" @error="handleImageError">
                <div class="header-text">
                    <h2 class="font-bold text-2xl text-gray-800">{{ empleado.first_name }} {{ empleado.last_name }}</h2>
                    <p class="text-sm text-gray-500">Expediente: {{ empleado.emp_code }} | Departamento: {{ empleado.department_name }}</p>
                </div>
                <img src="/images/logo_mujer_indigena.jpeg" alt="Logo" class="logo" @error="handleImageError">
            </div>

            <div class="content-body">
                <div class="table-header">
                    <h3 class="text-lg font-semibold text-gray-700">Tarjetas de asistencia del año {{ year }}</h3>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="text-center">Año</th>
                                <th class="text-center">Estatus</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="month in months" :key="month.id" :class="getRowClass(month.id)" class="transition-colors duration-150">
                                <td class="font-medium text-gray-700">{{ month.name }}</td>
                                <td class="text-center text-gray-500">{{ year }}</td>
                                <td class="text-center">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span v-if="downloadedMonths.has(month.id)" class="status-badge generated">Generado</span>
                                        <span v-else class="status-badge available">Disponible</span>
                                        
                                        <!-- INDICADOR DE FALTAS -->
                                        <div v-if="resumenFaltas[month.id] && resumenFaltas[month.id].length > 0" class="flex items-center text-xs text-red-600 font-bold mt-1">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            Falta día(s): {{ resumenFaltas[month.id].join(', ') }}
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right align-middle">
                                    <button @click="descargarTarjeta(month.id)" :disabled="loadingId !== null" class="download-button-small">
                                        <span v-if="loadingId === month.id">Cargando...</span>
                                        <span v-else>Descargar PDF</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <TarjetaPdf 
        v-if="generatingPdf"
        :employee="employeeForPdf"
        :schedule="pdfData.schedule"
        :months="months.map(m => m.name)" 
        :first-fortnight="pdfData.firstFortnight"
        :second-fortnight="pdfData.secondFortnight"
        :days-in-month="pdfData.daysInMonth"
        :selected-month="pdfData.selectedMonth"
        :selected-year="pdfData.selectedYear"
    />
</template>

<style scoped>
/* ESTILOS COPIADOS DE SCHEDULEVIEWER */
.schedule-card-wrapper { min-height: 100vh; background-color: #f3f4f6; padding: 24px; }
.nav-bar { max-width: 1000px; margin: 0 auto 16px auto; display: flex; justify-content: flex-end; }
.back-link { color: #6b7280; text-decoration: none; font-weight: 500; font-size: 14px; transition: color 0.2s; }
.back-link:hover { color: #2563eb; }
.schedule-card { max-width: 1000px; margin: 0 auto; padding: 32px; background-color: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
.header { display: flex; justify-content: space-between; align-items: center; text-align: center; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 24px; }
.header .logo { height: 90px; width: auto; }
.header .header-text { flex-grow: 1; }
.table-wrapper { width: 100%; overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
th { background-color: #f9fafb; font-weight: 600; color: #374151; }
td { color: #4b5563; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.status-badge { padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
.status-badge.available { background-color: #f3f4f6; color: #4b5563; }
.status-badge.generated { background-color: #dcfce7; color: #166534; }
.download-button-small { padding: 6px 12px; font-size: 13px; font-weight: 500; cursor: pointer; background-color: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; transition: all 0.2s; }
.download-button-small:hover:not(:disabled) { border-color: #2563eb; color: #2563eb; background-color: #eff6ff; }
.download-button-small:disabled { opacity: 0.5; cursor: not-allowed; }
</style>