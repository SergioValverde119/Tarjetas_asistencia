<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ExclamationTriangleIcon, XMarkIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

// Accedemos a las notificaciones compartidas globalmente
const page = usePage();
const notificaciones = computed(() => page.props.auth.notifications || []);

// Filtramos solo las alertas críticas de faltas
// (Asumiendo que en el backend le pusimos 'titulo' => 'Límite de Faltas Excedido')
const alertasDeBaja = computed(() => {
    return notificaciones.value.filter(n => n.data.titulo === 'Límite de Faltas Excedido');
});

// Función para marcar como leída (descartar la tarjeta)
const descartar = (id) => {
    router.post(route('notifications.read', id), {}, {
        preserveScroll: true
    });
};
</script>

<template>
    <div v-if="alertasDeBaja.length > 0" class="mb-8 space-y-4">
        
        <!-- Título de la Sección de Alerta -->
        <div class="flex items-center gap-2 text-red-700 font-bold text-lg px-2">
            <ExclamationTriangleIcon class="w-6 h-6 animate-pulse" />
            <span>Atención: Posibles Bajas por Faltas ({{ alertasDeBaja.length }})</span>
        </div>

        <!-- Contenedor de Tarjetas -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            
            <div 
                v-for="alerta in alertasDeBaja" 
                :key="alerta.id"
                class="relative bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-md transition-all hover:shadow-lg"
            >
                <!-- Botón Cerrar -->
                <button 
                    @click="descartar(alerta.id)"
                    class="absolute top-2 right-2 text-red-400 hover:text-red-700 transition-colors"
                    title="Marcar como revisado"
                >
                    <XMarkIcon class="w-5 h-5" />
                </button>

                <div class="flex flex-col">
                    <!-- Encabezado de la Tarjeta -->
                    <h3 class="font-bold text-red-800 text-lg truncate pr-6">
                        BAJA POR FALTAS
                    </h3>
                    
                    <!-- Detalles del Empleado -->
                    <div class="mt-2 text-sm text-red-700 space-y-1">
                        <p><span class="font-semibold">Empleado:</span> {{ alerta.data.emp_code }}</p>
                        <p class="font-medium text-base">{{ alerta.data.mensaje }}</p> <!-- Ej: Juan Perez tiene 4 faltas -->
                        <p class="text-xs text-red-500 mt-2">Detectado el: {{ alerta.data.fecha_alerta }}</p>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="mt-4 flex gap-2">
                        <button 
                            @click="descartar(alerta.id)"
                            class="flex-1 bg-white border border-red-200 text-red-700 text-xs font-bold py-2 px-3 rounded hover:bg-red-100 transition-colors flex items-center justify-center gap-1"
                        >
                            <CheckCircleIcon class="w-4 h-4" />
                            Enterado
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>