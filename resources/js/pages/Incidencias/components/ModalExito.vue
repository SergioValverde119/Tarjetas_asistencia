<script setup>
import { CheckCircle } from 'lucide-vue-next';

const props = defineProps({
    mostrar: Boolean,
    mensaje: String
});

const emit = defineEmits(['cerrar']);
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-all">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center animate-in zoom-in-95 duration-200 border border-gray-100">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6 border-4 border-green-50">
                <CheckCircle class="h-8 w-8 text-green-600" />
            </div>
            <h3 class="text-xl font-black text-gray-900 mb-2 tracking-tight uppercase">¡Proceso Terminado!</h3>
            <p class="text-sm text-gray-500 mb-8 font-medium leading-relaxed">{{ mensaje }}</p>
            <button @click="emit('cerrar')" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition-colors shadow-lg shadow-green-600/30 uppercase text-sm tracking-widest">
                Entendido
            </button>
        </div>
    </div>
</template>