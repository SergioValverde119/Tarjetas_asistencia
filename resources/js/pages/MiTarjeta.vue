<script setup>
import { ref, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

// --- IMPORTS ---
import { home } from '@/routes'; // Ruta para el botón de volver
import { getSchedule } from '@/routes'; // Ruta de la API (asegúrate de haber corrido php artisan wayfinder:generate)

// Importamos tu componente visual (ajusta la ruta si lo tienes en otra carpeta, ej: '@/Components/...')
import TarjetaPdf from './TarjetaPdf.vue'; 

const props = defineProps({
    empleado: {
        type: Object,
        required: true
    }
});

// Configuración Fija
const year = 2025;
const loadingId = ref(null); // Controla el spinner de carga por mes
const generatingPdf = ref(false); // Controla si se renderiza la tarjeta oculta

// --- ESTADO PARA LA TARJETA OCULTA (PDF) ---
// Estos datos alimentarán al componente TarjetaPdf cuando se genere el documento
const pdfData = ref({
    schedule: { horario: '', registros: [] },
    selectedMonth: 1,
    selectedYear: year,
    daysInMonth: 30,
    firstFortnight: [],
    secondFortnight: []
});

// Meses para el Grid
const months = [
    { id: 1, name: 'Enero' }, { id: 2, name: 'Febrero' }, { id: 3, name: 'Marzo' },
    { id: 4, name: 'Abril' }, { id: 5, name: 'Mayo' }, { id: 6, name: 'Junio' },
    { id: 7, name: 'Julio' }, { id: 8, name: 'Agosto' }, { id: 9, name: 'Septiembre' },
    { id: 10, name: 'Octubre' }, { id: 11, name: 'Noviembre' }, { id: 12, name: 'Diciembre' },
];

// Mapeo de datos del empleado para que coincidan con lo que espera TarjetaPdf
// (TarjetaPdf espera 'emp_code', pero tu controlador manda 'id')
const employeeForPdf = computed(() => ({
    ...props.empleado,
    emp_code: props.empleado.id, // Mapeamos ID a emp_code
    department_name: props.empleado.department_name // Aseguramos compatibilidad
}));

// --- HELPERS ---
const getDayFromDateString = (dateString) => {
    if (!dateString || typeof dateString !== 'string') return '';
    const parts = dateString.split('-');
    return parseInt(parts[2], 10);
};

// --- PROCESAMIENTO DE DATOS ---
const processDataForPdf = (apiData, monthId) => {
    const registrosRaw = apiData.registros || [];
    
    // 1. Limpieza y Reglas de Negocio Visuales (DESC + Obs = J)
    const processedRegistros = registrosRaw.map(registro => {
        const hasObservation = registro.observaciones && registro.observaciones.trim().length > 0;
        let displayCalificacion = registro.calificacion;
        
        if (registro.calificacion === 'DESC' && hasObservation) {
            displayCalificacion = 'J';
        }
        
        // Quitar segundos de la hora para limpieza visual
        const cleanCheckin = registro.checkin ? registro.checkin.substring(0, 5) : '';
        const cleanCheckout = registro.checkout ? registro.checkout.substring(0, 5) : '';

        return {
            ...registro,
            checkin: cleanCheckin,
            checkout: cleanCheckout,
            calificacion: displayCalificacion
        };
    });

    // 2. Separar en Quincenas (Requisito de TarjetaPdf)
    const first = processedRegistros.filter(r => {
        const d = getDayFromDateString(r.dia);
        return d <= 15;
    });
    
    const second = processedRegistros.filter(r => {
        const d = getDayFromDateString(r.dia);
        return d > 15;
    });

    // 3. Asignar al estado reactivo
    pdfData.value.schedule = apiData;
    pdfData.value.selectedMonth = monthId;
    pdfData.value.selectedYear = year;
    pdfData.value.daysInMonth = new Date(year, monthId, 0).getDate();
    pdfData.value.firstFortnight = first;
    pdfData.value.secondFortnight = second;
};

// --- FUNCIÓN PRINCIPAL DE DESCARGA ---
const descargarTarjeta = async (monthId) => {
    loadingId.value = monthId;
    
    try {
        // 1. Obtener datos de la API
        const url = getSchedule().url;
        const response = await axios.post(url, {
            emp_id: props.empleado.id,
            month: monthId,
            year: year
        });

        // 2. Procesar datos para el componente visual
        processDataForPdf(response.data, monthId);

        // 3. Renderizar el componente oculto
        generatingPdf.value = true;
        await nextTick(); // Esperamos a que Vue dibuje el DOM

        // 4. Generar el PDF (mismo método que en Tarjetas Generales)
        const element = document.getElementById('pdf-content');
        if (!element) throw new Error("No se pudo renderizar la tarjeta para impresión.");

        const canvas = await html2canvas(element, { 
            scale: 2, // Alta resolución
            useCORS: true,
            logging: false,
            windowWidth: element.scrollWidth,
            windowHeight: element.scrollHeight
        });
        
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pdf = new jsPDF('p', 'mm', 'letter'); // Vertical, mm, Carta
        
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        
        // 5. Guardar archivo
        const mesNombre = months.find(m => m.id === monthId).name;
        pdf.save(`Tarjeta_${props.empleado.id}_${mesNombre}_${year}.pdf`);

    } catch (error) {
        console.error("Error al generar PDF:", error);
        alert("Hubo un error al generar el documento. Por favor intente de nuevo.");
    } finally {
        generatingPdf.value = false; // Ocultar componente
        loadingId.value = null; // Quitar spinner
    }
};
</script>

<template>
    <Head title="Mis Tarjetas 2025" />

    <div class="min-h-screen bg-gray-50 p-6">
        <div class="max-w-6xl mx-auto">
            
            <!-- ENCABEZADO Y NAVEGACIÓN -->
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Mis Tarjetas</h1>
                    <p class="text-gray-500 mt-1">Historial Anual de Asistencia {{ year }}</p>
                </div>
                
                <Link :href="home().url" class="bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Volver al Inicio
                </Link>
            </div>

            <!-- FICHA DEL EMPLEADO -->
            <div class="bg-white rounded-xl shadow-md border-t-4 border-blue-600 mb-8 p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">ID Empleado</p>
                        <p class="text-xl font-bold text-gray-900">{{ empleado.id }}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg md:col-span-2">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Nombre Completo</p>
                        <p class="text-xl font-bold text-gray-900">{{ empleado.first_name }} {{ empleado.last_name }}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Departamento</p>
                        <p class="text-sm font-medium text-gray-900 truncate" :title="empleado.department_name">
                            {{ empleado.department_name }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- GRID DE MESES -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div 
                    v-for="month in months" 
                    :key="month.id" 
                    class="bg-white p-5 rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 flex flex-col justify-between h-full"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800">{{ month.name }}</h3>
                            <p class="text-xs text-gray-400">Periodo {{ year }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">
                            {{ month.id }}
                        </div>
                    </div>
                    
                    <button 
                        @click="descargarTarjeta(month.id)"
                        :disabled="loadingId !== null"
                        class="w-full mt-auto flex justify-center items-center gap-2 py-2.5 px-4 bg-white border border-gray-200 text-gray-700 hover:bg-blue-600 hover:text-white hover:border-blue-600 rounded-lg font-medium transition-all text-sm shadow-sm disabled:opacity-50 disabled:cursor-not-allowed group"
                    >
                        <!-- Spinner de carga -->
                        <template v-if="loadingId === month.id">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Generando...</span>
                        </template>
                        
                        <!-- Icono Descargar -->
                        <template v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Descargar PDF</span>
                        </template>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- 
      === COMPONENTE OCULTO PARA GENERACIÓN DE PDF ===
      Este componente solo se renderiza cuando generatingPdf es true.
      Usa la misma estructura que Tarjetas Generales para mantener consistencia.
      Está posicionado fuera de pantalla por su propio CSS.
    -->
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