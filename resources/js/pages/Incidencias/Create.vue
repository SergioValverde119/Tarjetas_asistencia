<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Save, PlusCircle, AlertCircle, CheckCircle, X, Search, 
    Loader2, Calendar, User, FileText, Clock, Layers,
    ArrowLeft, Undo2
} from 'lucide-vue-next';
import { debounce } from 'lodash';
import type { BreadcrumbItemType } from '@/types';

// Definición de tipos estricta para evitar errores de compilación
interface Props {
    employees: any[];
    categories: any[];
    filters: any;
    errors: any;
    flash: {
        success?: string | null;
        error?: string | null;
    };
}

const props = defineProps<Props>();

// --- CONFIGURACIÓN DE LAYOUT Y BREADCRUMBS ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Registrar Incidencia', href: '/incidencias/crear' },
        ]
    }, () => page),
});

// --- GESTIÓN DE MODO (DÍA ÚNICO VS RANGO) ---
const mode = ref('single');
const singleDate = ref(new Date().toISOString().split('T')[0]);
const singleStartTime = ref('07:00');
const singleEndTime = ref('22:00');

const syncTimes = () => {
    if (mode.value === 'single') {
        form.start_time = `${singleDate.value}T${singleStartTime.value}`;
        form.end_time = `${singleDate.value}T${singleEndTime.value}`;
    }
};

watch([mode, singleDate, singleStartTime, singleEndTime], syncTimes);

// --- BÚSQUEDA Y SELECCIÓN DE EMPLEADOS ---
const search = ref(props.filters?.search || '');
const searching = ref(false);
const showDropdown = ref(false);
const selectedName = ref('');

const handleSearch = debounce((val) => {
    searching.value = true;
    router.get('/incidencias/crear', { search: val }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['employees'],
        onFinish: () => searching.value = false
    });
}, 300);

watch(search, (val) => {
    if (form.employee_id && val !== selectedName.value) {
         form.employee_id = '';
         selectedName.value = '';
    }
    showDropdown.value = true;
    handleSearch(val);
});

const selectEmployee = (emp: any) => {
    form.employee_id = emp.id;
    const fullName = `${emp.first_name} ${emp.last_name} (${emp.emp_code})`;
    search.value = fullName;
    selectedName.value = fullName;
    showDropdown.value = false;
};

const closeDropdown = () => {
    setTimeout(() => { showDropdown.value = false; }, 200);
};

// --- BÚSQUEDA Y SELECCIÓN DE CATEGORÍAS ---
const categorySearch = ref('');
const showCategoryDropdown = ref(false);
const selectedCategoryName = ref('');

const filteredCategories = computed(() => {
    if (!categorySearch.value) return props.categories;
    const term = categorySearch.value.toLowerCase();
    return props.categories.filter(cat => 
        cat.name.toLowerCase().includes(term) || 
        cat.code.toLowerCase().includes(term)
    );
});

const selectCategory = (cat: any) => {
    form.category_id = cat.id;
    const catDisplayName = `${cat.name} (${cat.code})`;
    categorySearch.value = catDisplayName;
    selectedCategoryName.value = catDisplayName;
    showCategoryDropdown.value = false;
};

watch(categorySearch, (val) => {
    if (form.category_id && val !== selectedCategoryName.value) {
        form.category_id = '';
        selectedCategoryName.value = '';
    }
});

const closeCategoryDropdown = () => {
    setTimeout(() => { showCategoryDropdown.value = false; }, 200);
};

// --- FORMULARIO PRINCIPAL ---
const form = useForm({
    employee_id: '',
    category_id: '',
    start_time: '',
    end_time: '',
    reason: ''
});

const submit = () => {
    syncTimes();
    form.post('/incidencias', {
        onSuccess: () => {
            form.reset();
            search.value = '';
            selectedName.value = '';
            categorySearch.value = '';
            selectedCategoryName.value = '';
        }
    });
};

// --- MODAL NUEVA CATEGORÍA ---
const showCategoryModal = ref(false);
const categoryForm = useForm({
    name: '',
    code: '',
    unit: 3
});

const submitCategory = () => {
    categoryForm.post('/incidencias/categoria', {
        onSuccess: () => {
            showCategoryModal.value = false;
            categoryForm.reset();
        }
    });
};

onMounted(() => {
    syncTimes();
});
</script>

<template>
    <Head title="Registrar Incidencia" />

    <!-- CONTENEDOR DINÁMICO -->
    <div class="flex flex-col bg-slate-50 p-6 w-full min-w-0 font-sans pb-12">
        <div class="flex flex-col w-full min-w-0 space-y-4">
            
            <!-- ALERTAS -->
            <div v-if="flash?.success" class="flex-none bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm w-full">
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-5 w-5" />
                    <span class="font-medium">{{ flash.success }}</span>
                </div>
            </div>

            <div v-if="flash?.error" class="flex-none bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm w-full">
                <div class="flex items-center gap-2">
                    <AlertCircle class="h-5 w-5" />
                    <span class="font-medium">{{ flash.error }}</span>
                </div>
            </div>

            <!-- TARJETA DEL FORMULARIO -->
            <div class="bg-white shadow-lg rounded-2xl border border-slate-200 flex flex-col w-full min-w-0 overflow-visible">
                <div class="p-8">
                    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-10 w-full">
                        
                        <!-- RENGLÓN 1: PERSONAL Y TIPO DE PERMISO -->
                        <div class="md:col-span-6 w-full">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Selección de Personal</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Loader2 v-if="searching" class="h-4 w-4 text-blue-500 animate-spin" />
                                    <Search v-else class="h-4 w-4 text-slate-400" />
                                </div>
                                <input 
                                    type="text"
                                    v-model="search"
                                    @focus="showDropdown = true"
                                    @blur="closeDropdown"
                                    class="pl-12 w-full rounded-xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm h-12 font-bold transition-all bg-blue-50/50"
                                    :class="{'border-red-500': form.errors.employee_id}"
                                    placeholder="Nombre o número de nómina..."
                                    autocomplete="off"
                                />
                                <div v-if="form.employee_id" class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <CheckCircle class="h-5 w-5 text-green-500" />
                                </div>

                                <div v-if="showDropdown && employees.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto">
                                    <ul>
                                        <li 
                                            v-for="emp in employees" 
                                            :key="emp.id" 
                                            @mousedown.prevent="selectEmployee(emp)"
                                            class="px-4 py-3 hover:bg-slate-50 cursor-pointer transition-colors border-b border-slate-50 last:border-0"
                                        >
                                            <div class="font-bold text-slate-900 text-sm uppercase">{{ emp.first_name }} {{ emp.last_name }}</div>
                                            <div class="text-[10px] text-slate-500 font-black flex gap-2 uppercase tracking-tighter">
                                                <span class="text-blue-600">ID: {{ emp.emp_code }}</span>
                                                <span v-if="emp.department_name" class="text-gray-300">|</span>
                                                <span v-if="emp.department_name">{{ emp.department_name }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-if="form.errors.employee_id" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.employee_id }}</p>
                        </div>

                        <div class="md:col-span-6 w-full">
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Tipo de Permiso (Regla)</label>
                                <button type="button" @click="showCategoryModal = true" class="text-[10px] text-blue-600 hover:text-blue-800 font-black uppercase tracking-tighter flex items-center gap-1">
                                    <PlusCircle class="h-3 w-3" /> Nuevo
                                </button>
                            </div>
                            
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Search class="h-4 w-4 text-gray-400" />
                                </div>
                                <input 
                                    type="text"
                                    v-model="categorySearch"
                                    @focus="showCategoryDropdown = true"
                                    @blur="closeCategoryDropdown"
                                    class="pl-12 w-full rounded-xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm h-12 font-bold transition-all bg-blue-50/50"
                                    :class="{'border-red-500': form.errors.category_id}"
                                    placeholder="Escriba el tipo de permiso..."
                                    autocomplete="off"
                                />
                                <div v-if="form.category_id" class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <CheckCircle class="h-5 w-5 text-green-500" />
                                </div>

                                <div v-if="showCategoryDropdown && filteredCategories.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto">
                                    <ul>
                                        <li 
                                            v-for="cat in filteredCategories" 
                                            :key="cat.id" 
                                            @mousedown.prevent="selectCategory(cat)"
                                            class="px-4 py-3 hover:bg-slate-50 cursor-pointer transition-colors border-b border-slate-50 last:border-0"
                                        >
                                            <div class="font-bold text-slate-900 text-sm uppercase">{{ cat.name }}</div>
                                            <div class="text-[10px] text-blue-600 font-black uppercase">Código: {{ cat.code }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-if="form.errors.category_id" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.category_id }}</p>
                        </div>

                        <!-- RENGLÓN 2: MOTIVO Y FECHAS -->
                        <div class="md:col-span-6 w-full">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <FileText class="h-4 w-4" /> Motivo del Registro
                            </h3>
                            <textarea 
                                v-model="form.reason" 
                                rows="6" 
                                class="w-full rounded-2xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-medium p-4 bg-blue-50/50" 
                                placeholder="Describa el motivo detallado de esta incidencia..."
                            ></textarea>
                            <p v-if="form.errors.reason" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.reason }}</p>
                        </div>

                        <div class="md:col-span-6 w-full space-y-4">
                            <!-- SWITCHER DE MODO -->
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Periodo de la Incidencia</label>
                            <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200">
                                <button 
                                    type="button" 
                                    @click="mode = 'single'"
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Día Único
                                </button>
                                <button 
                                    type="button" 
                                    @click="mode = 'range'"
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Rango de Fechas
                                </button>
                            </div>

                            <!-- INPUTS DE FECHA/HORA DINÁMICOS -->
                            <div v-if="mode === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-2 duration-300 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Fecha</label>
                                    <input type="date" v-model="singleDate" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Clock class="h-3 w-3 shrink-0" /> Entrada</label>
                                    <input type="time" v-model="singleStartTime" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Clock class="h-3 w-3 shrink-0" /> Salida</label>
                                    <input type="time" v-model="singleEndTime" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-2 duration-300 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Inicio</label>
                                    <input type="datetime-local" v-model="form.start_time" class="w-full rounded-xl border-orange-200 shadow-sm h-12 font-bold text-sm bg-orange-50/30 focus:border-orange-500 focus:ring-orange-500" />
                                    <p v-if="form.errors.start_time" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.start_time }}</p>
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Fin</label>
                                    <input type="datetime-local" v-model="form.end_time" class="w-full rounded-xl border-orange-200 shadow-sm h-12 font-bold text-sm bg-orange-50/30 focus:border-orange-500 focus:ring-orange-500" />
                                    <p v-if="form.errors.end_time" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.end_time }}</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- FOOTER DE ACCIÓN CON COLORES CARAMELO -->
                <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-4 w-full rounded-b-2xl">
                    <!-- BOTÓN CANCELAR (ROJO CARAMELO VIBRANTE) -->
                    <Link 
                        href="/incidencias" 
                        class="inline-flex items-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg shadow-red-200/50 transition-all active:scale-95 gap-2"
                    >
                        <Undo2 class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <!-- BOTÓN GUARDAR (VERDE CARAMELO VIBRANTE) -->
                    <button 
                        @click="submit" 
                        :disabled="form.processing"
                        class="inline-flex items-center px-10 py-3 border border-transparent text-[11px] font-black uppercase tracking-[0.2em] rounded-xl shadow-lg shadow-emerald-200/50 text-white bg-emerald-500 hover:bg-emerald-600 focus:outline-none transition-all disabled:opacity-50 active:scale-95 cursor-pointer gap-2"
                    >
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4 mr-2" />
                        Guardar Registro
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: NUEVA CATEGORÍA -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative border border-slate-100">
                <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
                    <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest">Nueva Categoría</h3>
                    <button @click="showCategoryModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer"><X class="h-5 w-5" /></button>
                </div>
                
                <form @submit.prevent="submitCategory" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Nombre Completo</label>
                        <input v-model="categoryForm.name" type="text" placeholder="Ej. Comisión Externa" required class="block w-full rounded-xl border-slate-200 shadow-sm h-11 text-sm font-bold" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Código/Sigla</label>
                            <input v-model="categoryForm.code" type="text" maxlength="5" placeholder="COM" required class="block w-full rounded-xl border-slate-200 shadow-sm h-11 text-sm font-bold uppercase" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Unidad BioTime</label>
                            <select v-model="categoryForm.unit" class="block w-full rounded-xl border-gray-200 shadow-sm h-11 text-sm font-bold bg-white">
                                <option :value="3">Días</option>
                                <option :value="2">Horas</option>
                                <option :value="1">Minutos</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showCategoryModal = false" class="px-6 py-2.5 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-50 rounded-xl transition-colors cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="categoryForm.processing" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase rounded-xl shadow-md transition-all cursor-pointer">Crear Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>