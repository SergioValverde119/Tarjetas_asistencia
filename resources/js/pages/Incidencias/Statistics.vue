<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Search, Filter, ChevronDown, ChevronRight, 
    MessageSquare, ArrowLeft, Layers
} from 'lucide-vue-next';

interface IncidenciaItem {
    tipo: string;
    simbolo: string;
    desde: string;
    hasta: string;
    motivo: string | null;
    dias: string | number;
}

interface IncidenciaCategory {
    tipo: string;
    simbolo: string;
    veces: number;
    dias: number;
    items: IncidenciaItem[];
}

defineOptions({ layout: AppLayout });

const props = defineProps<{
    empleados: { data: any[]; total: number; links: any[]; };
    filters: any;
}>();

const form = ref({
    search: props.filters.search || '',
    general: props.filters.general || false,
    // Si ya existe un rango, el año debe iniciar vacío para no chocar
    ano: (props.filters.date_start || props.filters.date_end) ? '' : (props.filters.ano || new Date().getFullYear()),
    date_start: props.filters.date_start || '',
    date_end: props.filters.date_end || '',
});

const handleYearInput = () => {
    if (form.value.ano) {
        form.value.date_start = '';
        form.value.date_end = '';
    }
};

const handleRangeInput = () => {
    if (form.value.date_start || form.value.date_end) {
        form.value.ano = '' as any;
    }
};

const expandedId = ref<number | null>(null);
const expandedType = ref<string | null>(null);

const toggleEmployee = (id: number) => {
    if (expandedId.value === id) {
        expandedId.value = null;
        expandedType.value = null;
    } else {
        expandedId.value = id;
        expandedType.value = null;
    }
};

const toggleType = (type: string) => {
    expandedType.value = expandedType.value === type ? null : type;
};

const getGroupedDetails = (detalles: IncidenciaItem[]): IncidenciaCategory[] => {
    if (!detalles) return [];
    const groups = detalles.reduce((acc: Record<string, IncidenciaCategory>, curr: IncidenciaItem) => {
        if (!acc[curr.tipo]) {
            acc[curr.tipo] = { tipo: curr.tipo, simbolo: curr.simbolo, veces: 0, dias: 0, items: [] };
        }
        acc[curr.tipo].veces += 1;
        acc[curr.tipo].dias += Number(curr.dias);
        acc[curr.tipo].items.push(curr);
        return acc;
    }, {});
    return Object.values(groups);
};

const consultar = () => {
    // Limpieza profunda antes de enviar para evitar conflictos en el controlador
    const params: any = { ...form.value };
    if (params.date_start || params.date_end) {
        params.ano = null; 
    }
    router.get('/incidencias/estadisticas', params, { preserveState: true, replace: true });
};

const formatFecha = (fecha: string) => fecha ? fecha.split(' ')[0] : '---';
</script>

<template>
    <Head title="Estadísticas de Incidencias" />

    <div class="flex flex-col h-screen bg-slate-50 overflow-hidden w-full font-sans text-slate-900">
        
        <!-- HEADER -->
        <div class="bg-white border-b border-slate-200 p-4 shrink-0 shadow-sm z-40">
            <div class="w-full flex flex-col md:flex-row items-end gap-4 px-2">
                <div class="flex-1 space-y-2 w-full">
                    <div class="flex items-center gap-2">
                        <Link href="/incidencias" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <ArrowLeft class="w-5 h-5" />
                        </Link>
                        <h1 class="text-xl font-black text-slate-800 uppercase tracking-tight">Estadísticas de Incidencias</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.general" class="rounded border-slate-300 text-emerald-600">
                            <span class="text-[10px] font-black uppercase text-slate-500">Modo General</span>
                        </label>
                        <div class="relative min-w-[250px] flex-1" :class="{'opacity-40 pointer-events-none': form.general}">
                            <Search class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                            <input v-model="form.search" type="text" placeholder="Buscar empleado..." class="pl-10 w-full h-10 rounded-xl border-slate-200 text-sm font-bold shadow-sm bg-white" @keyup.enter="consultar">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 items-end">
                    <div class="w-32">
                        <label class="text-[9px] font-black uppercase text-slate-400 block mb-1">Año</label>
                        <input type="number" v-model="form.ano" @input="handleYearInput" placeholder="Ej. 2026" class="w-full h-10 rounded-xl border-slate-200 text-sm font-black text-center shadow-sm">
                    </div>
                    <div class="w-40">
                        <label class="text-[9px] font-black uppercase text-slate-400 block mb-1">Desde</label>
                        <input type="date" v-model="form.date_start" @input="handleRangeInput" class="w-full h-10 rounded-xl border-slate-200 text-sm font-bold shadow-sm">
                    </div>
                    <div class="w-40">
                        <label class="text-[9px] font-black uppercase text-slate-400 block mb-1">Hasta</label>
                        <input type="date" v-model="form.date_end" @input="handleRangeInput" class="w-full h-10 rounded-xl border-slate-200 text-sm font-bold shadow-sm">
                    </div>
                </div>

                <button @click="consultar" class="bg-slate-900 hover:bg-black text-white px-8 h-10 rounded-xl text-[11px] font-black uppercase tracking-[0.1em] flex items-center gap-2 transition-all shadow-lg active:scale-95 cursor-pointer">
                    <Filter class="w-4 h-4" /> Generar Análisis
                </button>
            </div>
        </div>

        <!-- VISOR -->
        <div class="flex-1 overflow-hidden flex flex-col p-4 w-full">
            <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col w-full">
                <div class="overflow-y-auto flex-1 scrollbar-custom relative">
                    <table class="w-full text-left border-collapse table-fixed">
                        <!-- CABECERA DE LISTA 1 (PEGADIZA) -->
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 sticky top-0 z-30 border-b shadow-sm">
                            <tr>
                                <th class="p-4 w-14 text-center"></th>
                                <th class="p-4 w-1/3 text-left">Empleado</th>
                                <th class="p-4 text-center">Tipos Usados</th>
                                <th class="p-4 text-right pr-12">Días Justificados</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <template v-for="emp in empleados.data" :key="emp.id">
                                <!-- NIVEL 1: EMPLEADO (NOMBRE PEGADIZO CUANDO SE EXPANDE) -->
                                <tr 
                                    @click="toggleEmployee(emp.id)" 
                                    class="cursor-pointer bg-white hover:bg-slate-50 transition-colors"
                                    :class="{ 'sticky top-[41px] z-20 shadow-md ring-1 ring-slate-200': expandedId === emp.id }"
                                >
                                    <td class="p-4 text-center">
                                        <ChevronRight v-if="expandedId !== emp.id" class="w-4 h-4 text-slate-300" />
                                        <ChevronDown v-else class="w-4 h-4 text-emerald-600" />
                                    </td>
                                    <td class="p-4 text-left">
                                        <div class="font-black text-slate-800 text-sm uppercase leading-none">{{ emp.first_name }} {{ emp.last_name }}</div>
                                        <div class="text-[9px] font-black text-slate-400 mt-1 uppercase">ID: {{ emp.emp_code }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-600 font-black text-xs">
                                            {{ getGroupedDetails(emp.detalles).length }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right pr-12 font-black text-blue-700">
                                        {{ emp.total_dias_periodo }} <span class="text-[9px] uppercase opacity-50">días</span>
                                    </td>
                                </tr>

                                <!-- NIVEL 2: CATEGORÍA (VERDE SÓLIDO) -->
                                <tr v-if="expandedId === emp.id">
                                    <td colspan="4" class="p-0 bg-emerald-600 border-y border-emerald-700">
                                        <div class="p-4 space-y-2">
                                            <div v-for="cat in getGroupedDetails(emp.detalles)" :key="cat.tipo" class="overflow-hidden rounded-xl border border-emerald-500 shadow-md bg-emerald-700">
                                                <!-- CABECERA NIVEL 2 (YA NO ES PEGADIZA) -->
                                                <div 
                                                    @click="toggleType(cat.tipo)" 
                                                    class="flex justify-between items-center p-4 cursor-pointer transition-all" 
                                                    :class="expandedType === cat.tipo ? 'bg-emerald-800 text-white shadow-lg' : 'bg-emerald-700 text-emerald-50 hover:bg-emerald-800'"
                                                >
                                                    <div class="flex items-center gap-3">
                                                        <Layers class="w-4 h-4 opacity-50" />
                                                        <span class="font-black text-xs uppercase tracking-wider">{{ cat.tipo }}</span>
                                                        <span class="text-[9px] font-black bg-emerald-900/50 px-2 py-0.5 rounded border border-emerald-500/30">{{ cat.simbolo }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-8">
                                                        <div class="text-center">
                                                            <div class="text-[8px] font-black uppercase opacity-60 leading-none mb-1">Días Acumulados</div>
                                                            <div class="font-black text-sm">{{ cat.dias }}</div>
                                                        </div>
                                                        <div class="text-center">
                                                            <div class="text-[8px] font-black uppercase opacity-60 leading-none mb-1">Veces tramitado</div>
                                                            <div class="font-black text-sm">{{ cat.veces }}</div>
                                                        </div>
                                                        <ChevronDown :class="{'rotate-180': expandedType === cat.tipo}" class="w-4 h-4 transition-transform opacity-50" />
                                                    </div>
                                                </div>

                                                <!-- NIVEL 3: DETALLE (AZUL SÓLIDO) -->
                                                <div v-if="expandedType === cat.tipo" class="bg-blue-600 p-4 border-t border-emerald-800">
                                                    <div class="bg-blue-700 rounded-lg border border-blue-500 overflow-hidden shadow-inner max-h-[600px] overflow-y-auto">
                                                        <table class="w-full text-[11px] border-collapse text-blue-50">
                                                            <thead class="text-[9px] font-black text-blue-200 uppercase border-b border-blue-500 bg-blue-800 shadow-sm">
                                                                <tr>
                                                                    <th class="py-3 px-4 text-center w-16">Número</th>
                                                                    <th class="py-3 text-center">Fecha Inicio</th>
                                                                    <th class="py-3 text-center">Fecha Final</th>
                                                                    <th class="py-3 text-center w-16">Días</th>
                                                                    <th class="py-3 text-left pl-6">Motivo del Permiso</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-blue-500/30">
                                                                <tr v-for="(item, idx) in cat.items" :key="idx" class="hover:bg-blue-500/20 transition-colors">
                                                                    <td class="py-3 px-4 text-center text-blue-300 font-mono">{{ idx + 1 }}</td>
                                                                    <td class="py-3 text-center font-bold">{{ formatFecha(item.desde) }}</td>
                                                                    <td class="py-3 text-center font-bold">{{ formatFecha(item.hasta) }}</td>
                                                                    <td class="py-3 text-center font-black text-white bg-blue-800/40">{{ item.dias }}</td>
                                                                    <td class="py-3 pl-6 italic text-blue-100 opacity-80 leading-snug">{{ item.motivo || 'Sin observaciones.' }}</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                <div class="bg-slate-50 p-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4 px-6 z-40">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Empleados encontrados: {{ empleados.total }}</span>
                    <div class="flex gap-1.5">
                        <Link v-for="link in empleados.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3.5 py-1.5 rounded-xl text-xs font-black transition-all border shadow-sm" :class="link.active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-400 border-slate-200 hover:bg-slate-50'" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-custom::-webkit-scrollbar { width: 5px; height: 5px; }
.scrollbar-custom::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.scrollbar-custom::-webkit-scrollbar-track { background: transparent; }

/* Efecto suave para los pegadizos */
.sticky {
    position: sticky;
    will-change: transform;
}
</style>