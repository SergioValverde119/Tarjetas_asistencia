<script setup lang="ts">
import { ref, watch, computed, h, onMounted } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    Save, Calendar, Clock, FileText, Users,
    Undo2, Loader2, CheckCircle, Search, Venus, Mars,
    X, AlertCircle, Eye, Trash2
} from 'lucide-vue-next';

// --- DEFINICIÓN DE PROPIEDADES ---
const props = defineProps<{
    categories: any[];
    flash: { success?: string; error?: string; };
}>();

const page = usePage();

// --- CONFIGURACIÓN DE DISEÑO ---
defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Inyección por Género', href: '#' },
        ]
    }, () => page),
});

// --- ESTADOS DE CONTROL ---
const mostrarExito = ref(false);
const mensajeExito = ref('');
const mostrarError = ref(false);
const mensajeError = ref('');

// Gestión de Tiempos y Modos
const mode = ref('single');
const fechaUnica = ref(new Date().toISOString().split('T')[0]);
const horaInicioUnica = ref('07:00');
const horaFinUnica = ref('22:00');

// Estados para Previsualización
const mostrarPreview = ref(false);
const cargandoPreview = ref(false);
const candidatos = ref<any[]>([]);

// --- LÓGICA DE CATEGORÍAS ---
const categorySearch = ref('');
const showCategoryDropdown = ref(false);

const filteredCategories = computed(() => {
    const cats = props.categories || [];
    if (!categorySearch.value) return cats;
    const term = categorySearch.value.toLowerCase();
    return cats.filter((cat: any) => 
        cat.name.toLowerCase().includes(term) || 
        (cat.code && cat.code.toLowerCase().includes(term))
    );
});

const selectCategory = (cat: any) => {
    form.category_id = String(cat.id);
    categorySearch.value = `${cat.name} (${cat.code || 'S/C'})`;
    showCategoryDropdown.value = false;
};

const closeCategoryDropdown = () => {
    setTimeout(() => { showCategoryDropdown.value = false; }, 200);
};

// --- FORMULARIO ---
const form = useForm({
    gender: '', // 'M' o 'F'
    category_id: '',
    start_time: '',
    end_time: '',
    reason: 'Incidencia masiva aplicada por género',
    selected_ids: [] as any[] // IDs finales a inyectar
});

const sincronizarTiempos = () => {
    if (mode.value === 'single') {
        form.start_time = `${fechaUnica.value}T${horaInicioUnica.value}`;
        form.end_time = `${fechaUnica.value}T${horaFinUnica.value}`;
    }
};

watch([mode, fechaUnica, horaInicioUnica, horaFinUnica], sincronizarTiempos);

/**
 * PASO 1: Consultar el personal según el género seleccionado.
 */
const handleConsultar = async () => {
    if (!form.gender) {
        mensajeError.value = "Por favor, seleccione un género.";
        mostrarError.value = true;
        return;
    }
    if (!form.category_id) {
        mensajeError.value = "Por favor, seleccione un tipo de incidencia.";
        mostrarError.value = true;
        return;
    }

    sincronizarTiempos();
    cargandoPreview.value = true;
    
    try {
        const res = await axios.post('/incidencias/por-genero/preview', {
            gender: form.gender
        });
        
        candidatos.value = res.data.candidatos || [];
        
        if (candidatos.value.length === 0) {
            mensajeError.value = "No se encontraron empleados activos para el género seleccionado.";
            mostrarError.value = true;
        } else {
            mostrarPreview.value = true;
        }
    } catch (e: any) {
        mensajeError.value = e.response?.data?.message || "Error al conectar con el servidor de BioTime.";
        mostrarError.value = true;
    } finally {
        cargandoPreview.value = false;
    }
};

/**
 * Elimina a un candidato de la lista temporal antes de inyectar.
 */
const quitarCandidato = (id: number) => {
    candidatos.value = candidatos.value.filter(c => c.id !== id);
    if (candidatos.value.length === 0) mostrarPreview.value = false;
};

/**
 * PASO 2: Ejecutar la inyección final solo con los seleccionados.
 */
const ejecutarInyeccion = () => {
    form.selected_ids = candidatos.value.map(c => c.id);
    
    form.post('/incidencias/por-genero', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            categorySearch.value = '';
            mostrarPreview.value = false;
        },
        onError: (err) => {
            mensajeError.value = Object.values(err)[0] as string || "Error al procesar la inyección.";
            mostrarError.value = true;
        }
    });
};

watch(() => (page.props as any).flash, (flash) => {
    if (flash?.success) {
        mensajeExito.value = flash.success;
        mostrarExito.value = true;
    }
}, { deep: true });

onMounted(() => sincronizarTiempos());
</script>

<template>
    <Head title="Inyectar por Género" />

    <div class="flex flex-col bg-[#fff1f2]/40 p-2 sm:p-4 w-full min-h-screen font-sans text-slate-900 overflow-hidden">
        <div class="max-w-5xl mx-auto w-full">
            
            <!-- TARJETA PRINCIPAL -->
            <div class="bg-white shadow-xl rounded-[2.5rem] border border-pink-100 overflow-hidden transition-all">
                <div class="p-6 lg:p-8">
                    
                    <form @submit.prevent="handleConsultar" class="grid grid-cols-1 md:grid-cols-12 gap-x-10 gap-y-8">
                        
                        <!-- SELECCIÓN DE GÉNERO (ROSA DULCE) -->
                        <div class="md:col-span-6 flex flex-col items-center justify-center">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-5">Grupo Objetivo</label>
                            <div class="flex gap-6">
                                <!-- Botón Mujer (Rosa Dulce) -->
                                <button type="button" @click="form.gender = 'F'"
                                    class="group flex flex-col items-center p-5 rounded-[2rem] border-2 transition-all w-36"
                                    :class="form.gender === 'F' ? 'border-pink-300 bg-pink-50 shadow-xl shadow-pink-100' : 'border-slate-50 bg-slate-50 hover:border-pink-200'">
                                    <div class="mb-2 transition-transform group-hover:scale-110"
                                        :class="form.gender === 'F' ? 'text-pink-400' : 'text-pink-100'">
                                        <Venus :size="64" stroke-width="2.5" />
                                    </div>
                                    <span class="font-black uppercase text-[11px] tracking-widest" :class="form.gender === 'F' ? 'text-pink-600' : 'text-slate-400'">Mujeres</span>
                                </button>

                                <!-- Botón Hombre (Azul) -->
                                <button type="button" @click="form.gender = 'M'"
                                    class="group flex flex-col items-center p-5 rounded-[2rem] border-2 transition-all w-36"
                                    :class="form.gender === 'M' ? 'border-blue-300 bg-blue-50 shadow-xl shadow-blue-100' : 'border-slate-50 bg-slate-50 hover:border-blue-200'">
                                    <div class="mb-2 transition-transform group-hover:scale-110"
                                        :class="form.gender === 'M' ? 'text-blue-400' : 'text-blue-100'">
                                        <Mars :size="64" stroke-width="2.5" />
                                    </div>
                                    <span class="font-black uppercase text-[11px] tracking-widest" :class="form.gender === 'M' ? 'text-blue-600' : 'text-slate-400'">Hombres</span>
                                </button>
                            </div>
                            <p v-if="form.errors.gender" class="text-red-600 text-[9px] font-black uppercase mt-3">{{ form.errors.gender }}</p>
                        </div>

                        <!-- BUSCADOR DE REGLA -->
                        <div class="md:col-span-6 flex flex-col justify-center">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Tipo de Incidencia</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Search :size="18" class="text-slate-400" />
                                </div>
                                <input 
                                    type="text" v-model="categorySearch"
                                    @focus="showCategoryDropdown = true" @blur="closeCategoryDropdown"
                                    class="pl-12 w-full h-14 rounded-2xl border-slate-200 bg-slate-50 text-sm font-bold focus:ring-pink-300 transition-all shadow-sm"
                                    placeholder="Buscar regla de BioTime..." autocomplete="off"
                                />
                                <div v-if="form.category_id" class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                                    <CheckCircle :size="20" class="text-emerald-500" />
                                </div>

                                <div v-if="showCategoryDropdown && filteredCategories.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-48 overflow-y-auto custom-scrollbar">
                                    <ul>
                                        <li v-for="cat in filteredCategories" :key="cat.id" @mousedown.prevent="selectCategory(cat)"
                                            class="px-5 py-3 hover:bg-pink-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors"
                                        >
                                            <div class="font-black text-slate-900 text-xs uppercase">{{ cat.name }}</div>
                                            <div class="text-[9px] text-pink-500 font-black uppercase tracking-widest">Código: {{ cat.code || 'N/A' }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-if="form.errors.category_id" class="text-red-600 text-[9px] font-black uppercase mt-1 ml-1">{{ form.errors.category_id }}</p>
                        </div>

                        <!-- MOTIVO -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 flex items-center gap-2">
                                <FileText :size="14" /> Motivo Justificante (Kárdex)
                            </label>
                            <textarea 
                                v-model="form.reason" rows="3" 
                                class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-medium p-5 focus:ring-pink-300 transition-all resize-none shadow-sm h-[120px]" 
                                placeholder="Escriba la razón de la incidencia..."
                            ></textarea>
                        </div>

                        <!-- VENTANA DE TIEMPO -->
                        <div class="md:col-span-6 flex flex-col gap-5">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1 text-center">Configuración de Tiempo</label>
                                <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200 shadow-inner">
                                    <button type="button" @click="mode = 'single'" 
                                        class="flex-1 py-2.5 text-[9px] font-black uppercase rounded-lg transition-all"
                                        :class="mode === 'single' ? 'bg-white text-blue-600 shadow-sm border border-blue-100' : 'text-slate-500'">
                                        Día Único
                                    </button>
                                    <button type="button" @click="mode = 'range'" 
                                        class="flex-1 py-2.5 text-[9px] font-black uppercase rounded-lg transition-all"
                                        :class="mode === 'range' ? 'bg-white text-orange-600 shadow-sm border border-orange-100' : 'text-slate-500'">
                                        Rango de Fechas
                                    </button>
                                </div>
                            </div>

                            <!-- INPUTS DINÁMICOS COMPACTOS -->
                            <div v-if="mode === 'single'" class="grid grid-cols-3 gap-3 animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="flex flex-col">
                                    <label class="text-[9px] font-black text-blue-600 uppercase mb-1.5 ml-1">Fecha</label>
                                    <input type="date" v-model="fechaUnica" class="w-full h-11 rounded-xl border-blue-100 font-black text-xs bg-blue-50/20 focus:border-blue-500 text-blue-900 px-3 transition-all" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[9px] font-black text-blue-600 uppercase mb-1.5 ml-1">Entrada</label>
                                    <input type="time" v-model="horaInicioUnica" class="w-full h-11 rounded-xl border-blue-100 font-black text-xs bg-blue-50/20 focus:border-blue-500 text-blue-900 px-3 transition-all" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[9px] font-black text-blue-600 uppercase mb-1.5 ml-1">Salida</label>
                                    <input type="time" v-model="horaFinUnica" class="w-full h-11 rounded-xl border-blue-100 font-black text-xs bg-blue-50/20 focus:border-blue-500 text-blue-900 px-3 transition-all" />
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-2 gap-3 animate-in fade-in slide-in-from-top-2 duration-300 w-full">
                                <div class="flex flex-col">
                                    <label class="text-[9px] font-black text-orange-600 uppercase mb-1.5 ml-1 text-center">Inicio Rango</label>
                                    <input type="datetime-local" v-model="form.start_time" class="w-full h-11 rounded-xl border-orange-100 bg-orange-50/20 text-xs font-black focus:ring-orange-500 px-3 shadow-sm transition-all" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[9px] font-black text-orange-600 uppercase mb-1.5 ml-1 text-center">Fin Rango</label>
                                    <input type="datetime-local" v-model="form.end_time" class="w-full h-11 rounded-xl border-orange-100 bg-orange-50/20 text-xs font-black focus:ring-orange-500 px-3 shadow-sm transition-all" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ACCIONES INFERIORES -->
                <div class="bg-slate-50 px-10 py-6 border-t border-slate-100 flex flex-col sm:flex-row justify-end items-center gap-5 rounded-b-[2rem]">
                    <!-- BOTÓN CANCELAR EN ROJO -->
                    <Link href="/incidencias" class="w-full sm:w-auto inline-flex items-center justify-center px-10 py-3.5 text-[11px] font-black uppercase tracking-widest text-white bg-red-600 rounded-2xl hover:bg-red-700 transition-all active:scale-95 gap-3 shadow-lg shadow-red-100">
                        <Undo2 :size="18" /> Cancelar
                    </Link>

                    <!-- BOTÓN CONSULTAR EN VERDE ESMERALDA -->
                    <button @click="handleConsultar" :disabled="cargandoPreview || !form.gender || !form.category_id"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-14 py-3.5 border border-transparent text-[11px] font-black uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-emerald-100 text-white bg-emerald-500 hover:bg-emerald-600 transition-all active:scale-95 gap-3 disabled:opacity-50"
                    >
                        <Loader2 v-if="cargandoPreview" :size="20" class="animate-spin" />
                        <Eye v-else :size="20" />
                        Consultar Personal
                    </button>
                </div>
            </div>

            <p class="text-center text-slate-300 text-[9px] font-black uppercase tracking-[0.4em] mt-5">PJDyJR</p>
        </div>

        <!-- MODAL DE PREVISUALIZACIÓN Y FILTRO -->
        <div v-if="mostrarPreview" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md animate-in fade-in duration-300">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden border border-slate-200">
                
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 px-8">
                    <div class="flex items-center gap-4">
                        <div class="bg-pink-400 p-2.5 rounded-xl text-white shadow-lg shadow-pink-100">
                            <Users :size="24" />
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-800 uppercase tracking-tighter leading-tight">Personal a Registrar</h3>
                            <p class="text-[10px] text-pink-500 font-black uppercase tracking-widest mt-1">Se detectaron {{ candidatos.length }} servidores públicos</p>
                        </div>
                    </div>
                    <button @click="mostrarPreview = false" class="p-2 hover:bg-red-50 text-slate-400 hover:text-red-500 rounded-full transition-all">
                        <X :size="28" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-white">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b-2 border-slate-50">
                                <th class="text-left px-4 py-4">Nómina</th>
                                <th class="text-left px-4 py-4">Nombre Completo</th>
                                <th class="text-center px-4 py-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="c in candidatos" :key="c.id" class="hover:bg-pink-50/30 transition-colors group">
                                <td class="px-4 py-4 font-mono font-black text-pink-400 text-xs tracking-tighter">{{ c.emp_code }}</td>
                                <td class="px-4 py-4 font-black text-slate-800 uppercase text-[11px] tracking-tight leading-tight">{{ c.first_name }} {{ c.last_name }}</td>
                                <td class="px-4 py-4 text-center">
                                    <!-- AJUSTE: Icono de basura en rojo (text-red-600) -->
                                    <button @click="quitarCandidato(c.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Eliminar de la lista">
                                        <Trash2 :size="18" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-8 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row justify-end items-center gap-6 px-10">
                    <div class="flex gap-4 w-full sm:w-auto">
                        <button @click="mostrarPreview = false" class="flex-1 sm:flex-none px-8 py-3.5 rounded-2xl bg-red-600 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-red-100 hover:bg-red-700 transition-all active:scale-95">
                            Cancelar
                        </button>
                        <!-- BOTÓN ACEPTAR EN VERDE -->
                        <button @click="ejecutarInyeccion" :disabled="form.processing" class="flex-1 sm:flex-none px-12 py-3.5 rounded-2xl bg-emerald-500 text-white text-[10px] font-black uppercase tracking-[0.2em] shadow-xl shadow-emerald-100 hover:bg-emerald-600 transition-all active:scale-95">
                            Confirmar e Inyectar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL ÉXITO -->
        <div v-if="mostrarExito" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-sm w-full p-10 text-center border border-gray-100 animate-in zoom-in-95 duration-200">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-100 mb-6 border-8 border-emerald-50 text-emerald-600">
                    <CheckCircle class="h-10 w-10" />
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-4 uppercase tracking-tighter leading-tight">Proceso Exitoso</h3>
                <p class="text-xs text-slate-500 mb-10 font-bold uppercase tracking-wide leading-relaxed">{{ mensajeExito }}</p>
                <button @click="mostrarExito = false" class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase text-xs tracking-widest">Entendido</button>
            </div>
        </div>

        <ErrorModal :show="mostrarError" :message="mensajeError" title="Atención en Proceso" @close="mostrarError = false" />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #f9a8d4; border-radius: 10px; }

@keyframes zoom-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-in {
    animation: fade-in-up 0.3s ease-out forwards;
}
</style>