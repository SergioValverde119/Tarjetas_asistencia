<script setup>
import { ref, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { Save, PlusCircle, AlertCircle, CheckCircle, X, Search, Loader2, Calendar, User, FileText, ChevronDown } from 'lucide-vue-next';
import { debounce } from 'lodash';

const props = defineProps({
    employees: Array,
    categories: Array,
    filters: Object,
    errors: Object,
    flash: Object
});

// --- BÚSQUEDA Y SELECCIÓN DE EMPLEADOS (AUTOCOMPLETE) ---
const search = ref(props.filters?.search || '');
const searching = ref(false);
const showDropdown = ref(false); // Controla la visibilidad de la lista

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
    // Si el usuario escribe, limpiamos el ID seleccionado para obligar a seleccionar de nuevo
    // (a menos que el texto coincida exactamente con una selección previa, pero por seguridad limpiamos)
    if (form.employee_id && val !== selectedName.value) {
         form.employee_id = '';
         selectedName.value = '';
    }
    showDropdown.value = true; // Mostrar lista al escribir
    handleSearch(val);
});

// Variable auxiliar para saber qué nombre se seleccionó y no borrarlo al escribir
const selectedName = ref('');

const selectEmployee = (emp) => {
    form.employee_id = emp.id;
    const fullName = `${emp.first_name} ${emp.last_name} (${emp.emp_code})`;
    search.value = fullName; // Mostrar nombre en el input
    selectedName.value = fullName; // Guardar referencia para no disparar limpieza
    showDropdown.value = false; // Ocultar lista
};

// Cierra el dropdown con un pequeño retraso para permitir el click en la opción
const closeDropdown = () => {
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
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
    form.post('/incidencias', {
        onSuccess: () => {
            form.reset();
            search.value = ''; // Limpiar buscador
            selectedName.value = '';
            showFlash.value = true;
        }
    });
};

// --- MODAL NUEVA CATEGORÍA ---
const showCategoryModal = ref(false);
const categoryForm = useForm({
    name: '',
    code: '',
    unit: 3 // 3 = Días
});

const submitCategory = () => {
    categoryForm.post('/incidencias/categoria', {
        onSuccess: () => {
            showCategoryModal.value = false;
            categoryForm.reset();
        }
    });
};

const showFlash = ref(true);
</script>

<template>
    <Head title="Registrar Incidencia" />

    <SidebarProvider>
        <AppSidebar>
            <!-- CONTENEDOR PRINCIPAL SIN SCROLL HORIZONTAL -->
            <div class="flex flex-col h-screen max-h-screen bg-gray-50 p-6 overflow-hidden">
                <div class="flex flex-col w-full h-full max-w-5xl mx-auto">
                    
                    <!-- ENCABEZADO -->
                    <div class="flex-none mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Registrar Incidencia</h1>
                        <p class="text-sm text-gray-500">Capture permisos, vacaciones o justificaciones para el personal.</p>
                    </div>

                    <!-- ALERTAS -->
                    <div v-if="$page.props.flash.success && showFlash" class="flex-none mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5" />
                            <span class="font-medium">{{ $page.props.flash.success }}</span>
                        </div>
                        <button @click="showFlash = false" class="text-green-500 hover:text-green-700"><X class="h-4 w-4" /></button>
                    </div>
                    <div v-if="$page.props.flash.error && showFlash" class="flex-none mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2">
                            <AlertCircle class="h-5 w-5" />
                            <span class="font-medium">{{ $page.props.flash.error }}</span>
                        </div>
                        <button @click="showFlash = false" class="text-red-500 hover:text-red-700"><X class="h-4 w-4" /></button>
                    </div>

                    <!-- TARJETA DEL FORMULARIO -->
                    <div class="flex-1 bg-white shadow-lg rounded-xl border border-gray-200 overflow-hidden flex flex-col">
                        <div class="flex-1 overflow-y-auto p-8">
                            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                
                                <!-- SECCIÓN 1: ¿QUIÉN? -->
                                <div class="md:col-span-12 border-b border-gray-100 pb-2 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                        <User class="h-5 w-5 text-gray-400" /> Datos del Empleado
                                    </h3>
                                </div>

                                <!-- AUTOCOMPLETE DE EMPLEADO -->
                                <div class="md:col-span-12 relative">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar y Seleccionar Empleado</label>
                                    
                                    <!-- Caja de búsqueda unificada -->
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <Loader2 v-if="searching" class="h-4 w-4 text-blue-500 animate-spin" />
                                            <Search v-else class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <input 
                                            type="text"
                                            v-model="search"
                                            @focus="showDropdown = true"
                                            @blur="closeDropdown"
                                            class="pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm h-10 transition-all"
                                            :class="{'border-red-500': form.errors.employee_id, 'rounded-b-none': showDropdown && employees.length > 0}"
                                            placeholder="Escriba nombre o ID..."
                                            autocomplete="off"
                                        />
                                        <!-- Indicador visual de selección -->
                                        <div v-if="form.employee_id" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <CheckCircle class="h-4 w-4 text-green-500" />
                                        </div>
                                    </div>

                                    <!-- Lista Desplegable (Dropdown) -->
                                    <div v-if="showDropdown && employees.length > 0" class="absolute z-50 w-full bg-white border border-gray-300 border-t-0 rounded-b-lg shadow-lg max-h-60 overflow-y-auto">
                                        <ul>
                                            <li 
                                                v-for="emp in employees" 
                                                :key="emp.id" 
                                                @mousedown.prevent="selectEmployee(emp)"
                                                class="px-4 py-2 hover:bg-red-50 cursor-pointer transition-colors border-b border-gray-50 last:border-0"
                                            >
                                                <div class="font-medium text-gray-900 text-sm">{{ emp.first_name }} {{ emp.last_name }}</div>
                                                <div class="text-xs text-gray-500 flex gap-2">
                                                    <span>ID: {{ emp.emp_code }}</span>
                                                    <span v-if="emp.department_name" class="text-gray-300">|</span>
                                                    <span v-if="emp.department_name">{{ emp.department_name }}</span>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Mensaje si no hay resultados -->
                                    <div v-if="showDropdown && search && employees.length === 0 && !searching" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg p-3 text-center text-sm text-gray-500 mt-1">
                                        No se encontraron coincidencias.
                                    </div>

                                    <p v-if="form.errors.employee_id" class="text-red-600 text-xs mt-1 font-medium">{{ form.errors.employee_id }}</p>
                                </div>

                                <!-- SECCIÓN 2: ¿QUÉ Y CUÁNDO? -->
                                <div class="md:col-span-12 border-b border-gray-100 pb-2 mb-2 mt-4">
                                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                        <FileText class="h-5 w-5 text-gray-400" /> Detalle de la Incidencia
                                    </h3>
                                </div>

                                <!-- Tipo de Permiso -->
                                <div class="md:col-span-12 lg:col-span-6">
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="block text-sm font-medium text-gray-700">Tipo de Permiso</label>
                                        <button type="button" @click="showCategoryModal = true" class="text-xs text-blue-600 hover:text-blue-800 font-semibold hover:underline flex items-center gap-1">
                                            <PlusCircle class="h-3 w-3" /> Nuevo Tipo
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <select v-model="form.category_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 h-10 appearance-none bg-white">
                                            <option value="" disabled>Seleccione...</option>
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }} ({{ cat.code }})</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                            <ChevronDown class="h-4 w-4 text-gray-400" />
                                        </div>
                                    </div>
                                    <p v-if="form.errors.category_id" class="text-red-600 text-xs mt-1 font-medium">{{ form.errors.category_id }}</p>
                                </div>

                                <!-- Fechas (Grid interno) -->
                                <div class="md:col-span-12 lg:col-span-6 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center gap-1">
                                            <Calendar class="h-3 w-3 text-gray-400" /> Inicia
                                        </label>
                                        <input type="datetime-local" v-model="form.start_time" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 h-10 text-sm" />
                                        <p v-if="form.errors.start_time" class="text-red-600 text-xs mt-1 font-medium">{{ form.errors.start_time }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center gap-1">
                                            <Calendar class="h-3 w-3 text-gray-400" /> Termina
                                        </label>
                                        <input type="datetime-local" v-model="form.end_time" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 h-10 text-sm" />
                                        <p v-if="form.errors.end_time" class="text-red-600 text-xs mt-1 font-medium">{{ form.errors.end_time }}</p>
                                    </div>
                                </div>

                                <!-- Motivo -->
                                <div class="md:col-span-12">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo / Justificación</label>
                                    <textarea 
                                        v-model="form.reason" 
                                        rows="3" 
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm" 
                                        placeholder="Describa brevemente la razón..."
                                    ></textarea>
                                    <p v-if="form.errors.reason" class="text-red-600 text-xs mt-1 font-medium">{{ form.errors.reason }}</p>
                                </div>
                            </form>
                        </div>

                        <!-- FOOTER DEL FORMULARIO (Fijo abajo) -->
                        <div class="flex-none bg-gray-50 px-8 py-4 border-t border-gray-200 flex justify-end">
                            <button 
                                @click="submit" 
                                :disabled="form.processing"
                                class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-900 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 mr-2 animate-spin" />
                                <Save v-else class="h-4 w-4 mr-2" />
                                Guardar Incidencia
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </AppSidebar>

        <!-- MODAL: NUEVA CATEGORÍA -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative animate-in fade-in zoom-in duration-200 border border-gray-200">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-lg font-bold text-gray-900">Nueva Categoría</h3>
                    <button @click="showCategoryModal = false" class="text-gray-400 hover:text-gray-600"><X class="h-5 w-5" /></button>
                </div>
                
                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Nombre</label>
                        <input v-model="categoryForm.name" type="text" placeholder="Ej. Home Office" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 h-9" />
                        <p v-if="categoryForm.errors.name" class="text-red-600 text-xs mt-1">{{ categoryForm.errors.name }}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Código</label>
                            <input v-model="categoryForm.code" type="text" maxlength="5" placeholder="HO" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase h-9" />
                            <p v-if="categoryForm.errors.code" class="text-red-600 text-xs mt-1">{{ categoryForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Unidad</label>
                            <select v-model="categoryForm.unit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 h-9">
                                <option :value="3">Días (3)</option>
                                <option :value="2">Horas (2)</option>
                                <option :value="1">Minutos (1)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-6 pt-2">
                        <button type="button" @click="showCategoryModal = false" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-sm font-medium">Cancelar</button>
                        <button type="submit" :disabled="categoryForm.processing" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm font-medium shadow-sm">Crear Categoría</button>
                    </div>
                </form>
            </div>
        </div>

    </SidebarProvider>
</template>