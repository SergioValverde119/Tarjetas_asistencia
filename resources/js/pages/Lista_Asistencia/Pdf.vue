<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

// Iconos para la interfaz (no se imprimen)
import { Printer } from 'lucide-vue-next';
import { 
    ArrowDownTrayIcon, 
    ArrowLeftIcon, 
    ArrowPathIcon 
} from '@heroicons/vue/24/outline';

import EncabezadoPdf from '../Tarjeta/EncabezadoPdf.vue';
import PieDePaginaPdf from '../Tarjeta/PieDePaginaPdf.vue';

// Propiedades recibidas del controlador
const props = defineProps({
    employee: { 
        type: Object, 
        default: () => ({}) 
    },
    selectedMonth: { 
        type: Number, 
        default: 1 
    },
    selectedYear: { 
        type: Number, 
        default: 2025 
    },
    months: { 
        type: Array, 
        default: () => [] 
    },
    schedule: { 
        type: String, 
        default: "08:00 - 16:00" 
    },
    justifications: { 
        type: Array, 
        default: () => [] 
    },
    holidays: { 
        type: Array, 
        default: () => [] 
    },
    weekends: { 
        type: Array, 
        default: () => [] 
    }
});

const isGenerating = ref(false);

const monthName = computed(() => {
    return props.months[props.selectedMonth - 1] || '---';
});

const daysInMonth = computed(() => {
    return new Date(props.selectedYear, props.selectedMonth, 0).getDate();
});

// Generación de quincenas
const firstFortnight = computed(() => Array.from({ length: 15 }, (_, i) => i + 1));
const secondFortnight = computed(() => {
    const days = [];
    for (let i = 16; i <= daysInMonth.value; i++) {
        days.push(i);
    }
    return days;
});

const getDayStatus = (day) => {
    const weekend = props.weekends.find(w => w.day === day);
    if (weekend) return { label: weekend.label, isBlocked: true };
    if (props.holidays.includes(day)) return { label: 'DÍA FERIADO', isBlocked: true };
    const just = props.justifications.find(j => parseInt(j.day) === day);
    if (just) return { label: just.motivo.toUpperCase(), isBlocked: true };
    return null;
};

const descargarPDF = async () => {
    if (isGenerating.value) return;
    isGenerating.value = true;
    try {
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 600));
        const element = document.getElementById('pdf-to-capture');
        const canvas = await html2canvas(element, { 
            scale: 2, 
            useCORS: true, 
            backgroundColor: '#ffffff' 
        });
        const imgData = canvas.toDataURL('image/jpeg', 0.98);
        const pdf = new jsPDF('p', 'mm', 'letter');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Lista_${props.employee.emp_code}_${monthName.value}.pdf`);
    } catch (error) {
        console.error("Error al generar el PDF:", error);
    } finally {
        isGenerating.value = false;
    }
};

const handlePrint = () => window.print();

</script>

<template>
    <Head :title="`Lista Asistencia - ${employee.emp_code}`" />
    
    <div class="no-print sticky top-0 z-50 bg-gray-800 p-4 mb-6 shadow-xl flex justify-between items-center">
        <Link href="/asistencia" class="text-white text-xs font-bold uppercase flex items-center gap-2">
            <ArrowLeftIcon class="w-4 h-4" /> Volver
        </Link>
        <div class="flex gap-3">
            <button @click="handlePrint" class="bg-gray-600 text-white px-5 py-2 rounded-lg text-[10px] font-bold uppercase flex items-center gap-2">
                <Printer class="w-4 h-4" /> Imprimir
            </button>
            <button @click="descargarPDF" :disabled="isGenerating" class="bg-gray-100 text-gray-900 px-6 py-2 rounded-lg text-[10px] font-bold uppercase flex items-center gap-2 shadow-lg active:scale-95 disabled:opacity-50">
                <ArrowPathIcon v-if="isGenerating" class="w-4 h-4 animate-spin" />
                <ArrowDownTrayIcon v-else class="w-4 h-4" />
                {{ isGenerating ? 'Procesando...' : 'Descargar PDF' }}
            </button>
        </div>
    </div>

    <div class="page-container pb-20">
        <div id="pdf-to-capture" class="pdf-card-container">
            <EncabezadoPdf />

            <!-- Información Personal -->
            <div class="personal-data-list">
                <div class="info-row">
                    <span class="label">Nombre:</span>
                    <span class="value">{{ employee.first_name }} {{ employee.last_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Área de adscripción:</span>
                    <span class="value uppercase">{{ employee.department_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Número de empleado:</span>
                    <span class="value font-mono">{{ employee.emp_code }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Jefe inmediato:</span>
                    <span class="value line-empty"></span>
                </div>
                <div class="info-row">
                    <span class="label">Horario:</span>
                    <!-- Se asume que el dato ya viene en formato 24h desde el controlador -->
                    <span class="value uppercase">{{ schedule }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Mes y Año:</span>
                    <span class="value font-bold uppercase">{{ monthName }} / {{ selectedYear }}</span>
                </div>
            </div>

            <div class="fortnights-container">
                <div class="fortnight-wrapper" v-for="(days, idx) in [firstFortnight, secondFortnight]" :key="idx">
                    <div class="fortnight-card">
                        <div class="fortnight-header">REGISTROS DE ASISTENCIA</div>
                        <div class="fortnight-body">
                            <table class="schedule-table-pdf">
                                <thead>
                                    <tr>
                                        <th class="col-dia">Día</th>
                                        <th>Entrada</th>
                                        <th>Firma</th>
                                        <th>Salida</th>
                                        <th>Firma</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="day in days" :key="day">
                                        <td class="font-bold">{{ String(day).padStart(2, '0') }}</td>
                                        <template v-if="getDayStatus(day)">
                                            <td colspan="4" class="blocked-cell">{{ getDayStatus(day).label }}</td>
                                        </template>
                                        <template v-else>
                                            <td></td><td></td><td></td><td></td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN DE FIRMAS: Distancia duplicada (60px) para evitar el encime -->
            <div class="signature-section-bottom">
                <div class="signature-row">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <p class="signature-name">{{ employee.first_name }} {{ employee.last_name }}</p>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <p class="signature-name">&nbsp;</p>
                    </div>
                </div>
            </div>
            
            <PieDePaginaPdf :year="selectedYear" />
        </div>
    </div>
</template>

<style scoped>
.page-container {
    background-color: #e2e8f0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    padding-top: 10px;
}

.pdf-card-container {
    width: 215.9mm;
    height: 279.4mm;
    padding: 6mm 10mm; 
    background-color: white;
    font-family: Arial, sans-serif;
    color: #000;
    position: relative;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.personal-data-list {
    margin: 5px 0 10px 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.info-row {
    display: flex;
    align-items: flex-end;
    font-size: 9.5pt;
}

.info-row .label {
    font-weight: bold;
    min-width: 160px;
}

.info-row .value {
    flex-grow: 1;
    border-bottom: 1px solid #ccc;
    padding-left: 8px;
}

.info-row .line-empty {
    height: 16px;
    border-bottom: 1px solid #ccc;
}

.fortnights-container {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-grow: 0; 
}

.fortnight-wrapper {
    width: 50%;
    display: flex;
    flex-direction: column;
}

.fortnight-card {
    border: 1px solid #000;
    border-radius: 6px;
    overflow: hidden;
    height: fit-content;
}

.fortnight-header {
    background-color: #51575c;
    color: white;
    font-weight: bold;
    padding: 4px 10px; 
    font-size: 9pt;
    text-align: center;
}

.fortnight-body {
    padding: 5px;
    background-color: white;
}

.schedule-table-pdf {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5pt;
    table-layout: fixed;
}

.schedule-table-pdf th,
.schedule-table-pdf td {
    border: 1px solid #000;
    padding: 2px;
    text-align: center;
}

.schedule-table-pdf th {
    background-color: #f2f2f2;
    font-weight: bold;
    font-size: 7pt;
}

.col-dia { width: 12%; }

.schedule-table-pdf tr {
    height: 21px; 
}

.blocked-cell {
    background-color: #d1d5db;
    font-weight: bold;
    font-size: 6.5pt;
    color: #374151;
}

/* DISTANCIA: Separación de 60px entre tablas y firmas (el doble de la anterior) */
.signature-section-bottom {
    margin-top: 60px; 
    margin-bottom: 10px;
}

.signature-row {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    gap: 40px;
}

.signature-box {
    width: 40%;
    text-align: center;
}

.signature-line {
    border-top: 1px solid #000;
    margin-bottom: 3px;
}

.signature-name {
    font-size: 8.5pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0;
}

@media print {
    .no-print { display: none !important; }
    .page-container { background: white; padding: 0; }
    .pdf-card-container { box-shadow: none; margin: 0; border: none; }
}
</style>