<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { defineProps, computed } from 'vue';
import * as kardex from '@/routes/kardex'; 
import * as reglas from '@/routes/reglas'; 
import * as empleado from '@/routes/empleado'; 

import AlertaBaja from '@/components/AlertaBaja.vue';

import { 
    MagnifyingGlassIcon, 
    UserIcon, 
    ChevronDownIcon,
    DocumentTextIcon,
    ArrowDownTrayIcon,
    Cog6ToothIcon,
    BriefcaseIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    datosKardex: Array,
    paginador: Object,
    rangoDeDias: Array,
    filtros: Object,
    listaNominas: Array,
});

const form = useForm({
    mes: props.filtros.mes,
    ano: props.filtros.ano,
    quincena: props.filtros.quincena,
    perPage: props.filtros.perPage,
    search: props.filtros.search || '',
    nomina: props.filtros.nomina || '',
});

const meses = [ { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' }, { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' }, { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' }, { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' } ];

const anos = computed(() => { const anoActual = new Date().getFullYear(); return [anoActual, anoActual - 1, anoActual - 2]; });
const quincenas = [ { value: 0, label: 'Mes Completo' }, { value: 1, label: '1ra Quincena (1-15)' }, { value: 2, label: '2da Quincena (16-Fin)' } ];
const perPageOptions = [10, 20, 50, 200];
const columnasResumen = [ "Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas" ];

function buscarDatos() {
    form.post(kardex.buscar().url, {
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
        nomina: form.nomina || '',
    }).toString();
    
    window.location.href = kardex.exportar().url + '?' + query;
}

function getColorForIncidencia(incidencia) {
    if (!incidencia || incidencia.trim() === '') {
        return 'bg-white'; 
    }
    if (incidencia === 'OK') {
        return 'bg-green-100 text-green-800 font-bold';
    }
    
    switch (incidencia) {
        case 'Descanso': return 'bg-gray-200 text-gray-600';
        case 'Falto': return 'bg-red-200 text-red-800 font-bold';
        case 'R': return 'bg-orange-200 text-orange-800 font-bold';
        case 'Sin Entrada':
        case 'Sin Salida': return 'bg-yellow-200 text-yellow-800 font-bold';
        default: return 'bg-blue-200 text-blue-800 font-bold'; 
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
                
                <Link :href="reglas.index().url" class="flex items-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <Cog6ToothIcon class="h-5 w-5 text-gray-500" />
                    <span>Reglas de Mapeo</span>
                </Link>
            </div>

            <AlertaBaja />

            <!-- Filtros -->
            <div class="my-4 p-4 bg-white rounded-lg shadow-lg grid grid-cols-1 lg:grid-cols-9 gap-4">
                
                <div class="lg:col-span-2">
                    <label for="filtro-search" class="block text-base font-medium text-gray-700">Buscar</label>
                    <div class="relative mt-1 rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <UserIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="text" id="filtro-search" v-model="form.search"
                               @keyup.enter="buscarDatos"
                               class="block w-full rounded-md border-gray-300 pl-10 text-base focus:border-blue-500 focus:ring-blue-500"
                               placeholder="Nombre o ID..." />
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label for="filtro-nomina" class="block text-base font-medium text-gray-700">Tipo Nómina</label>
                    <div class="relative mt-1">
                        <select id="filtro-nomina" v-model="form.nomina" 
                                @change="buscarDatos"
                                class="block w-full appearance-none rounded-md border-gray-300 py-2 px-3 pr-10 shadow-sm text-base focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <option v-for="nom in listaNominas" :key="nom.id" :value="nom.id">
                                {{ nom.area_name }}
                            </option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <BriefcaseIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                    </div>
                </div>

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

                <div class="flex items-end">
                    <button @click="buscarDatos" 
                            :disabled="form.processing" 
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-transparent bg-blue-600 py-2 px-4 font-bold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                        <MagnifyingGlassIcon class="h-5 w-5" />
                        <span class="hidden xl:inline">Buscar</span>
                    </button>
                </div>

                <div class="flex items-end">
                    <button @click="exportarExcel" 
                            :disabled="form.processing" 
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }">
                        <ArrowDownTrayIcon class="h-5 w-5 text-green-600" />
                        <span class="hidden xl:inline">Excel</span>
                    </button>
                </div>
            </div>

            <!-- Tabla con Doble Sticky -->
            <div class="mt-8 rounded-lg shadow-lg overflow-auto border border-gray-200" style="max-height: 75vh;">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th scope="col" class="sticky top-0 left-0 z-30 bg-gray-800 px-2 py-3 text-left text-sm font-semibold text-white uppercase tracking-wider border-b border-gray-600" style="min-width: 70px;">
                                ID
                            </th>
                            <th scope="col" class="sticky top-0 left-[70px] z-30 bg-gray-800 px-3 py-3 text-left text-sm font-semibold text-white uppercase tracking-wider border-b border-gray-600" style="min-width: 200px;">
                                Nombre
                            </th>
                             <th scope="col" class="sticky top-0 left-[270px] z-30 bg-gray-800 px-3 py-3 text-left text-sm font-semibold text-white uppercase tracking-wider border-b border-gray-600" style="min-width: 150px;">
                                Nómina
                            </th>

                            <th v-for="dia in rangoDeDias" :key="dia.num" scope="col" 
                                class="sticky top-0 z-20 bg-gray-800 px-2 py-3 text-center border-b border-gray-600 border-r border-gray-600"
                                style="min-width: 50px;">
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-bold text-white">{{ dia.num }}</span>
                                    <span class="text-[10px] font-bold text-cyan-400 uppercase mt-0.5">{{ dia.nombre }}</span>
                                </div>
                            </th>

                            <th v-for="(col, index) in columnasResumen" :key="col" scope="col" 
                                class="sticky top-0 right-0 z-30 bg-gray-800 px-2 py-3 text-center text-sm font-semibold text-white uppercase tracking-wider border-b border-gray-600"
                                :style="{ minWidth: '90px', right: col === 'Faltas' ? '0px' : col === 'Omisiones' ? '90px' : col === 'Retardos' ? '180px' : col === 'Permisos' ? '270px' : '360px' }">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-if="datosKardex.length === 0">
                            <td :colspan="rangoDeDias.length + 8" class="px-6 py-10 whitespace-nowrap text-base text-gray-500 text-center border-b border-gray-200">
                                <div class="flex flex-col items-center justify-center">
                                    <MagnifyingGlassIcon class="w-12 h-12 text-gray-400" />
                                    <span class="mt-2 text-lg font-medium">No se encontraron resultados</span>
                                    <span class="text-base text-gray-400">Intenta ajustar tus filtros.</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="fila in datosKardex" :key="fila.emp_code" class="group hover:bg-gray-50">
                            <td class="sticky left-0 z-10 bg-white px-2 py-2 whitespace-nowrap text-base font-medium text-gray-900 border-b border-gray-200 group-hover:bg-gray-50">
                                <!-- CAMBIO: Color Sky-500 (Azul Cielo Pastel) -->
                                <Link :href="empleado.show({ id: fila.id }).url" class="text-sky-500 hover:text-sky-700 hover:underline font-bold">
                                    {{ fila.emp_code }}
                                </Link>
                            </td>
                            <td class="sticky left-[70px] z-10 bg-white px-3 py-2 whitespace-nowrap text-base text-gray-800 border-b border-gray-200 group-hover:bg-gray-50">
                                <!-- CAMBIO: Color Sky-500 -->
                                <Link :href="empleado.show({ id: fila.id }).url" class="text-sky-500 hover:text-sky-700 hover:underline font-semibold">
                                    {{ fila.nombre }}
                                </Link>
                            </td>
                            <td class="sticky left-[270px] z-10 bg-white px-3 py-2 whitespace-nowrap text-sm text-gray-600 border-b border-gray-200 group-hover:bg-gray-50">
                                {{ fila.nomina || 'Sin Asignar' }}
                            </td>
                            
                            <td v-for="dia in rangoDeDias" :key="dia.num" 
                                class="px-2 py-2 whitespace-nowrap text-sm font-semibold text-center border-b border-r border-gray-100"
                                :class="[
                                    getColorForIncidencia(fila.incidencias_diarias[dia.num]),
                                    dia.esFin ? 'brightness-95' : '' 
                                ]">
                                {{ fila.incidencias_diarias[dia.num] === 'OK' ? '✓' : (fila.incidencias_diarias[dia.num] || '') }}
                            </td>

                            <td class="sticky right-[360px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-gray-600 text-center font-bold border-b border-gray-200 group-hover:bg-gray-50">{{ fila.total_vacaciones || 0 }}</td>
                            <td class="sticky right-[270px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-gray-600 text-center font-bold border-b border-gray-200 group-hover:bg-gray-50">{{ fila.total_permisos || 0 }}</td>
                            <td class="sticky right-[180px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-orange-600 text-center font-bold border-b border-gray-200 group-hover:bg-gray-50">{{ fila.total_retardos || 0 }}</td>
                            <td class="sticky right-[90px] z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-yellow-600 text-center font-bold border-b border-gray-200 group-hover:bg-gray-50">{{ fila.total_omisiones || 0 }}</td>
                            <td class="sticky right-0 z-10 bg-white px-2 py-2 whitespace-nowrap text-base text-red-600 text-center font-bold border-b border-gray-200 group-hover:bg-gray-50">{{ fila.total_faltas || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
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