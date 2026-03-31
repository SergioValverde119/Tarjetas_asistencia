<script setup lang="ts">
import { ref, watch, onMounted, h } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Save, Users, Calendar, Clock, FileText, 
    Undo2, Loader2, ChevronDown
} from 'lucide-vue-next';

interface Propiedades {
    areas: any[]; 
    categories: any[]; 
    flash: { success?: string | null; error?: string | null; };
}

const propiedades = defineProps<Propiedades>();

// --- CONFIGURACIÓN DE DISEÑO Y NAVEGACIÓN ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Registro por Sección', href: '#' },
        ]
    }, () => page),
});

// --- GESTIÓN DE MODO (DÍA ÚNICO VS RANGO) ---
const modo = ref('single');
const fechaUnica = ref(new Date().toISOString().split('T')[0]);
const horaInicioUnica = ref('07:00');
const horaFinUnica = ref('22:00');

// --- FORMULARIO DE REGISTRO MASIVO ---
const formulario = useForm({
    area_id: '', 
    category_id: '',
    start_time: '',
    end_time: '',
    reason: '81 Fracción XIII' // Motivo solicitado por defecto
});

const sincronizarHorarios = () => {
    if (modo.value === 'single') {
        formulario.start_time = `${fechaUnica.value}T${horaInicioUnica.value}`;
        formulario.end_time = `${fechaUnica.value}T${horaFinUnica.value}`;
    }
};

watch([modo, fechaUnica, horaInicioUnica, horaFinUnica], sincronizarHorarios);

const enviarFormulario = () => {
    sincronizarHorarios();
    formulario.post('/incidencias/por-seccion', {
        onSuccess: () => {
            formulario.reset('area_id', 'start_time', 'end_time');
        },
    });
};

onMounted(() => {
    sincronizarHorarios();
});
</script>

<template>
    <Head title="Registro por Sección Sindical" />

    <div class="flex flex-col bg-slate-50 p-6 w-full min-w-0 font-sans pb-12">
        <div class="flex flex-col w-full min-w-0 space-y-6">
            
            <!-- TARJETA DEL FORMULARIO -->
            <div class="bg-white shadow-xl rounded-2xl border border-slate-200 overflow-hidden">
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-10 border-b border-slate-50 pb-6">
                        <div class="h-14 w-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                            <Users class="h-8 w-8" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Registro por sección sindical</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Inyección masiva para personal sindicalizado u honorarios</p>
                        </div>
                    </div>

                    <form @submit.prevent="enviarFormulario" class="grid grid-cols-1 md:grid-cols-12 gap-8">
                        
                        <!-- SELECCIÓN DE ÁREA (FRACCIÓN SINDICAL) -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Seleccionar Fracción sindical (nomina)</label>
                            <div class="relative">
                                <select v-model="formulario.area_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 focus:border-indigo-500 appearance-none pl-4 transition-all">
                                    <option value="">Seleccione el área...</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">
                                        {{ area.area_name }}
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400">
                                    <ChevronDown class="h-4 w-4" />
                                </div>
                            </div>
                            <p v-if="formulario.errors.area_id" class="text-red-600 text-[10px] mt-1 font-black uppercase tracking-tighter">{{ formulario.errors.area_id }}</p>
                        </div>

                        <!-- TIPO DE INCIDENCIA -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tipo de Incidencia</label>
                            <div class="relative">
                                <select v-model="formulario.category_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 appearance-none pl-4 transition-all">
                                    <option value="">Seleccione tipo...</option>
                                    <option v-for="categoria in categories" :key="categoria.id" :value="categoria.id">
                                        {{ categoria.name }} ({{ categoria.code }})
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400">
                                    <ChevronDown class="h-4 w-4" />
                                </div>
                            </div>
                        </div>

                        <!-- MOTIVO -->
                        <div class="md:col-span-12">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Motivo</label>
                            <textarea v-model="formulario.reason" rows="2" class="w-full rounded-xl border-slate-200 bg-blue-50/30 text-sm font-medium p-4 focus:ring-indigo-500 transition-all" placeholder="Justificación..."></textarea>
                        </div>

                        <!-- SELECTOR DE PERIODO -->
                        <div class="md:col-span-12">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Periodo de la Incidencia</label>
                            <div class="flex p-1 bg-slate-100 rounded-xl w-full md:w-1/2 border border-slate-200 mb-6">
                                <button 
                                    type="button" 
                                    @click="modo = 'single'"
                                    class="flex-1 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="modo === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Día Único
                                </button>
                                <button 
                                    type="button" 
                                    @click="modo = 'range'"
                                    class="flex-1 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all"
                                    :class="modo === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500 hover:text-slate-700'"
                                >
                                    Rango de Fechas
                                </button>
                            </div>

                            <!-- INPUTS DINÁMICOS -->
                            <div v-if="modo === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1"><Calendar class="h-3 w-3" /> Fecha</label>
                                    <input type="date" v-model="fechaUnica" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1"><Clock class="h-3 w-3" /> Entrada</label>
                                    <input type="time" v-model="horaInicioUnica" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1"><Clock class="h-3 w-3" /> Salida</label>
                                    <input type="time" v-model="horaFinUnica" class="w-full rounded-xl border-blue-200 shadow-sm h-12 font-bold text-sm bg-blue-50/30 focus:border-blue-500" />
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1"><Calendar class="h-3 w-3" /> Fecha y Hora Inicio</label>
                                    <input type="datetime-local" v-model="formulario.start_time" class="w-full h-12 rounded-xl border-indigo-200 bg-indigo-50/20 text-sm font-bold focus:ring-indigo-500" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1"><Calendar class="h-3 w-3" /> Fecha y Hora Término</label>
                                    <input type="datetime-local" v-model="formulario.end_time" class="w-full h-12 rounded-xl border-indigo-200 bg-indigo-50/20 text-sm font-bold focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- PIE DE ACCIONES -->
                <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-4">
                    <Link 
                        href="/incidencias" 
                        class="inline-flex items-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg transition-all active:scale-95 gap-2"
                    >
                        <Undo2 class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <button 
                        @click="enviarFormulario" 
                        :disabled="formulario.processing"
                        class="inline-flex items-center px-10 py-3 text-[11px] font-black uppercase tracking-[0.15em] rounded-xl shadow-lg shadow-indigo-200 text-white bg-indigo-600 hover:bg-indigo-700 transition-all disabled:opacity-50 active:scale-95 gap-2"
                    >
                        <Loader2 v-if="formulario.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Ejecutar Registro Masivo
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>