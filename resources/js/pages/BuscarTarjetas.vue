<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import * as kardex from '@/routes/kardex'; 
import { 
    UserCircleIcon, 
    BriefcaseIcon,
    CalendarDaysIcon,
    PhoneIcon,
    EnvelopeIcon,
    IdentificationIcon,
    ArrowLeftIcon,
    TableCellsIcon,
    ListBulletIcon,
    ClockIcon,
    ChevronDownIcon,
    InformationCircleIcon,
    CalendarIcon,
    ChatBubbleLeftRightIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    empleado: Object,
    stats: Object,
    fechaActual: String,
    calendario: Array,
    filtros: Object,
    horario: Object,
    catalogoPermisos: Object,
});

const urlParams = new URLSearchParams(window.location.search);
const currentMonth = urlParams.get('mes') ? parseInt(urlParams.get('mes')) - 1 : new Date().getMonth();
const currentYear = urlParams.get('ano') ? parseInt(urlParams.get('ano')) : new Date().getFullYear();

const form = useForm({
    mes: currentMonth + 1,
    ano: currentYear,
});

const viewMode = ref('list'); // Por defecto abrimos la lista detallada
const loading = ref(false);

// Estado del acordeón de resumen
const expandedSummary = ref(null);

const meses = [ 
    { value: 1, label: 'Enero' }, { value: 2, label: 'Febrero' }, { value: 3, label: 'Marzo' }, 
    { value: 4, label: 'Abril' }, { value: 5, label: 'Mayo' }, { value: 6, label: 'Junio' }, 
    { value: 7, label: 'Julio' }, { value: 8, label: 'Agosto' }, { value: 9, label: 'Septiembre' }, 
    { value: 10, label: 'Octubre' }, { value: 11, label: 'Noviembre' }, { value: 12, label: 'Diciembre' } 
];

const anos = computed(() => { 
    const year = new Date().getFullYear(); 
    return [year, year - 1, year - 2]; 
});

// --- PERSISTENCIA DE FILTROS ---
// Guarda todo el query string actual para volver al Kárdex General sin perder búsquedas
const backUrl = computed(() => {
    const params = new URLSearchParams(window.location.search);
    return `${kardex.index().url}?${params.toString()}`;
});

const actualizarKardex = () => {
    router.get(`/empleado/${props.empleado.id}`, {
        mes: form.mes,
        ano: form.ano,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};

const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const getIncidencia = (dia) => {
    if (!props.stats || !props.stats.incidencias_diarias) return null;
    return props.stats.incidencias_diarias[dia] || null;
};

// --- HELPERS PARA DATOS ROBUSTOS ---
const getCalif = (inc) => {
    if (!inc) return '';
    return typeof inc === 'string' ? inc : (inc.calificacion || '');
};
const getCheckin = (inc) => typeof inc === 'object' && inc?.checkin ? inc.checkin : '--:--';
const getCheckout = (inc) => typeof inc === 'object' && inc?.checkout ? inc.checkout : '--:--';

// Alternar acordeón
const toggleSummary = (tipo) => {
    expandedSummary.value = expandedSummary.value === tipo ? null : tipo;
};

// Agrupar detalles para el acordeón
const getSummaryDetails = (tipo) => {
    const datosAgrupados = {};

    if (props.calendario) {
        props.calendario.forEach(dia => {
            if (dia.type !== 'day' || !dia.incidencia) return;
            
            const calif = getCalif(dia.incidencia);
            let match = false;
            let category = '';

            switch (tipo) {
                case 'faltas': 
                    if (calif === 'F') { match = true; category = 'Días de Falta'; } break;
                case 'rg': 
                    if (calif === 'RG') { match = true; category = 'Retardos Graves'; } break;
                case 'rl': 
                    if (calif === 'RL') { match = true; category = 'Retardos Leves'; } break;
                case 'omisiones': 
                    if (calif === 'S/E' || calif === 'S/S') { match = true; category = `Omisión (${calif})`; } break;
                case 'justificaciones': 
                    if (calif === 'J') { match = true; category = dia.incidencia.nombre_permiso || 'Justificado'; } break;
            }

            if (match) {
                if (!datosAgrupados[category]) datosAgrupados[category] = [];
                const fechaObj = new Date(form.ano, form.mes - 1, dia.day);
                const nombreDia = diasSemana[fechaObj.getDay()];
                datosAgrupados[category].push(`${nombreDia} ${dia.day}`);
            }
        });
    }
    return datosAgrupados;
};

const mostrarDetallePermiso = (incidenciaObj) => {
    if (!incidenciaObj) return;
    const calif = getCalif(incidenciaObj);
    
    // Solo mostramos alerta si es Justificación o tiene alguna observación manual
    if (calif === 'J' || (typeof incidenciaObj === 'object' && incidenciaObj.observaciones)) {
        const nombre = incidenciaObj.nombre_permiso || 'Justificación / Permiso';
        const obs = incidenciaObj.observaciones || 'Registrado por sistema.';
        alert(`📌 ${nombre}\n\nMotivo / Detalle:\n${obs}`);
    }
};

const diasDelMes = computed(() => {
    const days = [];
    const year = form.ano; 
    const month = form.mes - 1; 
    const firstDayOfMonth = new Date(year, month, 1, 12, 0, 0);
    const firstDayIndex = firstDayOfMonth.getDay(); 
    const totalDays = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    for (let i = 0; i < firstDayIndex; i++) {
        days.push({ type: 'empty', id: `empty-${i}` });
    }

    for (let i = 1; i <= totalDays; i++) {
        days.push({
            type: 'day',
            id: i,
            day: i,
            incidencia: getIncidencia(i),
            isToday: i === today.getDate() && month === today.getMonth() && year === today.getFullYear()
        });
    }
    return days;
});

const diasHorario = computed(() => {
    if (!props.horario || !props.horario.dias) return [];
    const dias = [];
    for (let i = 1; i <= 7; i++) {
        const dayIndex = i === 7 ? 0 : i; 
        const diaConfig = props.horario.dias.find(d => d.day_index === dayIndex);
        dias.push({
            nombre: diasSemana[dayIndex],
            activo: !!diaConfig,
            entrada: diaConfig ? diaConfig.in_time.substring(0, 5) : '-',
            salida: diaConfig ? diaConfig.out_time.substring(0, 5) : '-'
        });
    }
    return dias;
});

const getIniciales = (nombre, apellido) => {
    const n = nombre ? nombre.charAt(0) : '';
    const a = apellido ? apellido.charAt(0) : '';
    return (n + a).toUpperCase();
};

const getStatusColor = (valor, tipo) => {
    if (valor === 0) return 'text-gray-900';
    if (tipo === 'faltas' || tipo === 'rg') return 'text-red-600';
    if (tipo === 'rl') return 'text-yellow-600';
    if (tipo === 'omisiones') return 'text-purple-600';
    if (tipo === 'justificaciones') return 'text-blue-600';
    return 'text-gray-900';
};

// --- COLORES BRILLANTES (BLOQUE ENTERO UNIFICADO) ---
// Se usa tanto para la lista vertical como para el calendario de cuadritos
const getBlockColor = (incidenciaObj) => {
    const calif = getCalif(incidenciaObj);
    
    // Si no hay datos (ej. un día futuro) se queda en gris claro
    if (!calif) return 'bg-gray-100 border-gray-200 text-gray-400'; 
    
    // Asistencia
    if (calif === 'OK') return 'bg-green-500 border-green-600 text-white shadow-sm'; 
    
    switch (calif) {
        case 'DESC': return 'bg-gray-400 border-gray-500 text-white shadow-sm'; // Gris para descanso
        case 'F': return 'bg-rose-500 border-rose-600 text-white shadow-sm'; // Falta en Rosa/Rojo
        case 'RG': return 'bg-orange-500 border-orange-600 text-white shadow-sm'; // Retardo Grave
        case 'RL': return 'bg-yellow-400 border-yellow-500 text-black shadow-sm'; // Retardo Leve (Letra oscura para contraste)
        case 'S/E':
        case 'S/S': return 'bg-purple-500 border-purple-600 text-white shadow-sm'; // Omisiones en Morado
        case 'J': return 'bg-blue-500 border-blue-600 text-white shadow-sm'; // Justificado en Azul
        default: return 'bg-white border-gray-200 text-gray-800 shadow-sm'; 
    }
};

</script>

<template>
    <div>
        <Head :title="`${empleado.first_name} ${empleado.last_name}`" />

        <div class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
            
            <div class="mb-4">
                <!-- Botón Volver con Persistencia de Filtros -->
                <Link :href="backUrl" class="flex items-center text-gray-500 hover:text-blue-600 transition-colors font-medium text-sm w-max">
                    <ArrowLeftIcon class="w-4 h-4 mr-1" />
                    Volver al Kárdex General
                </Link>
            </div>

            <!-- TARJETA UNIFICADA: PERFIL COMPACTO -->
            <div class="bg-white overflow-hidden shadow-md rounded-lg mb-6 border border-gray-100">
                <div class="p-5">
                    <!-- Parte Superior: Identidad -->
                    <div class="flex items-center gap-5 pb-4 border-b border-gray-100">
                        <div class="flex-shrink-0">
                            <div v-if="empleado.photo" class="h-16 w-16 rounded-full overflow-hidden ring-2 ring-gray-100 shadow-sm">
                                <img :src="empleado.photo" alt="Foto" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center ring-2 ring-blue-50 shadow-sm">
                                <span class="text-xl font-bold text-white tracking-wider">
                                    {{ getIniciales(empleado.first_name, empleado.last_name) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex-1 w-full">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">
                                        {{ empleado.first_name }} {{ empleado.last_name }}
                                    </h1>
                                    <p class="text-sm text-gray-500 flex items-center gap-2 mt-0.5">
                                        <span>ID:</span>
                                        <span class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 rounded border border-indigo-100">
                                            {{ empleado.emp_code }}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20 shadow-sm">
                                        <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-1.5 animate-pulse"></span> Activo
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parte Inferior: Grilla de Datos Compacta -->
                    <div class="pt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="flex flex-col gap-1 p-2 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-1 text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-1">
                                <BriefcaseIcon class="w-3 h-3" /> Puesto y Área
                            </div>
                            <p class="text-sm font-bold text-gray-900 leading-tight">{{ empleado.position_name || 'Sin Puesto' }}</p>
                            <p class="text-xs text-gray-500">{{ empleado.dept_name || 'Sin Departamento' }}</p>
                            <p class="text-xs text-gray-600 mt-1"><span class="font-semibold text-gray-400">Nómina:</span> {{ empleado.nomina || 'Sin Asignar' }}</p>
                        </div>
                        
                        <div class="flex flex-col gap-1 p-2 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-1 text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-1">
                                <ChatBubbleLeftRightIcon class="w-3 h-3" /> Contacto
                            </div>
                            <div class="flex items-center gap-1.5">
                                <EnvelopeIcon class="w-3 h-3 text-gray-400" />
                                <p class="text-xs text-gray-700 truncate" :title="empleado.email">{{ empleado.email || 'Sin Email' }}</p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <PhoneIcon class="w-3 h-3 text-gray-400" />
                                <p class="text-xs text-gray-700">{{ empleado.mobile || 'Sin Celular' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 p-2 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-1 text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-1">
                                <CalendarIcon class="w-3 h-3" /> Fechas Clave
                            </div>
                            <p class="text-xs text-gray-600"><span class="font-semibold text-gray-400">Contrato:</span> {{ empleado.hire_date || '--' }}</p>
                            <p class="text-xs text-gray-600"><span class="font-semibold text-gray-400">Nacimiento:</span> {{ empleado.birthday ? new Date(empleado.birthday).toLocaleDateString() : '--' }}</p>
                        </div>

                        <div class="flex flex-col gap-1 p-2 rounded-lg bg-gray-50 border border-gray-100 justify-center">
                            <div class="flex items-center gap-1 text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-1">
                                <IdentificationIcon class="w-3 h-3" /> Seguro Social
                            </div>
                            <p class="text-sm font-medium text-gray-900 font-mono">{{ empleado.ssn || '--' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Columna Izquierda: Acordeón de Resumen Mensual -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white shadow-md rounded-lg border border-gray-100 overflow-hidden sticky top-6 z-20">
                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-gray-900">Resumen del Mes</h3>
                            <div class="text-xs text-gray-500 bg-white px-2 py-0.5 rounded border border-gray-200 font-medium shadow-sm">
                                {{ meses[form.mes - 1].label }} {{ form.ano }}
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100">
                            
                            <!-- BLOQUE FALTAS -->
                            <div>
                                <div @click="toggleSummary('faltas')" class="px-5 py-3 flex justify-between items-center hover:bg-rose-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-rose-700 transition-colors">Faltas Injustificadas</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'faltas'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-xl font-bold" :class="getStatusColor(stats.total_f, 'faltas')">{{ stats.total_f }}</span>
                                </div>
                                <div v-if="expandedSummary === 'faltas' && stats.total_f > 0" class="bg-gray-50 px-5 py-2 text-sm space-y-2 border-t border-gray-100">
                                    <div v-for="(fechas, cat) in getSummaryDetails('faltas')" :key="cat">
                                        <div class="flex flex-wrap gap-1.5 mt-1">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-rose-200 text-rose-700 rounded text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE RETARDOS GRAVES -->
                            <div>
                                <div @click="toggleSummary('rg')" class="px-5 py-3 flex justify-between items-center hover:bg-orange-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-orange-700 transition-colors">Retardos Graves</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'rg'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-xl font-bold" :class="getStatusColor(stats.total_rg, 'rg')">{{ stats.total_rg }}</span>
                                </div>
                                <div v-if="expandedSummary === 'rg' && stats.total_rg > 0" class="bg-gray-50 px-5 py-2 text-sm space-y-2 border-t border-gray-100">
                                    <div v-for="(fechas, cat) in getSummaryDetails('rg')" :key="cat">
                                        <div class="flex flex-wrap gap-1.5 mt-1">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-orange-300 text-orange-700 rounded text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE RETARDOS LEVES -->
                            <div>
                                <div @click="toggleSummary('rl')" class="px-5 py-3 flex justify-between items-center hover:bg-yellow-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-yellow-600 transition-colors">Retardos Leves</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'rl'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-xl font-bold" :class="getStatusColor(stats.total_rl, 'rl')">{{ stats.total_rl }}</span>
                                </div>
                                <div v-if="expandedSummary === 'rl' && stats.total_rl > 0" class="bg-gray-50 px-5 py-2 text-sm space-y-2 border-t border-gray-100">
                                    <div v-for="(fechas, cat) in getSummaryDetails('rl')" :key="cat">
                                        <div class="flex flex-wrap gap-1.5 mt-1">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-yellow-300 text-yellow-800 rounded text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE JUSTIFICACIONES -->
                            <div>
                                <div @click="toggleSummary('justificaciones')" class="px-5 py-3 flex justify-between items-center hover:bg-blue-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Justificaciones / Permisos</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'justificaciones'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-xl font-bold" :class="getStatusColor(stats.total_j, 'justificaciones')">{{ stats.total_j }}</span>
                                </div>
                                <div v-if="expandedSummary === 'justificaciones' && stats.total_j > 0" class="bg-gray-50 px-5 py-2 text-sm space-y-3 border-t border-gray-100">
                                    <div v-for="(fechas, cat) in getSummaryDetails('justificaciones')" :key="cat">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-blue-200 text-blue-700 rounded text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE OMISIONES -->
                            <div>
                                <div @click="toggleSummary('omisiones')" class="px-5 py-3 flex justify-between items-center hover:bg-purple-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-purple-600 transition-colors">Omisiones Checada</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'omisiones'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-xl font-bold" :class="getStatusColor(stats.total_omisiones, 'omisiones')">{{ stats.total_omisiones }}</span>
                                </div>
                                <div v-if="expandedSummary === 'omisiones' && stats.total_omisiones > 0" class="bg-gray-50 px-5 py-2 text-sm space-y-3 border-t border-gray-100">
                                    <div v-for="(fechas, cat) in getSummaryDetails('omisiones')" :key="cat">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-purple-200 text-purple-800 rounded text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Vista Detallada de Asistencia -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-md rounded-lg border border-gray-100 overflow-hidden h-full flex flex-col relative">
                        
                        <div v-if="loading" class="absolute inset-0 bg-white/80 z-50 flex items-center justify-center backdrop-blur-sm transition-opacity duration-300">
                            <ClockIcon class="w-8 h-8 text-blue-500 animate-spin" />
                        </div>

                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-3">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-1.5">
                                <CalendarDaysIcon class="w-4 h-4 text-blue-500" />
                                Asistencia Diaria
                            </h3>
                            
                            <div class="flex items-center gap-2">
                                <select v-model="form.mes" @change="actualizarKardex" class="block w-28 rounded border-gray-300 py-1 text-xs focus:border-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                                </select>
                                <select v-model="form.ano" @change="actualizarKardex" class="block w-24 rounded border-gray-300 py-1 text-xs focus:border-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                                </select>
                            </div>

                            <!-- Botones de Vistas -->
                            <div class="flex bg-gray-200 rounded p-1 shadow-inner">
                                <button @click="viewMode = 'list'" class="px-3 py-1 text-xs font-bold rounded transition-all flex items-center gap-1" :class="viewMode === 'list' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ListBulletIcon class="w-3 h-3" /> Detalle
                                </button>
                                <button @click="viewMode = 'monthly'" class="px-3 py-1 text-xs font-bold rounded transition-all flex items-center gap-1" :class="viewMode === 'monthly' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <TableCellsIcon class="w-3 h-3" /> Mes
                                </button>
                                <button @click="viewMode = 'schedule'" class="px-3 py-1 text-xs font-bold rounded transition-all flex items-center gap-1" :class="viewMode === 'schedule' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ClockIcon class="w-3 h-3" /> Turno
                                </button>
                            </div>
                        </div>

                        <div class="p-5 flex-1 bg-gray-50/30">
                            
                            <!-- VISTA LISTA INFINITA (BLOQUES CON COLORES BRILLANTES) -->
                            <div v-if="viewMode === 'list'" class="space-y-2 overflow-y-auto pr-2" style="max-height: 65vh;">
                                <div v-for="dia in diasDelMes.filter(d => d.type === 'day')" :key="dia.day" 
                                     class="flex items-center rounded-lg border p-3 transition-all hover:brightness-95"
                                     :class="[getBlockColor(dia.incidencia), dia.isToday ? 'ring-2 ring-blue-600 ring-offset-2' : '']"
                                >
                                    <!-- Número de Día y Nombre -->
                                    <div class="w-16 text-center border-r border-current/20 pr-3 mr-4 flex flex-col justify-center">
                                        <span class="text-[9px] font-bold uppercase opacity-75">{{ diasSemana[new Date(form.ano, form.mes - 1, dia.day).getDay()] }}</span>
                                        <span class="text-lg font-bold leading-tight">{{ dia.day }}</span>
                                    </div>
                                    
                                    <!-- Horas de Checada -->
                                    <div class="flex-1 flex gap-6 sm:gap-10 items-center">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-bold uppercase mb-0.5 opacity-75">Entrada</span>
                                            <span class="font-mono text-sm font-semibold" :class="getCheckin(dia.incidencia) !== '--:--' ? '' : 'opacity-50'">
                                                {{ getCheckin(dia.incidencia) }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-bold uppercase mb-0.5 opacity-75">Salida</span>
                                            <span class="font-mono text-sm font-semibold" :class="getCheckout(dia.incidencia) !== '--:--' ? '' : 'opacity-50'">
                                                {{ getCheckout(dia.incidencia) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Calificación / Botón de Info -->
                                    <div class="flex items-center justify-end gap-2 w-36">
                                        <!-- Cajita semi-transparente para resaltar la letra -->
                                        <span class="px-2.5 py-1 rounded text-xs font-bold shadow-sm bg-black/10">
                                            <template v-if="getCalif(dia.incidencia) === 'OK'">✓ OK</template>
                                            <template v-else-if="getCalif(dia.incidencia) === 'J'">{{ dia.incidencia?.nombre_permiso || 'J' }}</template>
                                            <template v-else>{{ getCalif(dia.incidencia) || 'F' }}</template>
                                        </span>

                                        <button v-if="getCalif(dia.incidencia) === 'J' || (typeof dia.incidencia === 'object' && dia.incidencia?.observaciones)" 
                                                @click="mostrarDetallePermiso(dia.incidencia)"
                                                class="p-1.5 rounded-full transition-colors flex-shrink-0 hover:bg-black/10"
                                                title="Ver detalle del permiso/justificación">
                                            <InformationCircleIcon class="w-5 h-5" />
                                        </button>
                                        <div v-else class="w-8"></div> <!-- Espaciador para alinear visualmente -->
                                    </div>
                                </div>
                            </div>

                            <!-- VISTA MENSUAL (CALENDARIO COMPACTO) -->
                            <div v-else-if="viewMode === 'monthly'">
                                <div class="grid grid-cols-7 gap-2">
                                    <div v-for="d in diasSemana" :key="d" class="text-center text-[10px] font-bold text-gray-400 uppercase pb-1">{{ d }}</div>
                                    
                                    <div v-for="dia in diasDelMes" :key="dia.id" 
                                        class="aspect-square rounded-lg flex flex-col items-center justify-center text-xs relative border transition-all cursor-default"
                                        :class="[
                                            dia.type === 'empty' ? 'border-transparent bg-transparent' : getBlockColor(dia.incidencia),
                                            dia.isToday ? 'ring-4 ring-blue-600 ring-offset-2 z-10' : '',
                                            (getCalif(dia.incidencia) === 'J') ? 'cursor-pointer hover:shadow-lg hover:scale-105 z-10' : ''
                                        ]"
                                        @click="dia.type === 'day' ? mostrarDetallePermiso(dia.incidencia) : null"
                                        :title="typeof dia.incidencia === 'object' ? dia.incidencia?.nombre_permiso : ''"
                                    >
                                        <template v-if="dia.type === 'day'">
                                            <span class="absolute top-1 left-1.5 text-[10px] font-bold opacity-80">
                                                {{ dia.day }}
                                            </span>
                                            
                                            <!-- Icono Info si es justificado -->
                                            <InformationCircleIcon v-if="getCalif(dia.incidencia) === 'J'" class="absolute top-1 right-1 w-3 h-3 opacity-90" />

                                            <div class="mt-3 font-bold text-center w-full px-0.5 overflow-hidden flex flex-col items-center">
                                                <span v-if="getCalif(dia.incidencia) === 'OK'" class="text-xl">✓</span>
                                                <span v-else class="leading-none block text-[10px] uppercase truncate w-full">
                                                    {{ getCalif(dia.incidencia) === 'J' ? (dia.incidencia?.nombre_permiso || 'J') : (getCalif(dia.incidencia) || 'F') }}
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                             <!-- VISTA HORARIO DEL EMPLEADO -->
                             <div v-else-if="viewMode === 'schedule'">
                                <div v-if="horario && horario.dias && horario.dias.length > 0" class="space-y-4">
                                    <div class="flex items-center gap-3 mb-4 p-4 bg-white rounded-lg border border-gray-100 shadow-sm text-gray-900">
                                        <div class="p-2.5 bg-indigo-50 rounded-full text-indigo-600">
                                            <ClockIcon class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h4 class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Turno Asignado Actualmente</h4>
                                            <p class="text-base font-bold text-gray-800">{{ horario.nombre }}</p>
                                        </div>
                                    </div>

                                    <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                                        <table class="min-w-full divide-y divide-gray-200 text-gray-900">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-600 uppercase">Día</th>
                                                    <th class="px-4 py-2.5 text-center text-xs font-bold text-gray-600 uppercase">Entrada</th>
                                                    <th class="px-4 py-2.5 text-center text-xs font-bold text-gray-600 uppercase">Salida</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr v-for="dia in diasHorario" :key="dia.nombre" :class="dia.activo ? 'hover:bg-gray-50' : 'bg-gray-50/50 text-gray-400'">
                                                    <td class="px-4 py-2.5 text-sm font-semibold">{{ dia.nombre }}</td>
                                                    <td class="px-4 py-2.5 text-sm text-center">
                                                        <span v-if="dia.activo" class="bg-green-100 text-green-800 px-2.5 py-1 rounded text-xs font-mono font-bold border border-green-200">{{ dia.entrada }}</span>
                                                        <span v-else class="text-xs font-medium uppercase">- Descanso -</span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-sm text-center">
                                                        <span v-if="dia.activo" class="bg-blue-100 text-blue-800 px-2.5 py-1 rounded text-xs font-mono font-bold border border-blue-200">{{ dia.salida }}</span>
                                                        <span v-else>-</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center h-48 bg-white rounded-lg border border-gray-100 text-gray-400 shadow-sm">
                                    <ClockIcon class="w-12 h-12 mb-2 opacity-30" />
                                    <p class="font-medium text-sm">Este empleado no tiene un turno asignado vigente.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>