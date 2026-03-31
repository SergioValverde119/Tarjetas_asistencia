<script setup>
import { ref, watch } from 'vue';
import { Filter, RotateCw, Search } from 'lucide-vue-next';

const props = defineProps({
    filtrosOriginales: Object
});

const emit = defineEmits(['cambio']);

const busqueda = ref(props.filtrosOriginales.search || '');
const fechaRegistro = ref(props.filtrosOriginales.date_apply || '');
const fechaIncidencia = ref(props.filtrosOriginales.date_incidence || '');
const fechaInicio = ref(props.filtrosOriginales.date_start || '');
const fechaFin = ref(props.filtrosOriginales.date_end || '');

watch([busqueda, fechaRegistro, fechaIncidencia, fechaInicio, fechaFin], () => {
    emit('cambio', {
        search: busqueda.value,
        date_apply: fechaRegistro.value,
        date_incidence: fechaIncidencia.value,
        date_start: fechaInicio.value,
        date_end: fechaFin.value
    });
});

const limpiar = () => {
    busqueda.value = '';
    fechaRegistro.value = '';
    fechaIncidencia.value = '';
    fechaInicio.value = '';
    fechaFin.value = '';
};

const alCambiarIncidencia = () => {
    if (fechaIncidencia.value) {
        fechaInicio.value = '';
        fechaFin.value = '';
    }
};

const alCambiarRango = () => {
    if (fechaInicio.value || fechaFin.value) {
        fechaIncidencia.value = '';
    }
};
</script>

<template>
    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 w-full">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <Filter class="h-4 w-4 text-blue-500" /> Búsqueda Avanzada
            </h3>
            <button @click="limpiar" class="text-xs text-gray-500 hover:text-red-600 flex items-center gap-1 font-bold transition-colors">
                <RotateCw class="h-3 w-3" /> Limpiar
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-3">
                <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Empleado</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input v-model="busqueda" type="text" class="block w-full pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-gray-50" placeholder="Nombre o ID..." />
                </div>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase tracking-widest">Día Registro</label>
                <input v-model="fechaRegistro" type="date" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-gray-50" />
            </div>

            <div class="hidden md:flex md:col-span-1 justify-center pb-2">
                <div class="h-6 w-px bg-gray-200"></div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-blue-600 mb-1 uppercase tracking-widest">Día Exacto</label>
                <input v-model="fechaIncidencia" @input="alCambiarIncidencia" type="date" class="block w-full rounded-lg border-blue-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm h-10 bg-blue-50/30" />
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-orange-600 mb-1 uppercase tracking-widest">Rango (Desde)</label>
                <input v-model="fechaInicio" @input="alCambiarRango" type="date" class="block w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
            </div>

            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-orange-600 mb-1 uppercase tracking-widest">Rango (Hasta)</label>
                <input v-model="fechaFin" @input="alCambiarRango" type="date" class="block w-full rounded-lg border-orange-200 shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm h-10 bg-orange-50/30" />
            </div>
        </div>
    </div>
</template>