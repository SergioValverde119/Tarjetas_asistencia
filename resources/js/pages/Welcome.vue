<script setup>
import { Head, Link } from '@inertiajs/vue3';

// --- WAYFINDER: Solo importamos las rutas de destino ---
// Asegúrate de correr: php artisan wayfinder:generate

// 1. Rutas de Tarjetas (Archivo generated resources/js/routes/tarjetas.js)
import { mi_tarjeta, general } from '@/routes/tarjetas'; 

// 2. Rutas de Kardex (Archivo generated resources/js/routes/kardex.js)
import { index as kardexIndex } from '@/routes/kardex'; 

defineProps({
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Bienvenido" />

    <div class="relative min-h-screen bg-gray-100 dark:bg-gray-900 selection:bg-red-500 selection:text-white">
        
        <div class="max-w-7xl mx-auto p-6 lg:p-8 min-h-screen flex flex-col justify-center items-center">
            
            <div class="text-center mb-12">
                <img src="/images/logo_cdmx.jpeg" alt="Logo Principal" class="mx-auto mb-6 h-32 w-auto shadow-lg rounded-full border-4 border-white dark:border-gray-800">
                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                    Sistema de Control de Asistencia
                </h1>
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Seleccione un módulo para ingresar. Si no ha iniciado sesión, el sistema se lo solicitará automáticamente.
                </p>
            </div>

            <!-- CAMBIO: Usamos Flexbox + justify-center para centrar las tarjetas -->
            <div class="flex flex-wrap justify-center gap-6 w-full max-w-5xl">
                
                <Link 
                    :href="mi_tarjeta().url"
                    class="group relative bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 border border-gray-100 dark:border-gray-700 flex flex-col items-center text-center w-full md:w-80"
                >
                    <div class="h-16 w-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Mi Tarjeta</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Tarjetas personales.</p>
                </Link>

                <Link 
                    :href="general().url"
                    class="group relative bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 border border-gray-100 dark:border-gray-700 flex flex-col items-center text-center w-full md:w-80"
                >
                    <div class="h-16 w-16 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-4 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Tarjetas Generales</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Buscador global de empleados.</p>
                </Link>
            </div>
        </div>
    </div>
</template>