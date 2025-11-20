<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { defineProps, ref, onMounted, computed } from 'vue';
import { 
    CheckCircleIcon, 
    MagnifyingGlassIcon,
    ArrowLeftIcon, 
    InboxArrowDownIcon,
    Cog6ToothIcon 
} from '@heroicons/vue/24/outline';
import * as reglas from '@/routes/reglas'; // Rutas para esta página
import * as kardex from '@/routes/kardex'; // Ruta para el botón "Volver"

const props = defineProps({
    categoriasBioTime: Array,
    mapeosGuardados: Object,
    opcionesDeMapeo: Array,
    limiteFaltas: Number,
    errors: Object,
    flash: Object,
});

// Término de búsqueda
const searchTerm = ref('');

// Formulario principal
const form = useForm({
    limite_faltas: props.limiteFaltas || 3, // Inicializamos con el valor de la BD o 3 por defecto
    mapeos: []
});

// Al cargar, preparamos los datos
onMounted(() => {
    form.mapeos = props.categoriasBioTime.map(categoria => {
        const symbol = categoria.report_symbol || '';
        const categoriaGuardada = props.mapeosGuardados[symbol];
        
        return {
            report_symbol: symbol,
            category_name: categoria.category_name,
            // Si ya la teníamos, usamos esa. Si no, usamos 'OTRO' por defecto.
            nuestra_categoria: categoriaGuardada || 'OTRO' 
        };
    });
});

// Filtro en tiempo real para la tabla
const mapeosFiltrados = computed(() => {
    if (!searchTerm.value) {
        return form.mapeos;
    }
    
    const lowerTerm = searchTerm.value.toLowerCase();
    
    return form.mapeos.filter(mapeo => {
        const symbol = mapeo.report_symbol || '';
        const name = mapeo.category_name || '';
        
        return (
            symbol.toLowerCase().includes(lowerTerm) ||
            name.toLowerCase().includes(lowerTerm)
        );
    });
});

// Función para guardar
function guardarReglas() {
    form.post(reglas.store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <Head title="Reglas de Mapeo" />

        <div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
            
            <!-- Encabezado -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <InboxArrowDownIcon class="w-8 h-8 text-blue-600" />
                    Reglas y Configuración
                </h1>
                <!-- Botón volver -->
                <Link :href="kardex.index()" class="flex items-center gap-2 text-gray-600 hover:text-blue-600 font-medium transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                    Volver al Kárdex
                </Link>
            </div>

            <!-- Notificación de Éxito (CORREGIDA) -->
            <div v-if="flash && flash.success" class="mb-6 p-4 bg-green-100 border border-green-300 text-green-800 rounded-md flex items-center gap-3">
                <CheckCircleIcon class="w-6 h-6" />
                <span class="font-medium">{{ flash.success }}</span>
            </div>

            <!-- TARJETA 1: CONFIGURACIÓN DE ALERTAS -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border border-gray-200">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <Cog6ToothIcon class="w-5 h-5 text-blue-600" />
                    Configuración de Alertas Automáticas
                </h2>
                <div class="max-w-xl">
                    <label for="limite" class="block text-sm font-medium text-gray-700 mb-2">
                        Enviar alerta al administrador cuando un empleado acumule:
                    </label>
                    <div class="flex items-center gap-3">
                        <div class="relative rounded-md shadow-sm w-32">
                            <input 
                                type="number" 
                                id="limite"
                                v-model="form.limite_faltas" 
                                class="block w-full rounded-md border-gray-300 pl-3 pr-12 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                min="1"
                            />
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <span class="text-gray-500 sm:text-sm">Faltas</span>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500">en la quincena actual.</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        * El sistema revisa esto automáticamente todos los días hábiles a las 6:00 PM.
                    </p>
                </div>
            </div>

            <!-- TARJETA 2: MAPEO DE REGLAS -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
                
                <!-- Barra de Herramientas -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                    
                    <!-- Buscador -->
                    <div class="relative w-full sm:w-96">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <input 
                            v-model="searchTerm" 
                            type="text" 
                            class="block w-full rounded-md border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm" 
                            placeholder="Buscar regla (ej: VAC, Permiso...)" 
                        />
                    </div>

                    <!-- Botón Guardar (Arriba) -->
                    <button 
                        @click="guardarReglas" 
                        :disabled="form.processing"
                        class="w-full sm:w-auto flex justify-center items-center gap-2 px-6 py-2 bg-blue-600 text-white font-bold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-all"
                    >
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Todo</span>
                    </button>
                </div>

                <!-- Tabla con Scroll -->
                <div class="overflow-y-auto" style="max-height: 60vh;">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-40">
                                    Símbolo (BioTime)
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Descripción Original
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-72">
                                    Clasificación (Kárdex)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            
                            <!-- Estado Vacío -->
                            <tr v-if="mapeosFiltrados.length === 0">
                                <td colspan="3" class="px-6 py-10 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <MagnifyingGlassIcon class="w-8 h-8 text-gray-300" />
                                        <p>No se encontraron reglas con ese nombre.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Filas -->
                            <tr v-for="(mapeo, index) in mapeosFiltrados" :key="mapeo.report_symbol" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 font-mono">
                                        {{ mapeo.report_symbol || '(Sin Símbolo)' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                    {{ mapeo.category_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select 
                                        v-model="mapeo.nuestra_categoria" 
                                        class="block w-full rounded-md border-gray-300 py-1.5 text-gray-900 shadow-sm focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 cursor-pointer"
                                        :class="{
                                            'bg-green-50 text-green-700 border-green-200 font-semibold': mapeo.nuestra_categoria === 'VACACION',
                                            'bg-blue-50 text-blue-700 border-blue-200 font-semibold': mapeo.nuestra_categoria.includes('PERMISO'),
                                            'bg-red-50 text-red-700 border-red-200 font-semibold': mapeo.nuestra_categoria === 'INCAPACIDAD',
                                            'text-gray-500': mapeo.nuestra_categoria === 'OTRO'
                                        }"
                                    >
                                        <option v-for="opcion in opcionesDeMapeo" :key="opcion" :value="opcion">
                                            {{ opcion }}
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pie de tabla -->
                <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 sm:px-6 flex justify-between items-center">
                    <p class="text-sm text-gray-500">
                        Mostrando {{ mapeosFiltrados.length }} de {{ form.mapeos.length }} reglas.
                    </p>
                    
                    <!-- Botón Guardar (Abajo) -->
                    <button 
                        @click="guardarReglas" 
                        :disabled="form.processing"
                        class="flex justify-center items-center gap-2 px-6 py-2 bg-blue-600 text-white font-bold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition-all"
                    >
                        <span v-if="form.processing">Guardando...</span>
                        <span v-else>Guardar Cambios</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>