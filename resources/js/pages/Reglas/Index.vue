<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3'; // Agregamos router
import { defineProps, ref, onMounted, computed, watch } from 'vue';
import { 
    CheckCircleIcon, MagnifyingGlassIcon, ArrowLeftIcon, 
    InboxArrowDownIcon, Cog6ToothIcon, PlusIcon, TrashIcon, XMarkIcon
} from '@heroicons/vue/24/outline';
import * as kardex from '@/routes/kardex'; 

const props = defineProps({
    categoriasBioTime: Array,
    mapeosGuardados: Object, 
    misCategorias: Array, 
    misPoliticas: Array,  
    nominas: Array,       
    limiteFaltas: Number,
    errors: Object,
    flash: Object,
});

// --- ESTADO ---
const activeTab = ref('mapping'); 
const searchTerm = ref('');
const showToast = ref(false); // Estado para la notificación flotante

// --- WATCHER PARA MOSTRAR TOAST ---
// Si llega un mensaje flash, mostramos el toast por 3 segundos
watch(() => props.flash, (newFlash) => {
    if (newFlash && newFlash.success) {
        showToast.value = true;
        setTimeout(() => showToast.value = false, 4000);
    }
}, { deep: true });

// --- FORMULARIOS ---

const formMapping = useForm({
    limite_faltas: props.limiteFaltas || 3,
    mapeos: []
});

const formCategory = useForm({
    name: '',
    color: 'blue',
    is_paid: true
});

const formPolicy = useForm({
    leave_category_id: '',
    area_id: '', 
    limit_amount: 1,
    frequency: 'ANUAL'
});

// --- INICIALIZACIÓN ---
onMounted(() => {
    formMapping.mapeos = props.categoriasBioTime.map(categoria => {
        const catIdGuardada = props.mapeosGuardados[String(categoria.id)];
        return {
            id: categoria.id,
            report_symbol: categoria.report_symbol || '',
            category_name: categoria.category_name,
            leave_category_id: catIdGuardada || '' 
        };
    });
});

// --- FILTROS ---
const mapeosFiltrados = computed(() => {
    if (!searchTerm.value) return formMapping.mapeos;
    const lowerTerm = searchTerm.value.toLowerCase();
    return formMapping.mapeos.filter(mapeo => 
        mapeo.report_symbol.toLowerCase().includes(lowerTerm) ||
        mapeo.category_name.toLowerCase().includes(lowerTerm)
    );
});

// --- ACCIONES (USANDO URLs DIRECTAS PARA EVITAR ERRORES) ---

function guardarMapeo() {
    formMapping.post('/reglas', { 
        preserveScroll: true 
    });
}

function crearCategoria() {
    formCategory.post('/reglas/category', {
        onSuccess: () => formCategory.reset(),
        preserveScroll: true
    });
}

function crearPolitica() {
    formPolicy.post('/reglas/policy', {
        onSuccess: () => formPolicy.reset(),
        preserveScroll: true
    });
}

function eliminarPolitica(id) {
    if(!confirm('¿Eliminar esta regla?')) return;
    router.delete(`/reglas/policy/${id}`, { preserveScroll: true });
}
</script>

<template>
    <div>
        <Head title="Configuración" />

        <!-- TOAST DE ÉXITO FLOTANTE (Siempre visible) -->
        <transition enter-active-class="transform ease-out duration-300 transition" enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2" enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showToast && flash.success" class="fixed top-4 right-4 z-50 max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <CheckCircleIcon class="h-6 w-6 text-green-400" aria-hidden="true" />
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm font-medium text-gray-900">¡Guardado!</p>
                            <p class="mt-1 text-sm text-gray-500">{{ flash.success }}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button @click="showToast = false" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <span class="sr-only">Cerrar</span>
                                <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
            
            <!-- Encabezado -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <Cog6ToothIcon class="w-8 h-8 text-blue-600" />
                    Configuración del Sistema
                </h1>
                <Link :href="kardex.index().url" class="flex items-center gap-2 text-gray-600 hover:text-blue-600 font-medium transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                    Volver al Kárdex
                </Link>
            </div>

            <!-- TABS -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'mapping'" :class="[activeTab === 'mapping' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                        1. Mapeo de Etiquetas
                    </button>
                    <button @click="activeTab = 'categories'" :class="[activeTab === 'categories' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                        2. Mis Categorías
                    </button>
                    <button @click="activeTab = 'policies'" :class="[activeTab === 'policies' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                        3. Reglas y Límites
                    </button>
                </nav>
            </div>

            <!-- TAB 1: MAPEO (Existente) -->
            <div v-if="activeTab === 'mapping'">
                <!-- Configuración Global -->
                <div class="bg-white rounded-lg shadow p-6 mb-6 border border-gray-200">
                    <h3 class="text-sm font-bold text-gray-700 uppercase mb-4">Configuración General</h3>
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-gray-600">Alerta de Faltas (Quincenal):</label>
                        <input type="number" v-model="formMapping.limite_faltas" class="w-20 rounded-md border-gray-300 text-sm" min="1">
                        <span class="text-sm text-gray-500">faltas.</span>
                    </div>
                </div>

                <!-- Tabla de Mapeo -->
                <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <div class="relative w-96">
                            <input v-model="searchTerm" type="text" class="block w-full rounded-md border-gray-300 pl-3 text-sm" placeholder="Buscar etiqueta..." />
                        </div>
                        <button @click="guardarMapeo" :disabled="formMapping.processing" class="px-4 py-2 bg-blue-600 text-white font-bold rounded-md text-sm hover:bg-blue-700 disabled:opacity-50">
                            {{ formMapping.processing ? 'Guardando...' : 'Guardar Todo' }}
                        </button>
                    </div>
                    <div class="overflow-y-auto" style="max-height: 60vh;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Símbolo</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Descripción Original</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Se traduce como:</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="mapeo in mapeosFiltrados" :key="mapeo.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-xs font-mono">{{ mapeo.report_symbol || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ mapeo.category_name }}</td>
                                    <td class="px-6 py-4">
                                        <select v-model="mapeo.leave_category_id" class="block w-full rounded-md border-gray-300 py-1 text-sm focus:ring-blue-500 cursor-pointer">
                                            <option value="">-- Sin Asignar (Otro) --</option>
                                            <option v-for="cat in misCategorias" :key="cat.id" :value="cat.id">
                                                {{ cat.name }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CATEGORÍAS -->
            <div v-if="activeTab === 'categories'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Formulario -->
                    <div class="bg-white rounded-lg shadow p-6 h-fit">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Nueva Categoría</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input v-model="formCategory.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Ej. Días Económicos">
                                <p v-if="formCategory.errors.name" class="text-red-500 text-xs mt-1">{{ formCategory.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                <select v-model="formCategory.color" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="blue">Azul</option>
                                    <option value="green">Verde</option>
                                    <option value="red">Rojo</option>
                                    <option value="yellow">Amarillo</option>
                                    <option value="purple">Morado</option>
                                    <option value="gray">Gris</option>
                                </select>
                            </div>
                            <div class="flex items-center">
                                <input v-model="formCategory.is_paid" type="checkbox" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label class="ml-2 block text-sm text-gray-900">¿Es con goce de sueldo?</label>
                            </div>
                            <button @click="crearCategoria" :disabled="formCategory.processing" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                                Crear Categoría
                            </button>
                        </div>
                    </div>

                    <!-- Lista -->
                    <div class="md:col-span-2 bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="cat in misCategorias" :key="cat.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ cat.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="`bg-${cat.color}-100 text-${cat.color}-800`" class="px-2 py-1 rounded text-xs font-bold uppercase">{{ cat.color }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ cat.is_paid ? 'Pagado' : 'Descuento' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: POLÍTICAS (Reglas de Límite) -->
            <div v-if="activeTab === 'policies'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Formulario -->
                    <div class="bg-white rounded-lg shadow p-6 h-fit">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Nueva Regla</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Categoría (Permiso)</label>
                                <select v-model="formPolicy.leave_category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="" disabled>Selecciona...</option>
                                    <option v-for="cat in misCategorias" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                                <p v-if="formPolicy.errors.leave_category_id" class="text-red-500 text-xs mt-1">{{ formPolicy.errors.leave_category_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Aplica a Nómina:</label>
                                <select v-model="formPolicy.area_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">TODAS (Global)</option>
                                    <option v-for="nom in nominas" :key="nom.id" :value="nom.id">{{ nom.area_name }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Límite</label>
                                    <input v-model="formPolicy.limit_amount" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" min="1">
                                    <p v-if="formPolicy.errors.limit_amount" class="text-red-500 text-xs mt-1">{{ formPolicy.errors.limit_amount }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Frecuencia</label>
                                    <select v-model="formPolicy.frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="ANUAL">Anual</option>
                                        <option value="SEMESTRAL">Semestral</option>
                                        <option value="MENSUAL">Mensual</option>
                                        <option value="QUINCENAL">Quincenal</option>
                                    </select>
                                </div>
                            </div>
                            <button @click="crearPolitica" :disabled="formPolicy.processing" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                                Agregar Regla
                            </button>
                        </div>
                    </div>

                    <!-- Lista de Reglas -->
                    <div class="md:col-span-2 bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permiso</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aplica a</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Límite</th>
                                    <th class="px-6 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="pol in misPoliticas" :key="pol.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        {{ pol.category?.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="pol.area_id ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded text-xs font-medium">
                                            {{ pol.area_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ pol.limit_amount }} por {{ pol.frequency }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="eliminarPolitica(pol.id)" class="text-red-600 hover:text-red-900">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</template>