<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { 
    UserCircleIcon, 
    BuildingOfficeIcon, 
    BriefcaseIcon,
    CalendarDaysIcon,
    PhoneIcon,
    IdentificationIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    empleado: Object,
    stats: Object,
    fechaActual: String,
});

// Función para obtener iniciales (ej: "Juan Perez" -> "JP")
const getIniciales = (nombre, apellido) => {
    const n = nombre ? nombre.charAt(0) : '';
    const a = apellido ? apellido.charAt(0) : '';
    return (n + a).toUpperCase();
};

// Función para determinar color de estatus
const getStatusColor = (valor, tipo) => {
    if (tipo === 'faltas') return valor > 0 ? 'text-red-600' : 'text-gray-900';
    if (tipo === 'retardos') return valor > 2 ? 'text-orange-600' : 'text-gray-900';
    return 'text-gray-900';
};
</script>

<template>
    <div>
        <Head :title="`${empleado.first_name} ${empleado.last_name}`" />

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            
            <!-- Botón Volver -->
            <div class="mb-6">
                <Link :href="route('kardex.index')" class="flex items-center text-gray-500 hover:text-blue-600 transition-colors">
                    <ArrowLeftIcon class="w-5 h-5 mr-1" />
                    Volver al Kárdex General
                </Link>
            </div>

            <!-- Encabezado del Perfil -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-6 sm:px-10 bg-white border-b border-gray-200">
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                        
                        <!-- Avatar / Foto -->
                        <div class="flex-shrink-0">
                            <div v-if="empleado.photo" class="h-24 w-24 rounded-full overflow-hidden ring-4 ring-gray-100">
                                <img :src="empleado.photo" alt="Foto" class="h-full w-full object-cover" />
                            </div>
                            <div v-else class="h-24 w-24 rounded-full bg-blue-600 flex items-center justify-center ring-4 ring-blue-50">
                                <span class="text-3xl font-bold text-white tracking-wider">
                                    {{ getIniciales(empleado.first_name, empleado.last_name) }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Principal -->
                        <div class="text-center md:text-left flex-1">
                            <h1 class="text-3xl font-bold text-gray-900">
                                {{ empleado.first_name }} {{ empleado.last_name }}
                            </h1>
                            <div class="mt-2 flex flex-col md:flex-row flex-wrap gap-3 md:gap-6 text-sm text-gray-500 justify-center md:justify-start">
                                <div class="flex items-center gap-1">
                                    <IdentificationIcon class="w-4 h-4" />
                                    <span>ID: {{ empleado.emp_code }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <BriefcaseIcon class="w-4 h-4" />
                                    <span>{{ empleado.position_name || 'Sin Puesto' }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <BuildingOfficeIcon class="w-4 h-4" />
                                    <span>{{ empleado.dept_name || 'Sin Departamento' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas del Mes -->
            <div class="mb-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Resumen de {{ fechaActual }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                    <!-- Tarjeta Faltas -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6 text-center">
                            <dt class="text-sm font-medium text-gray-500 truncate">Faltas</dt>
                            <dd class="mt-1 text-3xl font-semibold" :class="getStatusColor(stats.total_faltas, 'faltas')">
                                {{ stats.total_faltas }}
                            </dd>
                        </div>
                    </div>
                    <!-- Tarjeta Retardos -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6 text-center">
                            <dt class="text-sm font-medium text-gray-500 truncate">Retardos</dt>
                            <dd class="mt-1 text-3xl font-semibold" :class="getStatusColor(stats.total_retardos, 'retardos')">
                                {{ stats.total_retardos }}
                            </dd>
                        </div>
                    </div>
                     <!-- Tarjeta Vacaciones -->
                     <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6 text-center">
                            <dt class="text-sm font-medium text-gray-500 truncate">Vacaciones</dt>
                            <dd class="mt-1 text-3xl font-semibold text-blue-600">
                                {{ stats.total_vacaciones }}
                            </dd>
                        </div>
                    </div>
                    <!-- Tarjeta Permisos -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6 text-center">
                            <dt class="text-sm font-medium text-gray-500 truncate">Permisos</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ stats.total_permisos }}
                            </dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles Personales -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Información Personal</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Detalles registrados en BioTime.</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                    <dl class="sm:divide-y sm:divide-gray-200">
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Fecha de Contratación</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ empleado.hire_date || 'No registrada' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Cumpleaños</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ empleado.birthday ? new Date(empleado.birthday).toLocaleDateString() : 'No registrado' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Teléfono Celular</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div v-if="empleado.mobile" class="flex items-center gap-2">
                                    <PhoneIcon class="w-4 h-4 text-gray-400" />
                                    {{ empleado.mobile }}
                                </div>
                                <span v-else class="text-gray-400 italic">No registrado</span>
                            </dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">NSS (Seguro Social)</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ empleado.ssn || 'No registrado' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</template>