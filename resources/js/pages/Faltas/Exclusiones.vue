<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import ErrorModal from '@/components/ErrorModal.vue'; 
import { 
    UserMinus, UserPlus, Search, Trash2, ArrowLeft, 
    ShieldCheck, Loader2, CheckCircle2, XCircle, AlertTriangle, CheckCircle,
    AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    exclusiones: { type: Array, default: () => [] },
    cat_empleados: { type: Array, default: () => [] },
    flash: Object
});

const page = usePage();
const searchTerm = ref('');
const showForm = ref(false);

// --- ESTADOS DE ALERTA ---
const mostrarError = ref(false);
const mensajeError = ref('');
const mostrarExito = ref(false);
const mensajeExito = ref('');

// --- ESTADOS DE CONFIRMACIÓN DE BORRADO ---
const showDeleteModal = ref(false);
const itemToDelete = ref(null);

// --- LÓGICA DEL BUSCADOR DINÁMICO EN EL MODAL ---
const empSearchInput = ref('');
const showSuggestions = ref(false);
const selectedEmployee = ref(null);

const form = useForm({
    emp_code: '',
    motivo: ''
});

// Observador para detectar mensajes de éxito desde el controlador
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        mensajeExito.value = flash.success;
        mostrarExito.value = true;
    }
    if (flash?.error) {
        mensajeError.value = flash.error;
        mostrarError.value = true;
    }
}, { deep: true });

const suggestedEmployees = computed(() => {
    if (empSearchInput.value.length < 2) return [];
    const term = empSearchInput.value.toLowerCase();
    return props.cat_empleados.filter(emp => 
        String(emp.emp_code).includes(term) || 
        `${emp.first_name} ${emp.last_name}`.toLowerCase().includes(term)
    ).slice(0, 10);
});

const selectEmployee = (emp) => {
    selectedEmployee.value = emp;
    form.emp_code = String(emp.emp_code); 
    empSearchInput.value = `${emp.emp_code} - ${emp.first_name} ${emp.last_name}`;
    showSuggestions.value = false;
    form.clearErrors('emp_code');
};

const clearSelection = () => {
    selectedEmployee.value = null;
    form.emp_code = '';
    empSearchInput.value = '';
    form.clearErrors();
};

const filteredTable = computed(() => {
    if (!searchTerm.value) return props.exclusiones;
    const t = searchTerm.value.toLowerCase();
    return props.exclusiones.filter(e => 
        String(e.emp_code).includes(t) || 
        e.nombre.toLowerCase().includes(t) ||
        (e.motivo && e.motivo.toLowerCase().includes(t))
    );
});

const agregarExclusion = () => {
    form.post(storeExclusion.url(), { 
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            clearSelection();
        },
        onError: (errors) => {
            if (errors.emp_code) {
                mensajeError.value = errors.emp_code;
                mostrarError.value = true;
            } else {
                mensajeError.value = "No se pudo registrar la exclusión.";
                mostrarError.value = true;
            }
        }
    });
};

// PREPARAR EL BORRADO (Abrir Modal)
const prepareDelete = (item) => {
    itemToDelete.value = item;
    showDeleteModal.value = true;
};

// EJECUTAR EL BORRADO REAL
const executeDelete = () => {
    if (itemToDelete.value) {
        router.delete(destroyExclusion.url(itemToDelete.value.id), {
            onSuccess: () => {
                showDeleteModal.value = false;
                itemToDelete.value = null;
            },
            onError: () => {
                mensajeError.value = "Error al procesar la solicitud de eliminación.";
                mostrarError.value = true;
                showDeleteModal.value = false;
            }
        });
    }
};

// --- WAYFINDER ROUTES ---
import { index as listExclusions, store as storeExclusion, destroy as destroyExclusion } from "@/routes/exclusion";
import { index as backToFaltas } from "@/routes/faltas"; 

</script>

<template>
    <Head title="Nóminas Excluidas" />

    <SidebarProvider>
        <AppSidebar>
            <div class="p-6 bg-gray-50 min-h-screen w-full">
                <div class="max-w-6xl mx-auto space-y-6">
                    
                    <!-- CABECERA -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-black text-gray-900 flex items-center gap-3">
                                <ShieldCheck class="h-8 w-8 text-red-600" />
                                Lista Negra de Asistencia
                            </h1>
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mt-1">
                                Gestión de personal excluido del reporte de faltas
                            </p>
                        </div>

                        <Link 
                            :href="backToFaltas.url()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-50 transition-all shadow-sm"
                        >
                            <ArrowLeft class="h-4 w-4" /> Volver al Monitor
                        </Link>
                    </div>

                    <!-- BUSCADOR -->
                    <div class="flex flex-col sm:flex-row gap-4 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" :size="18" />
                            <input 
                                v-model="searchTerm"
                                type="text"
                                placeholder="Filtrar lista negra..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-200 focus:ring-emerald-500 text-sm font-bold bg-gray-50/50"
                            />
                        </div>
                        <!-- BOTÓN AGREGAR: VERDE BRILLANTE -->
                        <button 
                            @click="showForm = true"
                            class="bg-emerald-500 text-white px-6 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-100"
                        >
                            <UserPlus class="h-4 w-4" /> Agregar Exclusión
                        </button>
                    </div>

                    <!-- TABLA PRINCIPAL CON SCROLL -->
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                        <div class="overflow-x-auto overflow-y-auto max-h-[600px] custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[800px]">
                                <thead class="sticky top-0 z-10 bg-gray-100 shadow-sm">
                                    <tr class="text-gray-600 text-[10px] font-black uppercase tracking-widest border-b border-gray-200">
                                        <th class="px-6 py-4 w-32 bg-gray-100">Nómina</th>
                                        <th class="px-6 py-4 bg-gray-100">Servidor Público</th>
                                        <th class="px-6 py-4 bg-gray-100">Área / Unidad</th>
                                        <th class="px-6 py-4 bg-gray-100">Motivo de Exclusión</th>
                                        <th class="px-6 py-4 text-center w-24 bg-gray-100">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs">
                                    <tr v-for="item in filteredTable" :key="item.id" class="hover:bg-red-50/30 transition-colors">
                                        <td class="px-6 py-4 font-mono font-black text-red-600 text-sm">{{ item.emp_code }}</td>
                                        <td class="px-6 py-4 font-black text-gray-900 uppercase leading-tight">{{ item.nombre }}</td>
                                        <td class="px-6 py-4 font-bold text-gray-500 uppercase">{{ item.area }}</td>
                                        <td class="px-6 py-4 text-gray-700 font-bold italic uppercase">{{ item.motivo || 'SIN MOTIVO' }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <button @click="prepareDelete(item)" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all">
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredTable.length === 0">
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 italic text-sm bg-white">
                                            No se encontraron registros.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="text-center text-gray-300 text-[9px] font-black uppercase tracking-[0.3em] pt-4">
                        Primeramente Jehová Dios y Jesús Rey
                    </div>
                </div>
            </div>

            <!-- MODAL DE REGISTRO -->
            <div v-if="showForm" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full overflow-hidden border border-gray-100 animate-in zoom-in-95 duration-200">
                    <div class="p-8 border-b border-gray-50 bg-emerald-50/30 text-center">
                        <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <ShieldCheck :size="24" />
                        </div>
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Nueva Exclusión</h3>
                        <p class="text-[10px] text-emerald-600 font-black uppercase tracking-widest mt-1">El servidor público no se verá afectado por faltas</p>
                    </div>
                    
                    <form @submit.prevent="agregarExclusion" class="p-8 space-y-6">
                        <div class="relative">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Buscar Servidor Público</label>
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" :size="16" />
                                <input v-model="empSearchInput" type="text" @focus="showSuggestions = true" class="w-full pl-10 pr-10 py-3.5 rounded-2xl border-gray-200 bg-gray-50 font-bold focus:ring-emerald-500 text-sm" placeholder="Escriba nómina o nombre..." />
                                <button v-if="empSearchInput" type="button" @click="clearSelection" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><XCircle :size="16" /></button>
                            </div>
                            <div v-if="showSuggestions && suggestedEmployees.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-2xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                                <div v-for="emp in suggestedEmployees" :key="emp.id" @mousedown.prevent="selectEmployee(emp)" class="px-4 py-3 hover:bg-red-50 cursor-pointer border-b border-gray-50 last:border-0">
                                    <div class="text-sm font-black text-gray-900 uppercase">{{ emp.first_name }} {{ emp.last_name }}</div>
                                    <div class="text-[10px] font-mono text-red-600 font-bold">Nómina: {{ emp.emp_code }}</div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedEmployee" class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl flex items-center gap-3">
                            <CheckCircle2 class="text-emerald-500" :size="20" />
                            <div>
                                <p class="text-[10px] font-black text-emerald-800 uppercase">Servidor Identificado</p>
                                <p class="text-xs font-black text-gray-900 uppercase">{{ selectedEmployee.first_name }} {{ selectedEmployee.last_name }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Motivo (Opcional)</label>
                            <textarea v-model="form.motivo" rows="2" class="w-full px-4 py-3 rounded-2xl border-gray-200 bg-gray-50 font-bold focus:ring-emerald-500 text-sm resize-none" placeholder="Razón de la exclusión..."></textarea>
                        </div>

                        <div class="flex gap-4 pt-2">
                            <!-- BOTONES REGISTRO: ROJO CANCELAR, VERDE ACEPTAR -->
                            <button type="button" @click="showForm = false" class="flex-1 px-4 py-3.5 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black text-[10px] uppercase shadow-lg transition-all">Cancelar</button>
                            <button type="submit" :disabled="form.processing || !form.emp_code" class="flex-1 px-4 py-3.5 rounded-2xl bg-emerald-500 text-white font-black text-[10px] uppercase hover:bg-emerald-600 shadow-lg disabled:bg-gray-400">
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin mx-auto" />
                                <span v-else>Confirmar Registro</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL DE CONFIRMACIÓN DE BORRADO -->
            <div v-if="showDeleteModal" class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 animate-in zoom-in-95 duration-200">
                    <div class="p-8 text-center">
                        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6 border-8 border-red-50">
                            <AlertCircle class="h-10 w-10 text-red-600" />
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 mb-2 uppercase tracking-tighter">¿Reintegrar Empleado?</h3>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wide px-4 leading-relaxed mb-8">
                            La nómina <span class="text-red-600 font-black">{{ itemToDelete?.emp_code }}</span> volverá a ser auditada por el monitor de faltas quincenales.
                        </p>
                        
                        <div class="flex gap-4">
                            <!-- BOTÓN CANCELAR: ROJO BRILLANTE -->
                            <button 
                                @click="showDeleteModal = false" 
                                class="flex-1 py-4 bg-red-600 hover:bg-red-700 text-white font-black rounded-2xl transition-all shadow-lg shadow-red-200 uppercase text-[10px] tracking-widest"
                            >
                                Cancelar
                            </button>
                            
                            <!-- BOTÓN ACEPTAR: VERDE BRILLANTE -->
                            <button 
                                @click="executeDelete" 
                                class="flex-1 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl transition-all shadow-lg shadow-emerald-200 uppercase text-[10px] tracking-widest"
                            >
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VENTANA DE ÉXITO -->
            <div v-if="mostrarExito" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-10 text-center border border-gray-100 animate-in zoom-in-95 duration-200">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-emerald-100 mb-6 border-8 border-emerald-50">
                        <CheckCircle class="h-10 w-10 text-emerald-600" />
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-4 uppercase tracking-tighter">¡Hecho!</h3>
                    <p class="text-xs text-gray-500 mb-10 font-bold uppercase tracking-wide leading-relaxed">{{ mensajeExito }}</p>
                    <button @click="mostrarExito = false" class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-5 rounded-2xl transition-all shadow-lg shadow-emerald-200 uppercase text-xs tracking-widest">
                        Entendido
                    </button>
                </div>
            </div>

            <ErrorModal :show="mostrarError" :message="mensajeError" title="Atención de Registro" @close="mostrarError = false" />
        </AppSidebar>
    </SidebarProvider>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.sticky {
    position: sticky;
}

@keyframes zoom-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-in {
    animation: zoom-in 0.2s ease-out forwards;
}
</style>