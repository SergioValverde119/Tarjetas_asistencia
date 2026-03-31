<script setup>
import { Link } from '@inertiajs/vue3';
import { Edit2, Calendar, Clock, Filter } from 'lucide-vue-next';
import { edit as rutaEditar } from '@/routes/incidencias';

const props = defineProps({
    datos: Object,
    filtrosActuales: Object
});

const emit = defineEmits(['cambiarPagina']);

// Formatea la fecha para visualización amigable
const formatearFecha = (fecha) => {
    if (!fecha) return '-';
    return fecha.substring(0, 16).replace('T', ' ');
};
</script>

<template>
    <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden flex flex-col flex-grow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider w-20 text-[11px]">Folio</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Empleado</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Tipo</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Registro</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Vigencia</th>
                        <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px]">Motivo</th>
                        <th class="px-6 py-3 text-right font-bold text-gray-500 uppercase tracking-wider text-[11px]">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="inc in datos.data" :key="inc.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">#{{ inc.id }}</td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900 uppercase leading-tight">{{ inc.first_name }} {{ inc.last_name }}</div>
                            <div class="text-[10px] text-gray-400 font-mono">ID: {{ inc.emp_code }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800 border border-blue-200">
                                {{ inc.tipo }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ formatearFecha(inc.apply_time) }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col text-xs space-y-1">
                                <span class="flex items-center gap-1 font-medium text-green-700">
                                    <Calendar class="h-3 w-3" /> {{ formatearFecha(inc.start_time) }}
                                </span>
                                <span class="flex items-center gap-1 font-medium text-red-600">
                                    <Clock class="h-3 w-3" /> {{ formatearFecha(inc.end_time) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-600 max-w-xs truncate italic" :title="inc.apply_reason">
                            {{ inc.apply_reason || '-' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link 
                                v-if="rutaEditar"
                                :href="rutaEditar(inc.id).url" 
                                :data="{ ...filtrosActuales, page: datos.current_page }"
                                class="text-blue-600 hover:text-blue-900 inline-flex items-center gap-1 font-black uppercase tracking-widest text-[10px] bg-white border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-all shadow-sm active:scale-95"
                            >
                                <Edit2 class="h-3 w-3" /> Editar
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="datos.data.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 bg-gray-50/50 italic">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <Filter class="h-8 w-8 text-gray-300" />
                                <p class="text-sm font-medium">No se encontraron incidencias con estos filtros.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between shrink-0" v-if="datos.total > 0">
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">
                        Mostrando <span class="text-gray-900">{{ datos.from }}</span> a <span class="text-gray-900">{{ datos.to }}</span> de <span class="text-gray-900">{{ datos.total }}</span> resultados
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-lg shadow-sm gap-2">
                        <button @click="emit('cambiarPagina', datos.prev_page_url)" :disabled="!datos.prev_page_url" class="px-4 py-2 border border-gray-300 bg-white text-xs font-black uppercase text-gray-600 disabled:opacity-50 rounded-lg hover:bg-gray-100 transition-colors active:scale-95">Anterior</button>
                        <button @click="emit('cambiarPagina', datos.next_page_url)" :disabled="!datos.next_page_url" class="px-4 py-2 border border-gray-300 bg-white text-xs font-black uppercase text-gray-600 disabled:opacity-50 rounded-lg hover:bg-gray-100 transition-colors active:scale-95">Siguiente</button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>