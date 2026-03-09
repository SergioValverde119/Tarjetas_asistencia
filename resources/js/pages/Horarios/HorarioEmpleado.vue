<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    Calendar, User, Save, CheckCircle, ArrowLeft, 
    History, Clock, Trash2, Loader2, Plus, ShieldCheck, Search, Check, ChevronDown, ChevronUp
} from 'lucide-vue-next';

// Definición de las propiedades recibidas desde el controlador
const props = defineProps({
    empleado: {
        type: Object,
        required: true
    },
    historial: {
        type: Array,
        default: () => []
    },
    turnos: {
        type: Array,
        default: () => []
    },
    flash: {
        type: Object,
        default: () => ({})
    }
});

const showFlash = ref(true);
const showDeleteModal = ref(false);
const assignmentToDelete = ref(null);

// Estado para controlar qué elementos del historial están expandidos
// Iniciamos con el primer elemento (el vigente) expandido
const expandedItems = ref({
    [props.historial.length > 0 ? props.historial[0].id : null]: true
});

const toggleExpand = (id) => {
    expandedItems.value[id] = !expandedItems.value[id];
};

const diasAbreviados = ['DOM', 'LUN', 'MAR', 'MIE', 'JUE', 'VIE', 'SAB'];

// --- BUSCADOR DE SEMANARIOS ---
const searchHorario = ref('');
const filteredTurnos = computed(() => {
    if (!searchHorario.value) return props.turnos;
    const term = searchHorario.value.toLowerCase();
    return props.turnos.filter(t => 
        t.nombre.toLowerCase().includes(term) || 
        (t.entrada_ref && t.entrada_ref.includes(term))
    );
});

// --- FORMULARIO DE ASIGNACIÓN ---
const form = useForm({
    shift_id: '',
    start_date: '',
    end_date: '2030-12-31' 
});

const selectTurno = (id) => {
    form.shift_id = id;
};

const submit = () => {
    form.post(`/horarios-asignacion/${props.empleado.emp_code}/asignar`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('shift_id', 'start_date');
            showFlash.value = true;
            setTimeout(() => showFlash.value = false, 5000);
        }
    });
};

// --- GESTIÓN DE BORRADO ---
const openDeleteModal = (item) => {
    assignmentToDelete.value = item;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!assignmentToDelete.value) return;
    router.delete(`/horarios-asignacion/borrar/${assignmentToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            assignmentToDelete.value = null;
            showFlash.value = true;
            setTimeout(() => showFlash.value = false, 5000);
        }
    });
};
</script>

<template>
    <Head :title="`Configurar Horario - ${empleado.first_name}`" />

    <SidebarProvider>
        <AppSidebar>
            <div class="flex flex-col h-screen max-h-screen bg-slate-50 overflow-hidden w-full font-sans text-slate-900">
                
                <!-- CABECERA SUPERIOR FIJA -->
                <div class="flex-none bg-white p-4 md:px-6 flex justify-between items-center border-b border-slate-200 z-30 shadow-sm">
                    <div class="flex items-center gap-4">
                        <Link href="/horarios" class="p-2 bg-slate-100 border border-slate-200 rounded-xl hover:bg-slate-200 transition-colors text-slate-600">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 class="text-lg font-black text-slate-900 uppercase tracking-tight flex items-center gap-2 leading-none">
                                <User class="h-5 w-5 text-slate-400" />
                                {{ empleado.first_name }} {{ empleado.last_name }}
                            </h1>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                                Nómina: <span class="text-red-700 font-mono">{{ empleado.emp_code }}</span> | Área: {{ empleado.department_name }}
                            </p>
                        </div>
                    </div>

                    <div v-if="$page.props.flash?.success && showFlash" class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-2 rounded-xl flex items-center gap-2 text-xs font-bold animate-in fade-in">
                        <CheckCircle class="h-4 w-4" /> {{ $page.props.flash.success }}
                    </div>
                </div>

                <!-- CONTENIDO EN DOS COLUMNAS VERTICALES -->
                <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
                    
                    <!-- IZQUIERDA: SELECTOR DE SEMANARIOS (MEDIA PANTALLA) -->
                    <div class="w-full lg:w-1/2 flex flex-col bg-white border-r border-slate-200 overflow-hidden">
                        
                        <!-- Buscador Interno -->
                        <div class="p-4 border-b border-slate-100 shrink-0 bg-slate-50/40">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-[10px] font-black uppercase text-slate-500 tracking-widest flex items-center gap-2">
                                    <Plus class="h-4 w-4 text-emerald-500" /> 1. Elegir Nuevo Semanario
                                </h2>
                            </div>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                <input 
                                    v-model="searchHorario" 
                                    type="text" 
                                    placeholder="Buscar por nombre o entrada..." 
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border-slate-200 bg-white focus:ring-2 focus:ring-slate-900 text-sm font-bold shadow-inner"
                                />
                            </div>
                        </div>

                        <!-- Lista de Semanarios (Diseño de Blanco a Negro) -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar bg-white">
                            <div 
                                v-for="turno in filteredTurnos" :key="turno.id" 
                                @click="selectTurno(turno.id)"
                                class="w-full rounded-2xl border p-4 transition-all cursor-pointer group flex flex-col gap-3"
                                :class="form.shift_id === turno.id 
                                    ? 'bg-slate-900 border-slate-900 shadow-2xl scale-[1.01]' 
                                    : 'bg-white border-slate-100 hover:border-slate-300 shadow-sm'"
                            >
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-black uppercase tracking-tight" :class="form.shift_id === turno.id ? 'text-white' : 'text-slate-900'">
                                            {{ turno.nombre }}
                                        </span>
                                        <!-- Tolerancia al lado del nombre -->
                                        <div class="flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border"
                                             :class="form.shift_id === turno.id ? 'bg-emerald-500 border-emerald-400 text-white' : 'bg-red-50 border-red-100 text-red-700'">
                                            <ShieldCheck class="h-3 w-3" /> Tol: {{ turno.tolerancia }}m
                                        </div>
                                    </div>
                                    <div v-if="form.shift_id === turno.id" class="h-6 w-6 rounded-full bg-emerald-500 flex items-center justify-center animate-in zoom-in">
                                        <Check class="h-4 w-4 text-white stroke-[4]" />
                                    </div>
                                    <div v-else class="h-6 w-6 rounded-full border-2 border-slate-100 bg-slate-50"></div>
                                </div>

                                <!-- Bloques de los 7 días con LETRA GRANDE -->
                                <div class="grid grid-cols-7 gap-1">
                                    <div v-for="dayIdx in [1, 2, 3, 4, 5, 6, 0]" :key="dayIdx" 
                                         class="flex flex-col items-center justify-center py-2.5 rounded-xl border transition-colors"
                                         :class="form.shift_id === turno.id 
                                            ? 'bg-slate-800 border-slate-700' 
                                            : 'bg-slate-50 border-slate-100'"
                                    >
                                        <span class="text-[7px] font-black mb-1 opacity-50 uppercase" :class="form.shift_id === turno.id ? 'text-slate-400' : 'text-slate-500'">
                                            {{ diasAbreviados[dayIdx] }}
                                        </span>
                                        <div v-if="turno.dias[dayIdx]?.activo" class="text-center">
                                            <!-- Letra de horas más grande -->
                                            <p class="text-xs font-mono font-black tracking-tighter leading-tight" :class="form.shift_id === turno.id ? 'text-white' : 'text-slate-900'">
                                                {{ turno.dias[dayIdx].in }}
                                            </p>
                                            <p class="text-[10px] font-mono font-bold tracking-tighter opacity-60 leading-tight" :class="form.shift_id === turno.id ? 'text-slate-400' : 'text-slate-500'">
                                                {{ turno.dias[dayIdx].out }}
                                            </p>
                                        </div>
                                        <div v-else class="h-6 flex items-center">
                                            <div class="w-1.5 h-1.5 rounded-full bg-slate-300"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: Periodo y Botón -->
                        <div class="p-5 bg-slate-50 border-t border-slate-200 shrink-0 shadow-inner">
                            <h3 class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-4">2. Definir Vigencia</h3>
                            <div class="flex flex-col sm:flex-row gap-4 mb-5">
                                <div class="flex-1">
                                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Fecha de Inicio</label>
                                    <input v-model="form.start_date" type="date" required class="w-full rounded-xl border-slate-200 shadow-sm focus:ring-slate-900 h-11 bg-white font-bold" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Fecha de Vencimiento</label>
                                    <input v-model="form.end_date" type="date" required class="w-full rounded-xl border-slate-200 shadow-sm focus:ring-slate-900 h-11 bg-white font-bold" />
                                </div>
                            </div>
                            <button 
                                @click="submit" 
                                :disabled="form.processing || !form.shift_id || !form.start_date" 
                                class="w-full bg-slate-900 hover:bg-black text-white font-black py-4 rounded-2xl transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3 uppercase tracking-widest text-xs disabled:opacity-30"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" /> 
                                Confirmar y Aplicar Horario
                            </button>
                        </div>
                    </div>

                    <!-- DERECHA: HISTORIAL COLAPSABLE -->
                    <div class="w-full lg:w-1/2 flex flex-col bg-slate-50 overflow-hidden">
                        <div class="p-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 shadow-sm">
                            <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                                <History class="h-4 w-4 text-slate-400" /> Línea de Tiempo de Calendarios
                            </h3>
                            <span class="text-[9px] font-black text-slate-400 bg-slate-100 px-3 py-1 rounded-full border border-slate-200 uppercase tracking-tighter">
                                {{ historial.length }} PERIODOS
                            </span>
                        </div>

                        <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 custom-scrollbar">
                            <div v-for="(item, idx) in historial" :key="item.id" 
                                 class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300"
                                 :class="expandedItems[item.id] ? 'border-slate-400 ring-2 ring-slate-100 shadow-lg' : 'hover:border-slate-300'">
                                
                                <!-- Cabecera del Item (Siempre visible) -->
                                <div @click="toggleExpand(item.id)" class="p-5 flex justify-between items-center cursor-pointer select-none">
                                    <div class="flex items-center gap-4 overflow-hidden">
                                        <div class="h-10 w-10 rounded-xl flex items-center justify-center border-2 font-black text-sm shrink-0" 
                                             :class="idx === 0 ? 'bg-green-50 border-green-200 text-green-700' : 'bg-slate-50 border-slate-200 text-slate-400'">
                                            {{ idx === 0 ? 'V' : 'H' }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-black text-slate-900 text-sm uppercase tracking-tight truncate leading-none">
                                                    {{ item.nombre_turno }}
                                                </h4>
                                                <span class="px-2 py-0.5 bg-red-50 text-red-700 border border-red-100 text-[8px] font-black rounded uppercase tracking-widest shrink-0">
                                                    Tol: {{ item.tolerancia }}m
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <div v-if="idx === 0" class="px-1.5 py-0.5 bg-green-600 text-white text-[7px] font-black uppercase rounded shadow-sm">Vigente</div>
                                                <p class="text-[10px] font-mono font-bold text-slate-500">
                                                    {{ item.start_date }} <span class="text-red-500 italic">AL</span> {{ item.end_date }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button @click.stop="openDeleteModal(item)" class="p-2 text-slate-300 hover:text-red-600 transition-colors">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                        <div class="p-2 text-slate-400">
                                            <ChevronUp v-if="expandedItems[item.id]" class="h-5 w-5" />
                                            <ChevronDown v-else class="h-5 w-5" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Cuerpo del Item (Colapsable) -->
                                <div v-if="expandedItems[item.id]" class="p-5 pt-0 animate-in slide-in-from-top-2 border-t border-slate-100 bg-slate-50/30">
                                    <div class="mt-4 grid grid-cols-7 gap-1">
                                        <div v-for="dayIdx in [1, 2, 3, 4, 5, 6, 0]" :key="dayIdx" 
                                             class="rounded-xl border p-2 flex flex-col items-center justify-center min-h-[70px] bg-white transition-all shadow-sm"
                                             :class="item.dias[dayIdx]?.activo ? 'border-slate-200' : 'border-dashed border-slate-200 opacity-40'">
                                            
                                            <span class="text-[7px] font-black uppercase tracking-widest text-slate-400 mb-1">
                                                {{ diasAbreviados[dayIdx] }}
                                            </span>

                                            <div v-if="item.dias[dayIdx]?.activo" class="text-center">
                                                <p class="text-[10px] font-mono font-black text-slate-800 leading-none mb-0.5">{{ item.dias[dayIdx].in }}</p>
                                                <p class="text-[9px] font-mono font-bold text-slate-400 leading-none">{{ item.dias[dayIdx].out }}</p>
                                            </div>
                                            <div v-else class="h-4 flex items-center">
                                                <div class="w-1 h-1 rounded-full bg-slate-200"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vigencia destacada (Solo se ve al expandir) -->
                                    <div class="mt-4 flex justify-center">
                                        <div class="inline-flex items-center bg-slate-900 text-white px-5 py-2.5 rounded-2xl border-2 border-slate-800 shadow-lg">
                                            <Calendar class="h-4 w-4 text-slate-500 mr-3" />
                                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest mr-3">Vigencia Total:</span>
                                            <span class="text-sm font-mono font-black tracking-tighter text-white">
                                                {{ item.start_date }} 
                                                <span class="text-red-500 font-sans mx-2 italic font-black">AL</span> 
                                                {{ item.end_date }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="historial.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400 italic">
                                <History class="h-16 w-16 mx-auto mb-3 opacity-20" />
                                <p class="font-bold uppercase tracking-widest text-xs">Sin historial de horarios registrados.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- MODAL DE CONFIRMACIÓN -->
            <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm transition-all">
                <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-sm w-full p-10 text-center animate-in zoom-in-95 border border-gray-100">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-3xl bg-red-50 mb-8 border border-red-100">
                        <Trash2 class="h-10 w-10 text-red-600" />
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-3 uppercase tracking-tight leading-tight">¿Eliminar Registro?</h3>
                    <p class="text-sm text-gray-500 mb-10 font-medium leading-relaxed">Esta acción quitará este calendario del historial del servidor de forma permanente.</p>
                    <div class="flex gap-4">
                        <button @click="showDeleteModal = false" class="flex-1 py-4 bg-slate-100 text-slate-700 font-black rounded-2xl hover:bg-slate-200 text-xs tracking-widest uppercase transition-all">Cancelar</button>
                        <button @click="confirmDelete" class="flex-1 py-4 bg-red-600 text-white font-black rounded-2xl hover:bg-red-700 text-xs tracking-widest uppercase shadow-xl shadow-red-600/30 transition-all active:scale-95">Eliminar</button>
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

.animate-in {
    animation-duration: 0.3s;
    animation-fill-mode: forwards;
}
</style>