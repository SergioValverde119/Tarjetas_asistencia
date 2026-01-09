<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, UserPlus, Edit, Trash2, Shield, User } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'; 
import debounce from 'lodash/debounce';

import { create, edit, destroy, index } from '@/routes/users';
import { home } from '@/routes'; 

const props = defineProps({
    users: Object,
    filters: Object
});

const search = ref(props.filters.search);

const breadcrumbs = [
    { title: 'Dashboard', href: home() },
    { title: 'Usuarios', href: index().url },
];

watch(search, debounce((value) => {
    router.get(index().url, { search: value }, { preserveState: true, replace: true });
}, 300));

const deleteUser = (user) => {
    if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${user.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(destroy(user.id).url);
    }
};
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="bg-gray-50 p-4 md:p-8 w-full">
            
            <div class="max-w-7xl mx-auto w-full">
                <!-- Encabezado -->
                <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Usuarios del Sistema</h1>
                        <p class="text-sm text-gray-500 mt-1">Administra el acceso y vinculación de empleados.</p>
                    </div>
                    
                    <Link 
                        :href="create().url" 
                        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-sm"
                    >
                        <UserPlus class="w-4 h-4 mr-2" />
                        Nuevo Usuario
                    </Link>
                </div>

                <!-- Buscador -->
                <div class="mb-6 relative max-w-md w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input 
                        v-model="search"
                        type="text" 
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out shadow-sm"
                        placeholder="Buscar por Nombre, Usuario o Código..."
                    >
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
                    <div class="overflow-x-auto w-full">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Nombre / Rol</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Vinculación BioTime</th>
                                    
                                    <!-- COLUMNA FIJA (Encabezado) -->
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider sticky right-0 bg-gray-50 z-10 shadow-[-4px_0_8px_-2px_rgba(0,0,0,0.05)]">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in users.data" :key="user.id" class="group hover:bg-gray-50 transition-colors">
                                    
                                    <!-- Nombre y Rol -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-sm"
                                                :class="user.role === 'admin' ? 'bg-purple-600' : 'bg-blue-500'">
                                                {{ user.name.charAt(0) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                                <div class="text-xs flex items-center gap-1 mt-0.5">
                                                    <Shield v-if="user.role === 'admin'" class="w-3 h-3 text-purple-600" />
                                                    <User v-else class="w-3 h-3 text-blue-500" />
                                                    <span :class="user.role === 'admin' ? 'text-purple-600 font-bold' : 'text-gray-500'">
                                                        {{ user.role === 'admin' ? 'Administrador' : 'Empleado' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Usuario -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded inline-block">{{ user.username }}</div>
                                    </td>

                                    <!-- Vinculación BioTime -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="user.biotime_id" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            <span class="w-2 h-2 mr-1 bg-green-500 rounded-full"></span>
                                            ID: {{ user.emp_code || user.biotime_id }}
                                        </span>
                                        <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                            <span class="w-2 h-2 mr-1 bg-gray-400 rounded-full"></span>
                                            Sin vincular {{ user.emp_code ? `(${user.emp_code})` : '' }}
                                        </span>
                                    </td>

                                    <!-- COLUMNA FIJA (Celdas) -->
                                    <!-- 'sticky right-0': La pega al borde derecho -->
                                    <!-- 'bg-white group-hover:bg-gray-50': Asegura que tenga fondo sólido para tapar lo de atrás, pero cambie al pasar el mouse -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium sticky right-0 z-0 bg-white group-hover:bg-gray-50 shadow-[-4px_0_8px_-2px_rgba(0,0,0,0.05)]">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="edit(user.id).url" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 p-2 rounded-lg transition-colors" title="Editar">
                                                <Edit class="w-4 h-4" />
                                            </Link>
                                            <button 
                                                @click="deleteUser(user)" 
                                                class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                                :disabled="user.id === $page.props.auth.user.id"
                                                title="Eliminar"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6" v-if="users.links.length > 3">
                         <div class="flex-1 flex justify-between sm:hidden">
                            <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Anterior</Link>
                            <Link v-if="users.next_page_url" :href="users.next_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Siguiente</Link>
                         </div>
                         <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <p class="text-sm text-gray-700">Mostrando {{ users.from }} a {{ users.to }} de {{ users.total }}</p>
                            <div class="flex gap-1">
                                <Link v-for="(link, k) in users.links" :key="k" :href="link.url || '#'" v-html="link.label" 
                                    class="px-3 py-1 border rounded text-sm transition-colors" 
                                    :class="link.active ? 'bg-blue-50 border-blue-500 text-blue-600 font-medium' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'" />
                            </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>