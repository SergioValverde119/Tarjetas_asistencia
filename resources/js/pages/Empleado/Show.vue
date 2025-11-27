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
    EnvelopeIcon, // <-- Nuevo icono de Email
    IdentificationIcon,
    ArrowLeftIcon,
    CurrencyDollarIcon,
    TableCellsIcon,
    ViewColumnsIcon,
    ClockIcon,
    ChevronDownIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    empleado: Object,
    stats: Object,
    fechaActual: String,
    calendario: Array,
    filtros: Object,
    horario: Object, // <-- Nuevo prop
    catalogoPermisos: Object, // <-- Nuevo prop (Diccionario de claves)
});

const form = useForm({
    mes: props.filtros.mes,
    ano: props.filtros.ano
});

const viewMode = ref('monthly'); 
const loading = ref(false);

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

const cambiarFecha = () => {
    router.get(`/empleado/${props.empleado.id}`, {
        mes: form.mes,
        ano: form.ano
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

// --- Lógica para Tooltip de Permisos ---
const mostrarDetallePermiso = (simbolo) => {
    // Si es un permiso (no es OK, ni Falta, ni Descanso, etc.)
    const palabrasReservadas = ['OK', 'Descanso', 'Falto', 'R', 'Sin Entrada', 'Sin Salida', 'Sin Turno'];
    
    if (simbolo && !palabrasReservadas.includes(simbolo)) {
        // Buscamos el significado en el catálogo
        const significado = props.catalogoPermisos[simbolo] || 'Permiso Desconocido';
        alert(`Clave: ${simbolo}\nConcepto: ${significado}`);
    }
};
// ---------------------------------------

// --- Procesamiento de Horario ---
const diasHorario = computed(() => {
    if (!props.horario || !props.horario.dias) return [];
    
    // Mapeamos los índices de BioTime (0=Dom) a nombres
    // BioTime devuelve un array de objetos con day_index, in_time, out_time
    const dias = [];
    for (let i = 1; i <= 7; i++) {
        // Ajuste: BioTime 0=Dom. Queremos empezar en Lunes (1) y terminar en Domingo (0)
        const dayIndex = i === 7 ? 0 : i; // 1..6, 0
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
// --------------------------------

// Días del Mes
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

// Días de la Semana
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
            incidencia: isLoadedMonth ? getIncidencia(d.getDate()) : null,
            isToday: d.getDate() === today.getDate() && d.getMonth() === today.getMonth(),
            isLoadedMonth: isLoadedMonth
        });
    }
    return days;
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
        default: return 'bg-blue-200 border-blue-300 text-blue-800 font-bold cursor-pointer hover:bg-blue-300'; // Cursor pointer para permisos
    }
};
</script>

<template>
    <div>
        <Head :title="`${empleado.first_name} ${empleado.last_name}`" />

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <Link :href="kardex.index().url" class="flex items-center text-gray-500 hover:text-blue-600 transition-colors font-medium">
                    <ArrowLeftIcon class="w-5 h-5 mr-1" />
                    Volver al Kárdex General
                </Link>
            </div>

            <div class="bg-white overflow-hidden shadow-lg rounded-xl mb-8 border border-gray-100">
                <div class="p-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
                        <div class="flex-shrink-0">
                            <div v-if="empleado.photo" class="h-28 w-28 rounded-full overflow-hidden ring-4 ring-gray-50 shadow-sm">
                                <img :src="empleado.photo" alt="Foto" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="h-28 w-28 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center ring-4 ring-blue-50 shadow-sm">
                                <span class="text-3xl font-bold text-white tracking-wider">
                                    {{ getIniciales(empleado.first_name, empleado.last_name) }}
                                </span>
                            </div>
                        </div>

                        <div class="text-center md:text-left flex-1 w-full">
                            <div class="flex flex-col md:flex-row justify-between items-start">
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">
                                        {{ empleado.first_name }} {{ empleado.last_name }}
                                    </h1>
                                    <p class="text-sm text-gray-500 mt-1 flex items-center justify-center md:justify-start gap-2">
                                        <span>ID:</span>
                                        <span class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                            {{ empleado.emp_code }}
                                        </span>
                                    </p>
                                </div>
                                <div class="mt-4 md:mt-0">
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                        Activo
                                    </span>
                                </div>
                            </div>
                            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <BriefcaseIcon class="w-5 h-5 text-gray-400" />
                                    <div class="flex flex-col text-left">
                                        <span class="text-xs text-gray-400 uppercase font-semibold">Puesto</span>
                                        <span class="font-medium">{{ empleado.position_name || 'Sin Puesto' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <BuildingOfficeIcon class="w-5 h-5 text-gray-400" />
                                    <div class="flex flex-col text-left">
                                        <span class="text-xs text-gray-400 uppercase font-semibold">Departamento</span>
                                        <span class="font-medium">{{ empleado.dept_name || 'Sin Departamento' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <CurrencyDollarIcon class="w-5 h-5 text-gray-400" />
                                    <div class="flex flex-col text-left">
                                        <span class="text-xs text-gray-400 uppercase font-semibold">Nómina</span>
                                        <span class="font-medium">{{ empleado.nomina || 'Sin Asignar' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Izquierda: Resumen y Datos -->
                <div class="lg:col-span-1 space-y-8">
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="text-base font-semibold text-gray-900">Resumen Mensual</h3>
                            <div class="text-xs text-gray-500 bg-white px-2 py-1 rounded border border-gray-200">
                                {{ meses[form.mes - 1].label }} {{ form.ano }}
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div class="px-6 py-4 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Faltas</span>
                                <span class="text-2xl font-bold" :class="getStatusColor(stats.total_faltas, 'faltas')">{{ stats.total_faltas }}</span>
                            </div>
                            <div class="px-6 py-4 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Retardos</span>
                                <span class="text-2xl font-bold" :class="getStatusColor(stats.total_retardos, 'retardos')">{{ stats.total_retardos }}</span>
                            </div>
                            <div class="px-6 py-4 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Omisiones</span>
                                <span class="text-2xl font-bold" :class="getStatusColor(stats.total_omisiones, 'omisiones')">{{ stats.total_omisiones }}</span>
                            </div>
                            <div class="px-6 py-4 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Vacaciones</span>
                                <span class="text-2xl font-bold text-blue-600">{{ stats.total_vacaciones }}</span>
                            </div>
                            <div class="px-6 py-4 flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-600">Permisos</span>
                                <span class="text-2xl font-bold text-gray-800">{{ stats.total_permisos }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                         <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <h3 class="text-base font-semibold text-gray-900">Datos Personales</h3>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Contratación</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">{{ empleado.hire_date || 'No registrada' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Cumpleaños</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">{{ empleado.birthday ? new Date(empleado.birthday).toLocaleDateString() : 'No registrado' }}</dd>
                            </div>
                            <!-- EMAIL AGREGADO -->
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Correo Electrónico</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1 flex items-center gap-2">
                                    <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                                    {{ empleado.email || 'No registrado' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Celular</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1 flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-gray-400" />
                                    {{ empleado.mobile || 'No registrado' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">NSS</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">{{ empleado.ssn || 'No registrado' }}</dd>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Derecha: Calendario -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden h-full flex flex-col relative">
                        
                        <div v-if="loading" class="absolute inset-0 bg-white/80 z-50 flex items-center justify-center backdrop-blur-sm transition-opacity duration-300">
                            <ArrowPathIcon class="w-8 h-8 text-blue-500 animate-spin" />
                        </div>

                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                <CalendarDaysIcon class="w-5 h-5 text-blue-500" />
                                Asistencia
                            </h3>
                            
                            <div v-if="viewMode === 'monthly'" class="flex items-center gap-2">
                                <select v-model="form.mes" @change="cambiarFecha" class="block w-32 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm cursor-pointer">
                                    <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                                </select>
                                <select v-model="form.ano" @change="cambiarFecha" class="block w-24 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm cursor-pointer">
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
                                     class="flex items-center rounded-lg border p-3 transition-all hover:bg-gray-50"
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
                                            <span class="text-xl mr-2">✓</span> Asistencia Correcta
                                        </div>
                                        <div v-else-if="dia.incidencia" class="font-bold uppercase text-sm">{{ dia.incidencia }}</div>
                                        <div v-else-if="!dia.isLoadedMonth" class="text-gray-400 text-sm italic">Fuera del mes seleccionado</div>
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
                                            // Cursor pointer solo si es un permiso clickeable
                                            (dia.incidencia && !['OK','Falto','Descanso','Sin Entrada','Sin Salida'].includes(dia.incidencia)) ? 'cursor-pointer hover:shadow-md' : ''
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

                             <!-- VISTA HORARIO (AHORA SÍ CON DATOS) -->
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