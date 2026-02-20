<script setup>
import { ref, computed } from 'vue';
import { 
    X, FileDown, Loader2, Info, Printer, AlertTriangle, Filter, RotateCw, CheckCircle2, Copy, Check
} from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    employee: Object,
    pdfData: Object,
    generatingPdf: Boolean,
    rollingMonths: Array
});

const emit = defineEmits(['close', 'generatePdf', 'printPdf']);

// --- ESTADO DE FILTRADO ---
const statusFilter = ref(null); // null, 'OK', 'F', 'J', 'RG', 'RL'

const getSelectedMonthName = () => {
    const month = props.rollingMonths.find(m => m.id === props.pdfData.selectedMonth);
    return month ? month.name : 'Mes';
};

const formatDay = (dateStr) => {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    return parts.length === 3 ? parseInt(parts[2]) : dateStr;
};

const formatTime = (time) => {
    if (!time || time === '--:--' || time === '') return null;
    return time.substring(0, 5);
};

/**
 * Formateador para quitar los segundos del horario base (Ej. "09:00:00 A 15:00:00" -> "09:00 A 15:00")
 */
const formatHorario = (horarioStr) => {
    if (!horarioStr) return 'Sin horario';
    return horarioStr.replace(/(\d{2}:\d{2}):\d{2}/g, '$1');
};

// --- COPIAR AL PORTAPAPELES ---
const copiedName = ref(false);
const copiedNomina = ref(false);

const copyToClipboard = (text, type) => {
    // Usamos execCommand por compatibilidad y restricciones del navegador
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        if (type === 'name') {
            copiedName.value = true;
            setTimeout(() => copiedName.value = false, 2000);
        } else if (type === 'nomina') {
            copiedNomina.value = true;
            setTimeout(() => copiedNomina.value = false, 2000);
        }
    } catch (err) {
        console.error('Error al copiar al portapapeles', err);
    }
    document.body.removeChild(textArea);
};

/**
 * Conteo dinámico de estatus para los botones
 */
const statusCounts = computed(() => {
    const counts = { OK: 0, F: 0, J: 0, RG: 0, RL: 0 };
    const regs = props.pdfData?.schedule?.registros || [];
    regs.forEach(reg => {
        if (counts[reg.calificacion] !== undefined) {
            counts[reg.calificacion]++;
        }
    });
    return counts;
});

/**
 * Lógica de filtrado de registros
 */
const filteredRegistros = computed(() => {
    const regs = props.pdfData.schedule.registros || [];
    if (!statusFilter.value) return regs;
    return regs.filter(r => r.calificacion === statusFilter.value);
});

const setFilter = (status) => {
    statusFilter.value = statusFilter.value === status ? null : status;
};

/**
 * Lógica de colores para las filas basada en la calificación
 * Usamos el nivel 50 de Tailwind que es el más claro y sutil.
 */
const getStatusRowClass = (status) => {
    switch (status) {
        case 'OK':   return 'bg-green-50 hover:bg-green-100 border-l-4 border-l-green-400';
        case 'F':    return 'bg-red-50 hover:bg-red-100 border-l-4 border-l-red-400';
        case 'RG':   return 'bg-orange-50 hover:bg-orange-100 border-l-4 border-l-orange-400';
        case 'RL':   return 'bg-yellow-50 hover:bg-yellow-100 border-l-4 border-l-yellow-400';
        case 'DESC': return 'bg-gray-50 hover:bg-gray-100 border-l-4 border-l-gray-300';
        case 'J':    return 'bg-blue-50 hover:bg-blue-100 border-l-4 border-l-blue-400';
        default:     return 'bg-white hover:bg-gray-50 border-l-4 border-l-transparent';
    }
};

const getBadgeClass = (status) => {
    switch (status) {
        case 'OK':   return 'bg-green-500 text-white shadow-sm';
        case 'F':    return 'bg-red-500 text-white shadow-sm ring-1 ring-red-300';
        case 'RG':   return 'bg-orange-500 text-white shadow-sm';
        case 'RL':   return 'bg-yellow-400 text-gray-900 shadow-sm';
        case 'DESC': return 'bg-gray-500 text-white';
        case 'J':    return 'bg-blue-500 text-white shadow-sm';
        default:     return 'bg-gray-200 text-gray-600';
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-2 md:p-4 bg-black/80 backdrop-blur-sm transition-all duration-300">
        <!-- Ventana expandida al máximo ancho -->
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-[98vw] h-[98vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-300 border border-gray-200">
            
            <!-- CABECERA: Limpia en color blanco con texto oscuro -->
            <div class="bg-white p-4 md:px-8 flex justify-between items-center shadow-sm shrink-0 border-b border-gray-200 z-10">
                <div class="flex items-center gap-6">
                    <!-- Ficha Empleado -->
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 rounded-2xl bg-gray-100 flex items-center justify-center font-black text-gray-700 border border-gray-200 text-xl shadow-inner">
                            {{ employee.first_name.charAt(0) }}
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none">Servidor Público</p>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-black text-gray-900 uppercase tracking-tight">{{ employee.first_name }} {{ employee.last_name }}</h2>
                                <button @click="copyToClipboard(`${employee.first_name} ${employee.last_name}`, 'name')" class="text-gray-400 hover:text-blue-600 transition-colors" title="Copiar nombre">
                                    <Check v-if="copiedName" class="h-4 w-4 text-green-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hidden sm:block h-10 w-px bg-gray-200"></div>
                    
                    <div class="hidden sm:block">
                        <p class="text-[10px] font-black text-gray-400 uppercase leading-none mb-1">ID Nómina</p>
                        <div class="flex items-center gap-2">
                            <p class="text-md font-black text-red-900 font-mono tracking-widest">{{ employee.emp_code }}</p>
                            <button @click="copyToClipboard(employee.emp_code, 'nomina')" class="text-gray-400 hover:text-blue-600 transition-colors" title="Copiar nómina">
                                <Check v-if="copiedNomina" class="h-4 w-4 text-green-500" />
                                <Copy v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="hidden md:block h-10 w-px bg-gray-200"></div>

                    <div class="hidden md:block">
                        <p class="text-[10px] font-black text-gray-400 uppercase leading-none mb-1">Periodo</p>
                        <p class="text-md font-black text-gray-800 uppercase tracking-tighter">{{ getSelectedMonthName() }} {{ pdfData.selectedYear }}</p>
                    </div>

                    <div class="hidden lg:block h-10 w-px bg-gray-200"></div>

                    <!-- Horario grande y en negritas sin caja -->
                    <div class="hidden lg:block">
                        <p class="text-[10px] font-black text-gray-400 uppercase leading-none mb-1">Horario Base</p>
                        <p class="text-2xl font-black text-gray-900 tracking-tighter">{{ formatHorario(pdfData.schedule.horario) }}</p>
                    </div>
                </div>

                <!-- BOTÓN DE CIERRE (TACHE EN ROJO) -->
                <button @click="emit('close')" class="ml-4 p-2.5 bg-red-50 hover:bg-red-100 rounded-2xl transition-all group border border-transparent hover:border-red-200">
                    <X class="h-8 w-8 text-red-600 transition-transform group-hover:rotate-90" />
                </button>
            </div>

            <!-- BARRA DE FILTROS (Colores Vibrantes) -->
            <div class="bg-gray-50 border-b border-gray-200 p-3 flex items-center gap-3 overflow-x-auto shadow-sm shrink-0">
                <div class="flex items-center gap-2 text-gray-500 px-2 mr-2">
                    <Filter class="h-4 w-4" />
                    <span class="text-[10px] font-black uppercase tracking-widest">Filtrar:</span>
                </div>
                
                <button @click="setFilter('OK')" :class="statusFilter === 'OK' ? 'ring-4 ring-green-200 scale-105 shadow-xl' : 'opacity-100 hover:scale-105'" class="px-5 py-2 bg-green-500 text-white rounded-xl text-xs font-black uppercase transition-all shadow-md flex items-center gap-2 border border-green-600/20">
                    <CheckCircle2 class="h-4 w-4" /> OK
                    <span class="bg-black/10 px-1.5 py-0.5 rounded-md text-[10px] ml-1">{{ statusCounts.OK }}</span>
                </button>

                <button @click="setFilter('F')" :class="statusFilter === 'F' ? 'ring-4 ring-red-200 scale-105 shadow-xl' : 'opacity-100 hover:scale-105'" class="px-5 py-2 bg-red-500 text-white rounded-xl text-xs font-black uppercase transition-all shadow-md flex items-center gap-2 border border-red-600/20">
                    <X class="h-4 w-4" /> Faltas
                    <span class="bg-black/10 px-1.5 py-0.5 rounded-md text-[10px] ml-1">{{ statusCounts.F }}</span>
                </button>

                <button @click="setFilter('J')" :class="statusFilter === 'J' ? 'ring-4 ring-blue-200 scale-105 shadow-xl' : 'opacity-100 hover:scale-105'" class="px-5 py-2 bg-blue-500 text-white rounded-xl text-xs font-black uppercase transition-all shadow-md flex items-center gap-2 border border-blue-600/20">
                    <Info class="h-4 w-4" /> Justificados
                    <span class="bg-black/10 px-1.5 py-0.5 rounded-md text-[10px] ml-1">{{ statusCounts.J }}</span>
                </button>

                <button @click="setFilter('RG')" :class="statusFilter === 'RG' ? 'ring-4 ring-orange-200 scale-105 shadow-xl' : 'opacity-100 hover:scale-105'" class="px-5 py-2 bg-orange-500 text-white rounded-xl text-xs font-black uppercase transition-all shadow-md flex items-center gap-2 border border-orange-600/20">
                    <AlertTriangle class="h-4 w-4" /> Graves
                    <span class="bg-black/10 px-1.5 py-0.5 rounded-md text-[10px] ml-1">{{ statusCounts.RG }}</span>
                </button>

                <button @click="setFilter('RL')" :class="statusFilter === 'RL' ? 'ring-4 ring-yellow-200 scale-105 shadow-xl' : 'opacity-100 hover:scale-105'" class="px-5 py-2 bg-yellow-400 text-gray-900 rounded-xl text-xs font-black uppercase transition-all shadow-md flex items-center gap-2 border border-yellow-500/20">
                    <Clock class="h-4 w-4" /> Leves
                    <span class="bg-black/10 px-1.5 py-0.5 rounded-md text-[10px] ml-1">{{ statusCounts.RL }}</span>
                </button>

                <button v-if="statusFilter" @click="statusFilter = null" class="ml-auto px-4 py-2 bg-white border border-gray-300 text-gray-500 rounded-xl text-[10px] font-black uppercase hover:bg-gray-100 transition-all flex items-center gap-2 shadow-sm active:scale-95">
                    <RotateCw class="h-3 w-3" /> Limpiar Filtro
                </button>
            </div>

            <!-- CUERPO: Tabla extendida a todo el ancho -->
            <div class="flex-1 overflow-y-auto bg-gray-100 custom-scrollbar shadow-inner relative">
                <table class="min-w-full border-collapse">
                    <thead class="bg-gray-800 text-white sticky top-0 z-20 shadow-md">
                        <tr class="divide-x divide-gray-700">
                            <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest w-20">Día</th>
                            <th class="px-4 py-4 text-center text-[11px] font-black uppercase tracking-widest w-40">Entrada</th>
                            <th class="px-4 py-4 text-center text-[11px] font-black uppercase tracking-widest w-40">Salida</th>
                            <th class="px-4 py-4 text-center text-[11px] font-black uppercase tracking-widest w-32">Calificación</th>
                            <th class="px-6 py-4 text-left text-[11px] font-black uppercase tracking-widest">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50">
                        <tr v-for="reg in filteredRegistros" :key="reg.dia" 
                            :class="getStatusRowClass(reg.calificacion)"
                            class="transition-colors duration-150 divide-x divide-gray-200/50"
                        >
                            <!-- FECHA -->
                            <td class="px-6 py-4 text-sm font-black text-gray-900 whitespace-nowrap text-center bg-black/5">
                                {{ formatDay(reg.dia) }}
                            </td>

                            <!-- ENTRADA -->
                            <td class="px-4 py-4 text-center">
                                <div v-if="formatTime(reg.checkin)" class="font-mono font-black text-sm text-gray-900">
                                    {{ formatTime(reg.checkin) }}
                                </div>
                                <div v-else-if="reg.calificacion !== 'DESC'" class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 px-3 py-1 rounded-lg border border-purple-200 text-[10px] font-black uppercase tracking-tight">
                                    <AlertTriangle class="h-3 w-3" /> Sin hora
                                </div>
                                <div v-else class="text-gray-400 text-xs font-bold italic">--:--</div>
                            </td>

                            <!-- SALIDA -->
                            <td class="px-4 py-4 text-center">
                                <div v-if="formatTime(reg.checkout)" class="font-mono font-black text-sm text-gray-900">
                                    {{ formatTime(reg.checkout) }}
                                </div>
                                <div v-else-if="reg.calificacion !== 'DESC'" class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 px-3 py-1 rounded-lg border border-purple-200 text-[10px] font-black uppercase tracking-tight">
                                    <AlertTriangle class="h-3 w-3" /> Sin hora
                                </div>
                                <div v-else class="text-gray-400 text-xs font-bold italic">--:--</div>
                            </td>

                            <!-- ESTATUS -->
                            <td class="px-4 py-4 text-center">
                                <span :class="getBadgeClass(reg.calificacion)" 
                                      class="inline-block min-w-[50px] px-3 py-1.5 rounded-full text-[11px] font-black tracking-tighter uppercase shadow-sm border border-black/10"
                                >
                                    {{ reg.calificacion }}
                                </span>
                            </td>

                            <!-- OBSERVACIONES -->
                            <td class="px-6 py-4">
                                <div class="text-xs text-gray-700 font-bold italic">
                                    {{ reg.observaciones || '' }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRegistros.length === 0">
                            <td colspan="5" class="py-24 text-center text-gray-400 font-bold uppercase tracking-widest italic bg-white">
                                No se encontraron registros para este filtro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER: Acciones de Impresión y Descarga -->
            <div class="bg-white p-5 border-t border-gray-200 flex justify-between items-center shrink-0 z-30">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-green-500 rounded"></div> OK</div>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-red-500 rounded"></div> F</div>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-orange-500 rounded"></div> RG</div>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-yellow-400 rounded"></div> RL</div>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-blue-500 rounded"></div> J</div>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold"><div class="w-3 h-3 bg-purple-100 border border-purple-300 rounded"></div> SIN HORA</div>
                    </div>
                </div>
                
                <div class="flex gap-4">
                    <button @click="emit('printPdf')" :disabled="generatingPdf" class="px-8 py-3 bg-gray-800 text-white rounded-2xl text-xs font-black uppercase flex items-center gap-3 hover:bg-black transition-all shadow-md active:scale-95 disabled:opacity-50 border border-black">
                        <Loader2 v-if="generatingPdf" class="h-4 w-4 animate-spin" />
                        <Printer v-else class="h-4 w-4" />
                        Imprimir Tarjeta
                    </button>
                    
                    <button @click="emit('generatePdf')" :disabled="generatingPdf" class="px-8 py-3 bg-red-900 text-white rounded-2xl text-xs font-black uppercase flex items-center gap-3 hover:bg-red-800 transition-all shadow-md active:scale-95 group border border-red-950">
                        <Loader2 v-if="generatingPdf" class="h-4 w-4 animate-spin" />
                        <FileDown v-else class="h-4 w-4 group-hover:translate-y-1 transition-transform" />
                        Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
  border: 2px solid #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>