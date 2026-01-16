<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Search, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppSidebar from '@/components/AppSidebar.vue';

const props = defineProps({
    empleados: Object,
    filters: Object
});

const search = ref(props.filters.search || '');

// Búsqueda con debounce
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        // Asegúrate de que tu ruta en Laravel se llame 'tarjetas.disponibilidad' o similar
        // Si no has cambiado el nombre de la ruta, usa 'users.index'
        router.get(route('users.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
});

const months = [
    { n: 1, s: 'E' }, { n: 2, s: 'F' }, { n: 3, s: 'M' }, { n: 4, s: 'A' },
    { n: 5, s: 'M' }, { n: 6, s: 'J' }, { n: 7, s: 'J' }, { n: 8, s: 'A' },
    { n: 9, s: 'S' }, { n: 10, s: 'O' }, { n: 11, s: 'N' }, { n: 12, s: 'D' }
];

const getStatusColor = (status) => {
    if (status === 'blocked') return 'bg-red-500'; // Bloqueado
    if (status === 'ok') return 'bg-green-500';    // Disponible
    return 'bg-gray-200';                          // Futuro
};
</script>

<template>
    <AppSidebar>
        <Head title="Disponibilidad de Tarjetas" />
        
        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="text-2xl font-bold text-gray-800">Disponibilidad de Tarjetas 2025</h2>
                
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-2 top-2.5 h-4 w-4 text-gray-500" />
                    <Input 
                        v-model="search" 
                        placeholder="Buscar empleado..." 
                        class="pl-8 bg-white"
                    />
                </div>
            </div>

            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-bold border-b">
                            <tr>
                                <th class="px-4 py-3 w-16">ID</th>
                                <th class="px-4 py-3">Empleado</th>
                                <th class="px-4 py-3 hidden md:table-cell">Departamento</th>
                                <!-- Columnas de meses -->
                                <th v-for="m in months" :key="m.n" class="px-1 py-3 text-center w-8">
                                    {{ m.s }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="emp in empleados.data" :key="emp.id" class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-mono text-gray-500">{{ emp.emp_code }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ emp.nombre }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ emp.depto }}</td>
                                
                                <!-- Semáforos Mensuales -->
                                <td v-for="m in months" :key="m.n" class="px-1 py-3 text-center">
                                    <div 
                                        class="h-3 w-3 rounded-full mx-auto"
                                        :class="getStatusColor(emp.estatus_anual[m.n])"
                                        :title="emp.estatus_anual[m.n] === 'blocked' ? 'Bloqueado (Falta/RG)' : 'Disponible'"
                                    ></div>
                                </td>
                            </tr>
                            <tr v-if="empleados.data.length === 0">
                                <td colspan="15" class="px-4 py-8 text-center text-gray-500">
                                    No se encontraron empleados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 bg-gray-50">
                    <div class="text-xs text-gray-500">
                        Mostrando {{ empleados.data.length }} registros
                    </div>
                    <div class="flex gap-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="empleados.current_page === 1"
                            @click="router.visit(route('users.index', { page: empleados.current_page - 1, search }))"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="empleados.current_page === empleados.last_page"
                            @click="router.visit(route('users.index', { page: empleados.current_page + 1, search }))"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppSidebar>
</template>