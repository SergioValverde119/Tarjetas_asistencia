<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
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
    <div class="relative min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Botón de Login en la esquina superior derecha -->
        <header class="absolute top-0 right-0 p-6">
            <nav v-if="canLogin" class="flex flex-1 justify-end">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('home')"
                    class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Iniciar Sesión
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Contenido principal centrado -->
        <main class="flex h-screen items-center justify-center">
            <div class="text-center">
                <!-- Logo principal -->
                <img src="/images/logo_cdmx.jpeg" alt="Logo Principal" class="mx-auto mb-8 h-32 w-auto shadow-lg rounded-full">
                
                <!-- Título de la aplicación -->
                <h1 class="text-4xl font-bold text-gray-800 dark:text-white">
                    Sistema de Control de Asistencia
                </h1>
                
                <!-- Subtítulo o descripción -->
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
                    Bienvenido. Por favor, inicie sesión para continuar.
                </p>
            </div>
        </main>

        <!-- Footer -->
        <footer class="absolute bottom-0 w-full py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
        </footer>
    </div>
</template>