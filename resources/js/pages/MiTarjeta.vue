<script setup>
import { ref, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { LogOut } from 'lucide-vue-next'; // Icono para el botón

// --- IMPORTS ---
import { getSchedule } from '@/routes'; 
import { download_pdf } from '@/routes/tarjetas'; 
import TarjetaPdf from './TarjetaPdf.vue'; 

const props = defineProps({
    empleado: { type: Object, required: true },
    descargasPrevias: { type: Array, default: () => [] },
    resumenFaltas: { type: Object, default: () => ({}) }
});

const year = 2025;
const loadingId = ref(null);
const generatingPdf = ref(false);
const downloadedMonths = ref(new Set(props.descargasPrevias));

// Estado para el Modal de Error (Bloqueo)
const showModal = ref(false);

// Estado para el Modal de Confirmación (Aviso de descarga única)
const showConfirmModal = ref(false);
const pendingMonthId = ref(null);

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

// --- HELPER: DETECTAR FALTAS ---
const hasFaults = (monthId) => {
    return props.resumenFaltas && props.resumenFaltas[monthId] && props.resumenFaltas[monthId].length > 0;
};

// --- ESTILOS DE FILA ---
const getRowClass = (monthId) => {
    if (hasFaults(monthId)) {
        return 'bg-red-50 border-l-4 border-l-red-500'; // Estilo bloqueado
    }
    return 'hover:bg-blue-50'; 
};

// --- MANEJO DE CLIC EN EL BOTÓN ---
const handleAction = (monthId) => {
    // 1. Si tiene faltas, mostramos el modal de error/bloqueo
    if (hasFaults(monthId)) {
        showModal.value = true;
        return;
    }
    
    // 2. Si está disponible, pedimos confirmación con el aviso legal
    pendingMonthId.value = monthId;
    showConfirmModal.value = true;
};

// --- CONFIRMAR DESCARGA ---
const confirmDownload = () => {
    if (pendingMonthId.value !== null) {
        descargarTarjeta(pendingMonthId.value);
    }
    showConfirmModal.value = false;
    pendingMonthId.value = null;
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
            emp_id: props.empleado.id, 
            month: monthId,
            year: year
        });

        processDataForPdf(response.data, monthId);
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
        pdf.save(`Tarjeta_${props.empleado.emp_code}_${months[monthId-1].name}_${year}.pdf`);
        
        await registrarDescargaEnBackend(monthId);
    } catch (e) {
        alert("Error al generar PDF.");
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
            <!-- Botón Cerrar Sesión (Alineado a la derecha) -->
            <Link 
                href="/logout" 
                method="post" 
                as="button" 
                class="logout-link"
            >
                <LogOut class="w-4 h-4 mr-2" />
                Cerrar Sesión
            </Link>
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
                    <h3 class="text-lg font-semibold text-gray-700">Tarjetas de asistencia</h3>
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
                                    
                                    <!-- LÓGICA DE ESTATUS -->
                                    <template v-if="hasFaults(month.id)">
                                        <!-- CASO 1: CON FALTAS (Bloqueado) -->
                                        <div class="flex flex-col items-center">
                                            <span class="status-badge blocked">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                No disponible
                                            </span>
                                            <!-- Detalle de días -->
                                            <div class="text-[11px] text-red-600 font-bold mt-1">
                                                Falta día(s): {{ resumenFaltas[month.id].join(', ') }}
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <template v-else>
                                        <!-- CASO 2: SIN FALTAS -->
                                        <span v-if="downloadedMonths.has(month.id)" class="status-badge generated">
                                            <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Descargada
                                        </span>
                                        <span v-else class="status-badge available">Disponible</span>
                                    </template>

                                </td>
                                <td class="text-right align-middle">
                                    <!-- BOTÓN INTELIGENTE -->
                                    <button 
                                        @click="handleAction(month.id)" 
                                        :disabled="loadingId !== null"
                                        class="download-button-small"
                                        :class="{'btn-blocked': hasFaults(month.id)}"
                                    >
                                        <template v-if="hasFaults(month.id)">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Ver Detalle
                                        </template>
                                        <template v-else-if="loadingId === month.id">
                                            <svg class="animate-spin -ml-1 mr-2 h-3 w-3 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Cargando...
                                        </template>
                                        <template v-else>
                                            <svg class="-ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            Descargar
                                        </template>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE BLOQUEO (ERROR) -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative animate-fade-in-up">
            <!-- Icono Alerta -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            
            <h3 class="text-lg font-bold text-center text-gray-900 mb-2">Acceso Restringido</h3>
            
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
                Se encuentran faltas o retardos graves no justificados en ese mes. Si considera esto un error, por favor acérquese a planta baja al área de Administración.
            </p>
            
            <div class="flex justify-center">
                <button @click="showModal = false" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Entendido
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIRMACIÓN (AVISO) -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative animate-fade-in-up">
            <!-- Icono Info -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
            </div>
            
            <h3 class="text-lg font-bold text-center text-gray-900 mb-2">Aviso Importante</h3>
            
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
                Solo se puede descargar una vez la tarjeta en formato PDF para su impresión. Si se presenta un error con la descarga de esta, por favor acérquese a planta baja al área de administración.
            </p>
            
            <div class="flex justify-center gap-4">
                <button @click="showConfirmModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-md transition-colors">
                    Cancelar
                </button>
                <button @click="confirmDownload" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Descargar
                </button>
            </div>
        </div>
    </div>

    <!-- COMPONENTE OCULTO PARA PDF -->
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
.schedule-card-wrapper { min-height: 100vh; background-color: #f3f4f6; padding: 24px; }

/* Barra de navegación: Alineación a la derecha */
.nav-bar { 
    max-width: 1000px; 
    margin: 0 auto 16px auto; 
    display: flex; 
    justify-content: flex-end; 
}

.back-link { color: #6b7280; text-decoration: none; font-weight: 500; font-size: 14px; transition: color 0.2s; }
.back-link:hover { color: #2563eb; }

/* ESTILO NUEVO: Botón Cerrar Sesión */
.logout-link {
    display: flex;
    align-items: center;
    color: #ef4444; /* Rojo suave */
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
}
.logout-link:hover { color: #b91c1c; }

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

/* BADGES */
.status-badge { padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
.status-badge.available { background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
.status-badge.generated { background-color: #ecfdf5; color: #059669; border-color: #d1fae5; }
.status-badge.blocked { background-color: #fef2f2; color: #dc2626; border-color: #fecaca; }

/* BOTONES */
.download-button-small {
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    background-color: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.download-button-small:hover:not(:disabled) { border-color: #2563eb; color: #2563eb; background-color: #eff6ff; }
.download-button-small:disabled { opacity: 0.5; cursor: not-allowed; }

/* Estilo para botón bloqueado */
.download-button-small.btn-blocked {
    color: #991b1b;
    border-color: #fecaca;
    background-color: #fff1f2;
}
.download-button-small.btn-blocked:hover {
    background-color: #fee2e2;
    border-color: #f87171;
}

/* Animación simple modal */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
</style>