<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    ExclamationTriangleIcon, 
    CheckCircleIcon, 
    ChevronDownIcon, 
    ChevronUpIcon,
    XMarkIcon,
    ArrowPathIcon 
} from '@heroicons/vue/24/outline';
import * as notifications from '@/routes/notifications'; // <-- ¡USANDO WAYFINDER!

const page = usePage();

// Filtramos las notificaciones
const alertas = computed(() => {
    const todas = page.props.auth.notifications || [];
    return todas.filter(n => n.data.titulo === 'Límite de Faltas Excedido');
});

const isExpanded = ref(false);
const processingAll = ref(false);
const processingIds = ref([]);

// Función para marcar UNA como leída
const descartar = (id) => {
    processingIds.value.push(id);

    // --- CAMBIO: Usando la ruta tipada de Wayfinder ---
    router.post(notifications.read({ id: id }), {}, { 
        preserveScroll: true,
        onFinish: () => {
            const index = processingIds.value.indexOf(id);
            if (index > -1) processingIds.value.splice(index, 1);
        }
    });
};

// Función para marcar TODAS como leídas
const descartarTodas = () => {
    if (!confirm('¿Estás seguro de que quieres marcar todas estas alertas como revisadas? Desaparecerán de la lista.')) return;

    processingAll.value = true;
    
    // --- CAMBIO: Usando la ruta tipada de Wayfinder ---
    router.post(notifications.readAll(), {}, {
        preserveScroll: true,
        onFinish: () => {
            processingAll.value = false;
            isExpanded.value = false;
        }
    });
};
</script>

<template>
    <div v-if="alertas.length > 0" class="mb-6">
        
        <!-- TARJETA MAESTRA -->
        <div class="bg-red-50 border-l-4 border-red-600 rounded-r-lg shadow-md overflow-hidden transition-all animate-pulse-once">
            
            <!-- Cabecera -->
            <div class="p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                
                <!-- Título y Contador -->
                <div class="flex items-center gap-3">
                    <div class="bg-red-100 p-2 rounded-full relative">
                        <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                        <span class="absolute top-0 right-0 block h-3 w-3 rounded-full bg-red-500 ring-2 ring-white animate-ping"></span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-red-800">
                            Atención Requerida
                        </h3>
                        <p class="text-sm text-red-700">
                            Se han detectado <span class="font-bold text-red-900">{{ alertas.length }} empleados</span> con exceso de faltas esta quincena.
                        </p>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button 
                        @click="isExpanded = !isExpanded"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 bg-white border border-red-200 text-red-700 rounded-md hover:bg-red-100 transition-colors text-sm font-medium"
                    >
                        <span>{{ isExpanded ? 'Ocultar Lista' : 'Ver Lista' }}</span>
                        <component :is="isExpanded ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4" />
                    </button>

                    <button 
                        @click="descartarTodas"
                        :disabled="processingAll"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors text-sm font-bold shadow-sm disabled:opacity-75"
                    >
                        <ArrowPathIcon v-if="processingAll" class="w-4 h-4 animate-spin" />
                        <CheckCircleIcon v-else class="w-4 h-4" />
                        <span>{{ processingAll ? 'Procesando...' : 'Enterado de Todos' }}</span>
                    </button>
                </div>
            </div>

            <!-- Área Desplegable -->
            <div v-if="isExpanded" class="border-t border-red-200 bg-white">
                <div class="max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensaje</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faltas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="alerta in alertas" :key="alerta.id" class="hover:bg-red-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ alerta.data.emp_code }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ alerta.data.mensaje }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600">
                                    {{ alerta.data.faltas }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ alerta.data.fecha_alerta }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        @click="descartar(alerta.id)" 
                                        :disabled="processingIds.includes(alerta.id)"
                                        class="text-blue-600 hover:text-blue-900 hover:underline flex items-center gap-1 justify-end w-full disabled:opacity-50 disabled:cursor-wait"
                                    >
                                        <span v-if="processingIds.includes(alerta.id)">Guardando...</span>
                                        <span v-else>Marcar visto</span>
                                        
                                        <ArrowPathIcon v-if="processingIds.includes(alerta.id)" class="w-4 h-4 animate-spin" />
                                        <XMarkIcon v-else class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse-once {
    0% { transform: scale(1); }
    50% { transform: scale(1.01); }
    100% { transform: scale(1); }
}
.animate-pulse-once {
    animation: pulse-once 0.4s ease-out;
}
</style>