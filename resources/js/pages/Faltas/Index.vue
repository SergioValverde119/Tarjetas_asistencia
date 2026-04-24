<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

// Iconografía técnica refinada
import { 
    FileDown, Filter, Loader2, Search, RotateCw, Users, ShieldAlert, 
    ChevronDown, X, Building2, Tag, CheckCircle, Clock, 
    ChevronUp, ChevronRight, FolderTree, Building, Circle
} from 'lucide-vue-next';

/** * --- IMPORTACIÓN DE RUTAS (WAYFINDER) --- */
import { index as exclusionsIndex } from "@/routes/exclusion";
const getExclusionsRoute = exclusionsIndex;

/** * --- DEFINICIÓN DE TIPOS E INTERFACES --- */
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
    parent_dept_id?: number | string | null;
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

// Utilidad para normalizar búsquedas
const normalizeStr = (str: string) => 
    str ? str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : "";

/** * --- ESTADO REACTIVO DE FILTROS --- */
const mode = ref(props.filters?.date_incidence ? 'single' : 'range');
const dateIncidence = ref(props.filters?.date_incidence || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const selectedDept = ref(props.filters?.department_id || '');
const selectedAreas = ref<string[]>(Array.isArray(props.filters?.area_id) ? props.filters.area_id : (props.filters?.area_id ? [String(props.filters.area_id)] : []));

// UI y Estados de Carga
const isTagsVisible = ref(false); 
const loading = ref(false);

/** * --- LÓGICA DE SERVIDOR PÚBLICO (EMPLEADO) --- */
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

/** * --- LÓGICA DE DEPARTAMENTO (VISTA DE ÁRBOL DINÁMICA) --- */
const deptSearchInput = ref('');
const showDeptDropdown = ref(false);
const expandedNodes = ref(new Set<string>()); // Controla qué ramas están abiertas

// Sincronizar nombre si ya está seleccionado
if (selectedDept.value) {
    const dept = props.departamentos.find((d: Departamento) => String(d.id) === String(selectedDept.value));
    if (dept) deptSearchInput.value = dept.dept_name;
}

/**
 * Función para alternar la visibilidad de una rama
 */
const toggleNode = (id: string | number) => {
    const idStr = String(id);
    if (expandedNodes.value.has(idStr)) {
        expandedNodes.value.delete(idStr);
    } else {
        expandedNodes.value.add(idStr);
    }
};

/**
 * Procesador de Árbol Inteligente:
 * Muestra la estructura jerárquica y maneja el estado de expansión.
 */
const hierarchicalDepts = computed(() => {
    const term = normalizeStr(deptSearchInput.value);
    const result: any[] = [];
    
    // 1. Mapeo para acceso rápido
    const map: Record<string, any> = {};
    props.departamentos.forEach(d => {
        map[String(d.id)] = { ...d, children: [] };
    });

    // 2. Construcción del Árbol Real
    const tree: any[] = [];
    props.departamentos.forEach(d => {
        const parentId = d.parent_dept_id && d.parent_dept_id !== "" ? String(d.parent_dept_id) : null;
        if (parentId && map[parentId]) {
            map[parentId].children.push(map[String(d.id)]);
        } else {
            tree.push(map[String(d.id)]);
        }
    });

    // 3. Función para determinar si una rama debe estar abierta por búsqueda
    const hasMatch = (node: any): boolean => {
        if (normalizeStr(node.dept_name).includes(term)) return true;
        return node.children.some((c: any) => hasMatch(c));
    };

    // 4. Aplanado selectivo (Solo si está expandido o hay búsqueda activa)
    const flatten = (nodes: any[], level = 0) => {
        const sorted = [...nodes].sort((a, b) => a.dept_name.localeCompare(b.dept_name));
        
        sorted.forEach(node => {
            const nodeId = String(node.id);
            const matches = term ? normalizeStr(node.dept_name).includes(term) : true;
            const branchHasMatch = term ? hasMatch(node) : false;
            
            // Si hay búsqueda, mostramos el nodo si él o sus hijos coinciden
            if (!term || branchHasMatch || matches) {
                result.push({ 
                    ...node, 
                    level, 
                    hasChildren: node.children.length > 0,
                    // Si hay búsqueda, forzamos expansión para ver el resultado
                    isExpanded: term ? branchHasMatch : expandedNodes.value.has(nodeId)
                });

                // Solo bajamos al siguiente nivel si la rama está abierta
                if (node.children.length > 0 && (term || expandedNodes.value.has(nodeId))) {
                    flatten(node.children, level + 1);
                }
            }
        });
    };

    flatten(tree);
    return result;
});

const selectDept = (dept: Departamento) => {
    selectedDept.value = dept.id;
    deptSearchInput.value = dept.dept_name;
    showDeptDropdown.value = false;
};

const clearDept = () => {
    selectedDept.value = '';
    deptSearchInput.value = '';
    expandedNodes.value.clear();
};

watch(deptSearchInput, (newVal) => { if (!newVal) selectedDept.value = ''; });

const closeDeptDropdown = () => setTimeout(() => { showDeptDropdown.value = false; }, 200);

/** * --- LÓGICA DE NÓMINAS (ÁREAS) --- */
const showAreaDropdown = ref(false);
const areaContainer = ref<HTMLElement | null>(null);

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

const selectNominaGroup = (groupType: 'confianza' | 'sindical') => {
    if (!props.nominas) return;
    if (groupType === 'confianza') {
        const targets = ['BASE SIN SINDICATO', 'NOMINA 8', 'HONORARIOS'];
        selectedAreas.value = props.nominas.filter(n => targets.includes(n.area_name.toUpperCase())).map(n => String(n.id));
    } else {
        selectedAreas.value = props.nominas.filter(n => n.area_name.toUpperCase().includes('SECCIÓN SINDICAL') || n.area_name.toUpperCase() === 'BASE SINDICALIZADOS').map(n => String(n.id));
    }
    buscar();
};

/** * --- GESTIÓN DE EVENTOS Y TIEMPO --- */
const handleClickOutside = (event: MouseEvent) => {
    if (areaContainer.value && !areaContainer.value.contains(event.target as Node)) {
        showAreaDropdown.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));

watch(mode, (newMode) => {
    if (newMode === 'single') { startDate.value = ''; endDate.value = ''; }
    else { dateIncidence.value = ''; }
});

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
            startDate.value = `${yyyy}-${mm}-01`; endDate.value = `${yyyy}-${mm}-15`;
        }
    } else if (type === 'mensual') {
        mode.value = 'range'; startDate.value = `${yyyy}-${mm}-01`; endDate.value = yesterdayStr;
    }
    buscar();
};

/** * --- ACCIONES DE FORMULARIO --- */
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
        onFinish: () => { loading.value = false; showAreaDropdown.value = false; }
    });
};

const limpiarFiltros = () => {
    dateIncidence.value = ''; startDate.value = ''; endDate.value = '';
    isGeneral.value = true; selectedEmp.value = ''; searchInput.value = '';
    selectedDept.value = ''; deptSearchInput.value = ''; selectedAreas.value = [];
    expandedNodes.value.clear();
    buscar();
};

const descargarExcel = () => {
    const params = new URLSearchParams({
        date_incidence: dateIncidence.value, start_date: startDate.value, end_date: endDate.value,
        emp_id: isGeneral.value ? '' : selectedEmp.value, department_id: selectedDept.value,
    });
    selectedAreas.value.forEach(id => params.append('area_id[]', id));
    window.location.href = `${window.location.pathname}/exportar?${params.toString()}`;
};
</script>

<template>
    <Head title="Monitor de Faltas" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-2 sm:p-4 bg-gray-50 h-screen max-h-screen w-full flex flex-col overflow-hidden text-slate-900 transition-all">
                <div class="w-full max-w-full flex-grow flex flex-col gap-2 h-full">

                    <!-- SECCIÓN DE FILTROS COMPRIMIDA -->
                    <div class="bg-white p-3 lg:p-4 rounded-xl shadow-sm border border-gray-200 w-full shrink-0 z-20 relative flex flex-col gap-2.5">
                        
                        <!-- FILA 1: BUSCADOR DE EMPLEADO Y ACCIONES -->
                        <div class="flex flex-col lg:flex-row gap-2 items-end">
                            <div class="flex-grow w-full flex flex-col relative">
                                <div class="flex items-center justify-between mb-0.5">
                                    <label class="text-[9px] lg:text-xs font-black text-gray-500 uppercase tracking-widest ml-1 flex items-center gap-1">
                                        <Users class="h-3 w-3 lg:h-4 lg:w-4" /> Servidor Público
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer group">
                                        <input type="checkbox" v-model="isGeneral" class="w-3.5 h-3.5 border-2 border-gray-300 rounded checked:bg-red-600 transition-colors" />
                                        <span class="text-[8px] lg:text-[10px] font-bold text-gray-600 uppercase group-hover:text-red-600 tracking-tighter">Consultar Todos</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <input type="text" v-model="searchInput" @focus="!isGeneral ? showDropdown = true : null" @blur="closeDropdown" :disabled="isGeneral"
                                        class="pl-9 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-xs lg:text-sm h-9 lg:h-11 transition-all font-bold"
                                        :class="isGeneral ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-emerald-50/20'"
                                        placeholder="Escriba nombre o nómina..." autocomplete="off" />
                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 lg:h-4 lg:w-4 text-gray-400" />
                                    
                                    <div v-if="!isGeneral && showDropdown && filteredEmployees.length > 0" class="absolute top-[40px] lg:top-[48px] z-50 w-full bg-white border border-gray-200 rounded-lg shadow-2xl max-h-48 lg:max-h-64 overflow-y-auto custom-scrollbar">
                                        <ul class="py-1">
                                            <li v-for="emp in filteredEmployees" :key="emp.id" @mousedown.prevent="selectEmployee(emp)" class="px-3 py-2 lg:py-3 hover:bg-emerald-50 cursor-pointer border-b border-gray-50 last:border-0 flex flex-col transition-colors">
                                                <span class="font-bold text-gray-900 text-[10px] lg:text-xs uppercase">{{ emp.first_name }} {{ emp.last_name }}</span>
                                                <span class="text-[8px] lg:text-[10px] text-gray-500 font-mono tracking-tighter">ID: {{ emp.emp_code }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 w-full lg:w-auto">
                                <button @click="buscar" :disabled="loading" class="flex-1 lg:flex-none px-5 h-9 lg:h-11 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-black uppercase text-[9px] lg:text-[11px] transition-all shadow-md flex items-center justify-center gap-2" :class="{'btn-pulse': isDirty}">
                                    <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                                    <Search v-else class="h-3.5 w-3.5" /> Consultar
                                </button>
                                <button v-if="faltas.length > 0" @click="descargarExcel" class="px-3 h-9 lg:h-11 bg-green-700 hover:bg-green-800 text-white rounded-lg font-black uppercase text-[9px] lg:text-[11px] transition-all shadow-sm flex items-center gap-2">
                                    <FileDown class="h-3.5 w-3.5" /> Excel
                                </button>
                                <Link :href="getExclusionsRoute().url" class="px-3 h-9 lg:h-11 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition-all flex items-center shadow-sm" title="Lista Negra"><ShieldAlert class="h-3.5 w-3.5" /></Link>
                                <button @click="limpiarFiltros" class="px-3 h-9 lg:h-11 bg-gray-50 text-gray-500 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors border shadow-sm"><RotateCw class="h-3.5 w-3.5 lg:h-4 lg:w-4" /></button>
                            </div>
                        </div>

                        <!-- FILA 2: ESTRUCTURA UNIFICADA (ÁRBOL BIOTIME DINÁMICO) -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 pt-2 border-t border-gray-100 items-start">
                            
                            <!-- Filtro Departamento (Árbol Interactivo) -->
                            <div class="lg:col-span-3 flex flex-col relative">
                                <label class="text-[8px] lg:text-xs font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1">
                                    <FolderTree class="h-2.5 w-2.5 lg:h-3 lg:w-3 text-blue-600" /> Estructura Institucional
                                </label>
                                <div class="relative">
                                    <input type="text" v-model="deptSearchInput" @focus="showDeptDropdown = true" @blur="closeDeptDropdown"
                                        class="w-full h-8 lg:h-10 pl-3 pr-8 rounded-lg border border-gray-200 bg-blue-50/10 text-[9px] lg:text-xs font-bold focus:ring-emerald-500 transition-all shadow-sm" 
                                        placeholder="Filtrar Áreas/Direcciones..." />
                                    
                                    <button v-if="selectedDept" @click="clearDept" class="absolute inset-y-0 right-7 px-1 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                        <X class="h-3 w-3 lg:h-4 lg:w-4" />
                                    </button>
                                    <div v-if="selectedDept" class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                                        <CheckCircle class="h-3 w-3 lg:h-4 lg:w-4 text-emerald-500" />
                                    </div>

                                    <!-- Dropdown con Lógica de Árbol Desplegable Refinada -->
                                    <!-- AUMENTADO min-w-80 A min-w-[450px] PARA ESCRITORIO -->
                                    <div v-if="showDeptDropdown && hierarchicalDepts.length > 0" class="absolute top-[34px] lg:top-[42px] z-[70] w-full min-w-full lg:min-w-[450px] bg-white border border-gray-200 rounded-lg shadow-2xl max-h-96 overflow-y-auto custom-scrollbar">
                                        <div 
                                            v-for="d in hierarchicalDepts" 
                                            :key="d.id" 
                                            class="px-2 py-1.5 transition-all flex items-center gap-1 relative group border-b border-slate-50 last:border-0"
                                            :style="{ paddingLeft: (d.level * 20 + 8) + 'px' }"
                                            @click="!d.hasChildren ? selectDept(d) : null"
                                            :class="[!d.hasChildren ? 'cursor-pointer hover:bg-blue-600 hover:text-white' : 'cursor-default bg-white text-gray-700']"
                                        >
                                            <!-- Botón de Expandir/Contraer (Solo si tiene hijos) -->
                                            <button 
                                                v-if="d.hasChildren"
                                                @mousedown.prevent.stop="toggleNode(d.id)"
                                                class="p-1.5 hover:bg-blue-100 rounded-md transition-colors mr-1 shrink-0 z-10"
                                            >
                                                <component :is="d.isExpanded ? ChevronDown : ChevronRight" class="h-3.5 w-3.5 lg:h-4 lg:w-4 text-blue-500" />
                                            </button>
                                            <div v-else class="w-6 lg:w-7 shrink-0 flex justify-center items-center">
                                                <Circle class="h-1 w-1 fill-current opacity-30" />
                                            </div>

                                            <!-- Nombre Seleccionable (AJUSTADO: SIN TRUNCATE Y CON WRAPPING) -->
                                            <span 
                                                @mousedown.prevent.stop="selectDept(d)"
                                                :title="d.dept_name"
                                                class="flex-grow py-1.5 px-2 rounded-md transition-all cursor-pointer text-[8.5px] lg:text-xs whitespace-normal leading-tight break-words"
                                                :class="{
                                                    'font-black text-blue-900 group-hover:text-inherit': d.level === 0, 
                                                    'font-bold text-slate-600 group-hover:text-inherit': d.level > 0,
                                                    'bg-blue-700 text-white': String(selectedDept) === String(d.id)
                                                }"
                                            >
                                                {{ d.dept_name }}
                                            </span>

                                            <!-- Conector Visual (Línea de profundidad sutil) -->
                                            <div v-if="d.level > 0" class="absolute top-0 bottom-0 border-l border-slate-100" :style="{ left: (d.level * 20 - 4) + 'px' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-3 flex flex-col relative" ref="areaContainer">
                                <label class="text-[8px] lg:text-xs font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1"><Tag class="h-2.5 w-2.5 lg:h-3 lg:w-3" /> Nóminas</label>
                                <button @click="showAreaDropdown = !showAreaDropdown" class="w-full h-8 lg:h-10 px-3 rounded-lg border border-gray-200 bg-emerald-50/10 text-left text-[9px] lg:text-xs font-bold flex items-center justify-between overflow-hidden shadow-sm hover:bg-emerald-50/20 transition-all">
                                    <span v-if="selectedAreas.length === 0" class="text-gray-400">Todas</span>
                                    <span v-else class="truncate text-emerald-700 uppercase">{{ selectedAreas.length }} Seleccionadas</span>
                                    <ChevronDown class="h-3 w-3 lg:h-4 lg:w-4 text-gray-400" />
                                </button>
                                <div v-if="showAreaDropdown" class="absolute top-[34px] lg:top-[42px] z-[60] w-full bg-white border border-gray-200 rounded-lg shadow-2xl p-2.5 max-h-60 overflow-y-auto custom-scrollbar animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="flex flex-col gap-1">
                                        <label v-for="area in nominas" :key="area.id" class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded-lg cursor-pointer group">
                                            <input type="checkbox" :value="area.id" :checked="selectedAreas.includes(String(area.id))" @change="toggleArea(area.id)" class="w-3.5 h-3.5 lg:h-4 lg:w-4 border-2 border-gray-300 rounded checked:bg-emerald-600 transition-all" />
                                            <span class="text-[9px] lg:text-[11px] font-black text-gray-700 uppercase group-hover:text-emerald-600 transition-colors">{{ area.area_name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-6 flex flex-col">
                                <label class="text-[8px] lg:text-xs font-black text-gray-500 uppercase tracking-widest mb-1 ml-1 flex items-center gap-1"><Clock class="h-2.5 w-2.5 lg:h-3 lg:w-3" /> Tiempo</label>
                                <div class="flex flex-col sm:flex-row items-center gap-2 w-full">
                                    <div class="flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shadow-inner h-8 lg:h-10 w-full sm:w-36 lg:w-44 shrink-0">
                                        <button @click="mode = 'single'" class="flex-1 text-[7px] lg:text-[10px] font-black uppercase rounded transition-all flex items-center justify-center gap-1 relative overflow-hidden group" :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500'">
                                            <div class="w-1.5 h-1.5 lg:h-2 lg:w-2 rounded-full transition-all duration-300" :class="mode === 'single' ? 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.9)] animate-pulse' : 'bg-slate-300'"></div> Día
                                        </button>
                                        <button @click="mode = 'range'" class="flex-1 text-[7px] lg:text-[10px] font-black uppercase rounded transition-all flex items-center justify-center gap-1 relative overflow-hidden group" :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500'">
                                            <div class="w-1.5 h-1.5 lg:h-2 lg:w-2 rounded-full transition-all duration-300" :class="mode === 'range' ? 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.9)] animate-pulse' : 'bg-slate-300'"></div> Rango
                                        </button>
                                    </div>

                                    <div class="flex-grow w-full">
                                        <div v-if="mode === 'single'" class="animate-in fade-in slide-in-from-right-2 duration-300">
                                            <input v-model="dateIncidence" type="date" class="w-full rounded-lg border-blue-200 shadow-sm text-[9px] h-8 lg:h-10 bg-blue-50/30 font-bold focus:ring-blue-500 px-2 text-blue-900" />
                                        </div>
                                        <div v-else class="flex items-center gap-1 animate-in fade-in slide-in-from-right-2 duration-300 w-full">
                                            <input v-model="startDate" type="date" class="flex-1 min-w-0 rounded-lg border-orange-200 shadow-sm text-[8.5px] lg:text-xs h-8 lg:h-10 bg-orange-50/30 font-bold focus:ring-orange-500 px-2 text-orange-900" />
                                            <span class="text-slate-300 font-bold tracking-tighter">-</span>
                                            <input v-model="endDate" type="date" class="flex-1 min-w-0 rounded-lg border-orange-200 shadow-sm text-[8.5px] lg:text-xs h-8 lg:h-10 bg-orange-50/30 font-bold focus:ring-orange-500 px-2 text-orange-900" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FILA 3: ATAJOS -->
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100 bg-slate-50/50 p-2 rounded-lg">
                            <span class="text-[8px] lg:text-[10px] font-black uppercase text-slate-500 tracking-wider mr-1 ml-1">Atajos:</span>
                            <div class="flex gap-1.5">
                                <button @click="selectNominaGroup('confianza')" class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-md border border-indigo-200 shadow-sm font-black uppercase text-[7.5px] lg:text-[10px] tracking-tighter">Confianza</button>
                                <button @click="selectNominaGroup('sindical')" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-md border border-emerald-200 shadow-sm font-black uppercase text-[7.5px] lg:text-[10px] tracking-tighter">Sindicato</button>
                            </div>
                            <div class="w-px h-3 bg-slate-300 mx-0.5"></div>
                            <div class="flex gap-1.5">
                                <button @click="setRange('quincena_pasada')" class="px-2.5 py-1.5 bg-slate-200 text-slate-700 hover:bg-slate-300 rounded-md border shadow-sm font-black uppercase text-[7.5px] lg:text-[10px]">Q. Pasada</button>
                                <button @click="setRange('quincena')" class="px-2.5 py-1.5 bg-orange-100 text-orange-800 hover:bg-orange-200 rounded-md border shadow-sm font-black uppercase text-[7.5px] lg:text-[10px]">Q. Actual</button>
                                <button @click="setRange('mensual')" class="px-2.5 py-1.5 bg-blue-100 text-blue-800 hover:bg-blue-200 rounded-md border shadow-sm font-black uppercase text-[7.5px] lg:text-[10px]">Mes Actual</button>
                            </div>
                        </div>

                        <!-- CORTINA DE TAGS (NÓMINAS SELECCIONADAS) -->
                        <div v-if="selectedAreas.length > 0" class="pt-1 border-t border-slate-100">
                            <div class="flex items-center justify-between px-1 mb-1">
                                <span class="text-[8px] lg:text-[10px] font-black uppercase text-emerald-700 tracking-widest"><Tag class="h-2.5 w-2.5 inline mr-1 lg:h-3 lg:w-3" /> Filtros Activos ({{ selectedAreas.length }})</span>
                                <button @click="isTagsVisible = !isTagsVisible" class="flex items-center gap-1 px-1.5 py-0.5 lg:py-1 lg:px-3 bg-slate-50 text-slate-500 text-[8px] lg:text-[10px] font-black uppercase rounded-md border hover:bg-slate-100 transition-all shadow-sm">
                                    <component :is="isTagsVisible ? ChevronUp : ChevronDown" class="h-2.5 w-2.5 lg:h-3 lg:w-3" /> {{ isTagsVisible ? 'Ocultar' : 'Ver' }}
                                </button>
                            </div>
                            <transition name="curtain">
                                <div v-if="isTagsVisible" class="flex flex-wrap items-center gap-1 p-1 overflow-hidden">
                                    <div v-for="id in selectedAreas" :key="id" class="inline-flex items-center gap-1 px-1.5 py-0.5 lg:py-1 lg:px-3 bg-emerald-100 text-emerald-800 text-[8px] lg:text-[11px] font-black uppercase rounded-full border border-emerald-200 shadow-sm animate-in zoom-in-95">
                                        {{ getAreaName(id) }} <button @click="toggleArea(id)" class="hover:text-red-600 transition-colors"><X class="h-2 w-2 lg:h-3 lg:w-3" /></button>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>

                    <!-- TABLA DE RESULTADOS -->
                    <div class="bg-white shadow-sm rounded-xl border border-gray-200 flex flex-col flex-1 min-h-0 relative overflow-hidden">
                        <div v-if="loading" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center">
                            <Loader2 class="h-8 w-8 lg:h-12 lg:w-12 animate-spin mb-2 text-emerald-500" /><p class="font-black uppercase tracking-widest text-[10px] lg:text-sm text-gray-800">Cargando...</p>
                        </div>
                        <div v-else-if="!faltas || faltas.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <Filter class="h-8 w-8 lg:h-12 lg:w-12 mb-2 opacity-20" /><p class="font-bold text-[10px] lg:text-sm uppercase tracking-tighter">Sin registros.</p>
                        </div>
                        <div v-else class="overflow-auto flex-1 custom-scrollbar">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0 z-10 border-b shadow-sm">
                                    <tr>
                                        <th v-for="h in ['Nómina', 'Servidor Público', 'Departamento', 'Área', 'Fecha', 'In', 'Out', 'Horario']" :key="h" class="px-3 py-2 lg:py-4 text-left font-black text-gray-500 uppercase text-[8px] lg:text-[11px] tracking-widest">{{ h }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(falta, idx) in (faltas as Falta[])" :key="idx" class="hover:bg-red-50/40 group transition-colors text-xs lg:text-sm">
                                        <td class="px-3 py-1.5 lg:py-3 font-mono font-black text-red-600 text-[10px] lg:text-xs">{{ falta.nomina }}</td>
                                        <td class="px-3 py-1.5 lg:py-3 font-black text-gray-900 uppercase leading-tight group-hover:text-red-700">{{ falta.nombre }}</td>
                                        <td class="px-3 py-1.5 lg:py-3 font-bold text-gray-500 uppercase text-[8.5px] lg:text-[11px]">{{ falta.departamento }}</td>
                                        <td class="px-3 py-1.5 lg:py-3 font-bold text-gray-500 uppercase text-[8.5px] lg:text-[11px]">{{ falta.area }}</td>
                                        <td class="px-3 py-1.5 lg:py-3 text-center font-bold text-gray-600 text-[9px] lg:text-xs">{{ falta.fecha }}</td>
                                        <td class="px-2 py-1.5 lg:py-3 text-center font-mono font-black text-[9px] lg:text-xs text-slate-400 italic">{{ falta.checkin || '--:--' }}</td>
                                        <td class="px-2 py-1.5 lg:py-3 text-center font-mono font-black text-[9px] lg:text-xs text-slate-400 italic">{{ falta.checkout || '--:--' }}</td>
                                        <td class="px-3 py-1.5 lg:py-3 text-[8.5px] lg:text-[10px] font-bold text-gray-400 italic tracking-tighter">{{ falta.horario }}</td>
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

.curtain-enter-active, .curtain-leave-active { transition: all 0.3s ease; max-height: 400px; opacity: 1; }
.curtain-enter-from, .curtain-leave-to { max-height: 0; opacity: 0; }
</style>