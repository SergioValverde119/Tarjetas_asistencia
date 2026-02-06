<script setup>
import { ref, watch } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'; 
import { UserPlus, Save, CheckCircle, Search, AlertTriangle, Lock, XCircle, Mail } from 'lucide-vue-next';
import axios from 'axios';
import { store, check_biotime, index } from '@/routes/users';

const form = useForm({
    // Formato separado de nombres
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

const breadcrumbs = [
    { title: 'Usuarios', href: index().url },
    { title: 'Nuevo', href: '#' },
];

const checkingBioTime = ref(false);
const bioTimeStatus = ref(null); 
const bioTimeMessage = ref('');
const useEmployeeCodeAsPass = ref(false);
const useRfcAsUsername = ref(false);
const showResultModal = ref(false);
const resultType = ref('success');
const resultTitle = ref('');
const resultMessage = ref('');

// Auto-llenado Username
watch(() => form.rfc, (val) => {
    if (useRfcAsUsername.value && val) form.username = val.toUpperCase();
});
watch(useRfcAsUsername, (val) => {
    if (val && form.rfc) form.username = form.rfc.toUpperCase();
});

const checkBioTime = async () => {
    // Usamos los campos separados para armar el nombre de búsqueda
    let searchName = form.search_input;
    if (!searchName) {
        searchName = `${form.nombre || ''} ${form.paterno || ''} ${form.materno || ''}`.trim();
    }
    
    if (!searchName) {
        bioTimeStatus.value = 'error';
        bioTimeMessage.value = 'Ingresa datos para buscar.';
        return;
    }

    checkingBioTime.value = true;
    bioTimeStatus.value = null;
    bioTimeMessage.value = '';
    form.biotime_id = '';
    form.emp_code = '';

    try {
        const url = check_biotime().url;
        const isNumeric = /^\d+$/.test(form.search_input);
        
        const response = await axios.post(url, {
            emp_code: isNumeric ? String(form.search_input) : '', 
            name: !isNumeric ? (form.search_input || searchName) : ''
        });
        const data = response.data;

        if (data.status === 'success') {
            form.biotime_id = data.biotime_id;         
            if (data.match_type === 'code') {
                const codeString = String(data.emp_code_confirmed);
                form.emp_code = codeString;
                form.search_input = codeString;
                bioTimeStatus.value = 'success';
                bioTimeMessage.value = `Encontrado con código: ${data.employee.first_name} ${data.employee.last_name}`;
            } else if (data.match_type === 'name') {
                form.emp_code = form.search_input; 
                bioTimeStatus.value = 'warning';
                bioTimeMessage.value = `Encontrado por nombre: ${data.employee.first_name} ${data.employee.last_name}`;
            }
        } else {
            bioTimeStatus.value = 'error';
            bioTimeMessage.value = 'Sin coincidencias en BioTime.';
        }
    } catch (error) {
        bioTimeStatus.value = 'error';
        bioTimeMessage.value = 'Error al verificar.';
    } finally {
        checkingBioTime.value = false;
        updatePasswordLogic();
    }
};

const updatePasswordLogic = () => {
    const codeToUse = form.emp_code || form.search_input;
    if (useEmployeeCodeAsPass.value && codeToUse) {
        form.password = codeToUse;
        form.password_confirmation = codeToUse;
    }
};

watch(() => form.search_input, updatePasswordLogic);
watch(() => form.emp_code, updatePasswordLogic);
watch(useEmployeeCodeAsPass, (val) => {
    if (val) {
        updatePasswordLogic();
    } else {
        form.password = '';
        form.password_confirmation = '';
    }
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        biotime_id: data.biotime_id === '' ? null : data.biotime_id,
        emp_code: data.emp_code === '' ? null : data.emp_code,
        username: data.username.toUpperCase(),
        rfc: data.rfc ? data.rfc.toUpperCase() : null,
        curp: data.curp ? data.curp.toUpperCase() : null,
    })).post(store().url, { 
        onSuccess: () => {
            form.reset();
            bioTimeStatus.value = null;
            bioTimeMessage.value = '';
            useEmployeeCodeAsPass.value = false;
            useRfcAsUsername.value = false;
            resultType.value = 'success';
            resultTitle.value = '¡Usuario Creado!';
            resultMessage.value = 'El usuario ha sido registrado correctamente.';
            showResultModal.value = true;
        },
        onError: (errors) => {
            resultType.value = 'error';
            resultTitle.value = 'Error al Crear';
            const count = Object.keys(errors).length;
            resultMessage.value = `Por favor revisa el formulario, hay ${count} error(es).`;
            showResultModal.value = true;
        }
    });
};

const closeResultModal = () => { showResultModal.value = false; };
</script>

<template>
    <Head title="Crear Usuario" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- AJUSTE DE LAYOUT: Quitamos min-h-full -->
        <div class="bg-gray-50 p-4 md:p-8 flex justify-center w-full">
            <div class="max-w-5xl w-full">
                <!-- Título -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <div class="p-2 bg-blue-100 rounded-lg text-blue-600"><UserPlus class="h-6 w-6" /></div>
                        Crear Nuevo Usuario
                    </h1>
                    <p class="text-gray-500 mt-1 ml-12">Registro de personal.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 md:p-8">
                        
                        <!-- SECCIÓN 1: DATOS PERSONALES -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-700 border-b border-gray-100 pb-2 mb-4">Datos Personales (Opcionales)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre(s)</label>
                                    <input v-model="form.nombre" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" maxlength="100">
                                    <p v-if="form.errors.nombre" class="mt-1 text-sm text-red-600">{{ form.errors.nombre }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Apellido Paterno</label>
                                    <input v-model="form.paterno" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" maxlength="100">
                                    <p v-if="form.errors.paterno" class="mt-1 text-sm text-red-600">{{ form.errors.paterno }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Apellido Materno</label>
                                    <input v-model="form.materno" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" maxlength="100">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                                    <input v-model="form.rfc" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm uppercase" maxlength="13">
                                    <p v-if="form.errors.rfc" class="mt-1 text-sm text-red-600">{{ form.errors.rfc }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">CURP</label>
                                    <input v-model="form.curp" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm uppercase" maxlength="18">
                                    <p v-if="form.errors.curp" class="mt-1 text-sm text-red-600">{{ form.errors.curp }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: CUENTA DE SISTEMA -->
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-700 border-b border-gray-100 pb-2 mb-4">Cuenta de Sistema (Obligatorio)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuario (Login) <span class="text-red-500">*</span></label>
                                    <input v-model="form.username" type="text" class="w-full rounded-lg border-gray-300 focus:border-blue-500 shadow-sm uppercase bg-gray-50" :disabled="useRfcAsUsername" maxlength="20">
                                    <div class="flex items-center gap-2 mt-2">
                                        <input type="checkbox" id="useRfc" v-model="useRfcAsUsername" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                        <label for="useRfc" class="text-xs text-gray-600 cursor-pointer">Usar RFC como usuario</label>
                                    </div>
                                    <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico (Opcional)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><Mail class="h-4 w-4 text-gray-400" /></div>
                                        <input v-model="form.email" type="email" class="block w-full pl-10 rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" placeholder="correo@ejemplo.com">
                                    </div>
                                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                                    <select v-model="form.role" class="w-full rounded-lg border-gray-300 shadow-sm bg-white">
                                        <option value="empleado">Empleado</option>
                                        <option value="admin">Administrador</option>
                                        <option value="disponibilidad">Monitor de tarjetas</option>
                                        <option value="capturista">Capturista de Incidencias</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: VINCULACIÓN -->
                        <div class="mb-8 bg-blue-50 -mx-6 md:-mx-8 px-6 md:px-8 py-6 border-t border-b border-blue-100">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2"><Search class="w-5 h-5 text-gray-500" /> Vinculación BioTime</h3>
                            <div class="flex flex-col md:flex-row gap-4 items-end">
                                <div class="flex-grow w-full">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">No. Empleado (Reloj)</label>
                                    <div class="flex gap-2">
                                        <input v-model="form.search_input" type="text" class="flex-grow rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" @keyup.enter="checkBioTime" maxlength="20" placeholder="Ej. 1001">
                                        <button type="button" @click="checkBioTime" :disabled="checkingBioTime" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"><span v-if="!checkingBioTime">Comprobar</span><span v-else>...</span></button>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2 min-h-[42px] flex items-center">
                                    <div v-if="bioTimeStatus === 'success'" class="text-sm text-green-600 font-medium flex items-center gap-2 bg-green-50 px-3 py-2 rounded-lg border border-green-200 w-full"><CheckCircle class="w-4 h-4" /> {{ bioTimeMessage }}</div>
                                    <div v-else-if="bioTimeStatus === 'warning'" class="text-sm text-orange-600 font-medium flex items-center gap-2 bg-orange-50 px-3 py-2 rounded-lg border border-orange-200 w-full"><AlertTriangle class="w-4 h-4" /> {{ bioTimeMessage }}</div>
                                    <div v-else-if="bioTimeStatus === 'error'" class="text-sm text-red-600 font-medium flex items-center gap-2 bg-red-50 px-3 py-2 rounded-lg border border-red-200 w-full"><AlertTriangle class="w-4 h-4" /> {{ bioTimeMessage }}</div>
                                </div>
                            </div>
                            <p v-if="form.errors.biotime_id" class="mt-2 text-sm text-red-600">Error: {{ form.errors.biotime_id }}</p>
                        </div>

                        <!-- SECCIÓN 4: CONTRASEÑA -->
                        <div class="col-span-2">
                            <div class="flex items-center gap-2 mb-4">
                                <input type="checkbox" id="useCode" v-model="useEmployeeCodeAsPass" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <label for="useCode" class="text-sm text-gray-700 font-medium select-none cursor-pointer">Usar código como contraseña</label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" :class="{'opacity-50 pointer-events-none': useEmployeeCodeAsPass}">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña <span class="text-red-500">*</span></label>
                                    <div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><Lock class="h-4 w-4 text-gray-400" /></div><input v-model="form.password" type="password" class="block w-full pl-10 rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" placeholder="••••••••"></div>
                                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar</label>
                                    <div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><Lock class="h-4 w-4 text-gray-400" /></div><input v-model="form.password_confirmation" type="password" class="block w-full pl-10 rounded-lg border-gray-300 focus:border-blue-500 shadow-sm" placeholder="••••••••"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end border-t border-gray-100 pt-6 gap-3">
                            <Link :href="index().url" class="px-6 py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition-all shadow-sm">
                                Cancelar
                            </Link>
                            <button type="submit" :disabled="form.processing" class="flex items-center gap-2 px-8 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 disabled:opacity-50 shadow-md">
                                <Save class="w-5 h-5" /> {{ form.processing ? 'Guardando...' : 'Crear Usuario' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Modal (Mismo código que antes) -->
    <div v-if="showResultModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 relative animate-fade-in-up">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full mb-4" :class="resultType === 'success' ? 'bg-green-100' : 'bg-red-100'">
                <CheckCircle v-if="resultType === 'success'" class="h-8 w-8 text-green-600" /><XCircle v-else class="h-8 w-8 text-red-600" />
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-2">{{ resultTitle }}</h3>
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">{{ resultMessage }}</p>
            <div class="flex justify-center"><button @click="closeResultModal" class="font-semibold py-2 px-6 rounded-md transition-colors shadow-sm focus:outline-none text-white" :class="resultType === 'success' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">Aceptar</button></div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
</style>