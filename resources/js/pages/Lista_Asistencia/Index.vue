<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Search, RotateCw, Filter, CheckCircle, Loader2,
    ChevronDown, UserCheck, FilePlus, ListCollapseIcon, TableProperties
} from 'lucide-vue-next';
import { debounce } from 'lodash';

// IMPORTACIÓN DEL COMPONENTE MODULAR
import ListaPdf from './ListaPdf.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    employees: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    months: { type: Array, default: () => [] },
    selectedEmployee: { type: Object, default: null },
    attendanceData: { type: Object, default: null }
});

const form = ref({
    employee_id: props.filters.employee_id || '',
    search: props.filters.search || '',
    mes: parseInt(props.filters.mes) || new Date().getMonth() + 1,
    ano: parseInt(props.filters.ano) || new Date().getFullYear()
});

const internalSelectedName = ref(
    props.selectedEmployee 
    ? `${props.selectedEmployee.first_name} ${props.selectedEmployee.last_name}` 
    : ''
);
const isSearching = ref(false);
const showDropdown = ref(false);

const handleSearch = debounce((val) => {
    if (!val) { showDropdown.value = false; return; }
    isSearching.value = true;
    router.get('/asistencia', { search: val }, {
        preserveState: true, preserveScroll: true, replace: true,
        only: ['employees'],
        onFinish: () => isSearching.value = false
    });
}, 400);

watch(() => form.value.search, (val) => {
    if (form.value.employee_id && val !== internalSelectedName.value) {
        form.value.employee_id = '';
    }
    if (val !== internalSelectedName.value) {
        showDropdown.value = true;
        handleSearch(val);
    }
});

const selectEmployee = (emp) => {
    const fullName = `${emp.first_name} ${emp.last_name}`;
    form.value.employee_id = emp.id;
    form.value.search = fullName;
    internalSelectedName.value = fullName;
    showDropdown.value = false;
};

const crearLista = () => {
    if (!form.value.employee_id) return;
    router.get('/asistencia', {
        employee_id: form.value.employee_id,
        mes: form.value.mes,
        ano: form.value.ano,
        search: form.value.search
    }, { preserveState: true, preserveScroll: true });
};

const limpiarFiltros = () => {
    form.value.search = '';
    form.value.employee_id = '';
    internalSelectedName.value = '';
    router.get('/asistencia');
};

const anosDisponibles = computed(() => {
    const c = new Date().getFullYear();
    return [c, c - 1, c - 2];
});
</script>

<template>
    <Head title="Control de Asistencia" />

    <div class="flex flex-col min-h-screen bg-gray-50 w-full font-sans text-slate-900">
        
        <!-- BÚSQUEDA AVANZADA -->
        <div class="bg-white border-b border-gray-200 p-5 shrink-0 shadow-sm z-40 no-print w-full">
            <div class="w-full max-w-full space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2 uppercase tracking-widest">
                        <TableProperties class="h-4 w-4 text-blue-500" /> Generador de listas de asistencia
                    </h3>
                    <button @click="limpiarFiltros" class="text-xs text-gray-400 hover:text-red-600 font-bold uppercase tracking-tighter flex items-center gap-1 transition-colors">
                        <RotateCw class="h-3 w-3" /> Reiniciar Filtros
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <!-- Buscador -->
                    <div class="md:col-span-5 relative">
                        <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Nombre o nómina</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Loader2 v-if="isSearching" class="h-4 w-4 text-blue-500 animate-spin" />
                                <Search v-else class="h-4 w-4 text-gray-400" />
                            </div>
                            <input v-model="form.search" @focus="showDropdown = true" type="text" class="block w-full pl-10 pr-10 rounded-xl border-gray-200 text-sm h-11 font-bold uppercase bg-gray-50 focus:ring-blue-500 transition-all" placeholder="Escriba nombre o nómina..." autocomplete="off" />
                            <div v-if="form.employee_id" class="absolute inset-y-0 right-3 flex items-center"><CheckCircle class="h-5 w-5 text-emerald-500" /></div>

                            <div v-if="showDropdown && employees.length > 0" class="absolute z-50 left-0 right-0 mt-2 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                                <div v-for="emp in employees" :key="emp.id" @click="selectEmployee(emp)" class="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0 transition-colors">
                                    <div class="font-black text-slate-800 uppercase text-xs leading-tight">{{ emp.first_name }} {{ emp.last_name }}</div>
                                    <div class="text-[10px] font-bold text-blue-600 uppercase mt-0.5">ID: {{ emp.emp_code }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mes -->
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Mes</label>
                        <select v-model="form.mes" class="block w-full rounded-xl border-gray-200 text-sm h-11 font-black bg-gray-50 appearance-none pl-4">
                            <option v-for="(m, idx) in months" :key="idx" :value="idx + 1">{{ m }}</option>
                        </select>
                    </div>

                    <!-- Año -->
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Año</label>
                        <select v-model="form.ano" class="block w-full rounded-xl border-gray-200 text-sm h-11 font-black bg-gray-50 appearance-none pl-4">
                            <option v-for="a in anosDisponibles" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>

                    <!-- Botón Crear -->
                    <div class="md:col-span-3">
                        <button @click="crearLista" :disabled="!form.employee_id" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 rounded-xl shadow-lg shadow-blue-200 text-xs uppercase tracking-[0.2em] transition-all active:scale-95 disabled:opacity-30 disabled:grayscale flex items-center justify-center gap-2">
                            <FilePlus class="h-4 w-4" /> Generar Lista
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- VISOR DINÁMICO -->
        <div class="flex-1 p-6 overflow-y-auto w-full flex flex-col items-center">
            
            <!-- Estado vacío: Esperando selección -->
            <div v-if="!attendanceData" class="flex-1 flex flex-col items-center justify-center p-12 border-4 border-dashed border-gray-200 rounded-3xl w-full max-w-5xl bg-white/50 my-auto text-center no-print">
                <div class="bg-gray-100 p-6 rounded-full mb-4 shadow-inner">
                    <UserCheck class="h-16 w-16 text-gray-300" />
                </div>
                <p class="text-gray-400 font-black uppercase text-sm tracking-[0.3em]">Esperando Selección de Personal</p>
                <p class="text-gray-300 text-[10px] font-bold uppercase mt-2">Sincronizado con BioTime</p>
            </div>

            <!-- COMPONENTE LISTAPDF -->
            <ListaPdf 
                v-else
                :employee="selectedEmployee"
                :attendanceData="attendanceData"
                :months="months"
                :selectedMonth="form.mes"
                :selectedYear="form.ano"
            />
        </div>

        <!-- PIE DE PÁGINA DEL SISTEMA -->
        <div class="text-center py-6 bg-white border-t border-gray-100 no-print">
            <p class="text-[9px] font-black text-slate-300 uppercase tracking-[0.5em]">Gracias a Dios</p>
        </div>
    </div>
</template>

<style scoped>
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

@media print {
    .no-print { display: none !important; }
}
</style>