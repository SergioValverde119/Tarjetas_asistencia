<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import * as kardex from '@/routes/kardex'; 
import { 
    UserCircleIcon, 
    BuildingOfficeIcon, 
    BriefcaseIcon,
    CalendarDaysIcon,
    PhoneIcon,
    EnvelopeIcon,
    IdentificationIcon,
    ArrowLeftIcon,
    CurrencyDollarIcon,
    TableCellsIcon,
    ViewColumnsIcon,
    ClockIcon,
    ChevronDownIcon,
    ChevronUpIcon, // Para el acordeón
    ClipboardDocumentListIcon,
    XMarkIcon,
    CalendarIcon,
    ChatBubbleLeftRightIcon, 
    ChartBarIcon 
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
    tipo_permiso: props.filtros?.tipo_permiso || '' 
});

const viewMode = ref('monthly'); 
const loading = ref(false);

// --- ESTADO DEL ACORDEÓN ---
// Guarda el nombre de la sección expandida (ej: 'permisos', 'faltas') o null
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

const categoriasGenerales = [
    { value: 'VACACION', label: 'Vacaciones (Regla)' },
    { value: 'INCAPACIDAD', label: 'Incapacidad (Regla)' },
    { value: 'PERMISO_CON_GOCE', label: 'Permiso con Goce' },
    { value: 'PERMISO_SIN_GOCE', label: 'Permiso sin Goce' },
    { value: 'PERMISO_MATERNIDAD', label: 'Maternidad' },
    { value: 'PERMISO_PATERNIDAD', label: 'Paternidad' },
    { value: 'FALTA_JUSTIFICADA', label: 'Falta Justificada' },
    { value: 'Falto', label: 'Faltas (Sistema)' },
    { value: 'R', label: 'Retardos (Sistema)' },
    { value: 'OTRO', label: 'Otros / Sin Clasificar' }
];

const etiquetasEspecificas = computed(() => {
    if (!props.catalogoPermisos) return [];
    return Object.entries(props.catalogoPermisos).map(([simbolo, nombre]) => ({
        value: simbolo,
        label: `${simbolo} - ${nombre}`
    })).sort((a, b) => a.label.localeCompare(b.label));
});

const actualizarKardex = () => {
    router.get(`/empleado/${props.empleado.id}`, {
        mes: form.mes,
        ano: form.ano,
        tipo_permiso: form.tipo_permiso 
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};

const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const getIncidencia = (dia) => {
    if (!props.stats || !props.stats.incidencias_diarias) return '';
    return props.stats.incidencias_diarias[dia] || '';
};

// --- Lógica para alternar el acordeón ---
const toggleSummary = (tipo) => {
    if (expandedSummary.value === tipo) {
        expandedSummary.value = null; // Cerrar si ya está abierto
    } else {
        expandedSummary.value = tipo; // Abrir
    }
};

// --- Obtener detalles para la lista desplegable ---
const getSummaryDetails = (tipo) => {
    const datosAgrupados = {};

    if (props.calendario) {
        props.calendario.forEach(dia => {
            if (dia.type !== 'day' || !dia.incidencia) return;
            
            let match = false;
            let category = '';

            switch (tipo) {
                case 'faltas':
                    if (dia.incidencia === 'Falto') { match = true; category = 'Días de Falta'; }
                    break;
                case 'retardos':
                    if (dia.incidencia === 'R') { match = true; category = 'Días con Retardo'; }
                    break;
                case 'omisiones':
                    if (dia.incidencia === 'Sin Entrada' || dia.incidencia === 'Sin Salida' || dia.incidencia === 'Sin Turno') { match = true; category = dia.incidencia; }
                    break;
                case 'vacaciones':
                    if (dia.incidencia === 'VACACION') { match = true; category = 'Días de Vacaciones'; }
                    break;
                case 'permisos':
                    const estandar = ['OK', 'Descanso', 'Falto', 'R', 'Sin Entrada', 'Sin Salida', 'Sin Turno', 'VACACION'];
                    if (!estandar.includes(dia.incidencia)) { match = true; category = dia.incidencia; }
                    break;
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

const mostrarDetallePermiso = (simbolo) => {
    const palabrasReservadas = ['OK', 'Descanso', 'Falto', 'R', 'Sin Entrada', 'Sin Salida', 'Sin Turno'];
    if (simbolo && !palabrasReservadas.includes(simbolo)) {
        const significado = props.catalogoPermisos[simbolo] || 'Permiso Desconocido';
        alert(`Etiqueta: ${simbolo}\nClasificación: ${significado}`);
    }
};

// ... (Lógica de calendario y colores sigue igual) ...
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

const diasDeLaSemana = computed(() => {
    const days = [];
    const today = new Date();
    const selectedMonth = form.mes - 1;
    const selectedYear = form.ano;
    const isCurrentRealMonth = selectedMonth === today.getMonth() && selectedYear === today.getFullYear();
    let referenceDate = isCurrentRealMonth ? new Date(today) : new Date(selectedYear, selectedMonth, 1, 12, 0, 0);
    const startOfWeek = new Date(referenceDate);
    startOfWeek.setDate(referenceDate.getDate() - referenceDate.getDay());

    for (let i = 0; i < 7; i++) {
        const d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        const isLoadedMonth = d.getMonth() === selectedMonth;
        days.push({
            day: d.getDate(),
            name: diasSemana[d.getDay()],
            fullDate: d.toLocaleDateString(),
            incidencia: isLoadedMonth ? getIncidencia(d.getDate()) : null,
            isToday: d.getDate() === today.getDate() && d.getMonth() === today.getMonth(),
            isLoadedMonth: isLoadedMonth
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
    if (tipo === 'faltas') return valor > 0 ? 'text-red-600' : 'text-gray-900';
    if (tipo === 'retardos') return valor > 2 ? 'text-orange-600' : 'text-gray-900';
    if (tipo === 'omisiones') return valor > 0 ? 'text-yellow-600' : 'text-gray-900';
    return 'text-gray-900';
};

const getBgColor = (incidencia) => {
    if (!incidencia) return 'bg-white border-gray-100'; 
    if (incidencia === 'OK') return 'bg-green-100 border-green-200 text-green-800 font-bold'; 
    
    switch (incidencia) {
        case 'Descanso': return 'bg-gray-200 border-gray-300 text-gray-600 font-medium';
        case 'Falto': return 'bg-red-200 border-red-300 text-red-800 font-bold';
        case 'R': return 'bg-orange-200 border-orange-300 text-orange-800 font-bold';
        case 'Sin Entrada':
        case 'Sin Salida': return 'bg-yellow-200 border-yellow-300 text-yellow-800 font-bold';
        case 'Sin Turno': return 'bg-gray-600 border-gray-700 text-white font-bold';
        default: return 'bg-blue-200 border-blue-300 text-blue-800 font-bold cursor-pointer hover:bg-blue-300'; 
    }
};
</script>

<template>
    <div>
        <Head :title="`${empleado.first_name} ${empleado.last_name}`" />

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            
            <!-- Botón Volver -->
            <div class="mb-6">
                <Link :href="kardex.index().url" class="flex items-center text-gray-500 hover:text-blue-600 transition-colors font-medium">
                    <ArrowLeftIcon class="w-5 h-5 mr-1" />
                    Volver al Kárdex General
                </Link>
            </div>

            <!-- TARJETA UNIFICADA: PERFIL COMPLETO -->
            <div class="bg-white overflow-hidden shadow-lg rounded-xl mb-8 border border-gray-100">
                <div class="p-8">
                    <!-- Parte Superior: Identidad -->
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-8 pb-8 border-b border-gray-100">
                        <div class="flex-shrink-0">
                            <div v-if="empleado.photo" class="h-32 w-32 rounded-full overflow-hidden ring-4 ring-gray-50 shadow-sm">
                                <img :src="empleado.photo" alt="Foto" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="h-32 w-32 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center ring-4 ring-blue-50 shadow-sm">
                                <span class="text-4xl font-bold text-white tracking-wider">
                                    {{ getIniciales(empleado.first_name, empleado.last_name) }}
                                </span>
                            </div>
                        </div>

                        <div class="text-center md:text-left flex-1 w-full">
                            <div class="flex flex-col md:flex-row justify-between items-start">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">
                                        {{ empleado.first_name }} {{ empleado.last_name }}
                                    </h1>
                                    <p class="text-base text-gray-500 mt-1 flex items-center justify-center md:justify-start gap-2">
                                        <span>ID:</span>
                                        <span class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                            {{ empleado.emp_code }}
                                        </span>
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-4 py-1.5 text-sm font-bold text-green-700 ring-1 ring-inset ring-green-600/20 shadow-sm">
                                        <span class="w-2 h-2 bg-green-600 rounded-full mr-2 animate-pulse"></span>
                                        Activo
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parte Inferior: Grilla de Datos -->
                    <div class="pt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- ... Bloques de información (Trabajo, Contacto, Fechas, Legal) SIN CAMBIOS ... -->
                        <div class="flex flex-col gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2 text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <BriefcaseIcon class="w-4 h-4" /> Información Laboral
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ empleado.position_name || 'Sin Puesto' }}</p>
                                <p class="text-xs text-gray-500">{{ empleado.dept_name || 'Sin Departamento' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400">Nómina:</span>
                                <p class="text-sm font-medium text-gray-700">{{ empleado.nomina || 'Sin Asignar' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2 text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <ChatBubbleLeftRightIcon class="w-4 h-4" /> Contacto
                            </div>
                            <div class="flex items-center gap-2">
                                <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                                <p class="text-sm text-gray-700 truncate" :title="empleado.email">{{ empleado.email || 'Sin Email' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <PhoneIcon class="w-4 h-4 text-gray-400" />
                                <p class="text-sm text-gray-700">{{ empleado.mobile || 'Sin Celular' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2 text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <CalendarIcon class="w-4 h-4" /> Fechas Clave
                            </div>
                            <div>
                                <span class="text-xs text-gray-400">Contratación:</span>
                                <p class="text-sm font-medium text-gray-900">{{ empleado.hire_date || '--' }}</p>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400">Fecha de Nacimiento:</span>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ empleado.birthday ? new Date(empleado.birthday).toLocaleDateString() : '--' }}
                                </p>
                            </div>
                        </div>
                         <div class="flex flex-col gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2 text-gray-400 text-xs uppercase font-bold tracking-wider">
                                <IdentificationIcon class="w-4 h-4" /> Legal
                            </div>
                            <div>
                                <span class="text-xs text-gray-400">NSS:</span>
                                <p class="text-sm font-medium text-gray-900 font-mono">{{ empleado.ssn || '--' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Columna Izquierda: Resumen y Análisis -->
                <div class="lg:col-span-1 space-y-8">
                    
                    <!-- 1. Tarjeta de Resumen Mensual (ACORDEÓN) -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden sticky top-6 z-20">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="text-base font-semibold text-gray-900">Resumen Mensual</h3>
                            <div class="text-xs text-gray-500 bg-white px-2 py-1 rounded border border-gray-200 font-medium">
                                {{ meses[form.mes - 1].label }} {{ form.ano }}
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100">
                            
                            <!-- BLOQUE FALTAS -->
                            <div>
                                <div @click="toggleSummary('faltas')" class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Faltas</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'faltas'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-2xl font-bold" :class="getStatusColor(stats.total_faltas, 'faltas')">{{ stats.total_faltas }}</span>
                                </div>
                                <!-- Lista desplegable -->
                                <div v-if="expandedSummary === 'faltas' && stats.total_faltas > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-sm space-y-2 animate-in slide-in-from-top-2 fade-in duration-200">
                                    <div v-for="(fechas, cat) in getSummaryDetails('faltas')" :key="cat">
                                        <p class="text-xs font-bold text-gray-500 mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-gray-200 rounded text-gray-700 text-xs shadow-sm">
                                                {{ f }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE RETARDOS -->
                            <div>
                                <div @click="toggleSummary('retardos')" class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Retardos</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'retardos'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-2xl font-bold" :class="getStatusColor(stats.total_retardos, 'retardos')">{{ stats.total_retardos }}</span>
                                </div>
                                <div v-if="expandedSummary === 'retardos' && stats.total_retardos > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-sm space-y-2 animate-in slide-in-from-top-2 fade-in duration-200">
                                    <div v-for="(fechas, cat) in getSummaryDetails('retardos')" :key="cat">
                                        <p class="text-xs font-bold text-gray-500 mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-gray-200 rounded text-gray-700 text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE OMISIONES -->
                            <div>
                                <div @click="toggleSummary('omisiones')" class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Omisiones</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'omisiones'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-2xl font-bold" :class="getStatusColor(stats.total_omisiones, 'omisiones')">{{ stats.total_omisiones }}</span>
                                </div>
                                <div v-if="expandedSummary === 'omisiones' && stats.total_omisiones > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-sm space-y-2 animate-in slide-in-from-top-2 fade-in duration-200">
                                    <div v-for="(fechas, cat) in getSummaryDetails('omisiones')" :key="cat">
                                        <p class="text-xs font-bold text-gray-500 mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-gray-200 rounded text-gray-700 text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE VACACIONES -->
                            <div>
                                <div @click="toggleSummary('vacaciones')" class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Vacaciones</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'vacaciones'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-2xl font-bold text-blue-600">{{ stats.total_vacaciones }}</span>
                                </div>
                                <div v-if="expandedSummary === 'vacaciones' && stats.total_vacaciones > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-sm space-y-2 animate-in slide-in-from-top-2 fade-in duration-200">
                                    <div v-for="(fechas, cat) in getSummaryDetails('vacaciones')" :key="cat">
                                        <p class="text-xs font-bold text-gray-500 mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-gray-200 rounded text-gray-700 text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BLOQUE PERMISOS -->
                            <div>
                                <div @click="toggleSummary('permisos')" class="px-6 py-4 flex justify-between items-center hover:bg-gray-50 cursor-pointer transition-colors group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-600 transition-colors">Permisos</span>
                                        <ChevronDownIcon :class="{'rotate-180': expandedSummary === 'permisos'}" class="w-3 h-3 text-gray-400 transition-transform" />
                                    </div>
                                    <span class="text-2xl font-bold text-gray-800">{{ stats.total_permisos }}</span>
                                </div>
                                <div v-if="expandedSummary === 'permisos' && stats.total_permisos > 0" class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-sm space-y-2 animate-in slide-in-from-top-2 fade-in duration-200">
                                    <div v-for="(fechas, cat) in getSummaryDetails('permisos')" :key="cat">
                                        <p class="text-xs font-bold text-gray-500 mb-1">{{ cat }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="f in fechas" :key="f" class="px-2 py-0.5 bg-white border border-gray-200 rounded text-gray-700 text-xs shadow-sm">{{ f }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. NUEVA TARJETA: Análisis Anual (Placeholder) -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                <ChartBarIcon class="w-5 h-5 text-indigo-500" />
                                Análisis Anual
                            </h3>
                            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">2025</span>
                        </div>
                        <div class="p-6">
                            <!-- Placeholder visual -->
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-gray-500">Asistencia Global</span>
                                        <span class="font-bold text-gray-700">--%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="bg-gray-300 h-2 rounded-full" style="width: 0%"></div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 pt-2">
                                    <div class="bg-gray-50 p-3 rounded-lg text-center">
                                        <p class="text-xs text-gray-400 uppercase font-bold">Faltas Año</p>
                                        <p class="text-xl font-bold text-gray-400">--</p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg text-center">
                                        <p class="text-xs text-gray-400 uppercase font-bold">Vacaciones</p>
                                        <p class="text-xl font-bold text-gray-400">--</p>
                                    </div>
                                </div>
                                <p class="text-center text-xs text-gray-400 mt-2">
                                    * Datos acumulados del año en curso
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Columna Derecha: Calendario -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden h-full flex flex-col relative">
                        
                        <div v-if="loading" class="absolute inset-0 bg-white/80 z-50 flex items-center justify-center backdrop-blur-sm transition-opacity duration-300">
                            <ClockIcon class="w-8 h-8 text-blue-500 animate-spin" />
                        </div>

                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                <CalendarDaysIcon class="w-5 h-5 text-blue-500" />
                                Asistencia
                            </h3>
                            
                            <div v-if="viewMode === 'monthly' || viewMode === 'weekly'" class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                                
                                <!-- FILTRO DE PERMISOS -->
                                <div class="relative">
                                    <select v-model="form.tipo_permiso" @change="actualizarKardex" class="block w-48 rounded-md border-gray-300 py-1.5 pl-8 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm cursor-pointer">
                                        <option value="">Todos los Permisos</option>
                                        <optgroup label="Categorías Generales">
                                            <option v-for="cat in categoriasGenerales" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                                        </optgroup>
                                        <optgroup label="Etiquetas Específicas">
                                            <option v-for="etiqueta in etiquetasEspecificas" :key="etiqueta.value" :value="etiqueta.value">{{ etiqueta.label }}</option>
                                        </optgroup>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2">
                                        <ClipboardDocumentListIcon class="h-4 w-4 text-gray-400" />
                                    </div>
                                </div>

                                <select v-model="form.mes" @change="actualizarKardex" class="block w-28 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                                </select>
                                <select v-model="form.ano" @change="actualizarKardex" class="block w-24 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                                </select>
                            </div>

                            <div class="flex bg-gray-200 rounded-lg p-1">
                                <button @click="viewMode = 'weekly'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'weekly' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ViewColumnsIcon class="w-3 h-3" /> Semana
                                </button>
                                <button @click="viewMode = 'monthly'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'monthly' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <TableCellsIcon class="w-3 h-3" /> Mes
                                </button>
                                <button @click="viewMode = 'schedule'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'schedule' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ClockIcon class="w-3 h-3" /> Horario
                                </button>
                            </div>
                        </div>

                        <div class="p-6 flex-1">
                            
                            <!-- VISTA SEMANAL -->
                            <div v-if="viewMode === 'weekly'" class="space-y-3">
                                <div v-for="dia in diasDeLaSemana" :key="dia.name" 
                                     class="flex items-center rounded-lg border p-3 transition-all hover:bg-gray-50 cursor-default"
                                     :class="[
                                         dia.type === 'empty' ? 'opacity-40 bg-gray-50' : getBgColor(dia.incidencia),
                                         dia.isToday ? 'ring-2 ring-blue-400 ring-offset-1' : ''
                                     ]"
                                     @click="mostrarDetallePermiso(dia.incidencia)"
                                >
                                    <div class="w-20 text-center border-r border-gray-200 pr-3 mr-3 flex flex-col justify-center">
                                        <span class="text-xs font-bold text-gray-500 uppercase">{{ dia.name }}</span>
                                        <span v-if="dia.type !== 'empty'" class="text-xl font-bold text-gray-800">{{ dia.day }}</span>
                                    </div>
                                    
                                    <div class="flex-1" v-if="dia.type !== 'empty'">
                                        <div v-if="dia.incidencia === 'OK'" class="flex items-center text-green-700 font-bold">
                                            <span class="text-xl mr-2">✓</span> Asistencia
                                        </div>
                                        <div v-else-if="dia.incidencia" class="font-bold uppercase text-sm">{{ dia.incidencia }}</div>
                                        <div v-else-if="!dia.isLoadedMonth" class="text-gray-400 text-sm italic">Fuera del mes</div>
                                        <div v-else class="text-gray-400 text-sm">-</div>
                                    </div>
                                    <div v-else class="flex-1 text-gray-400 text-xs italic">Fuera del mes</div>
                                </div>
                            </div>

                            <!-- VISTA MENSUAL -->
                            <div v-else-if="viewMode === 'monthly'">
                                <div class="grid grid-cols-7 gap-2">
                                    <div v-for="d in diasSemana" :key="d" class="text-center text-xs font-bold text-gray-400 uppercase pb-2">{{ d }}</div>
                                    
                                    <div 
                                        v-for="dia in diasDelMes" 
                                        :key="dia.id" 
                                        class="aspect-square rounded-lg flex flex-col items-center justify-center text-xs relative border transition-all hover:z-10 hover:scale-105 cursor-default"
                                        :class="[
                                            dia.type === 'empty' ? 'border-transparent bg-transparent' : getBgColor(dia.incidencia),
                                            dia.isToday ? 'ring-2 ring-blue-400 ring-offset-1' : '',
                                            (dia.incidencia && !['OK','Falto','Descanso','Sin Entrada','Sin Salida', 'Sin Turno'].includes(dia.incidencia)) ? 'cursor-pointer hover:shadow-md hover:scale-105' : ''
                                        ]"
                                        @click="dia.type === 'day' ? mostrarDetallePermiso(dia.incidencia) : null"
                                    >
                                        <template v-if="dia.type === 'day'">
                                            <span class="absolute top-1 left-1.5 text-[10px] font-semibold opacity-60">{{ dia.day }}</span>
                                            <div class="mt-3 font-bold text-center px-0.5 w-full overflow-hidden">
                                                <span v-if="dia.incidencia === 'OK'" class="text-lg">✓</span>
                                                <span v-else class="leading-none block text-[9px] uppercase truncate">{{ dia.incidencia }}</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                             <!-- VISTA HORARIO -->
                             <div v-else-if="viewMode === 'schedule'">
                                <div v-if="horario" class="space-y-4">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="p-2 bg-blue-100 rounded-full text-blue-600">
                                            <ClockIcon class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm text-gray-500 uppercase font-bold">Turno Actual</h4>
                                            <p class="text-lg font-bold text-gray-800">{{ horario.nombre }}</p>
                                        </div>
                                    </div>

                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Día</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Entrada</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Salida</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="dia in diasHorario" :key="dia.nombre" :class="dia.activo ? '' : 'bg-gray-50 text-gray-400'">
                                                    <td class="px-4 py-3 text-sm font-medium">{{ dia.nombre }}</td>
                                                    <td class="px-4 py-3 text-sm text-center">
                                                        <span v-if="dia.activo" class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-mono">{{ dia.entrada }}</span>
                                                        <span v-else>-</span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-center">
                                                        <span v-if="dia.activo" class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-mono">{{ dia.salida }}</span>
                                                        <span v-else>-</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center h-64 text-gray-400">
                                    <ClockIcon class="w-16 h-16 mb-3 opacity-30" />
                                    <p class="font-medium">Este empleado no tiene un turno asignado vigente.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>