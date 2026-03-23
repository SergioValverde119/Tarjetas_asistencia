<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import type { PropType } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Save, ArrowLeft, CheckCircle, X, Loader2, Calendar, 
    User, FileText, Clock, Layers, Trash2, Undo2, ChevronDown, AlertCircle
} from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

// Definición de props con PropType para evitar errores de inferencia profunda
const props = defineProps({
    incidencia: { type: Object as PropType<any>, required: true },
    employees: { type: Array as PropType<any[]>, default: () => [] },
    categories: { type: Array as PropType<any[]>, default: () => [] },
    filters: { type: Object as PropType<any>, default: () => ({}) },
    errors: { type: Object as PropType<any>, default: () => ({}) },
    flash: { type: Object as PropType<any>, default: () => ({ success: null, error: null }) }
});

// --- CONFIGURACIÓN DE LAYOUT Y BREADCRUMBS ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Editar Registro', href: '#' },
        ]
    }, () => page),
});

// Helper para formatear fechas de la BD (YYYY-MM-DD HH:mm:ss) al formato datetime-local (YYYY-MM-DDTHH:mm)
const formatForInput = (dateStr: string) => {
    if (!dateStr) return '';
    return dateStr.substring(0, 16).replace(' ', 'T');
};

const formatDateOnly = (dateStr: string) => {
    if (!dateStr) return '';
    return dateStr.split(' ')[0];
};

const formatTimeOnly = (dateStr: string) => {
    if (!dateStr) return '';
    return dateStr.substring(11, 16);
};

// --- GESTIÓN DE MODO (DÍA ÚNICO VS RANGO) ---
const isInitialSingle = formatDateOnly(props.incidencia.start_time) === formatDateOnly(props.incidencia.end_time);

const mode = ref(isInitialSingle ? 'single' : 'range');
const singleDate = ref(formatDateOnly(props.incidencia.start_time));
const singleStartTime = ref(formatTimeOnly(props.incidencia.start_time));
const singleEndTime = ref(formatTimeOnly(props.incidencia.end_time));

// Formulario de Inertia
const form = useForm({
    employee_id: props.incidencia.employee_id,
    category_id: props.incidencia.category_id,
    start_time: formatForInput(props.incidencia.start_time),
    end_time: formatForInput(props.incidencia.end_time),
    reason: props.incidencia.apply_reason || '',
    ...props.filters 
});

const syncTimes = () => {
    if (mode.value === 'single') {
        form.start_time = `${singleDate.value}T${singleStartTime.value}`;
        form.end_time = `${singleDate.value}T${singleEndTime.value}`;
    }
};

watch([mode, singleDate, singleStartTime, singleEndTime], syncTimes);

const submit = () => {
    syncTimes();
    form.put(`/incidencias/${props.incidencia.id}`, {
        preserveScroll: true,
    });
};

// --- ELIMINACIÓN ---
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(`/incidencias/${props.incidencia.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="Editar Incidencia" />

    <!-- CONTENEDOR DINÁMICO: Alto natural para evitar estrés de scrolls dobles -->
    <div class="flex flex-col bg-slate-50 p-6 w-full min-w-0 font-sans pb-12">
        <div class="flex flex-col w-full min-w-0 space-y-4">
            
            <!-- ALERTAS -->
            <div v-if="flash?.success" class="flex-none bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm w-full">
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-5 w-5" />
                    <span class="font-medium">{{ flash.success }}</span>
                </div>
            </div>

            <div v-if="flash?.error || Object.keys(errors).length > 0" class="flex-none bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm w-full">
                <div class="flex items-center gap-2">
                    <AlertCircle class="h-5 w-5" />
                    <span class="font-medium">{{ flash?.error || 'Existen errores de validación.' }}</span>
                </div>
            </div>

            <!-- TARJETA DEL FORMULARIO -->
            <div class="bg-white shadow-lg rounded-2xl border border-slate-200 flex flex-col w-full min-w-0 overflow-visible">
                <div class="p-8">
                    <div class="flex justify-between items-start mb-8">
                        <div>
                            <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight flex items-center gap-2">
                                <Layers class="w-6 h-6 text-blue-600" />
                                Editar Incidencia #{{ incidencia.id }}
                            </h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Sincronización directa con BioTime</p>
                        </div>
                        
                        <button 
                            @click="showDeleteModal = true"
                            class="flex items-center gap-2 px-4 py-2 text-[10px] font-black text-red-500 hover:bg-red-50 rounded-xl transition-all border border-transparent hover:border-red-100 uppercase tracking-widest"
                        >
                            <Trash2 class="h-4 w-4" />
                            Eliminar Registro
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-10 w-full">
                        
                        <!-- RENGLÓN 1: INFO PERSONAL (AZULADO) Y TIPO DE PERMISO -->
                        <div class="md:col-span-6 w-full">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Información del Personal</label>
                            <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100 flex items-center gap-4">
                                <div class="h-12 w-12 rounded-xl bg-blue-600 flex items-center justify-center text-white text-lg font-black shadow-lg shadow-blue-200">
                                    {{ incidencia.first_name?.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-slate-800 uppercase truncate">{{ incidencia.first_name }} {{ incidencia.last_name }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[10px] font-black text-blue-600 bg-blue-100 px-2 py-0.5 rounded uppercase">Nómina: {{ incidencia.emp_code }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-6 w-full">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tipo de Permiso (Regla)</label>
                            <div class="relative w-full">
                                <select v-model="form.category_id" class="pl-4 w-full rounded-xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm h-12 font-bold transition-all bg-blue-50/50 appearance-none">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }} ({{ cat.code }})
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                                    <ChevronDown class="h-5 w-5" />
                                </div>
                            </div>
                            <p v-if="form.errors.category_id" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.category_id }}</p>
                        </div>

                        <!-- RENGLÓN 2: MOTIVO Y FECHAS -->
                        <div class="md:col-span-6 w-full">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <FileText class="h-4 w-4" /> Motivo del Registro
                            </h3>
                            <textarea 
                                v-model="form.reason" 
                                rows="6" 
                                class="w-full rounded-2xl border-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-medium p-4 bg-blue-50/50" 
                                placeholder="Describa el motivo detallado de la modificación..."
                            ></textarea>
                            <p v-if="form.errors.reason" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.reason }}</p>
                        </div>

                        <div class="md:col-span-6 w-full space-y-4">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Periodo Vigente</label>
                            <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200">
                                <button 
                                    type="button" 
                                    @click="mode = 'single'"
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Día Único
                                </button>
                                <button 
                                    type="button" 
                                    @click="mode = 'range'"
                                    class="flex-1 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Rango de Fechas
                                </button>
                            </div>

                            <!-- INPUTS DINÁMICOS CON COLORES TEMÁTICOS -->
                            <div v-if="mode === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-2 duration-300 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Fecha</label>
                                    <input type="date" v-model="singleDate" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Clock class="h-3 w-3 shrink-0" /> Entrada</label>
                                    <input type="time" v-model="singleStartTime" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate"><Clock class="h-3 w-3 shrink-0" /> Salida</label>
                                    <input type="time" v-model="singleEndTime" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-2 duration-300 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Inicio</label>
                                    <input type="datetime-local" v-model="form.start_time" class="w-full rounded-xl border-orange-200 shadow-sm h-12 font-bold text-sm bg-orange-50/30 focus:border-orange-500 focus:ring-orange-500" />
                                    <p v-if="form.errors.start_time" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.start_time }}</p>
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate"><Calendar class="h-3 w-3 shrink-0" /> Fin</label>
                                    <input type="datetime-local" v-model="form.end_time" class="w-full rounded-xl border-orange-200 shadow-sm h-12 font-bold text-sm bg-orange-50/30 focus:border-orange-500 focus:ring-orange-500" />
                                    <p v-if="form.errors.end_time" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.end_time }}</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- FOOTER DE ACCIÓN CON COLORES CARAMELO VIBRANTES -->
                <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-4 w-full rounded-b-2xl">
                    <!-- BOTÓN CANCELAR (ROJO CARAMELO) -->
                    <Link 
                        href="/incidencias" 
                        :data="filters"
                        class="inline-flex items-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg shadow-red-200/50 transition-all active:scale-95 gap-2"
                    >
                        <Undo2 class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <!-- BOTÓN ACTUALIZAR (VERDE CARAMELO) -->
                    <button 
                        @click="submit" 
                        :disabled="form.processing"
                        class="inline-flex items-center px-10 py-3 border border-transparent text-[11px] font-black uppercase tracking-[0.2em] rounded-xl shadow-lg shadow-emerald-200/50 text-white bg-emerald-500 hover:bg-emerald-600 focus:outline-none transition-all disabled:opacity-50 active:scale-95 cursor-pointer gap-2"
                    >
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4 mr-2" />
                        Actualizar Registro
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative border border-slate-100 animate-in zoom-in duration-200">
                <div class="flex items-center gap-4 mb-6 text-red-600">
                    <div class="p-3 bg-red-50 rounded-2xl">
                        <Trash2 class="h-8 w-8" />
                    </div>
                    <h3 class="text-xl font-black uppercase tracking-tight">Confirmar Borrado</h3>
                </div>
                
                <p class="text-slate-600 mb-8 leading-relaxed font-bold uppercase text-[11px] tracking-tight">
                    ¿Estás seguro de que deseas eliminar este permiso? Esta acción afectará directamente la asistencia en BioTime y no se puede deshacer.
                </p>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-50">
                    <button @click="showDeleteModal = false" :disabled="isDeleting" class="px-6 py-2.5 text-[10px] font-black uppercase text-slate-500 hover:bg-slate-50 rounded-xl transition-colors">
                        Mantener Registro
                    </button>
                    <button @click="confirmDelete" :disabled="isDeleting" class="inline-flex items-center px-8 py-2.5 bg-red-600 hover:bg-red-700 text-white text-[10px] font-black uppercase rounded-xl shadow-md shadow-red-200 transition-all active:scale-95">
                        <Loader2 v-if="isDeleting" class="h-4 w-4 mr-2 animate-spin" />
                        Sí, Eliminar de BioTime
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>