<script setup>
import { ref } from 'vue';
import { FileUp, X, AlertTriangle, Info, Download, Loader2 } from 'lucide-vue-next';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    mostrar: Boolean
});

const emit = defineEmits(['cerrar', 'exito', 'error']);
const isImporting = ref(false);

const manejarSubida = async (event) => {
    const formElement = event.target;
    const formData = new FormData(formElement);
    isImporting.value = true;

    try {
        const response = await axios.post('/incidencias/importar', formData, {
            responseType: 'blob', 
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Descarga del archivo de resultados
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `resultado_importacion_${new Date().toISOString().slice(0,10)}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        emit('exito', "El proceso ha finalizado. Se ha descargado un archivo de Excel con los resultados. Por favor, verifique la columna 'ESTATUS' en el archivo descargado.");
        formElement.reset(); 
        emit('cerrar');
        router.reload(); 

    } catch (error) {
        let mensaje = "Error al procesar el archivo.";
        if (error.response && error.response.data instanceof Blob) {
            const reader = new FileReader();
            reader.onload = () => {
                try {
                    const errorData = JSON.parse(reader.result);
                    emit('error', errorData.message || mensaje);
                } catch (e) {
                    emit('error', "Error crítico. Verifique el formato de fecha o si eliminó las instrucciones de la plantilla.");
                }
            };
            reader.readAsText(error.response.data);
        } else {
            emit('error', error.response?.data?.message || mensaje);
        }
    } finally {
        isImporting.value = false;
    }
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative animate-in zoom-in-95 duration-200">
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <h3 class="text-lg font-black text-gray-900 flex items-center gap-2"><FileUp class="h-5 w-5 text-blue-600" /> Importación Masiva</h3>
                <button @click="emit('cerrar')" class="p-1 hover:bg-gray-100 rounded-full text-gray-400 hover:text-gray-600 transition-colors"><X class="h-5 w-5" /></button>
            </div>

            <div class="mb-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg flex items-start gap-3 shadow-sm">
                <AlertTriangle class="h-6 w-6 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <p class="text-[10px] text-amber-900 font-bold mb-1 uppercase tracking-widest">Instrucción Crucial</p>
                    <p class="text-xs text-amber-800 leading-relaxed">
                        Asegúrese de que el archivo <strong>SOLO tenga los datos</strong> a partir de la fila 2. Elimine cualquier fila de ejemplo de la plantilla.
                    </p>
                </div>
            </div>

            <div class="mb-6 flex items-center gap-3 p-3 bg-blue-50 text-blue-800 rounded-xl border border-blue-100 shadow-sm">
                <Info class="h-5 w-5 shrink-0" />
                <p class="text-[10px] font-medium leading-tight italic">
                    El sistema saltará automáticamente las primeras 2 filas de la plantilla oficial.
                </p>
            </div>

            <form @submit.prevent="manejarSubida" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase tracking-widest">Archivo Excel (.xlsx)</label>
                    <input type="file" name="file" accept=".xlsx,.xls" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-xl p-1 cursor-pointer transition-colors" />
                </div>
                
                <div class="flex justify-center mb-4">
                    <a href="/incidencias/plantilla" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1.5 transition-colors uppercase tracking-tighter">
                        <Download class="h-4 w-4" /> Descargar plantilla oficial
                    </a>
                </div>

                <div class="flex justify-end pt-2 border-t border-gray-100">
                    <button type="submit" :disabled="isImporting" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 rounded-xl shadow-md disabled:opacity-50 flex justify-center items-center gap-2 transition-all active:scale-95 uppercase tracking-widest text-xs">
                        <Loader2 v-if="isImporting" class="h-4 w-4 animate-spin" />
                        {{ isImporting ? 'Procesando archivo...' : 'Subir y Procesar' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>