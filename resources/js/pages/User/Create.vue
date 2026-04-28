<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'; 
import { Save, Search, Lock, Mail, Loader2, CheckCircle, XCircle, Fingerprint, Eye, EyeOff } from 'lucide-vue-next';
import axios from 'axios';
import { store, check_biotime, index } from '@/routes/users';

/** --- DEFINICIÓN DE INTERFAZ --- */
interface UserForm {
    nombre: string;
    paterno: string;
    materno: string;
    rfc: string;
    curp: string;
    username: string; 
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    biotime_id: string | number; 
    emp_code: string | number;   
    search_input: string; 
}

/** * CORRECCIÓN CRÍTICA: 
 * En la creación, inicializamos todo vacío. 
 * No intentamos leer 'props.user'.
 */
const form = useForm<UserForm>({
    nombre: '', 
    paterno: '', 
    materno: '', 
    rfc: '', 
    curp: '',
    username: '', 
    email: '', 
    password: '', 
    password_confirmation: '',
    role: 'empleado', 
    biotime_id: '', 
    emp_code: '', 
    search_input: '', 
});

const checkingBioTime = ref(false);
const bioTimeStatus = ref<'success' | 'error' | null>(null); 
const bioTimeMessage = ref('');
const useEmployeeCodeAsPass = ref(false);
const useRfcAsUsername = ref(false);
const showResultModal = ref(false);
const resultType = ref<'success' | 'error'>('success');
const showPassword = ref(false);

/** * Breadcrumbs con href obligatorio para evitar errores de TS 
 */
const breadcrumbs = [
    { title: 'Usuarios', href: index().url },
    { title: 'Nuevo', href: '#' }, 
];

// Sincronización de RFC a username en minúsculas
watch(() => form.rfc, (val) => { 
    if (useRfcAsUsername.value && val) form.username = val.toLowerCase(); 
});
watch(useRfcAsUsername, (val) => { 
    if (val && form.rfc) form.username = form.rfc.toLowerCase(); 
});

const checkBioTime = async () => {
    if (!form.search_input) return;
    checkingBioTime.value = true;
    bioTimeStatus.value = null;
    try {
        const isNumeric = /^\d+$/.test(form.search_input);
        const res = await axios.post(check_biotime().url, {
            emp_code: isNumeric ? String(form.search_input) : '', 
            name: !isNumeric ? String(form.search_input) : ''
        });
        if (res.data.status === 'success') {
            form.biotime_id = res.data.biotime_id;
            form.emp_code = res.data.emp_code_confirmed;
            form.nombre = res.data.employee.first_name.toLowerCase();
            form.paterno = res.data.employee.last_name.toLowerCase();
            bioTimeStatus.value = 'success';
            bioTimeMessage.value = `Vinculado: ${res.data.employee.first_name}`;
        } else {
            bioTimeStatus.value = 'error';
            bioTimeMessage.value = 'No encontrado.';
        }
    } catch {
        bioTimeStatus.value = 'error';
        bioTimeMessage.value = 'Error de conexión.';
    } finally {
        checkingBioTime.value = false;
    }
};

const submit = () => {
    form.post(store().url, {
        onSuccess: () => {
            form.reset();
            resultType.value = 'success';
            showResultModal.value = true;
        },
        onError: () => {
            resultType.value = 'error';
            showResultModal.value = true;
        }
    });
};
</script>

<template>
    <Head title="Nuevo Acceso" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="bg-gray-100/50 p-2 sm:p-4 flex justify-center w-full min-h-screen text-slate-900 font-sans">
            <div class="w-full">
                
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        
                        <!-- SECCIÓN 1: BIOTIME -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm">
                            <div class="flex items-center gap-2 mb-3">
                                <Fingerprint class="w-4 h-4 text-blue-600" />
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Sincronización de BioTime</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                <div class="flex gap-2">
                                    <input v-model="form.search_input" type="text" 
                                        class="flex-grow h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500 bg-white" 
                                        placeholder="Número de Nómina" @keyup.enter="checkBioTime" />
                                    <button type="button" @click="checkBioTime" :disabled="checkingBioTime" 
                                        class="px-4 h-10 bg-slate-800 text-white rounded-lg transition-all flex items-center justify-center hover:bg-black shadow-md">
                                        <Loader2 v-if="checkingBioTime" class="w-4 h-4 animate-spin" />
                                        <Search v-else class="w-4 h-4" />
                                    </button>
                                </div>
                                <div v-if="bioTimeStatus" class="text-[10px] font-bold uppercase px-3 py-2 rounded-lg border" 
                                    :class="bioTimeStatus === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'">
                                    {{ bioTimeMessage }}
                                </div>
                            </div>
                            <p v-if="form.errors.biotime_id" class="text-[9px] font-bold text-red-500 uppercase mt-1 ml-1">{{ form.errors.biotime_id }}</p>
                        </div>

                        <!-- SECCIÓN 2: IDENTIDAD PERSONAL -->
                        <div class="space-y-4">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Datos Identitarios</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase ml-1">Nombre(s)</label>
                                    <input v-model="form.nombre" type="text" class="w-full h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500" />
                                    <p v-if="form.errors.nombre" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.nombre }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase ml-1">A. Paterno</label>
                                    <input v-model="form.paterno" type="text" class="w-full h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500" />
                                    <p v-if="form.errors.paterno" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.paterno }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase ml-1">A. Materno</label>
                                    <input v-model="form.materno" type="text" class="w-full h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase ml-1">RFC</label>
                                    <input v-model="form.rfc" type="text" class="w-full h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 uppercase focus:ring-2 focus:ring-blue-500" maxlength="13" />
                                    <p v-if="form.errors.rfc" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.rfc }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-500 uppercase ml-1">CURP</label>
                                    <input v-model="form.curp" type="text" class="w-full h-10 rounded-lg border border-slate-300 text-sm font-bold px-3 uppercase focus:ring-2 focus:ring-blue-500" maxlength="18" />
                                    <p v-if="form.errors.curp" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.curp }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: CUENTA Y SEGURIDAD -->
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-600 uppercase ml-1">Username de Acceso *</label>
                                    <input v-model="form.username" type="text" class="w-full h-10 rounded-lg border border-slate-300 bg-white text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500" :disabled="useRfcAsUsername" />
                                    <label class="flex items-center gap-2 mt-1.5 ml-1 cursor-pointer group">
                                        <input type="checkbox" v-model="useRfcAsUsername" class="w-3.5 h-3.5 rounded border-slate-400 text-blue-600 focus:ring-blue-500" />
                                        <span class="text-[9px] font-black text-slate-400 uppercase group-hover:text-blue-600 transition-colors">Usar RFC como username</span>
                                    </label>
                                    <p v-if="form.errors.username" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.username }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-black text-slate-600 uppercase ml-1">Rol / Perfil</label>
                                    <select v-model="form.role" class="w-full h-10 rounded-lg border border-slate-300 bg-white text-sm font-bold px-3 focus:ring-2 focus:ring-blue-500">
                                        <option value="empleado">Empleado</option>
                                        <option value="admin">Administrador</option>
                                        <option value="disponibilidad">Monitor de tarjetas</option>
                                        <option value="capturista">Capturista de Incidencias</option>
                                        <option value="asistencia">Checador de Asistencia</option>
                                    </select>
                                    <p v-if="form.errors.role" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.role }}</p>
                                </div>
                                <div class="md:col-span-2 space-y-1">
                                    <label class="text-[10px] font-black text-slate-600 uppercase ml-1">Correo (Opcional)</label>
                                    <div class="relative">
                                        <Mail class="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                        <input v-model="form.email" type="email" class="w-full h-10 pl-10 rounded-lg border border-slate-300 bg-white text-sm font-bold focus:ring-2 focus:ring-blue-500" placeholder="usuario@sistema.local" />
                                    </div>
                                    <p v-if="form.errors.email" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-200">
                                <label class="flex items-center gap-2 mb-3 cursor-pointer group">
                                    <input type="checkbox" v-model="useEmployeeCodeAsPass" class="w-4 h-4 rounded border-slate-400 text-blue-600 focus:ring-blue-500" />
                                    <span class="text-[10px] font-black text-slate-600 uppercase group-hover:text-blue-600 transition-colors">Usar nómina como contraseña temporal</span>
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 transition-all duration-300" :class="{'opacity-20 pointer-events-none grayscale': useEmployeeCodeAsPass}">
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-black text-slate-500 uppercase ml-1">Nueva Contraseña *</label>
                                        <div class="relative">
                                            <Lock class="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="w-full h-10 pl-10 pr-10 rounded-lg border border-slate-300 bg-white text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-3 text-slate-400 hover:text-blue-600 transition-colors">
                                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                                <EyeOff v-else class="w-4 h-4" />
                                            </button>
                                        </div>
                                        <p v-if="form.errors.password" class="text-[9px] font-bold text-red-500 uppercase ml-1">{{ form.errors.password }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-black text-slate-500 uppercase ml-1">Confirmar</label>
                                        <div class="relative">
                                            <Lock class="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                                            <input v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" class="w-full h-10 pl-10 pr-10 rounded-lg border border-slate-300 bg-white text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-3 text-slate-400 hover:text-blue-600 transition-colors">
                                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                                <EyeOff v-else class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN (ROJO Y VERDE) -->
                        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-slate-100">
                            <Link :href="index().url" class="px-8 h-12 flex items-center justify-center bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-red-100 transition-all active:scale-95">
                                Cancelar
                            </Link>
                            <button type="submit" :disabled="form.processing" class="px-14 h-12 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-lg shadow-emerald-100 transition-all active:scale-95 disabled:opacity-50">
                                <Save class="w-4 h-4 mr-2" /> {{ form.processing ? 'Guardando...' : 'Finalizar Registro' }}
                            </button>
                        </div>
                    </form>
                </div>
                
                <p class="text-center text-slate-300 text-[9px] font-black uppercase tracking-[0.5em] mt-8 mb-12">Primeramente Jehová Dios y Jesús Rey</p>
            </div>
        </div>

        <!-- MODAL DE RESULTADO -->
        <div v-if="showResultModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-sm w-full p-10 text-center animate-in zoom-in-95 duration-200 border border-slate-100">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full mb-6 border-8" :class="resultType === 'success' ? 'bg-emerald-100 border-emerald-50 text-emerald-600' : 'bg-red-100 border-red-50 text-red-700'">
                    <CheckCircle v-if="resultType === 'success'" class="h-10 w-10" />
                    <XCircle v-else class="h-10 w-10" />
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-2 uppercase tracking-tighter">{{ resultType === 'success' ? '¡Hecho!' : 'Atención' }}</h3>
                <p class="text-xs text-slate-500 mb-10 font-bold uppercase tracking-wide leading-relaxed px-4">
                    {{ resultType === 'success' ? 'El acceso administrativo ha sido registrado correctamente.' : 'Por favor verifique los datos marcados en rojo para continuar.' }}
                </p>
                <button @click="showResultModal = false" class="w-full py-5 rounded-2xl font-black text-[10px] uppercase tracking-widest text-white shadow-xl transition-all" :class="resultType === 'success' ? 'bg-emerald-500 shadow-emerald-100' : 'bg-red-600 shadow-red-100'">
                    {{ resultType === 'success' ? 'Continuar' : 'Entendido' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
input, select { border-width: 1.5px !important; }
input:focus, select:focus { border-color: #2563eb !important; background-color: white !important; }
</style>