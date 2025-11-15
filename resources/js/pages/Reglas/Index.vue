<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
// --- CAMBIO: Añadido ref y computed ---
import { defineProps, ref, onMounted, computed } from 'vue';
// --- CAMBIO: Añadido MagnifyingGlassIcon ---
import { CheckCircleIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import * as reglas from '@/routes/reglas'; // <-- Rutas para esta página
import * as kardex from '@/routes/kardex'; // <-- Ruta para el botón "Volver"

const props = defineProps({
    categoriasBioTime: Array,
    mapeosGuardados: Object,
    opcionesDeMapeo: Array,
    errors: Object,
    flash: Object,
});

// --- NUEVO: ref para el término de búsqueda ---
const searchTerm = ref('');

// Usamos useForm para guardar el estado de CADA regla
const form = useForm({
    mapeos: []
});

// Esta función se ejecuta cuando la página carga
// Prepara el formulario 'form.mapeos' con los datos que nos pasó el controlador
onMounted(() => {
    form.mapeos = props.categoriasBioTime.map(categoria => {
        // Buscamos si ya teníamos una regla guardada para este símbolo
        // (Usamos || null para manejar claves vacías)
        const categoriaGuardada = props.mapeosGuardados[categoria.report_symbol || ''];
        
        return {
            report_symbol: categoria.report_symbol || '',
            category_name: categoria.category_name,
            // Si ya la teníamos, usamos esa. Si no, usamos 'OTRO' por defecto.
            nuestra_categoria: categoriaGuardada || 'OTRO' 
        };
    });
});

// --- NUEVO: Lógica de filtrado en tiempo real ---
const mapeosFiltrados = computed(() => {
    if (!searchTerm.value) {
        return form.mapeos; // Si la búsqueda está vacía, muestra todo
    }
    
    const lowerTerm = searchTerm.value.toLowerCase();
    
    return form.mapeos.filter(mapeo => {
        const symbol = mapeo.report_symbol || '';
        const name = mapeo.category_name || '';
        
        // Busca en el símbolo Y en el nombre
        return (
            symbol.toLowerCase().includes(lowerTerm) ||
            name.toLowerCase().includes(lowerTerm)
        );
    });
});


// Función para guardar
function guardarReglas() {
    // Importante: Enviamos el form.mapeos *completo*, no solo el filtrado.
    form.post(reglas.store(), {
        preserveScroll: true,
        // No necesitamos onSuccess, el flash.success lo maneja
    });
}
</script>

<template>
    <div>
        <Head title="Reglas de Mapeo" />

        <div class="w-full p-4 sm:p-6 lg:p-8">
            
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    Reglas de Mapeo de Permisos
                </h1>
                <!-- Usamos la ruta de Wayfinder para volver -->
                <Link :href="kardex.index()" class="text-blue-600 hover:underline">
                    &larr; Volver al Kárdex
                </Link>
            </div>

            <!-- Notificación de Éxito -->
            <div v-if="flash && flash.success" class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-md flex items-center gap-3">
                <CheckCircleIcon class="w-6 h-6" />
                <span>{{ flash.success }}</span>
            </div>

            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg">
                <p class="font-semibold">Instrucciones:</p>
                <p>Clasifica cada "Categoría de BioTime" (el idioma "sucio") en una "Categoría Limpia" (la que usará el Kárdex). Esto solo necesitas hacerlo una vez.</p>
            </div>

            <!-- --- ¡NUEVO CAMPO DE BÚSQUEDA! --- -->
            <div class="mb-4">
                <label for="search-reglas" class="block text-sm font-medium text-gray-700">Buscar Reglas</label>
                <div class="relative mt-1 rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                    </div>
                    <input 
                        type="text" 
                        v-model="searchTerm" 
                        id="search-reglas" 
                        class="block w-full rounded-md border-gray-300 pl-10 text-base focus:border-blue-500 focus:ring-blue-500" 
                        placeholder="Buscar por Símbolo o Nombre (Ej: VAC o Vacaciones)"
                    />
                </div>
            </div>

            <div class="flex justify-end mb-4">
                <button 
                    @click="guardarReglas" 
                    :disabled="form.processing"
                    class="px-5 py-2 bg-blue-600 text-white font-bold rounded-md shadow-sm hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Guardando...' : 'Guardar Reglas' }}
                </button>
            </div>

            <!-- 
            ==================================
            TABLA DE REGLAS (¡CON SCROLL!)
            ==================================
            -->
            <!-- CAMBIO: Añadido overflow-y-auto y max-height -->
            <div class="bg-white rounded-lg shadow-lg overflow-y-auto border border-gray-200" style="max-height: 70vh;">
                <table class="min-w-full divide-y divide-gray-200">
                    <!-- CAMBIO: Añadido sticky top-0 para la cabecera -->
                    <thead class="bg-gray-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 uppercase">Símbolo (BioTime)</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 uppercase">Nombre de Categoría (BioTime)</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 uppercase" style="width: 250px;">Nuestra Categoría (Limpia)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- --- ¡NUEVO! Estado de Filtro Vacío --- -->
                        <tr v-if="mapeosFiltrados.length === 0">
                            <td colspan="3" class="px-6 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <MagnifyingGlassIcon class="w-12 h-12 text-gray-400" />
                                    <span class="mt-2 text-lg font-medium">No se encontraron reglas</span>
                                    <span class="text-base text-gray-400">Intenta con un término de búsqueda diferente.</span>
                                </div>
                            </td>
                        </tr>

                        <!-- --- CAMBIO: Iterando sobre 'mapeosFiltrados' --- -->
                        <tr v-for="(mapeo, index) in mapeosFiltrados" :key="mapeo.report_symbol" class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 bg-gray-200 text-gray-800 rounded-full text-xs font-mono">
                                    {{ mapeo.report_symbol || '(Vacío)' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ mapeo.category_name }}</td>
                            <td class="px-4 py-3">
                                <!-- El v-model se enlaza a la propiedad 'nuestra_categoria' de este mapeo específico -->
                                <select v-model="mapeo.nuestra_categoria" class="block w-full rounded-md border-gray-300 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                                    <option v-for="opcion in opcionesDeMapeo" :key="opcion" :value="opcion">
                                        {{ opcion }}
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end mt-4">
                <button 
                    @click="guardarReglas" 
                    :disabled="form.processing"
                    class="px-5 py-2 bg-blue-600 text-white font-bold rounded-md shadow-sm hover:bg-blue-700 disabled:opacity-50">
                    {{ form.processing ? 'Guardando...' : 'Guardar Reglas' }}
                </button>
            </div>

        </div>
    </div>
</template>