<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar, 
} from '@/components/ui/sidebar';

import { home } from '@/routes';
import { general, mi_tarjeta, disponibilidad } from '@/routes/tarjetas';
import { index as usersIndex } from '@/routes/users'; 
import { index as logsIndex } from '@/routes/logs'; 
import * as incidencias from '@/routes/incidencias';

import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, IdCard, Info, Command, FileClock, Archive, User, Users, TriangleAlert, CalendarCheck } from 'lucide-vue-next';

const { toggleSidebar } = useSidebar();
const page = usePage();
const user = page.props.auth.user;

// --- GESTIÓN DE ROLES ---
const role = user ? user.role : 'empleado';
const isAdmin = role === 'admin';
const isSupervisor = role === 'supervisor';
const isCapturista = role === 'capturista';

const mainNavItems = computed(() => {
    
    // CASO 1: MONITOR DE DISPONIBILIDAD (VISTA EXCLUSIVA)
    // Si el usuario tiene el rol 'disponibilidad', SOLO ve esto y nada más.
    if (role === 'disponibilidad') {
        return [
            { 
                title: 'Disponibilidad', 
                href: disponibilidad ? disponibilidad().url : '#', 
                icon: CalendarCheck 
            }
        ];
    }

    // CASO 2: BASE COMÚN (Para Empleado, Supervisor y Admin)
    const items = [
        
    ];

    if (role === 'capturista') {
        items.push({ 
            title: 'Incidencias', 
            href: incidencias.index ? incidencias.index().url : '#', 
            icon: TriangleAlert 
        });
    }

    // Módulo INCIDENCIAS (Supervisor + Admin)
    if (isAdmin || isSupervisor) {
        items.push({ 
            title: 'Incidencias', 
            href: incidencias.index ? incidencias.index().url : '#', 
            icon: TriangleAlert 
        });
    }

    // Módulos EXCLUSIVOS DE ADMIN
    if (isAdmin) {
        items.push(
            { 
                title: 'Generador de Tarjetas', 
                href: general ? general().url : '#', 
                icon: IdCard 
            },
            { 
                title: 'Disponibilidad', // El Admin también ve el semáforo
                href: disponibilidad ? disponibilidad().url : '#', 
                icon: CalendarCheck 
            },
            { 
                title: 'Bitácora Descargas', 
                href: logsIndex ? logsIndex().url : '#', 
                icon: FileClock 
            },
            { 
                title: 'Usuarios', 
                href: usersIndex ? usersIndex().url : '#', 
                icon: Users 
            }
        );
    }

    return items;
});

const footerNavItems = [
    { title: 'Datos', href: '#', icon: Folder },
    { title: 'Soporte', href: '#', icon: Info },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" @click="toggleSidebar" class="hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center h-8 w-8 rounded-md bg-red-900 text-white overflow-hidden border border-red-950">
                                <Command class="h-5 w-5" />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-bold">Tarjetas</span>
                                <span class="truncate text-xs text-gray-500">Control Asistencia</span>
                            </div>
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>