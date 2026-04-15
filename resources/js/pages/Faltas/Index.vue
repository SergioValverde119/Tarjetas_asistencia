<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3'; // 1. Agregado 'Link'
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    FileDown, Filter, Loader2, AlertCircle, Search, RotateCw, CheckCircle, Users, Calendar, ShieldAlert // 2. Agregado 'ShieldAlert'
} from 'lucide-vue-next';
import { debounce } from 'lodash';
import { index as listExclusions } from "@/routes/exclusion"; // 3. Importación de Wayfinder

const props = defineProps({
    faltas: { type: Array, default: () => [] },
    empleados: { type: Array, default: () => [] },
    filters: Object
});

// --- ESTADO DE FILTROS ---
const dateIncidence = ref(props.filters?.date_incidence || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

// --- LÓGICA DE BÚSQUEDA DINÁMICA ---
const isGeneral = ref(!props.filters?.emp_id);
const selectedEmp = ref(props.filters?.emp_id || '');
const searchInput = ref(''); 
const showDropdown = ref(false);

// Restaurar nombre en el buscador si ya hay alguien seleccionado
if (selectedEmp.value) {
    const emp = props.empleados.find(e => e.id == selectedEmp.value);
    if (emp) {
        searchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
    }
}

watch(isGeneral, (val) => {
    if (val) {
        selectedEmp.value = '';
        searchInput.value = '';
        showDropdown.value = false;
    }
});

const filteredEmployees = computed(() => {
    if (!searchInput.value) return props.empleados;
    const term = searchInput.value.toLowerCase();
    return props.empleados.filter(emp => {
        const fullName = `${emp.first_name} ${emp.last_name}`.toLowerCase();
        const empCode = String(emp.emp_code).toLowerCase();
        return fullName.includes(term) || empCode.includes(term);
    });
});

const selectEmployee = (emp) => {
    selectedEmp.value = emp.id;
    searchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
    showDropdown.value = false;
    isGeneral.value = false; 
};

const closeDropdown = () => { setTimeout(() => showDropdown.value = false, 200); };

// --- LIMPIEZA MUTUA DE FECHAS ---
const onIncidenceChange = () => {
    if (dateIncidence.value) {
        startDate.value = '';
        endDate.value = '';
    }
};

const onRangeChange = () => {
    if (startDate.value || endDate.value) {
        dateIncidence.value = '';
    }
};

// --- DETECCIÓN DE CAMBIOS (PULSO SUAVE) ---
const isDirty = computed(() => {
    const pStart = String(props.filters?.start_date || '');
    const pEnd = String(props.filters?.end_date || '');
    const pInc = String(props.filters?.date_incidence || '');
    const pEmp = String(props.filters?.emp_id || '');

    const currentEmp = isGeneral.value ? '' : String(selectedEmp.value || '');

    return String(startDate.value || '') !== pStart ||
            String(endDate.value || '') !== pEnd ||
            String(dateIncidence.value || '') !== pInc ||
            currentEmp !== pEmp;
});

const loading = ref(false);

const buscar = () => {
    loading.value = true;
    router.get(window.location.pathname, {
        date_incidence: dateIncidence.value,
        start_date: startDate.value,
        end_date: endDate.value,
        emp_id: isGeneral.value ? '' : selectedEmp.value, 
    }, {
        preserveState: true,
        onFinish: () => loading.value = false
    });
};

const limpiarFiltros = () => {
    dateIncidence.value = '';
    startDate.value = '';
    endDate.value = '';
    isGeneral.value = true;
    selectedEmp.value = '';
    searchInput.value = '';
    buscar();
};

const descargarExcel = () => {
    const params = new URLSearchParams({
        date_incidence: dateIncidence.value,
        start_date: startDate.value,
        end_date: endDate.value,
        emp_id: isGeneral.value ? '' : selectedEmp.value
    });
    window.location.href = `${window.location.pathname}/exportar?${params.toString()}`;
};

// --- SUGERENCIAS RÁPIDAS ---
const setRange = (type) => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');

    dateIncidence.value = '';
    startDate.value = '';
    endDate.value = '';

    if (type === 'hoy') {
        dateIncidence.value = `${yyyy}-${mm}-${dd}`;
    } else if (type === 'semana') {
        const lastWeek = new Date(today);
        lastWeek.setDate(today.getDate() - 7);
        startDate.value = lastWeek.toISOString().split('T')[0];
        endDate.value = `${yyyy}-${mm}-${dd}`;
    } else if (type === 'quincena') {
        if (today.getDate() <= 15) {
            startDate.value = `${yyyy}-${mm}-01`;
            endDate.value = `${yyyy}-${mm}-15`;
        } else {
            const lastDay = new Date(yyyy, today.getMonth() + 1, 0).getDate();
            startDate.value = `${yyyy}-${mm}-16`;
            endDate.value = `${yyyy}-${mm}-${lastDay}`;
        }
    } else if (type === 'mensual') {
        const lastDay = new Date(yyyy, today.getMonth() + 1, 0).getDate();
        startDate.value = `${yyyy}-${mm}-01`;
        endDate.value = `${yyyy}-${mm}-${lastDay}`;
    }
    buscar();
};
</script>

<template>
    <Head title="Monitor de Faltas" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-4 sm:p-6 bg-gray-50 h-screen max-h-screen w-full flex flex-col overflow-hidden">
                <div class="w-full max-w-full flex-grow flex flex-col gap-4 h-full">

                    <!-- BARRA DE FILTROS AHORA ES LO PRIMERO EN PANTALLA -->
                    <div class="bg-white p-4 md:p-5 rounded-xl shadow-sm border border-gray-200 w-full shrink-0 z-20 relative flex flex-col gap-4 md:gap-5">
                        
                        <!-- FILA 1: EMPLEADO + BOTONES -->
                        <div class="flex flex-col lg:flex-row gap-4 items-end">
                            
                            <!-- Búsqueda Empleado -->
                            <div class="flex-grow w-full flex flex-col relative">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Servidor Público</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer group">
                                        <div class="relative flex items-center justify-center w-4 h-4">
                                            <input type="checkbox" v-model="isGeneral" class="peer appearance-none w-4 h-4 border-2 border-gray-300 rounded hover:border-red-500 checked:bg-red-600 checked:border-red-600 transition-colors cursor-pointer" />
                                            <CheckCircle class="absolute w-3 h-3 text-white pointer-events-none opacity-0 peer-checked:opacity-100 transition-opacity" />
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-600 uppercase group-hover:text-red-600 transition-colors">General (Todos)</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Users v-if="isGeneral" class="h-4 w-4 text-gray-400" />
                                        <Search v-else class="h-4 w-4 text-red-500" />
                                    </div>
                                    <input 
                                        type="text"
                                        v-model="searchInput"
                                        @focus="!isGeneral ? showDropdown = true : null"
                                        @blur="closeDropdown"
                                        :disabled="isGeneral"
                                        class="pl-10 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm h-12 transition-all"
                                        :class="[
                                            isGeneral ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-emerald-50/30 focus:bg-white',
                                            showDropdown && filteredEmployees.length > 0 ? 'rounded-b-none border-b-0' : ''
                                        ]"
                                        :placeholder="isGeneral ? 'Consultando a todo el personal' : 'Escriba nombre o nómina...'"
                                        autocomplete="off"
                                    />
                                    <div v-if="!isGeneral && selectedEmp" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <CheckCircle class="h-5 w-5 text-green-500" />
                                    </div>
                                </div>
                                <div v-if="!isGeneral && showDropdown && filteredEmployees.length > 0" class="absolute top-[68px] z-50 w-full bg-white border border-gray-200 border-t-0 rounded-b-xl shadow-xl max-h-60 overflow-y-auto custom-scrollbar">
                                    <ul>
                                        <li 
                                            v-for="emp in filteredEmployees" 
                                            :key="emp.id" 
                                            @mousedown.prevent="selectEmployee(emp)"
                                            class="px-4 py-3 hover:bg-emerald-50 cursor-pointer transition-colors border-b border-gray-50 last:border-0 flex flex-col"
                                        >
                                            <span class="font-bold text-gray-900 text-sm leading-tight">{{ emp.first_name }} {{ emp.last_name }}</span>
                                            <span class="text-[10px] text-gray-500 font-mono tracking-widest mt-0.5">ID: {{ emp.emp_code }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="flex items-center gap-3 w-full lg:w-auto h-12">
                                <button 
                                    @click="buscar" 
                                    :disabled="loading" 
                                    class="flex-1 lg:flex-none px-6 sm:px-8 h-full bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center justify-center gap-2 shadow-sm disabled:opacity-50"
                                    :class="{'btn-pulse': isDirty}"
                                >
                                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                                    <Search v-else class="h-4 w-4" />
                                    Consultar
                                </button>

                                <button 
                                    v-if="faltas.length > 0"
                                    @click="descargarExcel"
                                    class="flex-1 lg:flex-none px-4 sm:px-6 h-full bg-green-700 hover:bg-green-800 text-white rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center justify-center gap-2 shadow-sm"
                                >
                                    <FileDown class="h-4 w-4" />
                                    Excel
                                </button>

                                <!-- BOTÓN AGREGADO: GESTIÓN DE EXCLUSIONES -->
                                <Link 
                                    :href="listExclusions.url()"
                                    class="flex-none px-4 h-full bg-red-50 border border-red-100 text-red-600 hover:bg-red-600 hover:text-white rounded-xl transition-all flex items-center justify-center shadow-sm"
                                    title="Gestionar Nóminas Excluidas"
                                >
                                    <ShieldAlert class="h-4 w-4" />
                                </Link>

                                <!-- Botón de Reiniciar Filtros -->
                                <button 
                                    @click="limpiarFiltros" 
                                    class="flex-none px-4 h-full bg-gray-50 border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-200 rounded-xl transition-colors flex items-center justify-center shadow-sm"
                                    title="Reiniciar Filtros"
                                >
                                    <RotateCw class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- FILA 2: FECHAS Y RANGOS -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 md:pt-5 border-t border-gray-100">
                            <!-- Día Único (Azul) -->
                            <div>
                                <label class="block text-[10px] font-black text-blue-600 uppercase mb-1.5 tracking-widest">Día Único</label>
                                <input v-model="dateIncidence" @input="onIncidenceChange" type="date" class="w-full rounded-lg border-blue-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-blue-50/30" />
                            </div>

                            <!-- Rango Desde (Naranja) -->
                            <div>
                                <label class="block text-[10px] font-black text-orange-600 uppercase mb-1.5 tracking-widest">Rango (Desde)</label>
                                <input v-model="startDate" @input="onRangeChange" type="date" class="w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
                            </div>

                            <!-- Rango Hasta (Naranja) -->
                            <div>
                                <label class="block text-[10px] font-black text-orange-600 uppercase mb-1.5 tracking-widest">Rango (Hasta)</label>
                                <input v-model="endDate" @input="onRangeChange" type="date" class="w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
                            </div>
                        </div>

                        <!-- FILA 3: ATAJOS -->
                        <div class="flex flex-wrap items-center gap-3 text-xs font-bold uppercase text-gray-500 mt-1">
                            <span class="flex items-center gap-1 mr-2"><Calendar class="h-4 w-4"/> Filtros Rápidos:</span>
                            <button @click="setRange('hoy')" class="px-5 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-xl transition-colors border border-blue-200 shadow-sm active:scale-95">Hoy</button>
                            <button @click="setRange('semana')" class="px-5 py-2 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-xl transition-colors border border-orange-200 shadow-sm active:scale-95">Esta Semana</button>
                            <button @click="setRange('quincena')" class="px-5 py-2 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-xl transition-colors border border-orange-200 shadow-sm active:scale-95">Quincena</button>
                            <button @click="setRange('mensual')" class="px-5 py-2 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-xl transition-colors border border-orange-200 shadow-sm active:scale-95">Mensual</button>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-200 flex flex-col flex-1 min-h-0 relative">
                        
                        <!-- MENSAJE DE CARGA MEJORADO -->
                        <div v-if="loading" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center text-gray-600">
                            <Loader2 class="h-12 w-12 animate-spin mb-4 text-emerald-500" />
                            <p class="font-black uppercase tracking-widest text-sm text-gray-800">Procesando Asistencias...</p>
                            <p class="text-xs mt-2 text-center max-w-sm font-medium">
                                Si es una consulta general de todo el mes, el servidor está calculando miles de registros. 
                                <span class="font-bold text-red-600">Por favor, espere.</span>
                            </p>
                        </div>
                        
                        <div v-else-if="!faltas || faltas.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <Filter class="h-12 w-12 mb-3 opacity-20" />
                            <p class="font-bold text-sm">Sin faltas detectadas en este periodo.</p>
                            <p class="text-xs mt-1 italic">Asegúrese de consultar una fecha válida.</p>
                        </div>

                        <div v-else class="overflow-auto flex-1 custom-scrollbar">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 w-24 border-b border-gray-200">Nómina</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 border-b border-gray-200">Servidor Público</th>
                                        <th class="px-6 py-3 text-center font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 w-32 border-b border-gray-200">Día de Falta</th>
                                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 w-28 border-b border-gray-200">Entrada</th>
                                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 w-28 border-b border-gray-200">Salida</th>
                                        <th class="px-6 py-3 text-center font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 w-32 border-b border-gray-200">Estatus</th>
                                        <th class="px-6 py-3 text-left font-bold text-gray-600 uppercase tracking-wider text-[11px] bg-gray-100 border-b border-gray-200">Horario Base</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="(falta, idx) in faltas" :key="idx" class="hover:bg-red-50/40 transition-colors group">
                                        <td class="px-6 py-3 font-mono font-bold text-red-900 text-xs">{{ falta.nomina }}</td>
                                        <td class="px-6 py-3 font-bold text-gray-900 uppercase group-hover:text-red-900 transition-colors text-xs">
                                            {{ falta.nombre }}
                                        </td>
                                        <td class="px-6 py-3 font-medium text-gray-600 text-center text-xs">
                                            {{ falta.fecha }}
                                        </td>
                                        
                                        <td class="px-4 py-3 text-center">
                                            <div class="font-mono font-black text-xs text-gray-700">
                                                {{ falta.checkin || '--:--' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="font-mono font-black text-xs text-gray-700">
                                                {{ falta.checkout || '--:--' }}
                                            </div>
                                        </td>

                                        <td class="px-6 py-3 text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                                                FALTA
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-xs font-bold text-gray-400 italic">
                                            {{ falta.horario || 'Sin horario asignado' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumen del pie -->
                        <div v-if="faltas.length > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-between items-center shrink-0">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">
                                Total de faltas encontradas: <span class="text-red-600 font-black">{{ faltas.length }}</span>
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ANIMACIÓN PARA PULSO SUAVE */
@keyframes pulse-emerald {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.btn-pulse {
    animation: pulse-emerald 2s infinite;
}
</style>