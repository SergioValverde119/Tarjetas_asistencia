<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { defineProps, computed } from 'vue';
import * as kardex from '@/routes/kardex' // ¡Tu "wayfinder" (correcto!)

// --- Props (Sin cambios) ---
const props = defineProps({
    datosKardex: Array,
    paginador: Object,
    rangoDeDias: Array,
    filtros: Object
});

// --- ¡CAMBIO! Añadimos 'search' al formulario ---
const form = useForm({
    mes: props.filtros.mes,
    ano: props.filtros.ano,
    quincena: props.filtros.quincena,
    perPage: props.filtros.perPage,
    search: props.filtros.search || '' // <-- ¡NUEVO CAMPO!
});

// --- Listas de Filtros (Sin cambios) ---
const meses = [ { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' }, { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' }, { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' }, { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' } ];
const anos = computed(() => { const anoActual = new Date().getFullYear(); return [anoActual, anoActual - 1, anoActual - 2]; });
const quincenas = [ { value: 0, label: 'Mes Completo' }, { value: 1, label: '1ra Quincena (1-15)' }, { value: 2, label: '2da Quincena (16-Fin)' } ];
const perPageOptions = [10, 20, 50, 200];
const columnasResumen = [ "Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas" ];

// --- Función de Búsqueda (Sin cambios, ¡ya funciona!) ---
function buscarDatos() {
    form.post(kardex.buscar(), {
        preserveScroll: true,
    });
}

// --- Función de Colores (Sin cambios, ¡ya funciona!) ---
function getColorForIncidencia(incidencia) {
    if (!incidencia || incidencia.trim() === '') {
        return 'bg-green-100 text-green-800'; // Verde
    }
    switch (incidencia) {
        case 'Descanso': return 'bg-gray-200 text-gray-600'; // Gris
        case 'Falto': return 'bg-red-200 text-red-800'; // Rojo
        case 'Sin Entrada':
        case 'Sin Salida': return 'bg-yellow-200 text-yellow-800'; // Amarillo
        default: return 'bg-blue-200 text-blue-800'; // Azul
    }
}
</script>

<template>
    <div>
        <Head title="Kárdex de Incidencias" />

        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <h1 class="text-2xl font-bold mb-4">
                Kárdex de Incidencias
            </h1>

            <div class="my-4 p-4 bg-gray-50 rounded-lg shadow-sm grid grid-cols-1 sm:grid-cols-6 gap-4">
                
                <div class="sm:col-span-2"> <label for="filtro-search" class="block text-sm font-medium text-gray-700">Buscar (Nombre o ID)</label>
                    <input type="text" id="filtro-search" v-model="form.search"
                           @keyup.enter="buscarDatos"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                           placeholder="Ej: Juan Perez o 12345" />
                </div>

                <div>
                    <label for="filtro-mes" class="block text-sm font-medium text-gray-700">Mes</label>
                    <select id="filtro-mes" v-model="form.mes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                    </select>
                </div>
                
                <div>
                    <label for="filtro-ano" class="block text-sm font-medium text-gray-700">Año</label>
                    <select id="filtro-ano" v-model="form.ano" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                    </select>
                </div>

                <div>
                    <label for="filtro-quincena" class="block text-sm font-medium text-gray-700">Quincena</label>
                    <select id="filtro-quincena" v-model="form.quincena" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option v-for="q in quincenas" :key="q.value" :value="q.value">{{ q.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="filtro-perPage" class="block text-sm font-medium text-gray-700">Mostrar</label>
                    <select id="filtro-perPage" v-model="form.perPage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option v-for="pp in perPageOptions" :key="pp" :value="pp">{{ pp }} por página</option>
                    </select>
                </div>

                <div class="flex items-end sm:col-start-6"> <button @click="buscarDatos" 
                            :disabled="form.processing" 
                            class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition-colors"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                        <span v-if="form.processing">Buscando...</span>
                        <span v-else>Buscar</span>
                    </button>
                </div>
            </div>

            </div>
    </div>
</template>