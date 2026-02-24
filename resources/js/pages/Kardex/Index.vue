<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { defineProps, computed } from 'vue';
import * as kardex from '@/routes/kardex'; 
import * as reglas from '@/routes/reglas'; 
import * as empleado from '@/routes/empleado'; 
import AlertaBaja from '@/components/AlertaBaja.vue';
import { MagnifyingGlassIcon, UserIcon, ChevronDownIcon, DocumentTextIcon, ArrowDownTrayIcon, Cog6ToothIcon, BriefcaseIcon, UserMinusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import {home} from '@/routes';

const props = defineProps({
    datosKardex: Array,
    paginador: Object,
    rangoDeDias: Array,
    filtros: Object,
    listaNominas: Array,
    catalogoPermisos: Object,
});

const form = useForm({
    mes: props.filtros.mes,
    ano: props.filtros.ano,
    quincena: props.filtros.quincena,
    perPage: props.filtros.perPage,
    search: props.filtros.search || '',
    nomina: props.filtros.nomina || '',
    sin_horario: props.filtros.sin_horario || false,
});

function toggleSinHorario() {
    form.sin_horario = !form.sin_horario;
    buscarDatos();
}

const meses = [ { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' }, { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' }, { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' }, { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' } ];
const anos = computed(() => { const anoActual = new Date().getFullYear(); return [anoActual, anoActual - 1, anoActual - 2]; });
const quincenas = [ { value: 0, label: 'Mes Completo' }, { value: 1, label: '1ra Quincena (1-15)' }, { value: 2, label: '2da Quincena (16-Fin)' } ];
const perPageOptions = [10, 20, 50, 200];
const columnasResumen = [ "Ret. Graves", "Ret. Leves", "Justificadas", "Faltas", "Omisiones" ];

const breadcrumbs = [
    { title: 'Bienvenida', href: home().url },
    { title: 'kardex', href: '#'}
]

function buscarDatos() {
    form.post(kardex.buscar().url, { preserveScroll: true });
}

function exportarExcel() {
    const query = new URLSearchParams({
        mes: form.mes, ano: form.ano, quincena: form.quincena, perPage: form.perPage,
        search: form.search || '', nomina: form.nomina || '', sin_horario: form.sin_horario ? '1' : '0',
    }).toString();
    window.location.href = kardex.exportar().url + '?' + query;
}

const mostrarDetallePermiso = (incidenciaObj) => {
    if (!incidenciaObj) return;
    if (incidenciaObj.observaciones) {
        alert(`Categoría: ${incidenciaObj.nombre_permiso || 'Justificación'}\nDetalle/Motivo: ${incidenciaObj.observaciones}`);
    }
};

function getColorForIncidencia(calificacion) {
    if (!calificacion || calificacion.trim() === '') return 'bg-white';
    if (calificacion === 'OK') return 'bg-green-100 text-green-800 font-bold';
    
    switch (calificacion) {
        case 'DESC': return 'bg-gray-200 text-gray-600';
        case 'F': return 'bg-red-200 text-red-800 font-bold';
        case 'RG': return 'bg-orange-600 text-white font-bold';
        case 'RL': return 'bg-orange-200 text-orange-800 font-bold';
        case 'S/E': 
        case 'S/S': return 'bg-yellow-200 text-yellow-800 font-bold';
        case 'J': return 'bg-blue-100 text-blue-800 cursor-pointer hover:bg-blue-300 transition-colors'; 
        default: return 'bg-blue-200 text-blue-800 font-bold'; 
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
    <div>
        <Head title="Kárdex de Incidencias" />
        <div class="w-full p-4 sm:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <DocumentTextIcon class="w-8 h-8 text-blue-600" /> Kárdex de Incidencias
                </h1>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button @click="toggleSinHorario" class="w-full sm:w-auto justify-center flex items-center gap-2 rounded-md border py-2 px-4 font-bold shadow-sm transition-all" :class="form.sin_horario ? 'bg-blue-600 text-white border-blue-700 hover:bg-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                        <UserMinusIcon class="h-5 w-5" :class="form.sin_horario ? 'text-white' : 'text-gray-500'" />
                        <span>{{ form.sin_horario ? 'Viendo Sin Horario' : 'Sin Horario' }}</span>
                    </button>
                    <Link :href="reglas.index().url" class="w-full sm:w-auto justify-center flex items-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                        <Cog6ToothIcon class="h-5 w-5 text-gray-500" /> <span>Reglas</span>
                    </Link>
                </div>
            </div>

            <!-- Filtros omitidos para brevedad, se quedan igual -->
            <div class="my-4 p-4 bg-white rounded-lg shadow-lg grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-9 gap-4">
                <div class="sm:col-span-2 xl:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Buscar</label>
                    <div class="relative mt-1 rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><UserIcon class="h-5 w-5 text-gray-400" /></div>
                        <input type="text" v-model="form.search" @keyup.enter="buscarDatos" class="block w-full rounded-md border-gray-300 pl-10 text-sm focus:border-blue-500" placeholder="Nombre o ID..." />
                    </div>
                </div>
                <div class="sm:col-span-2 xl:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Tipo Nómina</label>
                    <select v-model="form.nomina" @change="buscarDatos" class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm">
                        <option value="">Todas</option>
                        <option v-for="nom in listaNominas" :key="nom.id" :value="nom.id">{{ nom.area_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mes</label>
                    <select v-model="form.mes" @change="buscarDatos" class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm">
                        <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Año</label>
                    <select v-model="form.ano" @change="buscarDatos" class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm">
                        <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Quincena</label>
                    <select v-model="form.quincena" @change="buscarDatos" class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm">
                        <option v-for="q in quincenas" :key="q.value" :value="q.value">{{ q.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mostrar</label>
                    <select v-model="form.perPage" @change="buscarDatos" class="block w-full rounded-md border-gray-300 py-2 px-3 text-sm">
                        <option v-for="pp in perPageOptions" :key="pp" :value="pp">{{ pp }}</option>
                    </select>
                </div>
                <div class="flex items-end"><button @click="buscarDatos" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 py-2 px-4 font-bold text-white hover:bg-blue-700"><MagnifyingGlassIcon class="h-5 w-5" /><span class="hidden xl:inline">Buscar</span></button></div>
                <div class="flex items-end"><button @click="exportarExcel" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white py-2 px-4 font-bold text-gray-700 hover:bg-gray-50"><ArrowDownTrayIcon class="h-5 w-5 text-green-600" /><span class="hidden xl:inline">Excel</span></button></div>
            </div>

            <!-- Tabla -->
            <div class="mt-8 rounded-lg shadow-lg overflow-auto border border-gray-200" style="max-height: 75vh;">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="sticky top-0 left-0 z-30 bg-gray-800 px-2 py-3 text-left text-sm font-semibold border-b border-gray-600" style="min-width: 70px;">ID</th>
                            <th class="sticky top-0 left-[70px] z-30 bg-gray-800 px-3 py-3 text-left text-sm font-semibold border-b border-gray-600" style="min-width: 200px;">Nombre</th>
                            <th class="sticky top-0 left-[270px] z-30 bg-gray-800 px-3 py-3 text-left text-sm font-semibold border-b border-gray-600" style="min-width: 150px;">Nómina</th>
                            <th v-for="dia in rangoDeDias" :key="dia.num" class="sticky top-0 z-20 bg-gray-800 px-2 py-3 text-center border-b border-r border-gray-600" style="min-width: 50px;">
                                <div class="flex flex-col items-center"><span class="text-sm font-bold">{{ dia.num }}</span><span class="text-[10px] font-bold text-cyan-400 uppercase">{{ dia.nombre }}</span></div>
                            </th>
                            <th v-for="(col, index) in columnasResumen" :key="col" class="sticky top-0 right-0 z-30 bg-gray-800 px-2 py-3 text-center text-sm font-semibold border-b border-gray-600" :style="{ minWidth: '90px', right: `${(4 - index) * 90}px` }">{{ col }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-if="datosKardex.length === 0">
                            <td :colspan="rangoDeDias.length + 8" class="px-6 py-10 text-center text-gray-500">No se encontraron resultados.</td>
                        </tr>
                        <tr v-for="fila in datosKardex" :key="fila.emp_code" class="group hover:bg-gray-50">
                            <td class="sticky left-0 z-10 bg-white px-2 py-2 text-sm font-medium border-b group-hover:bg-gray-50">
                                <Link :href="empleado.show({ id: fila.id }).url" class="text-sky-500 hover:font-bold">{{ fila.emp_code }}</Link>
                            </td>
                            <td class="sticky left-[70px] z-10 bg-white px-3 py-2 text-sm border-b group-hover:bg-gray-50">
                                <Link :href="empleado.show({ id: fila.id }).url" class="text-sky-500 hover:font-bold">{{ fila.nombre }}</Link>
                            </td>
                            <td class="sticky left-[270px] z-10 bg-white px-3 py-2 text-sm text-gray-600 border-b group-hover:bg-gray-50">{{ fila.nomina || 'Sin Asignar' }}</td>
                            
                            <td v-for="dia in rangoDeDias" :key="dia.num" class="px-2 py-2 text-sm font-semibold text-center border-b border-r border-gray-100" 
                                :class="[getColorForIncidencia(fila.incidencias_diarias[dia.num]?.calificacion), dia.esFin ? 'brightness-95' : '']" 
                                @click="mostrarDetallePermiso(fila.incidencias_diarias[dia.num])">
                                {{ fila.incidencias_diarias[dia.num]?.calificacion === 'OK' ? '✓' : (fila.incidencias_diarias[dia.num]?.calificacion || '') }}
                            </td>

                            <td class="sticky right-[360px] z-10 bg-white px-2 py-2 text-sm text-center font-bold border-b text-orange-600 group-hover:bg-gray-50">{{ fila.total_rg || 0 }}</td>
                            <td class="sticky right-[270px] z-10 bg-white px-2 py-2 text-sm text-center font-bold border-b text-orange-400 group-hover:bg-gray-50">{{ fila.total_rl || 0 }}</td>
                            <td class="sticky right-[180px] z-10 bg-white px-2 py-2 text-sm text-center font-bold border-b text-blue-600 group-hover:bg-gray-50">{{ fila.total_j || 0 }}</td>
                            <td class="sticky right-[90px] z-10 bg-white px-2 py-2 text-sm text-center font-bold border-b text-red-600 group-hover:bg-gray-50">{{ fila.total_f || 0 }}</td>
                            <td class="sticky right-0 z-10 bg-white px-2 py-2 text-sm text-center font-bold border-b text-yellow-600 group-hover:bg-gray-50">{{ fila.total_omisiones || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="paginador.data.length > 0" class="flex flex-col sm:flex-row justify-between items-center mt-6">
                <div class="text-sm text-gray-700">Mostrando {{ paginador.from }} a {{ paginador.to }} de {{ paginador.total }}</div>
                <nav v-if="paginador.links.length > 3" class="inline-flex rounded-md shadow-sm -space-x-px">
                    <template v-for="(link, index) in paginador.links" :key="index">
                        <Link v-if="link.url" :href="link.url" v-html="link.label" class="px-4 py-2 border text-sm font-medium bg-white hover:bg-gray-50" :class="{ 'bg-blue-50 border-blue-500 text-blue-600': link.active, 'rounded-l-md': index===0, 'rounded-r-md': index===paginador.links.length-1 }" />
                        <span v-else v-html="link.label" class="px-4 py-2 border bg-gray-100 text-gray-400 text-sm font-medium" :class="{ 'rounded-l-md': index===0, 'rounded-r-md': index===paginador.links.length-1 }"></span>
                    </template>
                </nav>
            </div>
        </div>
    </div>
    </AppLayout>
</template>