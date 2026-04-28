<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { PlusCircle, FileUp, ChartSpline, BookOpen, Users, Clock } from 'lucide-vue-next';
import { debounce } from 'lodash';

// COMPONENTES MODULARES (Ubicados en la carpeta 'components' al lado de este archivo)
import BarraFiltros from './components/BarraFiltros.vue';
import TablaIncidencias from './components/TablaIncidencias.vue';
import TablaCategorias from './components/TablaCategorias.vue';
import ModalImportar from './components/ModalImportar.vue';
import ModalExito from './components/ModalExito.vue';

// Rutas de Wayfinder (Asegúrate de que estas rutas existan en tu archivo de rutas)
import { create as createIncidencia, statistics } from '@/routes/incidencias';

const props = defineProps({
    incidencias: Object, 
    categorias: Array,   
    areas: Array, // Corregido: Ahora recibimos las áreas sindicales
    flash: Object,
    filters: Object
});

// --- ESTADO LOCAL ---
const filtrosActuales = ref(props.filters);
const mostrarImportar = ref(false);
const mostrarExito = ref(false);
const mensajeExito = ref('');
const mostrarError = ref(false);
const mensajeError = ref('');

// --- GESTIÓN DE NOTIFICACIONES FLASH ---
const page = usePage();
watch(() => page.props.flash.success, (val) => {
    if (val) {
        mensajeExito.value = val;
        mostrarExito.value = true;
    }
}, { immediate: true });

// --- LÓGICA DE NAVEGACIÓN Y FILTROS ---
const actualizarFiltros = debounce((nuevosFiltros) => {
    filtrosActuales.value = nuevosFiltros;
    router.get('/incidencias', { ...nuevosFiltros, page: 1 }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 500);

const cambiarPagina = (url) => {
    if (url) {
        router.get(url, filtrosActuales.value, {
            preserveState: true,
            preserveScroll: true
        });
    }
};

const lanzarError = (msg) => {
    mensajeError.value = msg;
    mostrarError.value = true;
};

const lanzarExito = (msg) => {
    mensajeExito.value = msg;
    mostrarExito.value = true;
};
</script>

<template>
    <Head title="Bitácora de Incidencias" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 bg-gray-50 min-h-screen w-full flex flex-col gap-6">
                
                <!-- ENCABEZADO DE PÁGINA -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight leading-tight">Bitácora de Incidencias</h1>
                        <p class="text-sm text-gray-500">Gestión centralizada de permisos y asistencias por área sindical en BioTime.</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        

                        <!-- BOTÓN: NUEVA POR HORARIO (Acceso a CrearPorHorario.vue) -->
                        <Link 
                            href="/incidencias/por-horario" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-fuchsia-600 hover:bg-fuchsia-700 transition-all active:scale-95 shadow-fuchsia-100"
                        >
                            <Clock class="h-4 w-4 mr-2" /> Nueva por Horario
                        </Link>
                        <!-- BOTÓN: NUEVA POR SECCIÓN (Enlace a CrearPorSeccion.vue) -->
                        <Link 
                            href="/incidencias/por-seccion" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-all active:scale-95 shadow-indigo-100"
                        >
                            <Users class="h-4 w-4 mr-2" /> Nueva por Área
                        </Link>

                        <Link 
                            href="/incidencias/por-genero" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-rose-600 hover:bg-rose-700 transition-all active:scale-95 shadow-rose-100"
                        >
                            <UserRound class="h-4 w-4 mr-2" /> Nueva por Género
                        </Link>

                        <button 
                            @click="mostrarImportar = true" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 transition-all active:scale-95"
                        >
                            <FileUp class="h-4 w-4 mr-2" /> Importar Excel
                        </button>
                        
                        <Link 
                            :href="createIncidencia?.().url" 
                            :data="{ ...filtrosActuales, page: incidencias.current_page }" 
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                        >
                            <PlusCircle class="h-4 w-4 mr-2" /> Nueva Individual
                        </Link>

                        <Link 
                            v-if="statistics" 
                            :href="statistics().url" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-xs font-black uppercase tracking-widest rounded-lg text-white bg-orange-500 hover:bg-orange-600 transition-all active:scale-95"
                        >
                            <ChartSpline class="h-4 w-4 mr-2" /> <span>Estadísticas</span>
                        </Link>
                    </div>
                </div>

                <!-- COMPONENTES MODULARES ORQUESTADOS -->
                <BarraFiltros 
                    :filtrosOriginales="filters" 
                    @cambio="actualizarFiltros" 
                />
                
                <TablaIncidencias 
                    :datos="incidencias" 
                    :filtrosActuales="filtrosActuales" 
                    @cambiarPagina="cambiarPagina" 
                />

                <!-- CATÁLOGO DE PERMISOS -->
                <div class="space-y-4 pt-4">
                    <div class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5 text-gray-400" />
                        <h2 class="text-lg font-bold text-gray-800 uppercase tracking-tight">Catálogo de Tipos de Permiso</h2>
                    </div>
                    <TablaCategorias :categorias="categorias" />
                </div>
            </div>
        </AppSidebar>

        <!-- CAPA DE MODALES -->
        <ModalImportar 
            :mostrar="mostrarImportar" 
            @cerrar="mostrarImportar = false" 
            @exito="lanzarExito"
            @error="lanzarError"
        />

        <ModalExito 
            :mostrar="mostrarExito" 
            :mensaje="mensajeExito" 
            @cerrar="mostrarExito = false" 
        />

        <ErrorModal 
            :show="mostrarError" 
            :message="mensajeError" 
            title="Atención en Sistema" 
            @close="mostrarError = false" 
        />

    </SidebarProvider>
</template>

<style scoped>
/* Scrollbars personalizados */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-track { background: transparent; }
</style>