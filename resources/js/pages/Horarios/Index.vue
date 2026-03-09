<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    Search, X, CheckCircle, AlertCircle, User, Loader2, Settings2, CalendarDays, Clock
} from 'lucide-vue-next';
import axios from 'axios';

// ============================================================================
// PROPS Y ESTADO GENERAL
// ============================================================================
const props = defineProps({
    flash: Object
});

const showFlash = ref(true);

// ============================================================================
// BUSCADOR INDIVIDUAL (EMPLEADOS)
// ============================================================================
const searchEmp = ref('');
const searchingEmp = ref(false);
const resultadosBusqueda = ref([]);
const busquedaRealizada = ref(false);

const buscarEmpleado = async () => {
    if (!searchEmp.value) return;
    
    searchingEmp.value = true;
    resultadosBusqueda.value = [];
    busquedaRealizada.value = false;

    try {
        const response = await axios.get(`/api/empleado/${encodeURIComponent(searchEmp.value)}/horario`);
        
        // Verificamos el formato de respuesta del servidor (Protección de Array)
        if (response.data && Array.isArray(response.data)) {
            resultadosBusqueda.value = response.data;
        } else if (response.data && response.data.empleado) {
            resultadosBusqueda.value = [response.data];
        } else {
            resultadosBusqueda.value = [];
        }

        busquedaRealizada.value = true;
        searchingEmp.value = false;
    } catch (error) {
        console.error("Error en búsqueda:", error);
        resultadosBusqueda.value = []; 
        busquedaRealizada.value = true;
        searchingEmp.value = false;
    }
};

const gestionarHorario = (nomina) => {
    router.get(`/horarios-asignacion/${nomina}/historial`);
};
</script>

<template>
    <Head title="Gestión de Horarios" />

    <SidebarProvider>
        <AppSidebar>
            <!-- CONTENEDOR BLANCO Y ANCHO TOTAL -->
            <div class="flex flex-col h-screen max-h-screen bg-white overflow-hidden w-full font-sans">
                
                <div class="flex flex-col w-full h-full p-4 md:p-6 gap-4 md:gap-6">

                    <!-- ALERTAS GLOBALES -->
                    <div v-if="$page.props.flash?.success && showFlash" class="flex-none bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center justify-between shadow-sm animate-in fade-in slide-in-from-top-2">
                        <div class="flex items-center gap-2"><CheckCircle class="h-5 w-5" /><span class="font-bold">{{ $page.props.flash.success }}</span></div>
                        <button @click="showFlash = false" class="text-green-600 hover:text-green-800"><X class="h-4 w-4" /></button>
                    </div>

                    <!-- BARRA DE BÚSQUEDA (MÁS PEQUEÑA Y COMPACTA) -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-lg p-1.5 max-w-2xl mx-auto w-full">
                        <form @submit.prevent="buscarEmpleado" class="relative flex items-center">
                            <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
                                <Search class="h-5 w-5 text-gray-400" />
                            </div>
                            <input 
                                type="text" v-model="searchEmp" 
                                placeholder="Buscar calendario de empleado..." 
                                class="w-full pl-12 pr-32 py-3 rounded-xl border-0 focus:ring-0 text-base font-bold text-gray-900 placeholder-gray-400 bg-transparent"
                                autofocus
                            />
                            <button 
                                type="submit" 
                                :disabled="searchingEmp || !searchEmp" 
                                class="absolute right-1 px-6 py-2.5 bg-gray-900 text-white hover:bg-black rounded-lg text-xs font-black uppercase tracking-widest transition-all disabled:opacity-50 flex items-center gap-2 active:scale-95"
                            >
                                <Loader2 v-if="searchingEmp" class="h-3 w-3 animate-spin" />
                                <span v-else>Buscar</span>
                            </button>
                        </form>
                    </div>

                    <!-- ÁREA DE RESULTADOS (ANCHO TOTAL) -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar relative px-2">
                        
                        <!-- ESTADO INICIAL -->
                        <div v-if="resultadosBusqueda.length === 0 && !searchingEmp && !busquedaRealizada" class="flex flex-col items-center justify-center h-full text-gray-300">
                            <CalendarDays class="h-20 w-20 mb-3 stroke-1 opacity-30" />
                            <p class="text-sm font-black uppercase tracking-widest opacity-50">Ingrese nómina o nombre para comenzar</p>
                        </div>

                        <!-- SIN RESULTADOS -->
                        <div v-if="resultadosBusqueda.length === 0 && busquedaRealizada && !searchingEmp" class="flex flex-col items-center justify-center h-full">
                            <div class="bg-red-50 text-red-600 p-8 rounded-2xl border border-red-100 flex flex-col items-center text-center animate-in zoom-in duration-300">
                                <AlertCircle class="h-10 w-10 mb-3 opacity-50" />
                                <p class="font-black text-lg uppercase tracking-tight">No se encontraron empleados</p>
                                <p class="text-xs mt-1 font-medium">Verifique que el dato sea correcto en BioTime.</p>
                            </div>
                        </div>

                        <!-- LISTADO DE TARJETAS (MÁS PEQUEÑAS) -->
                        <div v-if="resultadosBusqueda.length > 0" class="space-y-4 animate-in fade-in slide-in-from-bottom-4 pb-10">
                            
                            <div v-for="item in resultadosBusqueda" :key="item.empleado.id" class="bg-white border border-gray-200 rounded-2xl shadow-md overflow-hidden hover:border-gray-400 transition-all group">
                                
                                <!-- Encabezado de la Tarjeta (Compacto) -->
                                <div class="p-4 md:p-5 flex flex-col md:flex-row justify-between items-center gap-4 border-b border-gray-100">
                                    <div class="flex items-center gap-5 w-full">
                                        <!-- Avatar más pequeño -->
                                        <div class="h-14 w-14 rounded-2xl bg-gray-900 flex items-center justify-center font-black text-2xl text-white shrink-0 shadow-md group-hover:rotate-2 transition-transform">
                                            {{ item.empleado.first_name ? item.empleado.first_name.charAt(0) : '?' }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <p class="text-[9px] font-black uppercase text-gray-400 tracking-widest mb-0.5">Servidor Público</p>
                                            <h3 class="text-xl font-black text-gray-900 uppercase leading-none truncate mb-2">{{ item.empleado.first_name }} {{ item.empleado.last_name }}</h3>
                                            
                                            <div class="flex items-center gap-3 text-[10px] font-bold flex-wrap">
                                                <span class="bg-red-50 text-red-700 px-3 py-1 rounded-full border border-red-100 font-mono tracking-tighter">ID: {{ item.empleado.emp_code }}</span>
                                                <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full border border-gray-200 uppercase tracking-widest">{{ item.empleado.department_name || 'Sin Área' }}</span>
                                                <span class="flex items-center gap-1.5 text-gray-400"><Clock class="h-3.5 w-3.5" /> <strong class="text-gray-900 uppercase">{{ item.horarioSemanal?.nombre_turno || 'Sin Plantilla' }}</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Botón más pequeño -->
                                    <button @click="gestionarHorario(item.empleado.emp_code)" class="w-full md:w-auto shrink-0 px-6 py-3 bg-gray-900 text-white hover:bg-black rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 active:scale-95 shadow-lg">
                                        <Settings2 class="h-4 w-4" /> Configurar
                                    </button>
                                </div>

                                <!-- Vista de la Semana (Los 7 bloques compactos) -->
                                <div class="bg-gray-50/40 p-4 md:p-5">
                                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                                        <div v-for="(dia, idx) in item.horarioSemanal.dias" :key="idx" 
                                             class="bg-white border rounded-xl p-3 flex flex-col justify-between min-h-[110px] shadow-sm transition-all"
                                             :class="dia.activo ? 'border-gray-300 hover:shadow-md' : 'opacity-30 border-dashed border-gray-200'">
                                            
                                            <span class="text-[9px] font-black uppercase tracking-widest" :class="dia.activo ? 'text-gray-900' : 'text-gray-400'">
                                                {{ ['DOM','LUN','MAR','MIE','JUE','VIE','SAB'][dia.dia_index] }}
                                            </span>

                                            <div v-if="dia.activo" class="mt-2 space-y-1.5">
                                                <div>
                                                    <p class="text-[7px] font-black text-gray-400 uppercase leading-none">In</p>
                                                    <p class="text-xs font-mono font-black text-gray-900">{{ dia.in_time }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[7px] font-black text-gray-400 uppercase leading-none">Out</p>
                                                    <p class="text-xs font-mono font-black text-gray-900">{{ dia.out_time }}</p>
                                                </div>
                                            </div>

                                            <div v-else class="flex-1 flex items-center justify-center">
                                                <span class="text-[9px] font-black text-gray-300 uppercase tracking-tighter">Libre</span>
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
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 20px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>