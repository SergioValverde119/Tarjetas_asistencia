<script>
// Definición del Layout fuera del script setup para evitar errores de compilación con defineOptions
import AppLayout from '@/layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    Search, Calendar, Printer, FileDown,
    RotateCw, Filter, CheckCircle, Loader2,
    ChevronDown, UserCheck, ArrowLeft, RefreshCw
} from 'lucide-vue-next';
import { debounce } from 'lodash';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

// Componentes del PDF
import EncabezadoPdf from '../Tarjeta/EncabezadoPdf.vue';
import PieDePaginaPdf from '../Tarjeta/PieDePaginaPdf.vue';

/**
 * Pantalla de Gestión de Listas de Asistencia Manual.
 * Integra búsqueda y previsualización en una sola vista.
 * Primeramente Jehová Dios y Jesús Rey.
 */
const props = defineProps({
    employees: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    months: { type: Array, default: () => [] },
    // Estas props vendrán cuando se presione "Crear" (Inertia Reload)
    selectedEmployee: { type: Object, default: null },
    attendanceData: { type: Object, default: null }
});

// --- ESTADO ---
const form = ref({
    employee_id: props.filters.employee_id || '',
    search: props.filters.search || '',
    mes: parseInt(props.filters.mes) || new Date().getMonth() + 1,
    ano: parseInt(props.filters.ano) || new Date().getFullYear()
});

const isSearching = ref(false);
const isGenerating = ref(false);
const showDropdown = ref(false);
const showList = ref(!!props.attendanceData);

// --- BÚSQUEDA ---
const handleSearch = debounce((val) => {
    isSearching.value = true;
    router.get('/asistencia', { search: val }, {
        preserveState: true, preserveScroll: true, replace: true,
        only: ['employees'],
        onFinish: () => isSearching.value = false
    });
}, 400);

watch(() => form.value.search, (val) => {
    if (form.value.employee_id && val !== `${props.selectedEmployee?.first_name} ${props.selectedEmployee?.last_name}`) {
        form.value.employee_id = '';
    }
    showDropdown.value = true;
    handleSearch(val);
});

const selectEmployee = (emp) => {
    form.value.employee_id = emp.id;
    form.value.search = `${emp.first_name} ${emp.last_name}`;
    showDropdown.value = false;
};

// --- ACCIONES ---
const crearLista = () => {
    if (!form.value.employee_id) return;
    router.get('/asistencia', {
        employee_id: form.value.employee_id,
        mes: form.value.mes,
        ano: form.value.ano,
        search: form.value.search
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { showList.value = true; }
    });
};

const limpiarFiltros = () => {
    form.value.search = '';
    form.value.employee_id = '';
    showList.value = false;
    router.get('/asistencia');
};

// --- LÓGICA DE LA CUADRÍCULA ---
const daysInMonth = computed(() => new Date(form.value.ano, form.value.mes, 0).getDate());
const firstFortnight = computed(() => Array.from({ length: 15 }, (_, i) => i + 1));
const secondFortnight = computed(() => {
    const days = [];
    const total = daysInMonth.value;
    for (let i = 16; i <= total; i++) days.push(i);
    return days;
});

const getDayStatus = (day) => {
    if (!props.attendanceData) return null;
    const { weekends, holidays, justifications } = props.attendanceData;
    
    const weekend = weekends?.find(w => w.day === day);
    if (weekend) return { label: weekend.label, isBlocked: true };
    
    if (holidays?.map(Number).includes(Number(day))) return { label: 'DÍA FERIADO', isBlocked: true };
    
    const just = justifications?.find(j => parseInt(j.day) === day);
    if (just) return { label: just.motivo.toUpperCase(), isBlocked: true };
    
    return null;
};

// --- DESCARGA E IMPRESIÓN ---
const descargarPDF = async () => {
    if (isGenerating.value) return;
    isGenerating.value = true;
    try {
        await nextTick();
        const element = document.getElementById('pdf-to-capture');
        const canvas = await html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
        const pdf = new jsPDF('p', 'mm', 'letter');
        const imgData = canvas.toDataURL('image/jpeg', 0.98);
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Lista_${props.selectedEmployee?.emp_code}_${props.months[form.value.mes-1]}.pdf`);
    } finally {
        isGenerating.value = false;
    }
};

const handlePrint = () => window.print();

const anosDisponibles = computed(() => {
    const c = new Date().getFullYear();
    return [c, c - 1, c - 2];
});
</script>

<template>
    <Head title="Asistencia Manual" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 bg-slate-100 min-h-screen w-full flex flex-col font-sans">
                
                <!-- BARRA DE FILTROS INTEGRADA -->
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 w-full mb-6 no-print">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-black text-slate-700 flex items-center gap-2 uppercase tracking-widest leading-none">
                            <Filter class="h-4 w-4 text-blue-500" /> Búsqueda Avanzada
                        </h3>
                        <button @click="limpiarFiltros" class="text-[10px] text-slate-400 hover:text-red-600 font-black uppercase tracking-tighter transition-all flex items-center gap-1">
                            <RotateCw class="h-3 w-3" /> Reiniciar Selección
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <!-- Buscador -->
                        <div class="md:col-span-4 relative">
                            <label class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">1. Trabajador</label>
                            <div class="relative">
                                <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-300" />
                                <input 
                                    v-model="form.search" 
                                    @focus="showDropdown = true" 
                                    type="text" 
                                    class="block w-full pl-10 pr-10 rounded-lg border-slate-200 text-sm h-10 font-bold uppercase bg-slate-50 focus:ring-blue-500" 
                                    placeholder="Nombre o nómina..." 
                                />
                                <div v-if="showDropdown && employees.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl overflow-hidden max-h-48 overflow-y-auto">
                                    <div v-for="emp in employees" :key="emp.id" @click="selectEmployee(emp)" class="p-3 hover:bg-blue-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors">
                                        <div class="font-black text-slate-800 uppercase text-[11px] leading-tight">{{ emp.first_name }} {{ emp.last_name }}</div>
                                        <div class="text-[9px] font-bold text-blue-600 uppercase mt-0.5">ID: {{ emp.emp_code }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mes -->
                        <div class="md:col-span-3">
                            <label class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">2. Mes</label>
                            <div class="relative">
                                <select v-model="form.mes" class="block w-full rounded-lg border-slate-200 text-sm h-10 font-black bg-slate-50 appearance-none pl-3 pr-8">
                                    <option v-for="(m, idx) in months" :key="idx" :value="idx + 1">{{ m }}</option>
                                </select>
                                <ChevronDown class="absolute right-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                            </div>
                        </div>

                        <!-- Año -->
                        <div class="md:col-span-2">
                            <label class="block text-[9px] font-black text-slate-400 mb-1 uppercase tracking-widest">3. Año</label>
                            <div class="relative">
                                <select v-model="form.ano" class="block w-full rounded-lg border-slate-200 text-sm h-10 font-black bg-slate-50 appearance-none pl-3 pr-8">
                                    <option v-for="a in anosDisponibles" :key="a" :value="a">{{ a }}</option>
                                </select>
                                <ChevronDown class="absolute right-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                            </div>
                        </div>

                        <!-- BOTÓN CREAR -->
                        <div class="md:col-span-3">
                            <button 
                                @click="crearLista" 
                                :disabled="!form.employee_id" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 rounded-lg shadow-lg shadow-blue-200 text-xs uppercase tracking-[0.2em] transition-all active:scale-95 disabled:opacity-30 disabled:grayscale"
                            >
                                Crear Formato
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CONTENEDOR DE LA LISTA YA HECHA -->
                <div class="flex-grow flex flex-col items-center">
                    
                    <div v-if="!showList" class="flex-grow flex flex-col items-center justify-center p-20 border-4 border-dashed border-slate-200 rounded-3xl w-full bg-white/40">
                        <UserCheck class="h-16 w-16 text-slate-200 mb-4" />
                        <p class="text-slate-400 font-black uppercase text-xs tracking-[0.3em]">Seleccione un trabajador y presione Crear</p>
                    </div>

                    <div v-else class="w-full flex flex-col items-center animate-in fade-in slide-in-from-bottom-4 duration-500">
                        
                        <!-- BOTONES DE ACCIÓN SOBRE LA LISTA -->
                        <div class="w-full max-w-[215.9mm] mb-4 flex justify-end gap-3 no-print">
                            <button @click="handlePrint" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-800 text-white px-5 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-md">
                                <Printer class="h-4 w-4" /> Imprimir
                            </button>
                            <button @click="descargarPDF" :disabled="isGenerating" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-md">
                                <RefreshCw v-if="isGenerating" class="h-4 w-4 animate-spin" />
                                <FileDown v-else class="h-4 w-4" />
                                {{ isGenerating ? 'Procesando...' : 'Descargar PDF' }}
                            </button>
                        </div>

                        <!-- EL PDF (CUADRÍCULA LIMPIA) -->
                        <div id="pdf-to-capture" class="pdf-paper shadow-2xl">
                            <EncabezadoPdf />

                            <!-- Info Personal Restaurada -->
                            <div class="personal-info-grid">
                                <div class="row"><span class="lbl">Nombre del Trabajador:</span><span class="val">{{ selectedEmployee?.first_name }} {{ selectedEmployee?.last_name }}</span></div>
                                <div class="row"><span class="lbl">Área de adscripción:</span><span class="val uppercase">{{ selectedEmployee?.department_name }}</span></div>
                                <div class="row"><span class="lbl">Número de empleado:</span><span class="val font-mono font-bold tracking-tight">{{ selectedEmployee?.emp_code }}</span></div>
                                <div class="row"><span class="lbl">Horario Establecido (24h):</span><span class="val">{{ attendanceData?.schedule || '08:00 - 16:00' }}</span></div>
                                <div class="row"><span class="lbl">Mes y Año de Registro:</span><span class="val font-bold uppercase">{{ months[form.mes-1] }} / {{ form.ano }}</span></div>
                            </div>

                            <!-- Tablas -->
                            <div class="grids-wrapper">
                                <div class="col" v-for="(days, idx) in [firstFortnight, secondFortnight]" :key="idx">
                                    <table class="att-table">
                                        <thead>
                                            <tr><th class="w-8">Día</th><th>Entrada</th><th>Firma</th><th>Salida</th><th>Firma</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="day in days" :key="day">
                                                <td class="bg-slate-50 font-bold text-[9pt]">{{ String(day).padStart(2, '0') }}</td>
                                                <template v-if="getDayStatus(day)">
                                                    <td colspan="4" class="blocked">{{ getDayStatus(day).label }}</td>
                                                </template>
                                                <template v-else><td></td><td></td><td></td><td></td></template>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Firmas a 120px -->
                            <div class="sig-section">
                                <div class="sig-box">
                                    <div class="line"></div>
                                    <p class="nme">{{ selectedEmployee?.first_name }} {{ selectedEmployee?.last_name }}</p>
                                </div>
                                <div class="sig-box">
                                    <div class="line"></div>
                                    <p class="nme">&nbsp;</p>
                                </div>
                            </div>

                            <PieDePaginaPdf :year="form.ano" />
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="text-center py-6 no-print">
                    <p class="text-[9px] font-black text-slate-300 uppercase tracking-[0.4em]">En el Nombre de Jehová Dios y Jesús Rey</p>
                </div>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>

<style scoped>
/* Estilos para el papel PDF */
.pdf-paper {
    width: 215.9mm;
    min-height: 279.4mm;
    background: white;
    padding: 10mm 12mm;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    color: #000;
    font-family: Arial, sans-serif;
}

.personal-info-grid {
    margin: 10px 0 15px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.personal-info-grid .row {
    display: flex;
    align-items: flex-end;
    font-size: 9.5pt;
}

.personal-info-grid .lbl {
    font-weight: bold;
    min-width: 175px;
}

.personal-info-grid .val {
    flex-grow: 1;
    border-bottom: 1px solid #cbd5e1;
    padding-left: 8px;
    height: 18px;
}

.grids-wrapper {
    display: flex;
    gap: 12px;
    margin-top: 5px;
}

.grids-wrapper .col {
    width: 50%;
}

.att-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5pt;
    table-layout: fixed;
}

.att-table th, .att-table td {
    border: 1px solid #000;
    padding: 2.5px 2px;
    text-align: center;
}

.att-table th {
    background-color: #f8fafc;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 6.5pt;
}

.att-table tr { height: 21px; }

.blocked {
    background-color: #e2e8f0;
    font-weight: bold;
    font-size: 6.5pt;
    color: #1f2937;
}

.sig-section {
    margin-top: 120px;
    display: flex;
    justify-content: space-around;
    margin-bottom: 25px;
}

.sig-box {
    width: 40%;
    text-align: center;
}

.sig-box .line {
    border-top: 1.2px solid #000;
    margin-bottom: 4px;
}

.sig-box .nme {
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
}

@media print {
    .no-print { display: none !important; }
    body { background: white; padding: 0; }
    .pdf-paper { box-shadow: none; margin: 0; border: none; }
}
</style>