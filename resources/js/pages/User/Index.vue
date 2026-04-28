<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Search, UserPlus, Edit, Trash2, Shield, User, 
    Loader2, ShieldAlert, Fingerprint, Copy, Check
} from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'; 
import debounce from 'lodash/debounce';

// --- RUTAS WAYFINDER ---
import { create, edit, destroy, index } from '@/routes/users';

/** --- DEFINICIÓN DE TIPOS --- */
interface User {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: 'admin' | 'supervisor' | 'capturista' | 'asistencia' | 'empleado' | 'disponibilidad';
    biotime_id: number | null;
    emp_code: string | null;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        from: number;
        to: number;
        total: number;
        links: any[];
    };
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();
const search = ref(props.filters.search || '');

// --- ESTADOS DE CONTROL ---
const showDeleteModal = ref(false);
const userToDelete = ref<User | null>(null);
const processingDelete = ref(false);
const copiedId = ref<number | null>(null);

/**
 * Función para copiar código al portapapeles
 * Compatible con entornos restringidos
 */
const copyToClipboard = (text: string, id: number) => {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    
    copiedId.value = id;
    setTimeout(() => {
        copiedId.value = null;
    }, 2000);
};

/**
 * Búsqueda con Debounce para evitar peticiones excesivas
 */
watch(search, debounce((value: string) => {
    router.get(index().url, { search: value }, { 
        preserveState: true, 
        replace: true,
        preserveScroll: true 
    });
}, 400));

const confirmDelete = (user: User) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!userToDelete.value) return;
    processingDelete.value = true;
    
    router.delete(destroy(userToDelete.value.id).url, {
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
        },
        onFinish: () => {
            processingDelete.value = false;
        }
    });
};

/**
 * Utilidad visual para etiquetas de roles
 */
const getRoleStyles = (role: string) => {
    const roles = {
        admin: 'bg-purple-100 text-purple-700 border-purple-200',
        supervisor: 'bg-indigo-100 text-indigo-700 border-indigo-200',
        capturista: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        asistencia: 'bg-orange-100 text-orange-700 border-orange-200',
        disponibilidad: 'bg-blue-100 text-blue-700 border-blue-200',
        empleado: 'bg-slate-100 text-slate-600 border-slate-200'
    };
    return roles[role as keyof typeof roles] || roles.empleado;
};

const getRoleIcon = (role: string) => {
    return role === 'admin' ? Shield : User;
};
</script>

<template>
    <Head title="Control de Accesos" />
    <AppLayout :breadcrumbs="[{ title: 'Usuarios', href: index().url }]">
        
        <div class="bg-gray-100/50 p-2 sm:p-6 w-full min-h-screen text-slate-900 font-sans transition-all">
            <div class="w-full">
                
                <!-- CABECERA DE ACCIÓN -->
                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="relative flex-grow max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-slate-400" />
                        </div>
                        <input 
                            v-model="search" 
                            type="text" 
                            class="block w-full pl-11 pr-4 py-3 border border-slate-200 bg-white rounded-2xl focus:ring-2 focus:ring-blue-500 font-bold text-sm shadow-sm transition-all" 
                            placeholder="Buscar por nombre, username o nómina..."
                        />
                    </div>

                    <Link 
                        :href="create().url" 
                        class="inline-flex items-center justify-center px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-black text-[10px] uppercase tracking-[0.2em] rounded-2xl shadow-lg shadow-blue-100 transition-all active:scale-95"
                    >
                        <UserPlus class="w-4 h-4 mr-2" /> Nuevo Usuario
                    </Link>
                </div>

                <!-- TABLA MAESTRA -->
                <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden w-full">
                    <div class="overflow-x-auto w-full">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50/50">
                                <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">
                                    <th class="px-8 py-5 text-left">Identidad del Usuario</th>
                                    <th class="px-6 py-5">Nivel de Acceso</th>
                                    <th class="px-6 py-5">Código BioTime</th>
                                    <th class="px-8 py-5 text-right sticky right-0 bg-slate-50 z-10 shadow-[-10px_0_15px_-10px_rgba(0,0,0,0.1)]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-50">
                                <tr v-for="user in users.data" :key="user.id" class="group hover:bg-slate-50/50 transition-colors">
                                    
                                    <!-- Celda: Identidad -->
                                    <td class="px-8 py-4">
                                        <div class="flex items-center">
                                            <div class="h-11 w-11 rounded-2xl flex items-center justify-center text-white font-black text-base shadow-md transition-transform group-hover:scale-110" 
                                                :class="user.role === 'admin' ? 'bg-purple-600 shadow-purple-100' : 'bg-blue-600 shadow-blue-100'">
                                                {{ user.name?.charAt(0).toUpperCase() || 'U' }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-black uppercase tracking-tight text-slate-800 leading-none mb-1.5">{{ user.name || user.username }}</div>
                                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-bold font-mono">
                                                    <span class="text-blue-500">@</span>{{ user.username }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Celda: Rol -->
                                    <td class="px-6 py-4 text-center">
                                        <span :class="getRoleStyles(user.role)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest border shadow-sm">
                                            <component :is="getRoleIcon(user.role)" class="w-3 h-3" />
                                            {{ user.role }}
                                        </span>
                                    </td>

                                    <!-- Celda: BioTime (AJUSTADA: BADGE VERDE + SIN GATO + COPIAR) -->
                                    <td class="px-6 py-4">
                                        <div v-if="user.biotime_id" class="flex items-center justify-center gap-2">
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-sm transition-all hover:scale-105">
                                                {{ user.emp_code }}
                                            </span>
                                            
                                            <button 
                                                @click="copyToClipboard(String(user.emp_code), user.id)"
                                                class="p-2 rounded-xl transition-all active:scale-90"
                                                :class="copiedId === user.id ? 'bg-emerald-100 text-emerald-600 shadow-inner' : 'bg-slate-100 text-slate-400 hover:bg-blue-100 hover:text-blue-600'"
                                                title="Copiar código de nómina"
                                            >
                                                <Check v-if="copiedId === user.id" class="w-3.5 h-3.5" />
                                                <Copy v-else class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                        <div v-else class="flex items-center justify-center gap-2 text-slate-300">
                                            <Fingerprint class="w-4 h-4 opacity-30" />
                                            <span class="text-[9px] font-black uppercase italic tracking-widest opacity-50">Sin Vincular</span>
                                        </div>
                                    </td>

                                    <!-- Celda: Acciones -->
                                    <td class="px-8 py-4 text-right sticky right-0 bg-white group-hover:bg-slate-50 transition-colors z-10 shadow-[-10px_0_15px_-10px_rgba(0,0,0,0.1)]">
                                        <div class="flex justify-end gap-3">
                                            <Link 
                                                :href="edit(user.id).url" 
                                                class="p-2.5 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-90"
                                                title="Editar perfil"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </Link>
                                            <button 
                                                @click="confirmDelete(user)" 
                                                class="p-2.5 bg-red-50 text-red-500 rounded-xl hover:bg-red-600 hover:text-white transition-all shadow-sm active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed" 
                                                :disabled="user.id === $page.props.auth.user.id"
                                                title="Eliminar acceso"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PIE DE TABLA: PAGINACIÓN -->
                    <div class="bg-slate-50/50 px-8 py-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                            Mostrando registros {{ users.from }} al {{ users.to }} de un total de {{ users.total }}
                        </div>
                        
                        <div class="flex gap-2" v-if="users.last_page > 1">
                            <Link 
                                v-if="users.prev_page_url" 
                                :href="users.prev_page_url" 
                                class="px-5 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-white hover:text-blue-600 transition-all shadow-sm"
                            >
                                Anterior
                            </Link>
                            <Link 
                                v-if="users.next_page_url" 
                                :href="users.next_page_url" 
                                class="px-5 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-white hover:text-blue-600 transition-all shadow-sm"
                            >
                                Siguiente
                            </Link>
                        </div>
                    </div>
                </div>

                <p class="text-center text-slate-300 text-[9px] font-black uppercase tracking-[0.5em] mt-8 mb-6">Primeramente Jehová Dios y Jesús Rey</p>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN DE BORRADO -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full p-10 text-center animate-in zoom-in-95 duration-200 border border-slate-100">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-red-100 mb-6 border-8 border-red-50">
                    <ShieldAlert class="h-10 w-10 text-red-600" />
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-2 uppercase tracking-tighter">Revocar Acceso</h3>
                <p class="text-xs text-slate-500 mb-10 font-bold uppercase tracking-wide leading-relaxed px-4">
                    ¿Está seguro de eliminar permanentemente a <span class="text-red-600 font-black">{{ userToDelete?.name }}</span>? Esta acción no se puede revertir.
                </p>
                
                <div class="flex gap-4">
                    <button 
                        @click="showDeleteModal = false" 
                        class="flex-1 py-4 bg-slate-100 text-slate-500 font-black rounded-2xl uppercase text-[10px] tracking-widest hover:bg-slate-200 transition-all"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="executeDelete" 
                        :disabled="processingDelete" 
                        class="flex-1 py-4 bg-red-600 text-white font-black rounded-2xl shadow-xl shadow-red-100 uppercase text-[10px] tracking-widest flex items-center justify-center gap-2 hover:bg-red-700 transition-all active:scale-95"
                    >
                        <Loader2 v-if="processingDelete" class="h-4 w-4 animate-spin" />
                        <span v-else>Confirmar</span>
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<style scoped>
/* Scrollbar personalizado para la tabla */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

@keyframes zoom-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-in {
    animation: zoom-in 0.2s ease-out forwards;
}
</style>