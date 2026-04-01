<script setup lang="ts">
import { ref, watch, onMounted, computed, h } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    Save, Users, Calendar, Clock, FileText, 
    Undo2, Loader2, ChevronDown, CheckCircle, Search, Layers
} from 'lucide-vue-next';

// --- DEFINICIÓN DE PROPIEDADES (Sintaxis compatible) ---
const props = defineProps({
    areas: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    flash: { type: Object, default: () => ({}) }
});

const page = usePage();

// --- CONFIGURACIÓN DE DISEÑO ---
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
const mode = ref('single');
const fechaUnica = ref(new Date().toISOString().split('T')[0]);
const horaInicioUnica = ref('07:00');
const horaFinUnica = ref('22:00');

// --- BÚSQUEDA Y SELECCIÓN DE SECCIÓN (ÁREA) ---
const areaSearch = ref('');
const showAreaDropdown = ref(false);
const selectedAreaName = ref('');

const filteredAreas = computed(() => {
    if (!areaSearch.value) return props.areas;
    const term = areaSearch.value.toLowerCase();
    return (props.areas as any[]).filter(area => 
        area.area_name.toLowerCase().includes(term)
    );
});

const selectArea = (area: any) => {
    formulario.area_id = area.id;
    areaSearch.value = area.area_name;
    selectedAreaName.value = area.area_name;
    showAreaDropdown.value = false;
};

const closeAreaDropdown = () => {
    setTimeout(() => { showAreaDropdown.value = false; }, 200);
};

// --- BÚSQUEDA Y SELECCIÓN DE CATEGORÍAS (REGLAS) ---
const categorySearch = ref('');
const showCategoryDropdown = ref(false);
const selectedCategoryName = ref('');

const filteredCategories = computed(() => {
    if (!categorySearch.value) return props.categories;
    const term = categorySearch.value.toLowerCase();
    return (props.categories as any[]).filter(cat => 
        cat.name.toLowerCase().includes(term) || 
        (cat.code && cat.code.toLowerCase().includes(term))
    );
});

const selectCategory = (cat: any) => {
    formulario.category_id = cat.id;
    const catDisplayName = `${cat.name} (${cat.code || 'S/C'})`;
    categorySearch.value = catDisplayName;
    selectedCategoryName.value = catDisplayName;
    showCategoryDropdown.value = false;
};

const closeCategoryDropdown = () => {
    setTimeout(() => { showCategoryDropdown.value = false; }, 200);
};

// --- FORMULARIO DE REGISTRO MASIVO ---
const formulario = useForm({
    area_id: '', 
    category_id: '',
    start_time: '',
    end_time: '',
    reason: '81 Fracción XIII'
});

const sincronizarHorarios = () => {
    if (mode.value === 'single') {
        formulario.start_time = `${fechaUnica.value}T${horaInicioUnica.value}`;
        formulario.end_time = `${fechaUnica.value}T${horaFinUnica.value}`;
    }
};

watch([mode, fechaUnica, horaInicioUnica, horaFinUnica], sincronizarHorarios);

// --- OBSERVADOR DE RESPUESTAS DEL SERVIDOR ---
watch(() => (page.props as any).flash, (flash) => {
    if (flash?.success) {
        mensajeExito.value = flash.success;
        mostrarExito.value = true;
    }
    if (flash?.error) {
        mensajeError.value = flash.error;
        mostrarError.value = true;
    }
}, { deep: true });

const enviarFormulario = () => {
    sincronizarHorarios();
    formulario.post('/incidencias/por-seccion', {
        preserveScroll: true,
        onSuccess: () => {
            formulario.reset('area_id');
            areaSearch.value = '';
            selectedAreaName.value = '';
            categorySearch.value = '';
            selectedCategoryName.value = '';
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
        <div class="flex flex-col w-full min-w-0 space-y-4">
            
            <!-- TARJETA ÚNICA (DISEÑO COMPACTO Y HORIZONTAL) -->
            <div class="bg-white shadow-xl rounded-2xl border border-slate-200 overflow-visible transition-all">
                <div class="p-8">
                    
                    <form @submit.prevent="enviarFormulario" class="grid grid-cols-1 md:grid-cols-12 gap-x-8 gap-y-10 w-full">
                        
                        <!-- FILA 1: SECCIÓN Y CATEGORÍA -->
                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Seleccionar Sección (Nómina)</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Layers class="h-4 w-4 text-slate-400" />
                                </div>
                                <input 
                                    type="text"
                                    v-model="areaSearch"
                                    @focus="showAreaDropdown = true"
                                    @blur="closeAreaDropdown"
                                    class="pl-12 w-full h-12 rounded-xl border-slate-200 bg-blue-50/40 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-bold transition-all"
                                    placeholder="Buscar área o sección..."
                                    autocomplete="off"
                                />
                                <div v-if="formulario.area_id" class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                                    <CheckCircle class="h-5 w-5 text-emerald-500" />
                                </div>

                                <!-- DROPDOWN SECCIONES -->
                                <div v-if="showAreaDropdown && filteredAreas.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                                    <ul>
                                        <li 
                                            v-for="area in filteredAreas" 
                                            :key="area.id" 
                                            @mousedown.prevent="selectArea(area)"
                                            class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition-colors border-b border-slate-50 last:border-0"
                                        >
                                            <div class="font-bold text-slate-900 text-sm uppercase">{{ area.area_name }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tipo de Incidencia (Regla)</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Search class="h-4 w-4 text-slate-400" />
                                </div>
                                <input 
                                    type="text"
                                    v-model="categorySearch"
                                    @focus="showCategoryDropdown = true"
                                    @blur="closeCategoryDropdown"
                                    class="pl-12 w-full h-12 rounded-xl border-slate-200 bg-blue-50/40 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-bold transition-all"
                                    placeholder="Buscar regla..."
                                    autocomplete="off"
                                />
                                <div v-if="formulario.category_id" class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                                    <CheckCircle class="h-5 w-5 text-emerald-500" />
                                </div>

                                <!-- DROPDOWN CATEGORÍAS -->
                                <div v-if="showCategoryDropdown && filteredCategories.length > 0" class="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                                    <ul>
                                        <li 
                                            v-for="cat in filteredCategories" 
                                            :key="cat.id" 
                                            @mousedown.prevent="selectCategory(cat)"
                                            class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition-colors border-b border-slate-50 last:border-0"
                                        >
                                            <div class="font-bold text-slate-900 text-sm uppercase">{{ cat.name }}</div>
                                            <div class="text-[10px] text-blue-600 font-black uppercase tracking-widest">Símbolo: {{ cat.code || 'N/A' }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- FILA 2: MOTIVO (IZQUIERDA) Y PERIODO (DERECHA) -->
                        <div class="md:col-span-6 flex flex-col">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                <FileText class="h-4 w-4" /> Motivo del Registro Masivo
                            </label>
                            <textarea 
                                v-model="formulario.reason" 
                                rows="6" 
                                class="w-full rounded-2xl border-slate-200 bg-blue-50/40 text-sm font-medium p-4 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none shadow-inner h-full min-h-[160px]" 
                                placeholder="Justificación oficial..."
                            ></textarea>
                        </div>

                        <div class="md:col-span-6 flex flex-col space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Periodo de Aplicación</label>
                                <div class="flex p-1 bg-slate-100 rounded-xl w-full border border-slate-200 shadow-inner">
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
                                        Rango
                                    </button>
                                </div>
                            </div>

                            <!-- INPUTS DINÁMICOS DE TIEMPO (h-12) -->
                            <div v-if="mode === 'single'" class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar class="h-3 w-3 shrink-0" /> Fecha</label>
                                    <input type="date" v-model="fechaUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 transition-all text-blue-900" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Clock class="h-3 w-3 shrink-0" /> Entrada</label>
                                    <input type="time" v-model="horaInicioUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 transition-all text-blue-900" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Clock class="h-3 w-3 shrink-0" /> Salida</label>
                                    <input type="time" v-model="horaFinUnica" class="w-full rounded-xl border-blue-100 shadow-sm h-12 font-black text-sm bg-blue-50/50 focus:border-blue-500 transition-all text-blue-900" />
                                </div>
                            </div>

                            <!-- MODO RANGO: HORIZONTAL (Línea de dos columnas) -->
                            <div v-else class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-4 duration-500 w-full">
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar class="h-3 w-3 shrink-0" /> Inicio (Fecha/Hora)</label>
                                    <input type="datetime-local" v-model="formulario.start_time" class="w-full h-12 rounded-xl border-orange-200 bg-orange-50/30 text-sm font-black focus:ring-orange-500 transition-all" />
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-[10px] font-black text-orange-600 uppercase mb-1 flex items-center gap-1 truncate ml-1"><Calendar class="h-3 w-3 shrink-0" /> Término (Fecha/Hora)</label>
                                    <input type="datetime-local" v-model="formulario.end_time" class="w-full h-12 rounded-xl border-orange-200 bg-orange-50/30 text-sm font-black focus:ring-orange-500 transition-all" />
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- PIE DE ACCIONES -->
                <div class="bg-slate-50 px-8 py-8 border-t border-slate-100 flex flex-col md:flex-row justify-end items-center gap-6 rounded-b-2xl">
                    <Link 
                        href="/incidencias" 
                        class="w-full md:w-auto inline-flex items-center justify-center px-10 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg shadow-red-200/50 transition-all active:scale-95 gap-3"
                    >
                        <Undo2 class="h-5 w-5" />
                        Cancelar
                    </Link>

                    <button 
                        @click="enviarFormulario" 
                        :disabled="formulario.processing"
                        class="w-full md:w-auto inline-flex items-center justify-center px-14 py-3 border border-transparent text-[11px] font-black uppercase tracking-[0.25em] rounded-xl shadow-2xl shadow-emerald-200/60 text-white bg-emerald-500 hover:bg-emerald-600 focus:outline-none transition-all disabled:opacity-50 active:scale-95 gap-3 cursor-pointer"
                    >
                        <Loader2 v-if="formulario.processing" class="h-5 w-5 animate-spin" />
                        <Save v-else class="h-5 w-5" />
                        Ejecutar Registro Masivo
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL DE ÉXITO -->
        <div v-if="mostrarExito" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-10 text-center border border-gray-100 animate-in zoom-in-95 duration-200">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-100 mb-6 border-8 border-emerald-50">
                    <CheckCircle class="h-10 w-10 text-emerald-600" />
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-4 uppercase tracking-tighter leading-tight">¡Proceso Terminado!</h3>
                <p class="text-xs text-gray-500 mb-10 font-bold leading-relaxed uppercase tracking-wide px-2">{{ mensajeExito }}</p>
                <button @click="mostrarExito = false" class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-5 rounded-2xl transition-all shadow-lg shadow-emerald-200 uppercase text-xs tracking-widest">
                    Aceptar
                </button>
            </div>
        </div>

        <ErrorModal :show="mostrarError" :message="mensajeError" title="Atención en Sistema" @close="mostrarError = false" />
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