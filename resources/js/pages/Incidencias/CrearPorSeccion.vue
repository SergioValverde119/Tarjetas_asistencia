<script setup lang="ts">
import { ref, watch, onMounted, h } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    Save, Users, Calendar, Clock, FileText, 
    Undo2, Loader2, ChevronDown, CheckCircle
} from 'lucide-vue-next';

interface Propiedades {
    areas: any[]; 
    categories: any[]; 
    // Los mensajes flash vienen del controlador
    flash: { success?: string | null; error?: string | null; };
}

const propiedades = defineProps<Propiedades>();
const page = usePage(); // Acceso a las propiedades globales de Inertia

// --- CONFIGURACIÓN DE DISEÑO Y NAVEGACIÓN ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Registro por Sección', href: '#' },
        ]
    }, () => page),
});

// --- ESTADO DE VENTANAS EMERGENTES (MODALES) ---
const mostrarExito = ref(false);
const mensajeExito = ref('');
const mostrarError = ref(false);
const mensajeError = ref('');

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
    reason: '81 Fracción XIII'
});

const sincronizarHorarios = () => {
    if (modo.value === 'single') {
        formulario.start_time = `${fechaUnica.value}T${horaInicioUnica.value}`;
        formulario.end_time = `${fechaUnica.value}T${horaFinUnica.value}`;
    }
};

watch([modo, fechaUnica, horaInicioUnica, horaFinUnica], sincronizarHorarios);

// --- OBSERVADOR DE RESPUESTAS DEL SERVIDOR ---
// Este "watch" es el que hace que aparezca la ventana cuando el controlador responde
watch(() => page.props.flash, (flash: any) => {
    if (flash.success) {
        mensajeExito.value = flash.success;
        mostrarExito.value = true;
    }
    if (flash.error) {
        mensajeError.value = flash.error;
        mostrarError.value = true;
    }
}, { deep: true });

const enviarFormulario = () => {
    sincronizarHorarios();
    formulario.post('/incidencias/por-seccion', {
        preserveScroll: true,
        onSuccess: () => {
            // No reseteamos todo para que el usuario vea qué envió, 
            // solo limpiamos el ID del área para la siguiente carga si desea continuar.
            formulario.reset('area_id');
        },
        onError: (err) => {
            mensajeError.value = Object.values(err)[0] as string || "Error en los datos del formulario.";
            mostrarError.value = true;
        }
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
                        
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Seleccionar Fracción sindical (nomina)</label>
                            <div class="relative">
                                <select v-model="formulario.area_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 appearance-none pl-4 transition-all">
                                    <option value="">Seleccione el área...</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.area_name }}</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-4 h-4 w-4 text-slate-400 pointer-events-none" />
                            </div>
                        </div>

                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tipo de Incidencia</label>
                            <div class="relative">
                                <select v-model="formulario.category_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 appearance-none pl-4 transition-all">
                                    <option value="">Seleccione tipo...</option>
                                    <option v-for="categoria in categories" :key="categoria.id" :value="categoria.id">{{ categoria.name }} ({{ categoria.code }})</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-4 h-4 w-4 text-slate-400 pointer-events-none" />
                            </div>
                        </div>

                        <div class="md:col-span-12">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Motivo</label>
                            <textarea v-model="formulario.reason" rows="2" class="w-full rounded-xl border-slate-200 bg-blue-50/30 text-sm font-medium p-4 focus:ring-indigo-500 transition-all"></textarea>
                        </div>

                        <div class="md:col-span-12">
                            <div class="flex p-1 bg-slate-100 rounded-xl w-full md:w-1/2 border border-slate-200 mb-6">
                                <button type="button" @click="modo = 'single'" class="flex-1 py-2 text-[10px] font-black uppercase rounded-lg transition-all" :class="modo === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500'">Día Único</button>
                                <button type="button" @click="modo = 'range'" class="flex-1 py-2 text-[10px] font-black uppercase rounded-lg transition-all" :class="modo === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500'">Rango de Fechas</button>
                            </div>

                            <div v-if="modo === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div><label class="block text-[10px] font-black text-blue-600 mb-1">Fecha</label><input type="date" v-model="fechaUnica" class="w-full rounded-xl border-blue-200 h-12 font-bold text-sm bg-blue-50/30" /></div>
                                <div><label class="block text-[10px] font-black text-blue-600 mb-1">Entrada</label><input type="time" v-model="horaInicioUnica" class="w-full rounded-xl border-blue-200 h-12 font-bold text-sm bg-blue-50/30" /></div>
                                <div><label class="block text-[10px] font-black text-blue-600 mb-1">Salida</label><input type="time" v-model="horaFinUnica" class="w-full rounded-xl border-blue-200 h-12 font-bold text-sm bg-blue-50/30" /></div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-4">
                    <Link href="/incidencias" class="inline-flex items-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg active:scale-95 gap-2">
                        <Undo2 class="h-4 w-4" /> Cancelar
                    </Link>

                    <button @click="enviarFormulario" :disabled="formulario.processing" class="inline-flex items-center px-10 py-3 text-[11px] font-black uppercase tracking-[0.15em] rounded-xl shadow-lg shadow-indigo-200 text-white bg-indigo-600 hover:bg-indigo-700 transition-all disabled:opacity-50 active:scale-95 gap-2">
                        <Loader2 v-if="formulario.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Ejecutar Registro Masivo
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL DE ÉXITO (LA PANTALLA QUE FALTABA) -->
        <div v-if="mostrarExito" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center border border-gray-100 animate-in zoom-in-95 duration-200">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6 border-8 border-green-50">
                    <CheckCircle class="h-10 w-10 text-green-600" />
                </div>
                <h3 class="text-xl font-black text-gray-900 mb-3 uppercase tracking-tight">¡Proceso Terminado!</h3>
                <p class="text-xs text-gray-500 mb-8 font-bold leading-relaxed uppercase">{{ mensajeExito }}</p>
                <button @click="mostrarExito = false" class="block w-full bg-green-600 hover:bg-green-700 text-white font-black py-4 rounded-2xl transition-all shadow-lg uppercase text-xs tracking-widest">
                    Aceptar
                </button>
            </div>
        </div>

        <!-- MODAL DE ERROR -->
        <ErrorModal :show="mostrarError" :message="mensajeError" title="Atención en Sistema" @close="mostrarError = false" />
    </div>
</template>