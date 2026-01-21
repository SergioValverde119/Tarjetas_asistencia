<script setup>
import { ref, watch } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider, SidebarInset } from '@/components/ui/sidebar';
import { UserPlus, Save, CheckCircle, Search, AlertTriangle, Lock, XCircle } from 'lucide-vue-next';
import axios from 'axios';

// --- IMPORTS DE RUTAS (WAYFINDER) ---
// Importamos las rutas generadas para el grupo 'users'
import { store, check_biotime } from '@/routes/users';

const form = useForm({
    name: '',
    username: '', // RFC
    password: '',
    password_confirmation: '',
    role: 'empleado',
    
    // DATOS DE BIOTIME
    biotime_id: '', // ID Interno (FK)
    emp_code: '',   // Código Visual (ej. 1001)
    
    // Campo auxiliar solo para escribir la búsqueda
    search_input: '', 
});

// Estados de la interfaz
const checkingBioTime = ref(false);
const bioTimeStatus = ref(null); 
const bioTimeMessage = ref('');
const useEmployeeCodeAsPass = ref(false);

// Estados para el Modal de Resultado
const showResultModal = ref(false);
const resultType = ref('success');
const resultTitle = ref('');
const resultMessage = ref('');

// --- LÓGICA: COMPROBAR CONEXIÓN BIOTIME ---
const checkBioTime = async () => {
    if (!form.search_input && !form.name) {
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
        // CORRECCIÓN: Usamos Wayfinder para obtener la URL segura
        const url = check_biotime().url;

        const response = await axios.post(url, {
            emp_code: form.search_input ? String(form.search_input) : '', 
            name: form.name
        });

        const data = response.data;

        if (data.status === 'success') {
            form.biotime_id = data.biotime_id;         

            if (data.match_type === 'code') {
                const codeString = String(data.emp_code_confirmed);
                form.emp_code = codeString;
                form.search_input = codeString;

                bioTimeStatus.value = 'success';
                bioTimeMessage.value = `Encontrado con el código de empleado: ${data.employee.first_name} ${data.employee.last_name}`;
                
                if (!form.name) form.name = `${data.employee.first_name} ${data.employee.last_name}`;

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
        console.error(error);
        bioTimeStatus.value = 'error';
        
        if (error.response) {
            if (error.response.status === 404) bioTimeMessage.value = 'Error: Ruta no encontrada.';
            else if (error.response.status === 500) bioTimeMessage.value = 'Error de Servidor.';
            else if (error.response.status === 422) bioTimeMessage.value = 'Datos inválidos.';
            else bioTimeMessage.value = `Error ${error.response.status}`;
        } else {
            bioTimeMessage.value = 'Error de conexión.';
        }
    } finally {
        checkingBioTime.value = false;
        updatePasswordLogic();
    }
};

// --- LÓGICA: CONTRASEÑA AUTOMÁTICA ---
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
    // Transformamos los datos antes de enviar
    form.transform((data) => ({
        ...data,
        biotime_id: data.biotime_id === '' ? null : data.biotime_id,
        emp_code: data.emp_code === '' ? null : data.emp_code,
    })).post(store().url, { // CORRECCIÓN: Usamos store().url de Wayfinder
        onSuccess: () => {
            form.reset();
            bioTimeStatus.value = null;
            bioTimeMessage.value = '';
            useEmployeeCodeAsPass.value = false;
            
            resultType.value = 'success';
            resultTitle.value = '¡Registro Exitoso!';
            resultMessage.value = 'El usuario ha sido registrado correctamente en el sistema.';
            showResultModal.value = true;
        },
        onError: (errors) => {
            resultType.value = 'error';
            resultTitle.value = 'Error en el Registro';
            const count = Object.keys(errors).length;
            resultMessage.value = `Por favor corrige los ${count} error(es) en el formulario antes de continuar.`;
            showResultModal.value = true;
        }
    });
};

const closeResultModal = () => {
    showResultModal.value = false;
};
</script>

<template>
    <Head title="Alta de Usuarios" />

    <SidebarProvider>
        <AppSidebar />
        
        <SidebarInset>
            <div class="min-h-full bg-gray-50 p-8 flex justify-center">
                <div class="max-w-4xl w-full">
                    
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                                <UserPlus class="h-6 w-6" />
                            </div>
                            Alta de Usuarios
                        </h1>
                        <p class="text-gray-500 mt-1 ml-12">Registro de usuarios</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        
                        <form @submit.prevent="submit" class="p-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                
                                <!-- SECCIÓN 1: DATOS DE ACCESO -->
                                <div class="col-span-2 border-b border-gray-100 pb-4 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-700">Credenciales de Acceso</h3>
                                </div>

                                <!-- Nombre -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
                                    <input 
                                        v-model="form.name"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                        placeholder="Ej. Juan Pérez"
                                    >
                                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                                </div>

                                <!-- RFC (Username) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                                    <input 
                                        v-model="form.username"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm uppercase"
                                        placeholder="XAXX010101000"
                                    >
                                    <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                                </div>

                                <!-- Rol -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol de Sistema</label>
                                    <select 
                                        v-model="form.role"
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm bg-white"
                                    >
                                        <option value="empleado">Empleado</option>
                                        <option value="disponibilidad">Monitor de tarjetas</option>
                                        <option value="admin">Administradorr</option>
                                    </select>
                                </div>

                                <div class="hidden md:block"></div>

                                <!-- SECCIÓN 2: VINCULACIÓN BIOTIME -->
                                <div class="col-span-2 border-t border-b border-gray-100 py-4 my-2 bg-gray-50 -mx-8 px-8">
                                    <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <Search class="w-5 h-5 text-gray-500" />
                                        Vinculación con BioTime
                                    </h3>
                                    
                                    <div class="flex flex-col md:flex-row gap-4 items-end">
                                        <div class="flex-grow w-full">
                                            <label class="block text-sm font-bold text-gray-700 mb-1">Número de Empleado</label>
                                            <div class="flex gap-2">
                                                <input 
                                                    v-model="form.search_input"
                                                    type="text"
                                                    class="flex-grow rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    placeholder="Ej. 1001"
                                                    @keyup.enter="checkBioTime"
                                                >
                                                <button 
                                                    type="button"
                                                    @click="checkBioTime"
                                                    :disabled="checkingBioTime"
                                                    class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-200 transition-colors disabled:opacity-50 flex items-center gap-2"
                                                >
                                                    <svg v-if="checkingBioTime" class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    <span v-else>Comprobar</span>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Mensajes de Estado -->
                                        <div class="w-full md:w-1/2 min-h-[42px] flex items-center">
                                            <div v-if="bioTimeStatus === 'success'" class="text-sm text-green-600 font-medium flex items-center gap-2 bg-green-50 px-3 py-2 rounded-lg border border-green-200 w-full">
                                                <CheckCircle class="w-4 h-4" /> {{ bioTimeMessage }}
                                            </div>
                                            <div v-else-if="bioTimeStatus === 'warning'" class="text-sm text-orange-600 font-medium flex items-center gap-2 bg-orange-50 px-3 py-2 rounded-lg border border-orange-200 w-full">
                                                <AlertTriangle class="w-4 h-4" /> {{ bioTimeMessage }}
                                            </div>
                                            <div v-else-if="bioTimeStatus === 'error'" class="text-sm text-red-600 font-medium flex items-center gap-2 bg-red-50 px-3 py-2 rounded-lg border border-red-200 w-full">
                                                <AlertTriangle class="w-4 h-4" /> {{ bioTimeMessage }}
                                            </div>
                                            <div v-else class="text-xs text-gray-400 italic">
                                                Ingresa el código o nombre y presiona comprobar.
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.biotime_id" class="mt-2 text-sm text-red-600">
                                        Error: {{ form.errors.biotime_id }}
                                    </p>
                                </div>

                                <!-- SECCIÓN 3: CONTRASEÑA -->
                                <div class="col-span-2">
                                    <div class="flex items-center gap-2 mb-4">
                                        <input 
                                            type="checkbox" 
                                            id="useCode" 
                                            v-model="useEmployeeCodeAsPass"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        >
                                        <label for="useCode" class="text-sm text-gray-700 font-medium select-none cursor-pointer">
                                            Usar código de empleado como contraseña
                                        </label>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" :class="{'opacity-50 pointer-events-none': useEmployeeCodeAsPass}">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <Lock class="h-4 w-4 text-gray-400" />
                                                </div>
                                                <input 
                                                    v-model="form.password"
                                                    type="password"
                                                    class="block w-full pl-10 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    placeholder="••••••••"
                                                >
                                            </div>
                                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <Lock class="h-4 w-4 text-gray-400" />
                                                </div>
                                                <input 
                                                    v-model="form.password_confirmation"
                                                    type="password"
                                                    class="block w-full pl-10 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    placeholder="••••••••"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-8 flex justify-end border-t border-gray-100 pt-6">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="flex items-center gap-2 px-8 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-100 transition-all disabled:opacity-50 shadow-md"
                                >
                                    <Save class="w-5 h-5" />
                                    {{ form.processing ? 'Registrando...' : 'Registrar Usuario' }}
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>

    <!-- MODAL DE RESULTADO (ÉXITO / ERROR) -->
    <div v-if="showResultModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 transition-opacity">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 relative animate-fade-in-up">
            
            <!-- Icono -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full mb-4"
                :class="resultType === 'success' ? 'bg-green-100' : 'bg-red-100'">
                <CheckCircle v-if="resultType === 'success'" class="h-8 w-8 text-green-600" />
                <XCircle v-else class="h-8 w-8 text-red-600" />
            </div>
            
            <h3 class="text-xl font-bold text-center text-gray-900 mb-2">{{ resultTitle }}</h3>
            
            <p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
                {{ resultMessage }}
            </p>
            
            <div class="flex justify-center">
                <button 
                    @click="closeResultModal" 
                    class="font-semibold py-2 px-6 rounded-md transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 text-white"
                    :class="resultType === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'"
                >
                    Aceptar
                </button>
            </div>
        </div>
    </div>
</template>