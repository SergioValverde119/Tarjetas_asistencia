<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { Search, Calendar, User, Clock, Settings2, AlertCircle, Loader2 } from 'lucide-vue-next';
import axios from 'axios';

// --- ESTADO ---
const search = ref('');
const searching = ref(false);
const resultadosBusqueda = ref([]);
const busquedaRealizada = ref(false);

const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Función para buscar los empleados y sus horarios
const buscarEmpleado = async () => {
    if (!search.value) return;
    
    searching.value = true;
    resultadosBusqueda.value = [];
    busquedaRealizada.value = false;

    try {
        // Petición al backend con la nómina o nombre (protegido con encodeURIComponent)
        const response = await axios.get(`/api/empleado/${encodeURIComponent(search.value)}/horario`);
        
        // Asignamos la lista de resultados recibida del Repositorio
        resultadosBusqueda.value = response.data;
        busquedaRealizada.value = true;
        searching.value = false;

    } catch (error) {
        console.error("Error al buscar empleado:", error);
        resultadosBusqueda.value = []; // Vaciamos para mostrar mensaje de "No encontrado"
        busquedaRealizada.value = true;
        searching.value = false;
    }
};

// Función para redirigir a la vista de configuración e historial
const gestionarHorario = (empleado) => {
    // Ahora enviamos a la URL real que carga el controlador
    router.get(`/horarios-asignacion/${empleado.emp_code}/historial`);
};
</script>

<template>
    <Head title="Horario del Personal" />

    <SidebarProvider>
        <AppSidebar>
            <div class="flex flex-col h-screen max-h-screen bg-slate-50 p-6 overflow-hidden">
                <div class="flex flex-col w-full h-full max-w-5xl mx-auto">
                    
                    <!-- ENCABEZADO Y BUSCADOR (Fijo arriba) -->
                    <div class="flex-none bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6 shrink-0">
                        <div class="flex flex-col md:flex-row justify-between gap-6">
                            <div class="flex-1">
                                <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3 mb-2">
                                    <Calendar class="h-7 w-7 text-indigo-600" />
                                    Horario Vigente del Personal
                                </h1>
                                <p class="text-sm text-slate-500 font-medium">Busque por nómina o nombre. Si hay múltiples coincidencias, se mostrará la lista completa.</p>
                            </div>

                            <div class="w-full md:w-96 flex flex-col justify-center">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Nómina o Nombre</label>
                                <form @submit.prevent="buscarEmpleado" class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <Search class="h-5 w-5 text-slate-400" />
                                    </div>
                                    <input 
                                        type="text" 
                                        v-model="search"
                                        placeholder="Ej. 14154 o Sergio" 
                                        class="w-full pl-10 pr-24 py-3 rounded-xl border border-slate-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all font-mono font-bold text-lg"
                                    />
                                    <button 
                                        type="submit"
                                        :disabled="searching || !search"
                                        class="absolute inset-y-1 right-1 px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-black uppercase tracking-wide transition-colors disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <Loader2 v-if="searching" class="h-4 w-4 animate-spin" />
                                        <span v-else>Buscar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ZONA DE CONTENIDO DESPLAZABLE -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 pb-6 space-y-6">

                        <!-- ESTADO INICIAL (VACÍO Y SIN BÚSQUEDA) -->
                        <div v-if="resultadosBusqueda.length === 0 && !searching && !busquedaRealizada" class="bg-white rounded-2xl border border-slate-200 border-dashed p-16 flex flex-col items-center justify-center text-slate-400 h-full min-h-[400px]">
                            <User class="h-16 w-16 mb-4 opacity-20" />
                            <p class="text-lg font-bold">Ingrese una nómina o nombre para comenzar</p>
                            <p class="text-sm mt-2">Se mostrará la estructura de lunes a domingo.</p>
                        </div>

                        <!-- ESTADO: NO SE ENCONTRARON RESULTADOS -->
                        <div v-if="resultadosBusqueda.length === 0 && busquedaRealizada && !searching" class="bg-white rounded-2xl border border-red-200 bg-red-50 p-16 flex flex-col items-center justify-center text-red-500 h-full min-h-[400px] animate-in fade-in zoom-in-95 duration-300">
                            <AlertCircle class="h-16 w-16 mb-4 opacity-50" />
                            <p class="text-lg font-bold text-red-700">No se encontraron empleados</p>
                            <p class="text-sm mt-2 text-red-600">Verifique el nombre o el número de nómina e intente de nuevo.</p>
                        </div>

                        <!-- RESULTADOS DE LA BÚSQUEDA (LISTA DE TARJETAS) -->
                        <div v-if="resultadosBusqueda.length > 0" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            
                            <!-- Contador de resultados -->
                            <div class="text-sm font-bold text-slate-500 uppercase tracking-widest pl-2 border-l-4 border-indigo-500">
                                Se encontraron {{ resultadosBusqueda.length }} coincidencia(s)
                            </div>

                            <!-- ITERAMOS SOBRE CADA EMPLEADO ENCONTRADO -->
                            <div v-for="item in resultadosBusqueda" :key="item.empleado.id" class="shadow-xl rounded-2xl bg-white border border-slate-200">
                                
                                <!-- FICHA DEL EMPLEADO Y BOTÓN DE CONFIGURAR -->
                                <div class="bg-indigo-900 rounded-t-2xl p-5 md:p-6 text-white flex flex-col sm:flex-row justify-between items-center gap-4">
                                    <div class="flex items-center gap-4 w-full sm:w-auto">
                                        <div class="h-14 w-14 rounded-full bg-white/20 flex items-center justify-center font-black text-2xl border border-white/30 shrink-0">
                                            {{ item.empleado.first_name ? item.empleado.first_name.charAt(0) : '?' }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <h2 class="text-xl font-black uppercase tracking-tight truncate">{{ item.empleado.first_name }} {{ item.empleado.last_name }}</h2>
                                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-indigo-200 text-xs font-bold mt-1 uppercase tracking-widest">
                                                <span class="font-mono bg-indigo-950/50 px-2 py-0.5 rounded">ID: {{ item.empleado.emp_code }}</span>
                                                <span class="flex items-center">{{ item.empleado.department_name }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BOTÓN ACTUALIZADO -->
                                    <button @click="gestionarHorario(item.empleado)" class="w-full sm:w-auto px-6 py-3 bg-white text-indigo-900 rounded-xl font-black uppercase text-xs hover:bg-indigo-50 transition-all shadow-lg flex items-center justify-center gap-2 active:scale-95 shrink-0 border border-transparent hover:border-indigo-200">
                                        <Settings2 class="h-4 w-4" />
                                        Configurar / Modificar
                                    </button>
                                </div>

                                <!-- LOS 7 BLOQUES DE LA SEMANA -->
                                <div class="p-5 md:p-6">
                                    <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                                        <div>
                                            <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Nombre de la Plantilla Actual</p>
                                            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                                {{ item.horarioSemanal.nombre_turno || 'Personalizado / Sin Plantilla' }}
                                                <span v-if="!item.horarioSemanal.nombre_turno" class="px-2 py-1 bg-amber-100 text-amber-700 text-[10px] rounded-md uppercase tracking-wider">Libre</span>
                                            </h3>
                                        </div>
                                    </div>

                                    <!-- GRID DE DÍAS -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                                        
                                        <div v-for="dia in item.horarioSemanal.dias" :key="dia.dia_index" 
                                             class="rounded-xl border p-3 flex flex-col justify-between min-h-[120px] transition-all"
                                             :class="dia.activo ? 'bg-indigo-50/50 border-indigo-100 hover:border-indigo-300 hover:shadow-md' : 'bg-slate-50 border-slate-100 opacity-70'">
                                            
                                            <!-- Nombre del Día -->
                                            <div class="flex justify-between items-start mb-3">
                                                <span class="text-xs font-black uppercase tracking-widest" :class="dia.activo ? 'text-indigo-900' : 'text-slate-400'">
                                                    {{ diasSemana[dia.dia_index] }}
                                                </span>
                                                <div v-if="!dia.activo" class="h-2 w-2 rounded-full bg-slate-300"></div>
                                                <div v-else class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.6)]"></div>
                                            </div>

                                            <!-- Horas o Descanso -->
                                            <div v-if="dia.activo" class="space-y-2 mt-auto">
                                                <div class="flex items-center gap-2">
                                                    <div class="p-1 rounded bg-indigo-100 text-indigo-700"><Clock class="h-3 w-3" /></div>
                                                    <div>
                                                        <p class="text-[8px] font-black text-slate-400 uppercase leading-none mb-0.5">Entrada</p>
                                                        <p class="text-sm font-mono font-black text-slate-800 leading-none">{{ dia.in_time }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="p-1 rounded bg-rose-100 text-rose-700"><Clock class="h-3 w-3" /></div>
                                                    <div>
                                                        <p class="text-[8px] font-black text-slate-400 uppercase leading-none mb-0.5">Salida</p>
                                                        <p class="text-sm font-mono font-black text-slate-800 leading-none">{{ dia.out_time }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-else class="flex flex-col items-center justify-center flex-1 text-slate-400 mt-2">
                                                <AlertCircle class="h-5 w-5 mb-1 stroke-1" />
                                                <span class="text-[10px] font-bold uppercase tracking-widest">Descanso</span>
                                            </div>

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>