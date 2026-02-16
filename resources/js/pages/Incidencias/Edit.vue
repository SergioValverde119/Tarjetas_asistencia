<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { 
    Save, ArrowLeft, CheckCircle, 
    X, Loader2, Calendar, User, FileText, 
    ChevronDown, Trash2, AlertTriangle, AlertCircle
} from 'lucide-vue-next';

/**
 * WAYFINDER: Importación de las rutas. 
 * Usamos Wayfinder para mantener la integridad de las URLs y métodos HTTP (PUT/DELETE).
 */
import { 
    index as indexIncidencias, 
    update as updateIncidencia, 
    destroy as destroyIncidencia 
} from '@/routes/incidencias';

const props = defineProps({
    incidencia: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    flash: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) }
});

// Helper para formatear fechas de la BD al formato del input local
const formatForInput = (dateStr) => {
    if (!dateStr) return '';
    return dateStr.substring(0, 16).replace(' ', 'T');
};

// Formulario reactivo de Inertia
const form = useForm({
    employee_id: props.incidencia.employee_id,
    category_id: props.incidencia.category_id,
    start_time: formatForInput(props.incidencia.start_time),
    end_time: formatForInput(props.incidencia.end_time),
    reason: props.incidencia.apply_reason || '',
    search: props.filters?.search || '',
    date_apply: props.filters?.date_apply || '',
    date_incidence: props.filters?.date_incidence || '',
    page: props.filters?.page || 1,
});

/**
 * Envío del formulario.
 * Pasamos directamente el objeto devuelto por Wayfinder a form.submit().
 * Esto garantiza que Inertia use el método 'PUT' y la URL con el ID correcto.
 */
const submit = () => {
    form.submit(updateIncidencia(props.incidencia.id), {
        preserveScroll: true,
        onSuccess: () => {
            showFlash.value = true;
        },
        onError: () => {
            showFlash.value = true;
        }
    });
};

// Control del modal de eliminación
const showDeleteModal = ref(false);
const isDeleting = ref(false);

const confirmDelete = () => {
    isDeleting.value = true;
    // Extraemos la info de Wayfinder para el borrado
    form.delete(destroyIncidencia(props.incidencia.id).url, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        }
    });
};

const showFlash = ref(true);
</script>

<template>
    <Head title="Editar Incidencia" />

    <SidebarProvider>
        <AppSidebar>
            <div class="flex flex-col h-screen max-h-screen bg-gray-50 p-6 overflow-hidden">
                <div class="flex flex-col w-full h-full max-w-5xl mx-auto">
                    
                    <!-- ENCABEZADO -->
                    <div class="flex-none mb-6 flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                                <Link :href="indexIncidencias().url" :data="filters" class="p-1.5 hover:bg-gray-200 rounded-full transition-colors">
                                    <ArrowLeft class="h-5 w-5 text-gray-500" />
                                </Link>
                                Editar Incidencia #{{ incidencia.id }}
                            </h1>
                            <p class="text-sm text-gray-500 ml-11 font-medium italic">Sincronización directa con BioTime</p>
                        </div>
                        
                        <button 
                            @click="showDeleteModal = true"
                            class="flex items-center gap-2 px-4 py-2 text-xs font-black text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-200 uppercase tracking-widest"
                        >
                            <Trash2 class="h-4 w-4" />
                            Eliminar Registro
                        </button>
                    </div>

                    <!-- ALERTAS DE ÉXITO -->
                    <div v-if="$page.props.flash.success && showFlash" class="flex-none mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-sm animate-in fade-in slide-in-from-top-2">
                        <div class="flex items-center gap-2 font-bold text-sm">
                            <CheckCircle class="h-5 w-5" />
                            <span>{{ $page.props.flash.success }}</span>
                        </div>
                        <button @click="showFlash = false" class="text-green-500 hover:text-green-700"><X class="h-4 w-4" /></button>
                    </div>

                    <!-- ALERTAS DE ERROR (CRÍTICO PARA DEBUG) -->
                    <div v-if="($page.props.flash.error || Object.keys(errors).length > 0) && showFlash" class="flex-none mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-sm animate-in fade-in slide-in-from-top-2">
                        <div class="flex items-center gap-2 font-bold text-sm">
                            <AlertCircle class="h-5 w-5" />
                            <span>{{ $page.props.flash.error || 'Existen errores en el formulario.' }}</span>
                        </div>
                        <button @click="showFlash = false" class="text-red-500 hover:text-red-700"><X class="h-4 w-4" /></button>
                    </div>

                    <!-- FORMULARIO PRINCIPAL -->
                    <div class="flex-1 bg-white shadow-xl rounded-2xl border border-gray-200 overflow-hidden flex flex-col">
                        <div class="flex-1 overflow-y-auto p-8 space-y-8">
                            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-12 gap-8">
                                
                                <!-- SECCIÓN: EMPLEADO -->
                                <div class="md:col-span-12">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="h-8 w-1 bg-red-900 rounded-full"></div>
                                        <h3 class="text-sm font-black uppercase text-gray-400 tracking-widest">Información del Personal</h3>
                                    </div>
                                    <div class="p-5 bg-gray-50 rounded-2xl border border-gray-100 flex items-center gap-5">
                                        <div class="h-14 w-14 rounded-2xl bg-red-900 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-red-900/20">
                                            {{ incidencia.first_name?.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="text-lg font-bold text-gray-900 leading-tight">{{ incidencia.first_name }} {{ incidencia.last_name }}</p>
                                            <div class="flex items-center gap-3 mt-1">
                                                <span class="px-2 py-0.5 bg-gray-200 text-gray-600 rounded text-[10px] font-black uppercase">Nómina: {{ incidencia.emp_code }}</span>
                                                <span class="text-[10px] text-gray-400 font-medium">ID Interno BioTime: {{ incidencia.employee_id }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" v-model="form.employee_id">
                                </div>

                                <!-- SECCIÓN: DETALLES -->
                                <div class="md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-6">
                                    <div class="md:col-span-12 flex items-center gap-2 mt-4">
                                        <div class="h-8 w-1 bg-blue-900 rounded-full"></div>
                                        <h3 class="text-sm font-black uppercase text-gray-400 tracking-widest">Ajustes de Incidencia</h3>
                                    </div>

                                    <!-- Tipo de Permiso -->
                                    <div class="md:col-span-6">
                                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Tipo de Permiso</label>
                                        <div class="relative">
                                            <select v-model="form.category_id" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 h-12 appearance-none bg-white font-semibold text-gray-700">
                                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                                    {{ cat.name }} ({{ cat.code }})
                                                </option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-400">
                                                <ChevronDown class="h-5 w-5" />
                                            </div>
                                        </div>
                                        <p v-if="form.errors.category_id" class="text-red-600 text-[11px] mt-1 font-bold">{{ form.errors.category_id }}</p>
                                    </div>

                                    <!-- Fechas -->
                                    <div class="md:col-span-6 grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2 flex items-center gap-1">
                                                <Calendar class="h-3 w-3" /> Inicia
                                            </label>
                                            <input type="datetime-local" v-model="form.start_time" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 h-12 text-sm font-semibold" />
                                            <p v-if="form.errors.start_time" class="text-red-600 text-[11px] mt-1 font-bold">{{ form.errors.start_time }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2 flex items-center gap-1">
                                                <Calendar class="h-3 w-3" /> Termina
                                            </label>
                                            <input type="datetime-local" v-model="form.end_time" class="w-full rounded-xl border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 h-12 text-sm font-semibold" />
                                            <p v-if="form.errors.end_time" class="text-red-600 text-[11px] mt-1 font-bold">{{ form.errors.end_time }}</p>
                                        </div>
                                    </div>

                                    <!-- Motivo -->
                                    <div class="md:col-span-12">
                                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Motivo / Justificación</label>
                                        <textarea 
                                            v-model="form.reason" 
                                            rows="4" 
                                            class="w-full rounded-xl border-gray-200 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm font-medium" 
                                            placeholder="Describa la razón detallada de la modificación..."
                                        ></textarea>
                                        <p v-if="form.errors.reason" class="text-red-600 text-[11px] mt-1 font-bold">{{ form.errors.reason }}</p>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- FOOTER FIJO -->
                        <div class="flex-none bg-gray-50 px-8 py-5 border-t border-gray-200 flex justify-end gap-4">
                            <Link :href="indexIncidencias().url" :data="filters" class="px-6 py-2.5 text-sm font-bold text-gray-500 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 transition-all uppercase tracking-widest">
                                Cancelar
                            </Link>
                            <button 
                                @click="submit" 
                                :disabled="form.processing"
                                class="inline-flex items-center px-8 py-2.5 border border-transparent text-sm font-black rounded-xl shadow-lg shadow-blue-900/20 text-white bg-blue-900 hover:bg-blue-800 focus:outline-none transition-all disabled:opacity-50 uppercase tracking-widest"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 mr-2 animate-spin" />
                                <Save v-else class="h-4 w-4 mr-2" />
                                Actualizar Registro
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AppSidebar>

        <!-- MODAL DE CONFIRMACIÓN -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md transition-opacity">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative animate-in zoom-in duration-300">
                <div class="flex items-center gap-4 mb-6 text-red-600">
                    <div class="p-3 bg-red-100 rounded-2xl">
                        <AlertTriangle class="h-8 w-8" />
                    </div>
                    <h3 class="text-xl font-black uppercase tracking-tight">Confirmar Eliminación</h3>
                </div>
                
                <p class="text-gray-600 mb-8 leading-relaxed font-medium">
                    Esta acción es <span class="text-red-600 font-bold">irreversible</span>. El registro se eliminará de BioTime y la acción quedará grabada en la bitácora de auditoría con su IP y usuario responsable.
                </p>
                
                <div class="flex justify-end gap-3">
                    <button @click="showDeleteModal = false" :disabled="isDeleting" class="px-5 py-3 text-sm font-bold text-gray-500 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors uppercase tracking-widest">
                        Cerrar
                    </button>
                    <button @click="confirmDelete" :disabled="isDeleting" class="inline-flex items-center px-6 py-3 text-white bg-red-600 rounded-xl hover:bg-red-700 text-sm font-black shadow-lg shadow-red-600/30 transition-all uppercase tracking-widest">
                        <Loader2 v-if="isDeleting" class="h-4 w-4 mr-2 animate-spin" />
                        Sí, Eliminar de BioTime
                    </button>
                </div>
            </div>
        </div>
    </SidebarProvider>
</template>