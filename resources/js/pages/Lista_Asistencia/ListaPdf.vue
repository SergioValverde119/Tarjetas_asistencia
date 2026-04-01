<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { Printer, FileDown, RefreshCw, LayoutPanelLeft } from 'lucide-vue-next';
import EncabezadoPdf from '../Tarjeta/EncabezadoPdf.vue';
import PieDePaginaPdf from '../Tarjeta/PieDePaginaPdf.vue';

const props = defineProps({
    employee: Object,
    attendanceData: Object,
    months: Array,
    selectedMonth: Number,
    selectedYear: Number
});

const isGenerating = ref(false);

// --- ESTADO PARA EL ÁREA (DEPARTAMENTO) EDITABLE ---
const editableDept = ref('');

// Sincronizar cuando cambie el empleado seleccionado
watch(() => props.employee, (newEmp) => {
    editableDept.value = newEmp?.department_name || '';
}, { immediate: true });

const daysInMonth = computed(() => new Date(props.selectedYear, props.selectedMonth, 0).getDate());
const firstFortnight = computed(() => Array.from({ length: 15 }, (_, i) => i + 1));
const secondFortnight = computed(() => {
    const days = [];
    for (let i = 16; i <= daysInMonth.value; i++) days.push(i);
    return days;
});

const getDayStatus = (day) => {
    if (!props.attendanceData) return null;
    const { weekends, holidays, justifications } = props.attendanceData;
    
    const weekend = weekends?.find(w => w.day === day);
    if (weekend) return { label: weekend.label, isBlocked: true };

    const holiday = holidays?.find(h => Number(h.day) === Number(day));
    if (holiday) return { label: holiday.name.toUpperCase(), isBlocked: true };

    const just = justifications?.find(j => parseInt(j.day) === day);
    if (just) return { label: just.motivo.toUpperCase(), isBlocked: true };
    
    return null;
};

const descargarPDF = async () => {
    if (isGenerating.value) return;
    isGenerating.value = true;
    try {
        await nextTick();
        await new Promise(resolve => setTimeout(resolve, 800));
        const element = document.getElementById('hoja-asistencia-pdf');
        if (!element) return;
        
        const canvas = await html2canvas(element, { 
            scale: 3, 
            useCORS: true, 
            backgroundColor: '#ffffff'
        });

        const pdf = new jsPDF('p', 'mm', 'letter');
        const imgData = canvas.toDataURL('image/jpeg', 1.0);
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Lista_${props.employee?.emp_code}_${props.months[props.selectedMonth - 1]}.pdf`);
    } catch (error) {
        console.error("Error al generar PDF:", error);
    } finally {
        isGenerating.value = false;
    }
};

const handlePrint = () => { window.print(); };
</script>

<template>
    <div class="w-full flex flex-col items-center animate-in fade-in duration-500 pb-12 text-black">
        
        <!-- 1. FILA SUPERIOR: TÍTULO Y BOTONES A LA DERECHA (ZONA NO IMPRIMIBLE) -->
        <div class="w-full max-w-[215.9mm] mb-4 flex justify-between items-center no-print px-4 sm:px-0">
            <div class="flex items-center gap-2">
                <div class="h-2 w-2 bg-emerald-500 rounded-full animate-pulse"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Vista Previa Lista de Firmas</span>
            </div>

            <div class="flex gap-3">
                <button @click="handlePrint" class="flex items-center gap-2 bg-slate-800 hover:bg-black text-white px-5 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-md active:scale-95">
                    <Printer class="w-4 h-4" /> Imprimir
                </button>
                <button @click="descargarPDF" :disabled="isGenerating" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-md active:scale-95 disabled:opacity-50">
                    <RefreshCw v-if="isGenerating" class="h-4 w-4 animate-spin" />
                    <FileDown v-else class="h-4 w-4" />
                    {{ isGenerating ? 'Procesando...' : 'Descargar PDF' }}
                </button>
            </div>
        </div>

        <!-- 2. FILA MEDIA: MODIFICAR DEPARTAMENTO (ZONA NO IMPRIMIBLE) -->
        <div class="w-full max-w-[215.9mm] mb-6 flex justify-start items-center no-print px-4 sm:px-0">
            <div class="flex items-center gap-3 w-full max-w-sm">
                <span class="text-[10px] font-black uppercase text-slate-500 whitespace-nowrap">Modificar Departamento:</span>
                <div class="relative flex-grow">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <LayoutPanelLeft class="h-3.5 w-3.5 text-blue-500" />
                    </div>
                    <input 
                        v-model="editableDept" 
                        type="text" 
                        class="block w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-[11px] font-bold uppercase focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-sm"
                        placeholder="ESCRIBA EL NUEVO ÁREA..."
                    />
                </div>
            </div>
        </div>

        <!-- 3. LA HOJA FÍSICA (EL PDF) -->
        <div id="hoja-asistencia-pdf" class="pdf-paper shadow-2xl">
            <EncabezadoPdf />

            <div class="personal-info-list">
                <div class="info-item">
                    <span class="label">Nombre del Trabajador:</span>
                    <span class="value">{{ employee?.first_name }} {{ employee?.last_name }}</span>
                </div>
                
                <div class="info-item">
                    <span class="label">Área de adscripción:</span>
                    <span class="value uppercase">{{ editableDept }}</span>
                </div>

                <div class="info-item">
                    <span class="label">Número de empleado:</span>
                    <span class="value font-bold">{{ employee?.emp_code }}</span>
                </div>

                <div class="info-item">
                    <span class="label">Jefe inmediato:</span>
                    <span class="value">&nbsp;</span>
                </div>

                <div class="info-item">
                    <span class="label">Horario:</span>
                    <span class="value uppercase">{{ attendanceData?.schedule || 'SIN HORARIO' }}</span>
                </div>
                
                <div class="info-item">
                    <span class="label">Mes y Año de Registro:</span>
                    <span class="value font-bold uppercase">{{ months[selectedMonth - 1] }} / {{ selectedYear }}</span>
                </div>
            </div>

            <div class="grids-wrapper">
                <div class="table-col" v-for="(days, idx) in [firstFortnight, secondFortnight]" :key="idx">
                    <table class="att-table">
                        <thead>
                            <tr>
                                <th class="w-[8%]">Día</th>
                                <th class="w-[19%]">Entrada</th>
                                <th class="w-[27%]">Firma</th>
                                <th class="w-[19%]">Salida</th>
                                <th class="w-[27%]">Firma</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in days" :key="day">
                                <td class="day-num">{{ String(day).padStart(2, '0') }}</td>
                                <template v-if="getDayStatus(day)">
                                    <td colspan="4" class="blocked">{{ getDayStatus(day).label }}</td>
                                </template>
                                <template v-else>
                                    <td></td><td></td><td></td><td></td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="signature-section">
                <div class="signature-row">
                    <div class="signature-box"><div class="line"></div><p class="name">{{ employee?.first_name }} {{ employee?.last_name }}</p></div>
                    <div class="signature-box"><div class="line"></div><p class="name">&nbsp;</p></div>
                </div>
            </div>

            <PieDePaginaPdf :year="selectedYear" class="mt-auto" />
        </div>
    </div>
</template>

<style scoped>
.pdf-paper {
    width: 215.9mm;
    height: 279.4mm; /* Cambiado de min-height a height fijo para evitar que crezca */
    background: white;
    padding: 8mm 10mm; /* Reducción ligera de paddings internos */
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    color: #000;
    font-family: Arial, sans-serif;
    position: relative;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    overflow: hidden; /* Evita que contenido extra genere páginas nuevas */
}

.personal-info-list { margin: 5px 0 5px 0; display: flex; flex-direction: column; gap: 2px; } /* Gap reducido de 4px a 2px */

.info-item { 
    display: flex; 
    align-items: baseline; 
    font-size: 9.5pt; 
}

.info-item .label { 
    font-weight: bold; 
    min-width: 180px; 
    padding-right: 5px; 
    text-transform: uppercase; 
}

.info-item .value { 
    flex-grow: 1; 
    border-bottom: 1.2px solid #cbd5e1; 
    padding-left: 8px; 
    padding-bottom: 1px; /* Reducido de 2px a 1px */
    text-transform: uppercase;
    text-align: center;
    min-height: 21px; /* Reducido de 24px a 21px */
    display: flex;
    align-items: flex-end;
    justify-content: center;
    line-height: 1.1; /* Reducido ligeramente */
}

.grids-wrapper { display: flex; gap: 15px; margin-top: 5px; } /* Margen reducido */
.table-col { width: 50%; }
.att-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5pt; }
.att-table th, .att-table td { border: 1px solid #000; padding: 1px 2px; text-align: center; vertical-align: middle; }
.att-table th { background-color: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 6.5pt; height: 22px; } /* Altura reducida */

.att-table tr { height: 27px; } /* Altura de fila reducida de 28px a 27px */

.day-num { background-color: #f8fafc; font-weight: bold; font-size: 9pt; }
.blocked { 
    background-color: #e2e8f0; 
    font-weight: bold; 
    font-size: 6pt; 
    color: #1f2937;
    line-height: 1.1;
    padding: 1px;
}

.signature-section { margin-top: 45px; margin-bottom: 20px; } /* Margen reducido de 60px a 45px */
.signature-row { display: flex; justify-content: space-around; }
.signature-box { width: 40%; text-align: center; }
.signature-box .line { border-top: 1.2px solid #000; margin-bottom: 4px; }
.signature-box .name { font-size: 9pt; font-weight: bold; text-transform: uppercase; }

.mt-auto { margin-top: auto !important; }

@media print {
    @page { 
        size: letter; 
        margin: 0 !important; 
    }
    html, body {
        height: 100%;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important; /* Solución para la hoja en blanco */
    }
    :global(nav), :global(aside), :global(header), :global(button), :global(.no-print), .no-print {
        display: none !important;
    }
    .pdf-paper {
        box-shadow: none !important;
        margin: 0 !important;
        padding: 8mm 10mm !important;
        width: 215.9mm !important;
        height: 279.4mm !important;
        border: none !important;
        position: absolute !important;
        top: 0;
        left: 0;
    }
}
</style>