<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import * as kardex from '@/routes/kardex'; 
import * as empleadoRoutes from '@/routes/empleado'; 
import { 
    UserCircleIcon, 
    BuildingOfficeIcon, 
    BriefcaseIcon,
    CalendarDaysIcon,
    PhoneIcon,
    IdentificationIcon,
    ArrowLeftIcon,
    CurrencyDollarIcon,
    TableCellsIcon,
    ViewColumnsIcon,
    ChevronDownIcon,
    ClockIcon // Nuevo icono para Horario
} from '@heroicons/vue/24/outline';

const props = defineProps({
    empleado: Object,
    stats: Object,
    fechaActual: String,
});

const urlParams = new URLSearchParams(window.location.search);
const currentMonth = urlParams.get('mes') ? parseInt(urlParams.get('mes')) - 1 : new Date().getMonth();
const currentYear = urlParams.get('ano') ? parseInt(urlParams.get('ano')) : new Date().getFullYear();

const form = useForm({
    mes: currentMonth + 1,
    ano: currentYear
});

// Estado para el modo de vista: 'weekly', 'monthly', 'schedule'
const viewMode = ref('monthly'); 

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
    router.get(route('empleado.show', props.empleado.id), {
        mes: form.mes,
        ano: form.ano
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const getIncidencia = (dia) => {
    if (!props.stats || !props.stats.incidencias_diarias) return '';
    return props.stats.incidencias_diarias[dia] || '';
};

// --- Corrección Lógica Días del Mes (Versión Robusta) ---
const diasDelMes = computed(() => {
    const days = [];
    // Usamos el año y mes seleccionados en el formulario (mes 0-11 para JS Date)
    const year = form.ano; 
    const month = form.mes - 1; 
    
    // Crear fecha del primer día del mes. 
    // Usamos hora 12:00 para evitar problemas de cambio de horario de verano/invierno
    const firstDayOfMonth = new Date(year, month, 1, 12, 0, 0);
    
    // Obtener el día de la semana (0=Dom, 1=Lun, etc.)
    const firstDayIndex = firstDayOfMonth.getDay(); 
    
    // Total de días en el mes (día 0 del mes siguiente = último día de este mes)
    const totalDays = new Date(year, month + 1, 0).getDate();

    // Rellenar vacíos iniciales
    // Esto alinea el día 1 con su columna correcta (Dom, Lun, etc.)
    for (let i = 0; i < firstDayIndex; i++) {
        days.push({ type: 'empty', id: `empty-${i}` });
    }

    // Rellenar días reales
    const today = new Date();
    for (let i = 1; i <= totalDays; i++) {
        days.push({
            type: 'day',
            id: i,
            day: i,
            incidencia: getIncidencia(i),
            // Es hoy solo si coincide año, mes y día con la fecha real actual
            isToday: i === today.getDate() && month === today.getMonth() && year === today.getFullYear()
        });
    }
    return days;
});

// Días de la Semana (Calculados para mostrar la semana actual real)
const diasDeLaSemana = computed(() => {
    const days = [];
    const today = new Date();
    // Fecha base para calcular la semana actual
    // Si estamos viendo el mes actual, usamos 'today'. Si no, usamos el día 1 del mes seleccionado.
    
    const selectedMonth = form.mes - 1;
    const selectedYear = form.ano;
    const isCurrentRealMonth = selectedMonth === today.getMonth() && selectedYear === today.getFullYear();
    
    let referenceDate;

    if (isCurrentRealMonth) {
        // Si es el mes actual, usamos hoy como referencia
        referenceDate = new Date(today);
    } else {
        // Si es otro mes, usamos el día 1 de ese mes
        referenceDate = new Date(selectedYear, selectedMonth, 1, 12, 0, 0);
    }

    // Calcular el Domingo de esta semana
    const dayIndex = referenceDate.getDay(); // 0 = Domingo
    const startOfWeek = new Date(referenceDate);
    startOfWeek.setDate(referenceDate.getDate() - dayIndex);

    for (let i = 0; i < 7; i++) {
        const d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        
        // Solo mostramos datos si el día pertenece al mes seleccionado
        const belongsToMonth = d.getMonth() === selectedMonth;
        
        days.push({
            day: d.getDate(),
            name: diasSemana[d.getDay()],
            fullDate: d.toLocaleDateString(),
            incidencia: belongsToMonth ? getIncidencia(d.getDate()) : null,
            isToday: d.getDate() === today.getDate() && d.getMonth() === today.getMonth() && d.getFullYear() === today.getFullYear(),
            isLoadedMonth: belongsToMonth
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
    return 'text-gray-900';
};

const getBgColor = (incidencia) => {
    if (!incidencia) return 'bg-white border-gray-100';
    if (incidencia === 'OK') return 'bg-green-50 border-green-200 text-green-800';
    
    switch (incidencia) {
        case 'Descanso': return 'bg-gray-50 border-gray-200 text-gray-400';
        case 'Falto': return 'bg-red-50 border-red-200 text-red-800 font-bold';
        case 'R': return 'bg-orange-50 border-orange-200 text-orange-800 font-bold';
        case 'Sin Entrada':
        case 'Sin Salida': return 'bg-yellow-50 border-yellow-200 text-yellow-800 font-bold';
        default: return 'bg-blue-50 border-blue-200 text-blue-800 font-bold'; 
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

            <!-- Encabezado del Perfil -->
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
                                        <span>ID de Empleado:</span>
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
                
                <!-- Columna Izquierda: Resumen -->
                <div class="lg:col-span-1 space-y-8">
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="text-base font-semibold text-gray-900">Resumen</h3>
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
                                <span class="text-sm font-medium text-gray-600">Vacaciones</span>
                                <span class="text-2xl font-bold text-blue-600">{{ stats.total_vacaciones }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Datos Personales -->
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                         <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <h3 class="text-base font-semibold text-gray-900">Datos Personales</h3>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Fecha de Contratación</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">{{ empleado.hire_date || 'No registrada' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 uppercase font-semibold">Cumpleaños</dt>
                                <dd class="text-sm font-medium text-gray-900 mt-1">{{ empleado.birthday ? new Date(empleado.birthday).toLocaleDateString() : 'No registrado' }}</dd>
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

                <!-- Columna Derecha: Visualizador -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden h-full flex flex-col">
                        
                        <!-- Header con Controles -->
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                <CalendarDaysIcon class="w-5 h-5 text-blue-500" />
                                Asistencia
                            </h3>
                            
                            <!-- Controles de Fecha (Solo visible en Mensual) -->
                            <div v-if="viewMode === 'monthly'" class="flex items-center gap-2 transition-opacity duration-300">
                                <select v-model="form.mes" @change="cambiarFecha" class="block w-32 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                                    <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                                </select>
                                <select v-model="form.ano" @change="cambiarFecha" class="block w-24 rounded-md border-gray-300 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                                    <option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option>
                                </select>
                            </div>

                            <!-- Switch Vista -->
                            <div class="flex bg-gray-200 rounded-lg p-1">
                                <button @click="viewMode = 'weekly'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'weekly' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ViewColumnsIcon class="w-3 h-3" />
                                    Semana
                                </button>
                                <button @click="viewMode = 'monthly'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'monthly' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <TableCellsIcon class="w-3 h-3" />
                                    Mes
                                </button>
                                <button @click="viewMode = 'schedule'" class="px-3 py-1 text-xs font-medium rounded-md transition-all flex items-center gap-1" :class="viewMode === 'schedule' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    <ClockIcon class="w-3 h-3" />
                                    Horario
                                </button>
                            </div>
                        </div>

                        <div class="p-6 flex-1">
                            
                            <!-- VISTA SEMANAL (Rectángulos Alargados) -->
                            <div v-if="viewMode === 'weekly'" class="space-y-2">
                                <div v-for="dia in diasDeLaSemana" :key="dia.day" 
                                     class="flex items-center rounded-lg border p-3 transition-all hover:bg-gray-50"
                                     :class="[
                                         getBgColor(dia.incidencia),
                                         dia.isToday ? 'ring-2 ring-blue-400 ring-offset-1' : ''
                                     ]"
                                >
                                    <!-- Fecha -->
                                    <div class="w-16 text-center border-r border-gray-200 pr-3 mr-3">
                                        <div class="text-xs font-bold text-gray-500 uppercase">{{ dia.name }}</div>
                                        <div class="text-xl font-bold text-gray-800">{{ dia.day }}</div>
                                    </div>
                                    
                                    <!-- Estado -->
                                    <div class="flex-1">
                                        <div v-if="dia.incidencia === 'OK'" class="flex items-center text-green-700 font-bold">
                                            <span class="text-lg mr-2">✓</span> Asistencia
                                        </div>
                                        <div v-else-if="dia.incidencia" class="font-bold uppercase text-sm">
                                            {{ dia.incidencia }}
                                        </div>
                                        <div v-else-if="!dia.isLoadedMonth" class="text-gray-400 text-sm italic">
                                            Datos de otro mes
                                        </div>
                                        <div v-else class="text-gray-400 text-sm">
                                            -
                                        </div>
                                    </div>
                                </div>
                                <p class="text-center text-xs text-gray-400 mt-4">Mostrando semana actual</p>
                            </div>

                            <!-- VISTA MENSUAL (Calendario Cuadrado) -->
                            <div v-else-if="viewMode === 'monthly'">
                                <div class="grid grid-cols-7 gap-2">
                                    <div v-for="d in diasSemana" :key="d" class="text-center text-xs font-bold text-gray-400 uppercase pb-2">
                                        {{ d }}
                                    </div>
                                    
                                    <div 
                                        v-for="dia in diasDelMes" 
                                        :key="dia.id" 
                                        class="aspect-square rounded-lg flex flex-col items-center justify-center text-xs relative border transition-all hover:z-10 hover:scale-105 cursor-default"
                                        :class="[
                                            dia.type === 'empty' ? 'border-transparent bg-transparent' : getBgColor(dia.incidencia),
                                            dia.isToday ? 'ring-2 ring-blue-400 ring-offset-1' : ''
                                        ]"
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

                             <!-- VISTA HORARIO (Placeholder) -->
                             <div v-else-if="viewMode === 'schedule'" class="flex flex-col items-center justify-center h-64 text-gray-400">
                                <ClockIcon class="w-12 h-12 mb-2 opacity-50" />
                                <p>Configuración de horarios próximamente...</p>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>