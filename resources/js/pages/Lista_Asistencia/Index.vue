<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Search, Calendar, User, FileText, ChevronDown, CheckCircle, Loader2, Printer } from 'lucide-vue-next';
import { debounce } from 'lodash';

defineOptions({ layout: AppLayout });

const props = defineProps({
    employees: Array,
    filters: Object,
    months: Array
});

// --- ESTADO DEL FORMULARIO ---
const form = ref({
    employee_id: '',
    search: props.filters.search || '',
    mes: new Date().getMonth() + 1,
    ano: new Date().getFullYear()
});

const selectedEmployeeName = ref('');
const isSearching = ref(false);
const showDropdown = ref(false);

// --- BÚSQUEDA DINÁMICA ---
const handleSearch = debounce((val) => {
    isSearching.value = true;
    router.get('/asistencia', { search: val }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['employees'],
        onFinish: () => isSearching.value = false
    });
}, 300);

watch(() => form.value.search, (val) => {
    if (form.value.employee_id && val !== selectedEmployeeName.value) {
        form.value.employee_id = '';
    }
    showDropdown.value = true;
    handleSearch(val);
});

const selectEmployee = (emp) => {
    form.value.employee_id = emp.id;
    const name = `${emp.first_name} ${emp.last_name}`;
    form.value.search = name;
    selectedEmployeeName.value = name;
    showDropdown.value = false;
};

// --- GENERACIÓN ---
const generarLista = () => {
    if (!form.value.employee_id) return;
    
    router.get(`/asistencia/lista/${form.value.employee_id}`, {
        mes: form.value.mes,
        ano: form.value.ano
    });
};

const anos = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});
</script>

<template>
    <Head title="Generador de Listas de Asistencia" />

    <div class="p-8 bg-slate-50 min-h-screen font-sans text-slate-900">
        <div class="max-w-3xl mx-auto space-y-8">
            
            <!-- ENCABEZADO -->
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight italic">Generador de Listas</h1>
                <p class="text-slate-500 font-bold text-sm uppercase">Seleccione al personal y el periodo para generar la lista de firmas manuales.</p>
            </div>

            <!-- TARJETA PRINCIPAL -->
            <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
                <div class="p-10 space-y-8">
                    
                    <!-- PASO 1: EMPLEADO -->
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                            <User class="w-4 h-4 text-blue-500" /> 1. Buscar Empleado
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <Loader2 v-if="isSearching" class="w-5 h-5 text-blue-500 animate-spin" />
                                <Search v-else class="w-5 h-5 text-slate-300" />
                            </div>
                            <input 
                                v-model="form.search"
                                @focus="showDropdown = true"
                                type="text"
                                class="w-full h-14 pl-12 pr-12 bg-slate-50 border-slate-200 rounded-2xl font-bold text-slate-700 focus:ring-4 focus:ring-blue-100 transition-all"
                                placeholder="Escriba nombre o número de nómina..."
                            />
                            <div v-if="form.employee_id" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                <CheckCircle class="w-6 h-6 text-emerald-500" />
                            </div>

                            <!-- DROPDOWN -->
                            <div v-if="showDropdown && employees.length > 0" class="absolute z-50 left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in slide-in-from-top-2">
                                <div 
                                    v-for="emp in employees" 
                                    :key="emp.id"
                                    @click="selectEmployee(emp)"
                                    class="p-4 hover:bg-blue-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors"
                                >
                                    <div class="font-black text-slate-800 uppercase text-sm">{{ emp.first_name }} {{ emp.last_name }}</div>
                                    <div class="text-[10px] font-bold text-slate-400 flex gap-2 uppercase">
                                        <span class="text-blue-600">ID: {{ emp.emp_code }}</span>
                                        <span>|</span>
                                        <span>{{ emp.department_name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 2: PERIODO (MES Y AÑO) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <Calendar class="w-4 h-4 text-orange-500" /> 2. Seleccionar Mes
                            </label>
                            <div class="relative">
                                <select v-model="form.mes" class="w-full h-14 pl-5 pr-10 bg-slate-50 border-slate-200 rounded-2xl font-black text-slate-700 appearance-none focus:ring-4 focus:ring-orange-100 transition-all">
                                    <option v-for="(m, idx) in months" :key="idx" :value="idx + 1">{{ m }}</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-4 w-6 h-6 text-slate-400 pointer-events-none" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <Calendar class="w-4 h-4 text-emerald-500" /> 3. Seleccionar Año
                            </label>
                            <div class="relative">
                                <select v-model="form.ano" class="w-full h-14 pl-5 pr-10 bg-slate-50 border-slate-200 rounded-2xl font-black text-slate-700 appearance-none focus:ring-4 focus:ring-emerald-100 transition-all">
                                    <option v-for="a in anos" :key="a" :value="a">{{ a }}</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-4 w-6 h-6 text-slate-400 pointer-events-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIÓN FINAL -->
                <div class="bg-slate-50 p-8 border-t border-slate-100">
                    <button 
                        @click="generarLista"
                        :disabled="!form.employee_id"
                        class="w-full h-16 bg-slate-900 hover:bg-black text-white rounded-2xl font-black uppercase tracking-[0.3em] text-xs flex items-center justify-center gap-3 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-30 disabled:grayscale"
                    >
                        <Printer class="w-5 h-5" /> Generar Lista de Asistencia
                    </button>
                    <p v-if="!form.employee_id" class="text-center text-[9px] font-black text-slate-400 uppercase mt-4 tracking-widest">
                        Debe seleccionar un empleado para continuar
                    </p>
                </div>
            </div>

            <!-- PIE DE PÁGINA -->
            <div class="text-center space-y-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">En el Nombre de Jehová Dios y Jesús Rey</p>
                <div class="flex justify-center gap-4">
                    <div class="h-1 w-8 bg-blue-500 rounded-full"></div>
                    <div class="h-1 w-8 bg-orange-500 rounded-full"></div>
                    <div class="h-1 w-8 bg-emerald-500 rounded-full"></div>
                </div>
            </div>

        </div>
    </div>
</template>