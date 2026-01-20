
<script setup>
import { defineProps, defineEmits } from 'vue';

defineProps({
    show: { type: Boolean, default: false },
    message: { type: String, default: '' },
    title: { type: String, default: 'Error' }
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 relative animate-in fade-in zoom-in duration-200">
            
            <!-- Icono de Error -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h3 class="text-lg font-bold text-center text-gray-900 mb-2">{{ title }}</h3>
            
            <!-- TU COMPONENTE DE TEXTO -->
            <div class="text-center mb-6">
                <p class="text-sm text-red-600 dark:text-red-500 font-medium">
                    {{ message }}
                </p>
            </div>
            
            <div class="flex justify-center">
                <button 
                    @click="emit('close')" 
                    class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-md transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>