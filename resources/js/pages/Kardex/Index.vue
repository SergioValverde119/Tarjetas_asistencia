<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { defineProps, computed } from 'vue';
import * as kardex from '@/routes/kardex'; 
import * as reglas from '@/routes/reglas'; 
import AlertaBaja from '@/components/AlertaBaja.vue';

import { 
    MagnifyingGlassIcon, 
    UserIcon, 
    ChevronDownIcon,
    DocumentTextIcon,
    ArrowDownTrayIcon,
    Cog6ToothIcon,
    BriefcaseIcon // <-- Icono de maletín para Nómina
} from '@heroicons/vue/24/outline';

const props = defineProps({
    datosKardex: Array,
    paginador: Object,
    rangoDeDias: Array,
    filtros: Object,
    listaNominas: Array, // Recibimos la lista (aunque no filtremos, el controller la manda)
});

const form = useForm({
    mes: props.filtros.mes,
    ano: props.filtros.ano,
    quincena: props.filtros.quincena,
    perPage: props.filtros.perPage,
    search: props.filtros.search || '',
    // nomina: props.filtros.nomina || '', // Comentado por ahora si no vas a filtrar
});

// ... (Listas y computed igual que antes) ...
const meses = [ { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' }, { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' }, { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' }, { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' } ];
const anos = computed(() => { const anoActual = new Date().getFullYear(); return [anoActual, anoActual - 1, anoActual - 2]; });
const quincenas = [ { value: 0, label: 'Mes Completo' }, { value: 1, label: '1ra Quincena (1-15)' }, { value: 2, label: '2da Quincena (16-Fin)' } ];
const perPageOptions = [10, 20, 50, 200];
const columnasResumen = [ "Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas" ];

function buscarDatos() {
    form.post(kardex.buscar(), {
        preserveScroll: true,
    });
}

function exportarExcel() {
    const query = new URLSearchParams({
        mes: form.mes,
        ano: form.ano,
        quincena: form.quincena,
        perPage: form.perPage,
        search: form.search || '',
        // nomina: form.nomina || '',
    }).toString();
    
    window.location.href = kardex.exportar() + '?' + query;
}

function getColorForIncidencia(incidencia) {
    if (!incidencia || incidencia.trim() === '') {
        return 'bg-green-100 text-green-800';
    }
    switch (incidencia) {
        case 'Descanso': return 'bg-gray-200 text-gray-600';
        case 'Falto': return 'bg-red-200 text-red-800';
        case 'R': return 'bg-orange-200 text-orange-800';
        case 'Sin Entrada':
        case 'Sin Salida': return 'bg-yellow-200 text-yellow-800';
        default: return 'bg-blue-200 text-blue-800'; 
    }
}
</script>

<template>
    <div>
        <Head title="Kárdex de Incidencias" />

        <div class="w-full p-4 sm:p-6 lg:p-8">
            
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <DocumentTextIcon class="w-8 h-8 text-blue-600" />
                    Kárdex de Incidencias
                </h1>
                
                <Link :href="reglas.index()" class="flex items-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <Cog6ToothIcon class="h-5 w-5 text-gray-500" />
                    <span>Reglas de Mapeo</span>
                </Link>
            </div>

            <AlertaBaja />

            <!-- Filtros -->
            <div class="my-4 p-4 bg-white rounded-lg shadow-lg grid grid-cols-1 lg:grid-cols-8 gap-4">
                
                <!-- Buscador (2 cols) -->
                <div class="lg:col-span-2">
                    <label for="filtro-search" class="block text-base font-medium text-gray-700">Buscar (Nombre o ID)</label>
                    <div class="relative mt-1 rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <UserIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="text" id="filtro-search" v-model="form.search"
                               @keyup.enter="buscarDatos"
                               class="block w-full rounded-md border-gray-300 pl-10 text-base focus:border-blue-500 focus:ring-blue-500"
                               placeholder="Juan Perez o 12345" />
                    </div>
                </div>

                <!-- Mes -->
                <div>
                    <label for="filtro-mes" class="block text-base font-medium text-gray-700">Mes</label>
                    <div class="relative mt-1">
                        <select id="filtro-mes" v-model="form.mes" 
                                @change="buscarDatos"
                                class="block w-full appearance-none rounded-md border-gray-300 py-2 px-3 pr-10 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                            <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <ChevronDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                    </div>
                </div>
                
                <!-- Año -->
                <div>
                    <label for="filtro-ano" class="block text-base font-medium text-gray-700">Año</label>
                    <div class="relative mt-1">
                        <select id="filtro-ano" v-model="form.ano" 
                                @change="buscarDatos"
                                class="block w-full appearance-none rounded-md border-gray-300 py-2 px-3 pr-10 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                            <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <ChevronDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                    </div>
                </div>

                <!-- Quincena -->
                <div>
                    <label for="filtro-quincena" class="block text-base font-medium text-gray-700">Quincena</label>
                    <div class="relative mt-1">
                        <select id="filtro-quincena" v-model="form.quincena" 
                                @change="buscarDatos"
                                class="block w-full appearance-none rounded-md border-gray-300 py-2 px-3 pr-10 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                            <option v-for="q in quincenas" :key="q.value" :value="q.value">{{ q.label }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <ChevronDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                    </div>
                </div>

                <!-- Por Página -->
                <div>
                    <label for="filtro-perPage" class="block text-base font-medium text-gray-700">Mostrar</label>
                    <div class="relative mt-1">
                        <select id="filtro-perPage" v-model="form.perPage" 
                                @change="buscarDatos"
                                class="block w-full appearance-none rounded-md border-gray-300 py-2 px-3 pr-10 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                            <option v-for="pp in perPageOptions" :key="pp" :value="pp">{{ pp }}</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <ChevronDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                    </div>
                </div>

                <!-- Botón Buscar -->
                <div class="flex items-end">
                    <button @click="buscarDatos" 
                            :disabled="form.processing" 
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-transparent bg-blue-600 py-2 px-4 font-bold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                        <MagnifyingGlassIcon class="h-5 w-5" />
                        <span>Buscar</span>
                    </button>
                </div>

                <!-- Botón Excel -->
                <div class="flex items-end">
                    <button @click="exportarExcel" 
                            :disabled="form.processing" 
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                        <ArrowDownTrayIcon class="h-5 w-5 text-green-600" />
                        <span>Excel</span>
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="mt-8 rounded-lg shadow-lg overflow-auto border border-gray-200" style="max-height: 75vh;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <!-- Columna ID -->
                            <th scope="col" class="sticky top-0 left-0 z-30 bg-gray-100 px-2 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 70px;">
                                ID
                            </th>
                            
                            <!-- Columna Nombre (Ajustada) -->
                            <th scope="col" class="sticky top-0 left-[70px] z-30 bg-gray-100 px-3 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 200px;">
                                Nombre
                            </th>

                            <!-- ¡NUEVA COLUMNA NÓMINA! (Sticky) -->
                            <!-- Nota: left-[270px] es 70px (ID) + 200px (Nombre) -->
                            <th scope="col" class="sticky top-0 left-[270px] z-30 bg-gray-100 px-3 py-3 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider" style="min-width: 150px;">
                                Nómina
                            </th>

                            <!-- Días -->
                            <th v-for="dia in rangoDeDias" :key="dia" scope="col" 
                                class="sticky top-0 z-20 bg-gray-100 px-2 py-3 text-center text-sm font-semibold text-gray-600 uppercase tracking-wider"
                                style="min-width: 40px;">
                                {{ dia }}
                            </th>

                            <!-- Resumen -->
                            <th v-for="(col, index) in columnasResumen" :key="col" scope="col" 
                                class="sticky top-0 right-0 z-30 bg-gray-100 px-2 py-3 text-center text-sm font-semibold text-gray-600 uppercase tracking-wider"
                                :style="{ minWidth: '90px', right: col === 'Faltas' ? '0px' : col === 'Omisiones' ? '90px' : col === 'Retardos' ? '180px' : col === 'Permisos' ? '270px' : '360px' }">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="datosKardex.length === 0">
                            <td :colspan="rangoDeDias.length + 8" class="px-6 py-10 whitespace-nowrap text-base text-gray-500 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <MagnifyingGlassIcon class="w-12 h-12 text-gray-400" />
                                    <span class="mt-2 text-lg font-medium">No se encontraron resultados</span>
                                    <span class="text-base text-gray-400">Intenta ajustar tus filtros.</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="fila in datosKardex" :key="fila.emp_code" class="group transition-colors hover:bg-gray-50">
                            <!-- ID -->
                            <td class="sticky left-0 z-10 bg-white px-2 py-2 whitespace-nowrap text-base font-medium text-gray-900 transition-colors group-hover:bg-gray-50">
                                {{ fila.emp_code }}
                            </td>
                            <!-- Nombre -->
                            <td class="sticky left-[70px] z-10 bg-white px-3 py-2 whitespace-nowrap text-base text-gray-800 transition-colors group-hover:bg-gray-50">
                                {{ fila.nombre }}
                            </td>

                            <!-- ¡NUEVA COLUMNA NÓMINA! -->
                            <td class="sticky left-[270px] z-10 bg-white px-3 py-2 whitespace-nowrap text-sm text-gray-600 transition-colors group-hover:bg-gray-50">
                                {{ fila.nomina || 'Sin Asignar' }}
                            </td>
                            
                            <!-- Días -->
                            <td v-for="dia in rangoDeDias" :key="dia" 
                                class="px-2 py-2 whitespace-nowrap text-sm font-semibold text-center"
                                :class="getColorForIncidencia(fila.incidencias_diarias[dia])">
                                {{ fila.incidencias_diarias[dia] || '✓' }}
                            </td>

                            <!-- Resumen -->
                            <td class="sticky right-[360px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-gray-600 text-center font-bold transition-colors group-hover:bg-gray-50">{{ fila.total_vacaciones || 0 }}</td>
                            <td class="sticky right-[270px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-gray-600 text-center font-bold transition-colors group-hover:bg-gray-50">{{ fila.total_permisos || 0 }}</td>
                            <td class="sticky right-[180px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-orange-600 text-center font-bold transition-colors group-hover:bg-gray-50">{{ fila.total_retardos || 0 }}</td>
                            <td class="sticky right-[90px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-yellow-600 text-center font-bold transition-colors group-hover:bg-gray-50">{{ fila.total_omisiones || 0 }}</td>
                            <td class="sticky right-0 z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-red-600 text-center font-bold transition-colors group-hover:bg-gray-50">{{ fila.total_faltas || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div v-if="paginador.data.length > 0" class="flex flex-col sm:flex-row justify-between items-center mt-6">
                <div class="text-base text-gray-700 mb-2 sm:mb-0">
                    Mostrando desde <span class="font-bold">{{ paginador.from }}</span> hasta <span class="font-bold">{{ paginador.to }}</span> de <span class="font-bold">{{ paginador.total }}</span> resultados
                </div>
                <nav v-if="paginador.links.length > 3" class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <template v-for="(link, index) in paginador.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            class="relative inline-flex items-center px-4 py-2 border text-base font-medium transition-colors bg-white border-gray-300 text-gray-500 hover:bg-gray-50"
                            :class="{ 'z-10 bg-blue-50 border-blue-500 text-blue-600': link.active, 'rounded-l-md': index === 0, 'rounded-r-md': index === paginador.links.length - 1 }"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="relative inline-flex items-center px-4 py-2 border bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed text-base font-medium"
                            :class="{ 'rounded-l-md': index === 0, 'rounded-r-md': index === paginador.links.length - 1 }"
                        />
                    </template>
                </nav>
            </div>

        </div>
    </div>
</template>