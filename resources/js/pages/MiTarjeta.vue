<script setup>
import { ref, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { LogOut, AlertCircle, FileDown, CheckCircle, Loader2, Lock } from 'lucide-vue-next';

// --- IMPORTS ---
import { getSchedule } from '@/routes'; 
import { download_pdf } from '@/routes/tarjetas'; 
import TarjetaPdf from './TarjetaPdf.vue'; 

const props = defineProps({
    empleado: { type: Object, required: true },
    descargasPrevias: { type: Array, default: () => [] }, // Formato: ["1-2025", "2-2025"]
    resumenFaltas: { type: Object, default: () => ({}) },   // Formato: { 2025: { 1: [days] } }
    rollingMonths: { type: Array, default: () => [] }     // Lista de 12 meses deslizantes
});

const loadingId = ref(null);
const generatingPdf = ref(false);

// Estado para el Modal de Bloqueo (Error)
const showModal = ref(false);

// Estado para el Modal de Confirmación (Aviso de descarga única)
const showConfirmModal = ref(false);
const pendingMonth = ref(null); // Guardará el objeto mes completo {id, year, name...}

const pdfData = ref({
    schedule: { horario: '', registros: [] },
    selectedMonth: 1,
    selectedYear: 2025,
    daysInMonth: 30,
    firstFortnight: [],
    secondFortnight: []
});

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

// --- HELPER: DETECTAR FALTAS (Considerando Año) ---
const hasFaults = (monthId, year) => {
    return props.resumenFaltas[year] && 
           props.resumenFaltas[year][monthId] && 
           props.resumenFaltas[year][monthId].length > 0;
};

// --- HELPER: DETECTAR DESCARGA (Considerando Año) ---
const isDownloaded = (monthId, year) => {
    return props.descargasPrevias.includes(`${monthId}-${year}`);
};

// --- ESTILOS DE FILA ---
const getRowClass = (monthId, year) => {
    if (hasFaults(monthId, year)) {
        return 'bg-red-50 border-l-4 border-l-red-500';
    }
    return 'hover:bg-blue-50'; 
};

// --- MANEJO DE CLIC EN EL BOTÓN ---
const handleAction = (monthObj) => {
    if (hasFaults(monthObj.id, monthObj.year)) {
        showModal.value = true;
        return;
    }
    
    pendingMonth.value = monthObj;
    showConfirmModal.value = true;
};

const confirmDownload = () => {
    if (pendingMonth.value) {
        descargarTarjeta(pendingMonth.value);
    }
    showConfirmModal.value = false;
};

// --- PROCESAMIENTO PDF ---
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

const registrarDescargaEnBackend = async (monthId, year) => {
    try {
        await axios.post(download_pdf().url, { month: monthId, year: year });
        // Recargamos la página para actualizar el estatus de descarga en los props
        router.reload({ only: ['descargasPrevias'] });
    } catch (e) { console.error(e); }
};

const descargarTarjeta = async (monthObj) => {
    const monthId = monthObj.id;
    const year = monthObj.year;

    loadingId.value = `${monthId}-${year}`;
    try {
        const response = await axios.post(getSchedule().url, {
            emp_id: props.empleado.id, 
            month: monthId,
            year: year
        });

        processDataForPdf(response.data, monthId, year);
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
        pdf.save(`Tarjeta_${props.empleado.emp_code}_${monthObj.name}_${year}.pdf`);
        
        await registrarDescargaEnBackend(monthId, year);
    } catch (e) {
        console.error(e);
        alert("Error al generar PDF.");
    } finally {
        generatingPdf.value = false;
        loadingId.value = null;
        pendingMonth.value = null;
    }
};
</script>

<template>
    <Head title="Mis Tarjetas de Asistencia" />

    <div class="schedule-card-wrapper">
        <div class="nav-bar">
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
                    <p class="text-sm text-gray-500">
                        Expediente: {{ empleado.emp_code }} | 
                        Departamento: {{ empleado.department_name }}
                    </p>
                </div>
                <!-- <img src="/images/logo_mujer_indigena.jpeg" alt="Logo" class="logo" @error="handleImageError"> -->
                <img src="/images/logo_Margarita_Maza.png" alt="Logo" class="logo" @error="handleImageError">
            </div>

            <div class="content-body">
                <div class="table-header">
                    <h3 class="text-lg font-semibold text-gray-700">Historial de Tarjetas (Últimos 12 meses)</h3>
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
                            <tr v-for="m in rollingMonths" :key="`${m.id}-${m.year}`" :class="getRowClass(m.id, m.year)" class="transition-colors duration-150">
                                <td class="font-medium text-gray-700">{{ m.name }}</td>
                                <td class="text-center text-gray-500 font-bold">{{ m.year }}</td>
                                <td class="text-center">
                                    
                                    <template v-if="hasFaults(m.id, m.year)">
                                        <div class="flex flex-col items-center">
                                            <span class="status-badge blocked">
                                                <AlertCircle class="w-3 h-3 inline mr-1" />
                                                No disponible
                                            </span>
                                            <div class="text-[11px] text-red-600 font-bold mt-1 uppercase tracking-tighter">
                                                Falta día(s): {{ resumenFaltas[m.year][m.id].join(', ') }}
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <template v-else>
                                        <span v-if="isDownloaded(m.id, m.year)" class="status-badge generated">
                                            <CheckCircle class="w-3 h-3 inline mr-1" />
                                            Descargada
                                        </span>
                                        <span v-else class="status-badge available">Disponible</span>
                                    </template>

                                </td>
                                <td class="text-right align-middle">
                                    <button 
                                        @click="handleAction(m)" 
                                        :disabled="loadingId !== null"
                                        class="download-button-small"
                                        :class="{'btn-blocked': hasFaults(m.id, m.year)}"
                                    >
                                        <template v-if="hasFaults(m.id, m.year)">
                                            <Lock class="-ml-1 mr-2 h-4 w-4" />
                                            Ver Detalle
                                        </template>
                                        <template v-else-if="loadingId === `${m.id}-${m.year}`">
                                            <Loader2 class="animate-spin -ml-1 mr-2 h-3 w-3 inline" />
                                            Cargando...
                                        </template>
                                        <template v-else>
                                            <FileDown class="-ml-1 mr-2 h-4 w-4 inline" />
                                            Descargar
                                        </template>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="rollingMonths.length === 0">
                                <td colspan="4" class="text-center py-10 text-gray-400 italic">No hay tarjetas disponibles en este periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE BLOQUEO (ERROR) -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 backdrop-blur-sm transition-all">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative animate-fade-in-up border-t-4 border-red-600">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <AlertCircle class="h-6 w-6 text-red-600" />
            </div>
            <h3 class="text-lg font-bold text-center text-gray-900 mb-2">Acceso Restringido</h3>
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
                Se encuentran faltas o retardos graves no justificados en ese mes. Si considera esto un error, por favor acérquese a planta baja al área de Administración.
            </p>
            <div class="flex justify-center">
                <button @click="showModal = false" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md transition-colors shadow-sm focus:outline-none">
                    Entendido
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIRMACIÓN (AVISO) -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 backdrop-blur-sm transition-all">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 relative animate-fade-in-up border-t-4 border-blue-600">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <AlertCircle class="h-6 w-6 text-blue-600" />
            </div>
            <h3 class="text-lg font-bold text-center text-gray-900 mb-2">Aviso Importante</h3>
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
                Solo se puede descargar una vez la tarjeta en formato PDF para su impresión. Asegúrese de que su impresora esté lista antes de continuar.
            </p>
            <div class="flex justify-center gap-4">
                <button @click="showConfirmModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-md transition-colors">
                    Cancelar
                </button>
                <button @click="confirmDownload" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md transition-colors shadow-sm">
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
        :months="['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']" 
        :first-fortnight="pdfData.firstFortnight"
        :second-fortnight="pdfData.secondFortnight"
        :days-in-month="pdfData.daysInMonth"
        :selected-month="pdfData.selectedMonth"
        :selected-year="pdfData.selectedYear"
    />
</template>

<style scoped>
.schedule-card-wrapper { min-height: 100vh; background-color: #f3f4f6; padding: 24px; }
.nav-bar { max-width: 1000px; margin: 0 auto 16px auto; display: flex; justify-content: flex-end; }

.logout-link {
    display: flex;
    align-items: center;
    color: #ef4444; 
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    background: white;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #fee2e2;
    cursor: pointer;
    transition: all 0.2s;
}
.logout-link:hover { color: white; background-color: #b91c1c; }

.schedule-card { max-width: 1000px; margin: 0 auto; padding: 32px; background-color: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
.header { display: flex; justify-content: space-between; align-items: center; text-align: center; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 24px; }
.header .logo { height: 80px; width: auto; }
.header .logo .logo-Margarita { height: 50px; width: auto; }
.header .header-text { flex-grow: 1; }
.table-wrapper { width: 100%; overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
th { background-color: #f9fafb; font-weight: 600; color: #374151; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
td { color: #4b5563; font-size: 14px; }
.text-center { text-align: center; }
.text-right { text-align: right; }

.status-badge { padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; border: 1px solid transparent; text-transform: uppercase; }
.status-badge.available { background-color: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
.status-badge.generated { background-color: #ecfdf5; color: #059669; border-color: #d1fae5; }
.status-badge.blocked { background-color: #fef2f2; color: #dc2626; border-color: #fecaca; }

.download-button-small {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    background-color: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
}
.download-button-small:hover:not(:disabled) { border-color: #2563eb; color: white; background-color: #2563eb; }
.download-button-small:disabled { opacity: 0.5; cursor: not-allowed; }

.download-button-small.btn-blocked {
    color: #991b1b;
    border-color: #fecaca;
    background-color: #fff1f2;
}
.download-button-small.btn-blocked:hover {
    background-color: #fee2e2;
    border-color: #f87171;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
</style>