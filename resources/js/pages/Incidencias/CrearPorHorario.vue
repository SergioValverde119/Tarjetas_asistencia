<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import ErrorModal from '@/components/ErrorModal.vue'; 

/** * IMPORTACIÓN DE RUTAS WAYFINDER
 * Estas funciones resuelven automáticamente el método y la URL
 */
import { 
    index, 
    storeBySchedule, 
    previewBySchedule 
} from '@/routes/incidencias';

import { 
    Calendar, Clock, FileText,
    Undo2, Loader2, CheckCircle, Search, Eye, X, Users, AlertCircle
} from 'lucide-vue-next';

// --- DEFINICIÓN DE PROPIEDADES (Sintaxis plana para evitar errores de compilación) ---
const props = defineProps(['categories', 'flash']);

const page = usePage();

// --- CONFIGURACIÓN DE DISEÑO ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: index().url },
            { title: 'Inyección por Horario', href: '#' },
        ]
    }, () => page),
});

// --- ESTADOS DE CONTROL ---
const mostrarExito = ref(false);
const mensajeExito = ref('');
const mostrarError = ref(false);
const mensajeError = ref('');

// --- ESTADOS PARA PREVISUALIZACIÓN ---
const mostrarPreview = ref(false);
const cargandoPreview = ref(false);
const candidatos = ref<any[]>([]);

// --- GESTIÓN DE MODO ---
const mode = ref('single');
const fechaUnica = ref(new Date().toISOString().split('T')[0]);
const horaInicioUnica = ref('07:00');
const horaFinUnica = ref('22:00');

// --- BÚSQUEDA Y SELECCIÓN DE CATEGORÍAS ---
const categorySearch = ref('');
const showCategoryDropdown = ref(false);

const filteredCategories = computed(() => {
    const cats = (props.categories as any[]) || [];
    if (!categorySearch.value) return cats;
    const term = categorySearch.value.toLowerCase();
    return cats.filter((cat: any) => 
        cat.name.toLowerCase().includes(term) || 
        (cat.code && cat.code.toLowerCase().includes(term))
    );
});

const selectCategory = (cat: any) => {
    formulario.category_id = String(cat.id);
    const catDisplayName = `${cat.name} (${cat.code || 'S/C'})`;
    categorySearch.value = catDisplayName;
    showCategoryDropdown.value = false;
};

const closeCategoryDropdown = () => {
    setTimeout(() => { showCategoryDropdown.value = false; }, 200);
};

// --- FORMULARIO DE REGISTRO ---
const formulario = useForm({
    filter_type: 'entrada', 
    category_id: '',
    start_time: '',
    end_time: '',
    reason: 'Justificación por incidencia masiva en horario'
});

const sincronizarHorarios = () => {
    if (mode.value === 'single') {
        formulario.start_time = `${fechaUnica.value}T${horaInicioUnica.value}`;
        formulario.end_time = `${fechaUnica.value}T${horaFinUnica.value}`;
    }
};

watch([mode, fechaUnica, horaInicioUnica, horaFinUnica], sincronizarHorarios);

/**
 * PASO 1: Consultar candidatos y abrir modal.
 * Se utiliza previewBySchedule.url() de Wayfinder para la petición Axios.
 */
const handleConsultar = async () => {
    sincronizarHorarios();
    
    if (!formulario.category_id) {
        mensajeError.value = "Por favor, seleccione el tipo de incidencia (Regla) antes de continuar.";
        mostrarError.value = true;
        return;
    }

    cargandoPreview.value = true;
    try {
        const res = await axios.post(previewBySchedule.url(), {
            filter_type: formulario.filter_type,
            start_time: formulario.start_time,
            end_time: formulario.end_time
        });
        
        candidatos.value = res.data.candidatos || [];
        
        if (candidatos.value.length === 0) {
            mensajeError.value = "No se localizaron empleados con ese horario en BioTime para la fecha seleccionada.";
            mostrarError.value = true;
        } else {
            mostrarPreview.value = true;
        }
    } catch (e: any) {
        const technicalMsg = e.response?.data?.mensaje_tecnico || "Error de comunicación con el servidor de BioTime.";
        mensajeError.value = technicalMsg;
        mostrarError.value = true;
    } finally {
        cargandoPreview.value = false;
    }
};

/**
 * PASO 2: Confirmación final e inyección física.
 * Utiliza formulario.submit() pasándole el objeto de acción de Wayfinder.
 */
const ejecutarInyeccion = () => {
    formulario.submit(storeBySchedule(), {
        preserveScroll: true,
        onSuccess: () => {
            mostrarPreview.value = false;
            formulario.reset('category_id', 'start_time', 'end_time');
            categorySearch.value = '';
        },
        onError: (err: any) => {
            mensajeError.value = Object.values(err)[0] as string || "Ocurrió un error al procesar la inyección.";
            mostrarError.value = true;
        }
    });
};

// --- OBSERVADOR FLASH ---
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) {
        mensajeExito.value = flash.success;
        mostrarExito.value = true;
    }
    if (flash?.error) {
        mensajeError.value = flash.error;
        mostrarError.value = true;
    }
}, { deep: true });

onMounted(() => sincronizarHorarios());
</script>

<template>
    <Head title="Inyectar por Horario" />

    <div class="flex flex-col bg-slate-50 p-6 w-full min-w-0 font-sans pb-12 text-slate-900">
        <div class="flex flex-col w-full min-w-0 space-y-4">
            
            <div class="bg-white shadow-xl rounded-2xl border border-slate-200 overflow-visible transition-all">
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-8 border-b border-slate-50 pb-6">
                        <div class="h-12 w-12 bg-emerald-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-emerald-100">
                            <Clock :size="24" />
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Acción Masiva por Criterio de Horario</h2>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Sincronización basada en jornada programada de BioTime</p>
                        </div>
                    </div>

                    <form @submit.prevent="handleConsultar" class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-10 w-full">
                        
                        <!-- FILTRO DE AFECTACIÓN -->
                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">1. ¿A quién desea afectar?</label>
                            <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200 shadow-inner">
                                <button type="button" @click="formulario.filter_type = 'entrada'" 
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="formulario.filter_type === 'entrada' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500 hover:text-slate-700'">
                                    Entrada
                                </button>
                                <button type="button" @click="formulario.filter_type = 'salida'" 
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="formulario.filter_type === 'salida' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500 hover:text-slate-700'">
                                    Salida
                                </button>
                                <button type="button" @click="formulario.filter_type = 'total'" 
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="formulario.filter_type === 'total' ? 'bg-white text-emerald-600 shadow-sm border border-emerald-100' : 'text-slate-500 hover:text-slate-700'">
                                    Jornada Total
                                </button>
                            </div>
                        </div>

                        <!-- CATEGORÍA / REGLA -->
                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">2. Tipo de Incidencia (Regla)</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Search :size="16" class="text-slate-400" />
                                </div>
                                <input 
                                    type="text" v-model="categorySearch"
                                    @focus="showCategoryDropdown = true" @blur="closeCategoryDropdown"
                                    class="pl-12 w-full h-12 rounded-xl border-slate-200 bg-emerald-50/20 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm font-bold transition-all"
                                    placeholder="Buscar regla de BioTime..." autocomplete="off"
                                />
                                <div v-if="formulario.category_id" class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                                    <CheckCircle :size="20" class="text-emerald-500" />
                                </div>

                                <div v-if="showCategoryDropdown && filteredCategories.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                                    <ul>
                                        <li v-for="cat in filteredCategories" :key="(cat as any).id" @mousedown.prevent="selectCategory(cat)"
                                            class="px-4 py-3 hover:bg-emerald-50 cursor-pointer border-b border-slate-50 last:border-0"
                                        >
                                            <div class="font-bold text-slate-900 text-sm uppercase">{{ (cat as any).name }}</div>
                                            <div class="text-[10px] text-emerald-600 font-black uppercase tracking-widest">Código: {{ (cat as any).code || 'N/A' }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- MOTIVO -->
                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                <FileText :size="16" /> 3. Motivo Justificante
                            </label>
                            <textarea 
                                v-model="formulario.reason" rows="6" 
                                class="w-full rounded-2xl border-slate-200 bg-emerald-50/20 text-sm font-medium p-4 focus:ring-emerald-500 focus:border-emerald-500 transition-all resize-none shadow-inner h-full min-h-[160px]" 
                                placeholder="Escriba la razón administrativa oficial..."
                            ></textarea>
                        </div>

                        <!-- TIEMPOS -->
                        <div class="md:col-span-6 flex flex-col space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">4. Ventana de Horario</label>
                                <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200 shadow-inner">
                                    <button type="button" @click="mode = 'single'" 
                                        class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                        :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500 hover:text-slate-700'">
                                        Día Único
                                    </button>
                                    <button type="button" @click="mode = 'range'" 
                                        class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                        :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500 hover:text-slate-700'">
                                        Rango
                                    </button>
                                </div>
                            </div>

                            <div v-if="mode === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar :size="12" /> Fecha</label>
                                    <input type="date" v-model="fechaUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 text-blue-900 px-3" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Clock :size="12" /> Entrada</label>
                                    <input type="time" v-model="horaInicioUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 text-blue-900 px-3" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Clock :size="12" /> Salida</label>
                                    <input type="time" v-model="horaFinUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 text-blue-900 px-3" />
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-4 duration-500 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar :size="12" /> Inicio</label>
                                    <input type="datetime-local" v-model="formulario.start_time" class="w-full h-12 rounded-xl border-orange-200 bg-orange-50/30 text-sm font-black focus:ring-orange-500 px-3" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar :size="12" /> Fin</label>
                                    <input type="datetime-local" v-model="formulario.end_time" class="w-full h-12 rounded-xl border-orange-200 bg-orange-50/30 text-sm font-black focus:ring-orange-500 px-3" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ACCIONES INFERIORES: VENTANA ORIGINAL -->
                <div class="bg-slate-50 px-8 py-8 border-t border-slate-100 flex flex-col md:flex-row justify-end items-center gap-4 rounded-b-2xl">
                    <!-- BOTÓN CANCELAR: ROJO -->
                    <Link :href="index().url" class="w-full md:w-auto inline-flex items-center justify-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-600 rounded-xl hover:bg-red-700 transition-all active:scale-95 gap-3 shadow-lg shadow-red-100">
                        <Undo2 :size="20" /> Cancelar Operación
                    </Link>

                    <!-- BOTÓN ACCIÓN: VERDE -->
                    <button @click="handleConsultar" :disabled="cargandoPreview || !formulario.start_time"
                        class="w-full md:w-auto inline-flex items-center justify-center px-12 py-3 border border-transparent text-[11px] font-black uppercase tracking-[0.25em] rounded-xl shadow-2xl shadow-emerald-200 text-white bg-emerald-500 hover:bg-emerald-600 transition-all active:scale-95 gap-3 disabled:opacity-50"
                    >
                        <Loader2 v-if="cargandoPreview" :size="20" class="animate-spin" />
                        <Eye v-else :size="20" />
                        Consultar y Preparar Inyección
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL EMERGENTE DE PREVISUALIZACIÓN Y CONFIRMACIÓN -->
        <div v-if="mostrarPreview" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md animate-in fade-in duration-200">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden border border-slate-200">
                
                <!-- Header Modal -->
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="bg-emerald-600 p-2.5 rounded-xl text-white shadow-lg shadow-emerald-100">
                            <Users :size="22" />
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800 uppercase tracking-tighter">Personal Identificado</h3>
                            <p class="text-[10px] text-emerald-600 font-black uppercase tracking-widest">Total encontrado: {{ candidatos.length }} empleados</p>
                        </div>
                    </div>
                    <button @click="mostrarPreview = false" class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 rounded-full transition-all">
                        <X :size="24" />
                    </button>
                </div>

                <!-- Tabla de Candidatos -->
                <div class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-white">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b-2 border-slate-50">
                                <th class="text-left px-4 py-4">Nómina</th>
                                <th class="text-left px-4 py-4">Servidor Público</th>
                                <th class="text-center px-4 py-4">Turno</th>
                                <th class="text-center px-4 py-4">Entrada Prog.</th>
                                <th class="text-center px-4 py-4">Salida Prog.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="c in candidatos" :key="(c as any).id" class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-4 py-4 font-mono font-black text-emerald-600 text-xs tracking-tighter">{{ (c as any).emp_code }}</td>
                                <td class="px-4 py-4 font-black text-slate-800 uppercase text-[11px] leading-tight">{{ (c as any).first_name }} {{ (c as any).last_name }}</td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-slate-500 text-[10px] font-bold uppercase">{{ (c as any).nombre_turno || 'S/N' }}</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg font-mono text-[10px] font-black">{{ (c as any).entrada_programada }}</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-lg font-mono text-[10px] font-black">{{ (c as any).salida_programada }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Modal -->
                <div class="p-8 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-3">
                        <AlertCircle :size="18" class="text-orange-500" />
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest leading-none">Confirme la lista antes de inyectar a BioTime</p>
                    </div>
                    <div class="flex gap-4 w-full sm:w-auto">
                        <!-- CANCELAR MODAL: ROJO -->
                        <button @click="mostrarPreview = false" class="flex-1 sm:flex-none px-8 py-3 rounded-2xl bg-red-600 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-red-100 hover:bg-red-700 transition-all active:scale-95">
                            Cerrar y Cancelar
                        </button>
                        <!-- CONFIRMAR MODAL: VERDE -->
                        <button @click="ejecutarInyeccion" :disabled="formulario.processing" class="flex-1 sm:flex-none px-12 py-3 rounded-2xl bg-green-600 text-white text-[10px] font-black uppercase tracking-[0.2em] shadow-xl shadow-green-100 hover:bg-green-700 transition-all active:scale-95">
                            Confirmar e Inyectar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE ÉXITO -->
        <div v-if="mostrarExito" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-sm w-full p-10 text-center border border-gray-100 animate-in zoom-in-95 duration-200">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-100 mb-6 border-8 border-emerald-50">
                    <CheckCircle :size="40" class="text-emerald-600" />
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-4 uppercase tracking-tighter leading-tight">Proceso Finalizado</h3>
                <p class="text-[11px] text-gray-500 mb-10 font-bold leading-relaxed uppercase tracking-wide px-2">{{ mensajeExito }}</p>
                <button @click="mostrarExito = false" class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-5 rounded-2xl transition-all shadow-lg shadow-emerald-200 uppercase text-xs tracking-widest">
                    Entendido
                </button>
            </div>
        </div>

        <ErrorModal :show="mostrarError" :message="mensajeError" title="Atención en Proceso" @close="mostrarError = false" />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-in {
    animation: fade-in-up 0.4s ease-out forwards;
}
</style>