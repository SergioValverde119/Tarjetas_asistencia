<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    FileDown, Filter, Loader2, Search, RotateCw, Users, ShieldAlert, ChevronDown, X, Building2, Tag, Calendar, CheckCircle, Clock
} from 'lucide-vue-next';

// --- INTERFACES DE TYPESCRIPT ---
interface Empleado {
    id: number | string;
    emp_code: string;
    first_name: string;
    last_name: string;
    area_name?: string;
}

interface Departamento {
    id: number | string;
    dept_name: string;
}

interface Nomina {
    id: number | string;
    area_name: string;
    area_code?: string;
}

interface Falta {
    nomina: string;
    nombre: string;
    departamento: string;
    area: string;
    fecha: string;
    checkin?: string;
    checkout?: string;
    horario: string;
}

// --- FUNCIÓN DE NORMALIZACIÓN ---
const normalizeStr = (str: string) => 
    str ? str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : "";

// --- IMPORTACIÓN DE WAYFINDER ---
import { index as listExclusions } from "@/routes/exclusion";

const props = defineProps<{
    faltas: Falta[];
    empleados: Empleado[];
    departamentos: Departamento[];
    nominas: Nomina[];
    filters: any;
}>();

// --- ESTADO DE FILTROS ---
const mode = ref(props.filters?.date_incidence ? 'single' : 'range');
const dateIncidence = ref(props.filters?.date_incidence || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const selectedDept = ref(props.filters?.department_id || '');
const selectedAreas = ref<string[]>(Array.isArray(props.filters?.area_id) ? props.filters.area_id : (props.filters?.area_id ? [String(props.filters.area_id)] : []));

// --- LÓGICA DE BÚSQUEDA DINÁMICA DE EMPLEADO ---
const isGeneral = ref(!props.filters?.emp_id);
const selectedEmp = ref(props.filters?.emp_id || '');
const searchInput = ref(''); 
const showDropdown = ref(false);
const showAreaDropdown = ref(false);
const areaContainer = ref<HTMLElement | null>(null);

if (selectedEmp.value) {
    const emp = props.empleados.find((e: Empleado) => String(e.id) === String(selectedEmp.value));
    if (emp) searchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
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
    const term = normalizeStr(searchInput.value);
    return props.empleados.filter((emp: Empleado) => {
        const fullName = normalizeStr(`${emp.first_name} ${emp.last_name}`);
        const empCode = normalizeStr(String(emp.emp_code));
        return fullName.includes(term) || empCode.includes(term);
    });
});

const selectEmployee = (emp: Empleado) => {
    selectedEmp.value = emp.id;
    searchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
    showDropdown.value = false;
    isGeneral.value = false; 
};

const closeDropdown = () => {
    setTimeout(() => { showDropdown.value = false; }, 200);
};

// --- LÓGICA DE BÚSQUEDA DINÁMICA DE DEPARTAMENTO ---
const deptSearchInput = ref('');
const showDeptDropdown = ref(false);

if (selectedDept.value) {
    const dept = props.departamentos.find((d: Departamento) => String(d.id) === String(selectedDept.value));
    if (dept) deptSearchInput.value = dept.dept_name;
}

const filteredDepts = computed(() => {
    if (!deptSearchInput.value) return props.departamentos;
    const term = normalizeStr(deptSearchInput.value);
    return props.departamentos.filter((d: Departamento) => 
        normalizeStr(d.dept_name).includes(term)
    );
});

const selectDept = (dept: Departamento) => {
    selectedDept.value = dept.id;
    deptSearchInput.value = dept.dept_name;
    showDeptDropdown.value = false;
};

// Función para limpiar el departamento (Deseleccionar)
const clearDept = () => {
    selectedDept.value = '';
    deptSearchInput.value = '';
};

// Si el usuario borra el texto manualmente, deseleccionamos el ID
watch(deptSearchInput, (newVal) => {
    if (!newVal) selectedDept.value = '';
});

const closeDeptDropdown = () => {
    setTimeout(() => { showDeptDropdown.value = false; }, 200);
};

// --- LÓGICA DE SELECCIÓN MÚLTIPLE DE NÓMINAS (ÁREAS) ---
const toggleArea = (id: number | string) => {
    const index = selectedAreas.value.indexOf(String(id));
    if (index > -1) {
        selectedAreas.value.splice(index, 1);
    } else {
        selectedAreas.value.push(String(id));
    }
};

const getAreaName = (id: number | string) => props.nominas.find((n: Nomina) => String(n.id) === String(id))?.area_name || id;

// --- GESTIÓN DE CIERRE AUTOMÁTICO (CLICK OUTSIDE) ---
const handleClickOutside = (event: MouseEvent) => {
    if (areaContainer.value && !areaContainer.value.contains(event.target as Node)) {
        showAreaDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// --- GESTIÓN DE MODOS DE FECHA ---
watch(mode, (newMode) => {
    if (newMode === 'single') {
        startDate.value = '';
        endDate.value = '';
    } else {
        dateIncidence.value = '';
    }
});

// --- DETECCIÓN DE CAMBIOS ---
const isDirty = computed(() => {
    return String(startDate.value || '') !== String(props.filters?.start_date || '') ||
           String(endDate.value || '') !== String(props.filters?.end_date || '') ||
           String(dateIncidence.value || '') !== String(props.filters?.date_incidence || '') ||
           String(selectedDept.value || '') !== String(props.filters?.department_id || '') ||
           JSON.stringify(selectedAreas.value) !== JSON.stringify(props.filters?.area_id || []) ||
           (isGeneral.value ? '' : String(selectedEmp.value || '')) !== String(props.filters?.emp_id || '');
});

const loading = ref(false);

const buscar = () => {
    loading.value = true;
    router.get(window.location.pathname, {
        date_incidence: dateIncidence.value,
        start_date: startDate.value,
        end_date: endDate.value,
        emp_id: isGeneral.value ? '' : selectedEmp.value,
        department_id: selectedDept.value,
        area_id: selectedAreas.value, 
    }, {
        preserveState: true,
        onFinish: () => {
            loading.value = false;
            showAreaDropdown.value = false;
        }
    });
};

const limpiarFiltros = () => {
    dateIncidence.value = ''; startDate.value = ''; endDate.value = '';
    isGeneral.value = true; selectedEmp.value = ''; searchInput.value = '';
    selectedDept.value = ''; deptSearchInput.value = ''; selectedAreas.value = [];
    buscar();
};

const descargarExcel = () => {
    const params = new URLSearchParams({
        date_incidence: dateIncidence.value,
        start_date: startDate.value,
        end_date: endDate.value,
        emp_id: isGeneral.value ? '' : selectedEmp.value,
        department_id: selectedDept.value,
    });
    selectedAreas.value.forEach(id => params.append('area_id[]', id));
    window.location.href = `${window.location.pathname}/exportar?${params.toString()}`;
};

// --- SUGERENCIAS RÁPIDAS ---
const setRange = (type: string) => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');

    if (type === 'hoy') {
        mode.value = 'single';
        dateIncidence.value = `${yyyy}-${mm}-${dd}`;
    } else if (type === 'quincena') {
        mode.value = 'range';
        if (today.getDate() <= 15) {
            startDate.value = `${yyyy}-${mm}-01`;
            endDate.value = `${yyyy}-${mm}-15`;
        } else {
            const lastDay = new Date(yyyy, today.getMonth() + 1, 0).getDate();
            startDate.value = `${yyyy}-${mm}-16`;
            endDate.value = `${yyyy}-${mm}-${lastDay}`;
        }
    } else if (type === 'mensual') {
        mode.value = 'range';
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
            <div class="p-4 sm:p-6 bg-gray-50 h-screen max-h-screen w-full flex flex-col overflow-hidden text-slate-900">
                <div class="w-full max-w-full flex-grow flex flex-col gap-4 h-full">

                    <!-- SECCIÓN DE FILTROS -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 w-full shrink-0 z-20 relative flex flex-col gap-5">
                        
                        <!-- FILA 1: BUSCADOR DE EMPLEADO Y ACCIONES -->
                        <div class="flex flex-col lg:flex-row gap-4 items-end">
                            <div class="flex-grow w-full flex flex-col relative">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Servidor Público</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer group">
                                        <input type="checkbox" v-model="isGeneral" class="w-4 h-4 border-2 border-gray-300 rounded checked:bg-red-600 transition-colors cursor-pointer" />
                                        <span class="text-[10px] font-bold text-gray-600 uppercase group-hover:text-red-600">General (Todos)</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Users v-if="isGeneral" class="h-4 w-4 text-gray-400" />
                                        <Search v-else class="h-4 w-4 text-red-500" />
                                    </div>
                                    <input type="text" v-model="searchInput" 
                                        @focus="!isGeneral ? showDropdown = true : null" 
                                        @blur="closeDropdown"
                                        :disabled="isGeneral"
                                        class="pl-10 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm h-12 transition-all font-bold"
                                        :class="isGeneral ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-emerald-50/20'"
                                        :placeholder="isGeneral ? 'Consultando a todo el personal' : 'Escriba nombre o nómina...'" autocomplete="off" />
                                    
                                    <div v-if="!isGeneral && showDropdown && filteredEmployees.length > 0" class="absolute top-[52px] z-50 w-full bg-white border border-gray-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                                        <ul class="py-1">
                                            <li v-for="emp in filteredEmployees" :key="emp.id" @mousedown.prevent="selectEmployee(emp)" class="px-4 py-3 hover:bg-emerald-50 cursor-pointer border-b border-gray-50 last:border-0 flex flex-col transition-colors">
                                                <span class="font-bold text-gray-900 text-sm uppercase">{{ emp.first_name }} {{ emp.last_name }}</span>
                                                <span class="text-[10px] text-gray-500 font-mono tracking-widest">ID: {{ emp.emp_code }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 w-full lg:w-auto">
                                <button @click="buscar" :disabled="loading" class="flex-1 lg:flex-none px-8 h-12 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-black uppercase tracking-widest text-xs transition-all shadow-sm flex items-center justify-center gap-2" :class="{'btn-pulse': isDirty}">
                                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                                    <Search v-else class="h-4 w-4" /> Consultar
                                </button>
                                <button v-if="faltas.length > 0" @click="descargarExcel" class="px-6 h-12 bg-green-700 hover:bg-green-800 text-white rounded-xl font-black uppercase tracking-widest text-xs transition-all shadow-sm flex items-center gap-2">
                                    <FileDown class="h-4 w-4" /> Excel
                                </button>
                                <Link :href="listExclusions.url()" class="px-4 h-12 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl transition-all flex items-center shadow-sm" title="Lista Negra">
                                    <ShieldAlert class="h-4 w-4" />
                                </Link>
                                <button @click="limpiarFiltros" class="px-4 h-12 bg-gray-50 text-gray-500 hover:bg-red-50 hover:text-red-600 rounded-xl transition-colors border shadow-sm">
                                    <RotateCw class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- FILA 2: DEPARTAMENTO, NÓMINAS Y SWITCH DE MODO -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-gray-100">
                            
                            <!-- Filtro Departamento con botón de LIMPIEZA (X) -->
                            <div class="flex flex-col relative">
                                <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1 flex items-center gap-1">
                                    <Building2 class="h-3 w-3" /> Departamento
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="deptSearchInput" 
                                        @focus="showDeptDropdown = true" 
                                        @blur="closeDeptDropdown"
                                        class="w-full h-10 pl-4 pr-16 rounded-xl border border-gray-200 bg-blue-50/20 text-xs font-bold focus:ring-emerald-500 transition-all" 
                                        placeholder="BUSCAR DEPARTAMENTO..."
                                    />
                                    
                                    <!-- Botón X para deseleccionar -->
                                    <button v-if="selectedDept" @click="clearDept" class="absolute inset-y-0 right-8 px-2 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                        <X class="h-4 w-4" />
                                    </button>

                                    <div v-if="selectedDept" class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                        <CheckCircle class="h-4 w-4 text-emerald-500" />
                                    </div>
                                    <div v-if="showDeptDropdown && filteredDepts.length > 0" class="absolute top-[44px] z-[70] w-full bg-white border border-gray-200 rounded-xl shadow-2xl max-h-48 overflow-y-auto custom-scrollbar">
                                        <div v-for="d in filteredDepts" :key="d.id" @mousedown.prevent="selectDept(d)" class="px-4 py-2.5 hover:bg-emerald-50 cursor-pointer text-[10px] font-black uppercase text-gray-700 border-b border-gray-50 last:border-0 transition-colors">
                                            {{ d.dept_name }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Multi-Filtro Nómina (Áreas) con Ref para ClickOutside -->
                            <div class="flex flex-col relative" ref="areaContainer">
                                <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1 flex items-center gap-1">
                                    <Tag class="h-3 w-3" /> Tipos de Nómina
                                </label>
                                <button @click="showAreaDropdown = !showAreaDropdown" class="w-full h-10 px-4 rounded-xl border border-gray-200 bg-blue-50/20 text-left text-xs font-bold flex items-center justify-between overflow-hidden shadow-sm transition-all hover:bg-blue-50/40">
                                    <span v-if="selectedAreas.length === 0" class="text-gray-400">TODAS LAS NÓMINAS</span>
                                    <span v-else class="truncate text-emerald-700 uppercase">{{ selectedAreas.length }} SELECCIONADAS</span>
                                    <ChevronDown class="h-4 w-4 text-gray-400" />
                                </button>
                                
                                <div v-if="showAreaDropdown" class="absolute top-[44px] z-[60] w-full bg-white border border-gray-200 rounded-xl shadow-2xl p-4 max-h-72 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="flex flex-col gap-2">
                                        <label v-for="area in nominas" :key="area.id" class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer transition-colors group">
                                            <input type="checkbox" :value="area.id" :checked="selectedAreas.includes(String(area.id))" @change="toggleArea(area.id)"
                                                class="w-4 h-4 border-2 border-gray-300 rounded checked:bg-emerald-600 transition-all cursor-pointer" />
                                            <span class="text-[11px] font-black text-gray-700 uppercase group-hover:text-emerald-600 transition-colors">{{ area.area_name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- SWITCH DE MODO DE FECHA -->
                            <div class="flex flex-col">
                                <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Modalidad de Tiempo</label>
                                <div class="flex p-1 bg-slate-100 rounded-xl border border-gray-200 shadow-inner h-10">
                                    <button @click="mode = 'single'" class="flex-1 text-[9px] font-black uppercase rounded-lg transition-all flex items-center justify-center gap-1.5"
                                        :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'">
                                        <Calendar class="h-3 w-3" /> Día Único
                                    </button>
                                    <button @click="mode = 'range'" class="flex-1 text-[9px] font-black uppercase rounded-lg transition-all flex items-center justify-center gap-1.5"
                                        :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-500'">
                                        <Clock class="h-3 w-3" /> Rango
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- FILA 3: SUGERENCIAS + INPUTS DE FECHA -->
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-4 border-t border-gray-100">
                            
                            <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase text-gray-500 tracking-wider">
                                <Calendar class="h-3 w-3 text-slate-400" /> Sugerencias:
                                <button @click="setRange('hoy')" class="px-4 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg border border-blue-200 transition-all active:scale-95 shadow-sm">Hoy</button>
                                <button @click="setRange('quincena')" class="px-4 py-1.5 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-lg border border-orange-200 transition-all active:scale-95 shadow-sm">Quincena Actual</button>
                                <button @click="setRange('mensual')" class="px-4 py-1.5 bg-slate-50 text-slate-700 hover:bg-slate-200 rounded-lg border border-slate-200 transition-all active:scale-95 shadow-sm font-black">Mes Completo</button>
                            </div>

                            <div class="flex items-center justify-end min-w-[320px]">
                                <div v-if="mode === 'single'" class="animate-in fade-in slide-in-from-right-2 duration-300">
                                    <input v-model="dateIncidence" type="date" 
                                        class="w-56 rounded-xl border-blue-100 shadow-sm text-sm h-11 bg-blue-50/30 font-bold focus:ring-blue-500 transition-all px-4" />
                                </div>
                                <div v-else class="flex items-center gap-3 animate-in fade-in slide-in-from-right-2 duration-300">
                                    <div class="flex items-center gap-2">
                                        <input v-model="startDate" type="date" 
                                            class="w-44 rounded-xl border-orange-100 shadow-sm text-xs h-11 bg-orange-50/30 font-bold focus:ring-orange-500 transition-all px-3" />
                                        <span class="text-slate-300 font-bold">-</span>
                                        <input v-model="endDate" type="date" 
                                            class="w-44 rounded-xl border-orange-100 shadow-sm text-xs h-11 bg-orange-50/30 font-bold focus:ring-orange-500 transition-all px-3" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAGS (Solo si hay selección) -->
                        <div v-if="selectedAreas.length > 0" class="flex flex-wrap gap-2">
                            <span v-for="id in selectedAreas" :key="id" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase rounded-full border border-emerald-200 shadow-sm">
                                {{ getAreaName(id) }}
                                <button @click="toggleArea(id)" class="hover:text-red-600 transition-colors"><X class="h-3 w-3" /></button>
                            </span>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS -->
                    <div class="bg-white shadow-sm rounded-2xl border border-gray-200 flex flex-col flex-1 min-h-0 relative overflow-hidden">
                        <div v-if="loading" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center">
                            <Loader2 class="h-12 w-12 animate-spin mb-4 text-emerald-500" />
                            <p class="font-black uppercase tracking-widest text-sm text-gray-800">Analizando checadas físicas...</p>
                        </div>
                        
                        <div v-else-if="!faltas || faltas.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <Filter class="h-12 w-12 mb-3 opacity-20" />
                            <p class="font-bold text-sm uppercase tracking-tighter">Sin faltas detectadas en este periodo.</p>
                            <p class="text-[10px] mt-1 font-medium">Ajuste los filtros de personal o fechas.</p>
                        </div>

                        <div v-else class="overflow-auto flex-1 custom-scrollbar">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0 z-10 border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left font-black text-gray-500 uppercase text-[10px] tracking-widest">Nómina</th>
                                        <th class="px-6 py-4 text-left font-black text-gray-500 uppercase text-[10px] tracking-widest">Servidor Público</th>
                                        <th class="px-6 py-4 text-left font-black text-gray-500 uppercase text-[10px] tracking-widest">Departamento</th>
                                        <th class="px-6 py-4 text-left font-black text-gray-500 uppercase text-[10px] tracking-widest">Área / Nómina</th>
                                        <th class="px-6 py-4 text-center font-black text-gray-500 uppercase text-[10px] tracking-widest">Fecha</th>
                                        <th class="px-4 py-4 text-center font-black text-gray-500 uppercase text-[10px] tracking-widest">Entrada</th>
                                        <th class="px-4 py-4 text-center font-black text-gray-500 uppercase text-[10px] tracking-widest">Salida</th>
                                        <th class="px-6 py-4 text-center font-black text-gray-500 uppercase text-[10px] tracking-widest">Horario</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(falta, idx) in (faltas as Falta[])" :key="idx" class="hover:bg-red-50/40 transition-colors group">
                                        <td class="px-6 py-4 font-mono font-black text-red-600">{{ falta.nomina }}</td>
                                        <td class="px-6 py-4 font-black text-gray-900 uppercase text-[11px] leading-tight group-hover:text-red-700 transition-colors">{{ falta.nombre }}</td>
                                        <td class="px-6 py-4 font-bold text-gray-500 uppercase text-[10px]">{{ falta.departamento }}</td>
                                        <td class="px-6 py-4 font-bold text-gray-500 uppercase text-[10px]">{{ falta.area }}</td>
                                        <td class="px-6 py-4 text-center font-bold text-gray-600 text-xs">{{ falta.fecha }}</td>
                                        <td class="px-4 py-4 text-center font-mono font-black text-xs text-slate-400 italic">{{ falta.checkin || '--:--' }}</td>
                                        <td class="px-4 py-4 text-center font-mono font-black text-xs text-slate-400 italic">{{ falta.checkout || '--:--' }}</td>
                                        <td class="px-6 py-4 text-[10px] font-bold text-gray-400 italic tracking-tighter">{{ falta.horario }}</td>
                                    </tr>
                                </tbody>
                            </table>
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

@keyframes pulse-emerald {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.btn-pulse { animation: pulse-emerald 2s infinite; }

@keyframes fade-in {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in { animation: fade-in 0.3s ease-out forwards; }
</style>