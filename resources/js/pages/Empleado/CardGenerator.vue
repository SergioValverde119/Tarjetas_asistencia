<script setup>
import { ref, nextTick } from 'vue';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { ArrowDownTrayIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { getSchedule } from '@/routes';
import TarjetaPdf from './TarjetaPdf.vue';

// Definición de propiedades sin tipado estricto de TS para evitar errores de esbuild
const props = defineProps({
    employee: Object,
    month: Number,
    year: Number
});

// --- ESTADO ---
const loading = ref(false);
const generatingPdf = ref(false);

const pdfData = ref({
    schedule: { horario: '', registros: [] },
    selectedMonth: 1,
    selectedYear: 2025,
    daysInMonth: 30,
    firstFortnight: [],
    secondFortnight: []
});

const monthNames = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

// --- LÓGICA ---
const getDayFromDate = (dateString) => {
    if (!dateString) return 0;
    const parts = dateString.split('-');
    return parseInt(parts[2], 10);
};

const processAttendanceData = (apiData, monthId, year) => {
    const rawRecords = apiData.registros || [];
    const processed = rawRecords.map(reg => {
        const hasObs = reg.observaciones && reg.observaciones.trim().length > 0;
        let calif = reg.calificacion;
        
        // Si es descanso pero tiene algo escrito, se marca como Justificado para la tarjeta
        if (reg.calificacion === 'DESC' && hasObs) {
            calif = 'J';
        }

        return {
            ...reg,
            checkin: reg.checkin ? reg.checkin.substring(0, 5) : '',
            checkout: reg.checkout ? reg.checkout.substring(0, 5) : '',
            calificacion: calif
        };
    });

    pdfData.value.schedule = apiData;
    pdfData.value.selectedMonth = monthId;
    pdfData.value.selectedYear = year;
    pdfData.value.daysInMonth = new Date(year, monthId, 0).getDate();
    pdfData.value.firstFortnight = processed.filter(r => getDayFromDate(r.dia) <= 15);
    pdfData.value.secondFortnight = processed.filter(r => getDayFromDate(r.dia) > 15);
};

const startDownload = async () => {
    if (loading.value) return;
    loading.value = true;

    try {
        // 1. Obtener datos del servidor
        const response = await axios.post(getSchedule().url, {
            emp_id: props.employee.id,
            month: props.month,
            year: props.year
        });

        // 2. Procesar para el formato de la tarjeta
        processAttendanceData(response.data, props.month, props.year);
        
        // 3. Renderizar componente oculto
        generatingPdf.value = true;
        await nextTick();
        
        // Espera de seguridad para renderizado de estilos
        await new Promise(resolve => setTimeout(resolve, 600));

        // 4. Convertir a PDF
        const element = document.getElementById('pdf-content');
        if (!element) throw new Error("DOM element not found");

        const canvas = await html2canvas(element, { 
            scale: 2, 
            useCORS: true,
            logging: false 
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pdf = new jsPDF('p', 'mm', 'letter');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Tarjeta_${props.employee.emp_code}_${monthNames[props.month - 1]}_${props.year}.pdf`);

    } catch (error) {
        console.error("Error generating card:", error);
    } finally {
        loading.value = false;
        generatingPdf.value = false;
    }
};
</script>

<template>
    <div class="flex items-center">
        <!-- Botón de descarga directa -->
        <button 
            @click="startDownload"
            :disabled="loading"
            class="inline-flex items-center px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-blue-200 transition-all active:scale-95 disabled:opacity-50 gap-2 border-b-4 border-blue-800"
        >
            <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
            <ArrowDownTrayIcon v-else class="h-4 w-4" />
            {{ loading ? 'Generando...' : 'Descargar Kardex Mensual' }}
        </button>

        <!-- Contenedor oculto para renderizado de html2canvas -->
        <div v-if="generatingPdf" class="fixed left-[-9999px] top-0 overflow-hidden">
            <TarjetaPdf
                :employee="employee"
                :schedule="pdfData.schedule"
                :months="monthNames"
                :first-fortnight="pdfData.firstFortnight"
                :second-fortnight="pdfData.secondFortnight"
                :days-in-month="pdfData.daysInMonth"
                :selected-month="pdfData.selectedMonth"
                :selected-year="pdfData.selectedYear"
            />
        </div>
    </div>
</template>