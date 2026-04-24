<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

// Iconografía técnica (Solo las utilizadas para evitar errores de linter)
import { 
    FileDown, Filter, Loader2, Search, RotateCw, Users, ShieldAlert, 
    ChevronDown, X, Building2, Tag, CheckCircle, Clock, 
    ChevronUp 
} from 'lucide-vue-next';

/** * --- IMPORTACIÓN DE RUTAS (WAYFINDER) ---
 * Solución al error 2339: Se debe llamar a la función de la ruta para obtener el objeto.
 */
import { index as exclusionsIndex } from "@/routes/exclusion";
const getExclusionsRoute = exclusionsIndex;

/** * --- DEFINICIÓN DE TIPOS E INTERFACES --- 
 * Garantizamos la integridad de los datos procesados.
 */
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

const props = defineProps<{
    faltas: Falta[];
    empleados: Empleado[];
    departamentos: Departamento[];
    nominas: Nomina[];
    filters: any;
}>();

// Utilidad para normalizar búsquedas (sin acentos, minúsculas)
const normalizeStr = (str: string) => 
    str ? str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : "";

/** * --- ESTADO REACTIVO DE FILTROS --- 
 */
const mode = ref(props.filters?.date_incidence ? 'single' : 'range');
const dateIncidence = ref(props.filters?.date_incidence || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const selectedDept = ref(props.filters?.department_id || '');
const selectedAreas = ref<string[]>(Array.isArray(props.filters?.area_id) ? props.filters.area_id : (props.filters?.area_id ? [String(props.filters.area_id)] : []));

// UI y Estados de Carga
const isTagsVisible = ref(false); 
const loading = ref(false);

/** * --- LÓGICA DE BÚSQUEDA DE EMPLEADO --- 
 */
const isGeneral = ref(!props.filters?.emp_id);
const selectedEmp = ref(props.filters?.emp_id || '');
const searchInput = ref(''); 
const showDropdown = ref(false);

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
        const empCode = String(emp.emp_code);
        return fullName.includes(term) || empCode.includes(term);
    });
});

const selectEmployee = (emp: Empleado) => {
    selectedEmp.value = emp.id;
    searchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
    showDropdown.value = false;
    isGeneral.value = false; 
};

const closeDropdown = () => setTimeout(() => { showDropdown.value = false; }, 200);

/** * --- LÓGICA DE DEPARTAMENTO --- 
 */
const deptSearchInput = ref('');
const showDeptDropdown = ref(false);

if (selectedDept.value) {
    const dept = props.departamentos.find((d: Departamento) => String(d.id) === String(selectedDept.value));
    if (dept) deptSearchInput.value = dept.dept_name;
}

const filteredDepts = computed(() => {
    if (!deptSearchInput.value) return props.departamentos;
    const term = normalizeStr(deptSearchInput.value);
    return props.departamentos.filter((d: Departamento) => normalizeStr(d.dept_name).includes(term));
});

const selectDept = (dept: Departamento) => {
    selectedDept.value = dept.id;
    deptSearchInput.value = dept.dept_name;
    showDeptDropdown.value = false;
};

const clearDept = () => {
    selectedDept.value = '';
    deptSearchInput.value = '';
};

watch(deptSearchInput, (newVal) => { if (!newVal) selectedDept.value = ''; });

const closeDeptDropdown = () => setTimeout(() => { showDeptDropdown.value = false; }, 200);

/** * --- LÓGICA DE NÓMINAS (ÁREAS) --- 
 */
const showAreaDropdown = ref(false);
const areaContainer = ref<HTMLElement | null>(null);

// Alternar selección de área (Solución a error @typescript-eslint/no-unused-expressions)
const toggleArea = (id: number | string) => {
    const idStr = String(id);
    const idx = selectedAreas.value.indexOf(idStr);
    if (idx > -1) {
        selectedAreas.value.splice(idx, 1);
    } else {
        selectedAreas.value.push(idStr);
    }
};

const getAreaName = (id: number | string) => props.nominas.find((n: Nomina) => String(n.id) === String(id))?.area_name || id;

// Selección masiva de grupos predefinidos (Confianza vs Sindicato)
const selectNominaGroup = (groupType: 'confianza' | 'sindical') => {
    if (!props.nominas) return;
    
    if (groupType === 'confianza') {
        const targets = ['BASE SIN SINDICATO', 'NOMINA 8', 'HONORARIOS'];
        selectedAreas.value = props.nominas
            .filter(n => targets.includes(n.area_name.toUpperCase()))
            .map(n => String(n.id));
    } else {
        selectedAreas.value = props.nominas
            .filter(n => {
                const name = n.area_name.toUpperCase();
                return name.includes('SECCIÓN SINDICAL') || name === 'BASE SINDICALIZADOS';
            })
            .map(n => String(n.id));
    }
    buscar(); 
};

/** * --- GESTIÓN DE EVENTOS Y TIEMPO --- 
 */
const handleClickOutside = (event: MouseEvent) => {
    if (areaContainer.value && !areaContainer.value.contains(event.target as Node)) {
        showAreaDropdown.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));

// Reseteo de campos cruzados al cambiar modalidad (Día vs Rango)
watch(mode, (newMode) => {
    if (newMode === 'single') {
        startDate.value = '';
        endDate.value = '';
    } else {
        dateIncidence.value = '';
    }
});

// Atajos de periodos de tiempo (Resta 1 día para evitar faltas futuras de hoy)
const setRange = (type: string) => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yesterday = new Date(); yesterday.setDate(today.getDate() - 1);
    const yesterdayStr = yesterday.toISOString().split('T')[0];

    if (type === 'quincena') {
        mode.value = 'range';
        startDate.value = today.getDate() <= 15 ? `${yyyy}-${mm}-01` : `${yyyy}-${mm}-16`;
        endDate.value = yesterdayStr; 
    } else if (type === 'quincena_pasada') {
        mode.value = 'range';
        if (today.getDate() <= 15) {
            const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            startDate.value = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}-16`;
            endDate.value = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}-${lastMonth.getDate()}`;
        } else {
            startDate.value = `${yyyy}-${mm}-01`; 
            endDate.value = `${yyyy}-${mm}-15`;
        }
    } else if (type === 'mensual') {
        mode.value = 'range'; 
        startDate.value = `${yyyy}-${mm}-01`; 
        endDate.value = yesterdayStr;
    }
    buscar();
};

/** * --- ACCIONES DE FORMULARIO --- 
 */
// Detectar si hay cambios pendientes de búsqueda
const isDirty = computed(() => {
    return String(startDate.value || '') !== String(props.filters?.start_date || '') ||
           String(endDate.value || '') !== String(props.filters?.end_date || '') ||
           String(dateIncidence.value || '') !== String(props.filters?.date_incidence || '') ||
           String(selectedDept.value || '') !== String(props.filters?.department_id || '');
});

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
</script>

<template>
    <Head title="Monitor de Faltas" />

    <SidebarProvider>
        <AppSidebar>
            <!-- Contenedor Principal con Scroll Controlado -->
            <div class="p-2 sm:p-4 bg-gray-50 h-screen max-h-screen w-full flex flex-col overflow-hidden text-slate-900">
                <div class="w-full max-w-full flex-grow flex flex-col gap-2 h-full">

                    <!-- SECCIÓN DE FILTROS COMPRIMIDA -->
                    <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-200 w-full shrink-0 z-20 relative flex flex-col gap-2.5">
                        
                        <!-- FILA 1: BUSCADOR DE EMPLEADO Y ACCIONES -->
                        <div class="flex flex-col lg:flex-row gap-2 items-end">
                            <div class="flex-grow w-full flex flex-col relative">
                                <div class="flex items-center justify-between mb-0.5">
                                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1 flex items-center gap-1">
                                        <Users class="h-3 w-3" /> Servidor Público
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer group">
                                        <input type="checkbox" v-model="isGeneral" class="w-3 h-3 border-2 border-gray-300 rounded checked:bg-red-600 transition-colors" />
                                        <span class="text-[8px] font-bold text-gray-600 uppercase group-hover:text-red-600 tracking-tighter">Consultar Todos</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <input type="text" v-model="searchInput" @focus="!isGeneral ? showDropdown = true : null" @blur="closeDropdown" :disabled="isGeneral"
                                        class="pl-9 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-xs h-9 transition-all font-bold"
                                        :class="isGeneral ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-emerald-50/20'"
                                        placeholder="Escriba nombre o nómina..." autocomplete="off" />
                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-gray-400" />
                                    
                                    <div v-if="!isGeneral && showDropdown && filteredEmployees.length > 0" class="absolute top-[38px] z-50 w-full bg-white border border-gray-200 rounded-lg shadow-2xl max-h-48 overflow-y-auto custom-scrollbar">
                                        <ul class="py-1">
                                            <li v-for="emp in filteredEmployees" :key="emp.id" @mousedown.prevent="selectEmployee(emp)" class="px-3 py-1.5 hover:bg-emerald-50 cursor-pointer border-b border-gray-50 last:border-0 flex flex-col transition-colors">
                                                <span class="font-bold text-gray-900 text-[10px] uppercase">{{ emp.first_name }} {{ emp.last_name }}</span>
                                                <span class="text-[8px] text-gray-500 font-mono tracking-tighter">ID: {{ emp.emp_code }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 w-full lg:w-auto">
                                <button @click="buscar" :disabled="loading" class="flex-1 lg:flex-none px-5 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-black uppercase text-[9px] transition-all shadow-md flex items-center justify-center gap-2" :class="{'btn-pulse': isDirty}">
                                    <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                                    <Search v-else class="h-3.5 w-3.5" /> Consultar
                                </button>
                                <button v-if="faltas.length > 0" @click="descargarExcel" class="px-3 h-9 bg-green-700 hover:bg-green-800 text-white rounded-lg font-black uppercase text-[9px] transition-all shadow-sm flex items-center gap-2">
                                    <FileDown class="h-3.5 w-3.5" /> Excel
                                </button>
                                <Link :href="getExclusionsRoute().url" class="px-3 h-9 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition-all flex items-center shadow-sm" title="Lista Negra"><ShieldAlert class="h-3.5 w-3.5" /></Link>
                                <button @click="limpiarFiltros" class="px-3 h-9 bg-gray-50 text-gray-500 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors border shadow-sm"><RotateCw class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>

                        <!-- FILA 2: ESTRUCTURA UNIFICADA (DEPTO, NÓMINAS Y TIEMPO) -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 pt-2 border-t border-gray-100 items-start">
                            <div class="lg:col-span-3 flex flex-col relative">
                                <label class="text-[8px] font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1"><Building2 class="h-2.5 w-2.5" /> Departamento</label>
                                <div class="relative">
                                    <input type="text" v-model="deptSearchInput" @focus="showDeptDropdown = true" @blur="closeDeptDropdown"
                                        class="w-full h-8 pl-3 pr-8 rounded-lg border border-gray-200 bg-blue-50/10 text-[9px] font-bold focus:ring-emerald-500 transition-all shadow-sm" placeholder="Buscar Depto..." />
                                    <button v-if="selectedDept" @click="clearDept" class="absolute inset-y-0 right-7 px-1 flex items-center text-gray-400 hover:text-red-500 transition-colors"><X class="h-3 w-3" /></button>
                                    <div v-if="selectedDept" class="absolute inset-y-0 right-2 flex items-center pointer-events-none"><CheckCircle class="h-3 w-3 text-emerald-500" /></div>
                                    <div v-if="showDeptDropdown && filteredDepts.length > 0" class="absolute top-[34px] z-[70] w-full bg-white border border-gray-200 rounded-lg shadow-2xl max-h-32 overflow-y-auto custom-scrollbar">
                                        <div v-for="d in filteredDepts" :key="d.id" @mousedown.prevent="selectDept(d)" class="px-3 py-1.5 hover:bg-emerald-50 cursor-pointer text-[8.5px] font-black uppercase text-gray-700 border-b last:border-0 transition-colors">{{ d.dept_name }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-3 flex flex-col relative" ref="areaContainer">
                                <label class="text-[8px] font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1"><Tag class="h-2.5 w-2.5" /> Nóminas</label>
                                <button @click="showAreaDropdown = !showAreaDropdown" class="w-full h-8 px-3 rounded-lg border border-gray-200 bg-emerald-50/10 text-left text-[9px] font-bold flex items-center justify-between overflow-hidden shadow-sm hover:bg-emerald-50/20 transition-all">
                                    <span v-if="selectedAreas.length === 0" class="text-gray-400">Todas</span>
                                    <span v-else class="truncate text-emerald-700 uppercase">{{ selectedAreas.length }} Seleccionadas</span>
                                    <ChevronDown class="h-3 w-3 text-gray-400" />
                                </button>
                                <div v-if="showAreaDropdown" class="absolute top-[34px] z-[60] w-full bg-white border border-gray-200 rounded-lg shadow-2xl p-2.5 max-h-60 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="flex flex-col gap-1">
                                        <label v-for="area in nominas" :key="area.id" class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded-lg cursor-pointer group">
                                            <input type="checkbox" :value="area.id" :checked="selectedAreas.includes(String(area.id))" @change="toggleArea(area.id)" class="w-3.5 h-3.5 border-2 border-gray-300 rounded checked:bg-emerald-600 transition-all" />
                                            <span class="text-[9px] font-black text-gray-700 uppercase group-hover:text-emerald-600 transition-colors">{{ area.area_name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-6 flex flex-col">
                                <label class="text-[8px] font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1"><Clock class="h-2.5 w-2.5" /> Tiempo</label>
                                <div class="flex flex-col sm:flex-row items-center gap-2 w-full">
                                    <div class="flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shadow-inner h-8 w-full sm:w-32 shrink-0">
                                        <button @click="mode = 'single'" class="flex-1 text-[7px] font-black uppercase rounded transition-all flex items-center justify-center gap-1 relative overflow-hidden group" :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500'">
                                            <div class="w-1.5 h-1.5 rounded-full transition-all duration-300" :class="mode === 'single' ? 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.9)] animate-pulse' : 'bg-slate-300'"></div> Día
                                        </button>
                                        <button @click="mode = 'range'" class="flex-1 text-[7px] font-black uppercase rounded transition-all flex items-center justify-center gap-1 relative overflow-hidden group" :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500'">
                                            <div class="w-1.5 h-1.5 rounded-full transition-all duration-300" :class="mode === 'range' ? 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.9)] animate-pulse' : 'bg-slate-300'"></div> Rango
                                        </button>
                                    </div>

                                    <div class="flex-grow w-full">
                                        <div v-if="mode === 'single'" class="animate-in fade-in slide-in-from-right-2 duration-300">
                                            <input v-model="dateIncidence" type="date" class="w-full rounded-lg border-blue-200 shadow-sm text-[9px] h-8 bg-blue-50/30 font-bold focus:ring-blue-500 px-2 text-blue-900" />
                                        </div>
                                        <div v-else class="flex items-center gap-1 animate-in fade-in slide-in-from-right-2 duration-300 w-full">
                                            <input v-model="startDate" type="date" class="flex-1 min-w-0 rounded-lg border-orange-200 shadow-sm text-[8.5px] h-8 bg-orange-50/30 font-bold focus:ring-orange-500 px-2 text-orange-900" />
                                            <span class="text-slate-300 font-bold tracking-tighter">-</span>
                                            <input v-model="endDate" type="date" class="flex-1 min-w-0 rounded-lg border-orange-200 shadow-sm text-[8.5px] h-8 bg-orange-50/30 font-bold focus:ring-orange-500 px-2 text-orange-900" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FILA 3: ATAJOS -->
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100 bg-slate-50/50 p-2 rounded-lg">
                            <span class="text-[8px] font-black uppercase text-slate-500 tracking-wider mr-1 ml-1">Atajos:</span>
                            <div class="flex gap-1.5">
                                <button @click="selectNominaGroup('confianza')" class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-md border border-indigo-200 shadow-sm font-black uppercase text-[7.5px] tracking-tighter">Confianza</button>
                                <button @click="selectNominaGroup('sindical')" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-md border border-emerald-200 shadow-sm font-black uppercase text-[7.5px] tracking-tighter">Sindicato</button>
                            </div>
                            <div class="w-px h-3 bg-slate-300 mx-0.5"></div>
                            <div class="flex gap-1.5">
                                <button @click="setRange('quincena_pasada')" class="px-2.5 py-1.5 bg-slate-200 text-slate-700 hover:bg-slate-300 rounded-md border shadow-sm font-black uppercase text-[7.5px]">Q. Pasada</button>
                                <button @click="setRange('quincena')" class="px-2.5 py-1.5 bg-orange-100 text-orange-800 hover:bg-orange-200 rounded-md border shadow-sm font-black uppercase text-[7.5px]">Q. Actual</button>
                                <button @click="setRange('mensual')" class="px-2.5 py-1.5 bg-blue-100 text-blue-800 hover:bg-blue-200 rounded-md border shadow-sm font-black uppercase text-[7.5px]">Mes Actual</button>
                            </div>
                        </div>

                        <!-- CORTINA DE TAGS (RETRÁCTIL) -->
                        <div v-if="selectedAreas.length > 0" class="pt-1 border-t border-slate-100">
                            <div class="flex items-center justify-between px-1 mb-1">
                                <span class="text-[8px] font-black uppercase text-emerald-700 tracking-widest"><Tag class="h-2.5 w-2.5 inline mr-1" /> Filtros Activos ({{ selectedAreas.length }})</span>
                                <button @click="isTagsVisible = !isTagsVisible" class="flex items-center gap-1 px-1.5 py-0.5 bg-slate-50 text-slate-500 text-[8px] font-black uppercase rounded-md border hover:bg-slate-100 transition-all shadow-sm">
                                    <component :is="isTagsVisible ? ChevronUp : ChevronDown" class="h-2.5 w-2.5" /> {{ isTagsVisible ? 'Ocultar' : 'Ver' }}
                                </button>
                            </div>
                            <transition name="curtain">
                                <div v-if="isTagsVisible" class="flex flex-wrap items-center gap-1 p-1 overflow-hidden">
                                    <div v-for="id in selectedAreas" :key="id" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-800 text-[8px] font-black uppercase rounded-full border border-emerald-200 shadow-sm animate-in zoom-in-95">
                                        {{ getAreaName(id) }} <button @click="toggleArea(id)" class="hover:text-red-600 transition-colors"><X class="h-2 w-2" /></button>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS (ESPACIO MAXIMIZADO) -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-200 flex flex-col flex-1 min-h-0 relative overflow-hidden">
                        <div v-if="loading" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center">
                            <Loader2 class="h-8 w-8 animate-spin mb-2 text-emerald-500" /><p class="font-black uppercase tracking-widest text-[10px] text-gray-800">Cargando...</p>
                        </div>
                        <div v-else-if="!faltas || faltas.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <Filter class="h-8 w-8 mb-2 opacity-20" /><p class="font-bold text-[10px] uppercase tracking-tighter">Sin registros.</p>
                        </div>
                        <div v-else class="overflow-auto flex-1 custom-scrollbar">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0 z-10 border-b shadow-sm">
                                    <tr>
                                        <th v-for="h in ['Nómina', 'Servidor Público', 'Departamento', 'Área', 'Fecha', 'In', 'Out', 'Horario']" :key="h" class="px-3 py-2 text-left font-black text-gray-500 uppercase text-[8px] tracking-widest">{{ h }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(falta, idx) in (faltas as Falta[])" :key="idx" class="hover:bg-red-50/40 group transition-colors">
                                        <td class="px-3 py-1.5 font-mono font-black text-red-600 text-[10px]">{{ falta.nomina }}</td>
                                        <td class="px-3 py-1.5 font-black text-gray-900 uppercase text-[9px] leading-tight group-hover:text-red-700">{{ falta.nombre }}</td>
                                        <!-- SE REMOVIÓ EL TRUNCADO PARA MOSTRAR TEXTO COMPLETO -->
                                        <td class="px-3 py-1.5 font-bold text-gray-500 uppercase text-[8.5px]">{{ falta.departamento }}</td>
                                        <td class="px-3 py-1.5 font-bold text-gray-500 uppercase text-[8.5px]">{{ falta.area }}</td>
                                        <td class="px-3 py-1.5 text-center font-bold text-gray-600 text-[9px]">{{ falta.fecha }}</td>
                                        <td class="px-2 py-1.5 text-center font-mono font-black text-[9px] text-slate-400 italic">{{ falta.checkin || '--:--' }}</td>
                                        <td class="px-2 py-1.5 text-center font-mono font-black text-[9px] text-slate-400 italic">{{ falta.checkout || '--:--' }}</td>
                                        <td class="px-3 py-1.5 text-[8.5px] font-bold text-gray-400 italic tracking-tighter">{{ falta.horario }}</td>
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
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

@keyframes pulse-emerald {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.btn-pulse { animation: pulse-emerald 2s infinite; }

@keyframes fade-in { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: translateY(0); } }
.animate-in { animation: fade-in 0.3s ease-out forwards; }

.curtain-enter-active, .curtain-leave-active { transition: all 0.3s ease; max-height: 200px; opacity: 1; }
.curtain-enter-from, .curtain-leave-to { max-height: 0; opacity: 0; }
</style>