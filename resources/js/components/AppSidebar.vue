<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar, 
} from '@/components/ui/sidebar';

import { home } from '@/routes';
// Agregamos 'disponibilidad' al import (asegúrate de crear esta ruta en tu archivo de rutas)
import { general, mi_tarjeta, disponibilidad } from '@/routes/tarjetas';
import { index as usersIndex } from '@/routes/users'; 
import { index as logsIndex } from '@/routes/logs'; 

import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, IdCard, Info, Command, FileClock, Archive, User, Users, TriangleAlert, CalendarCheck } from 'lucide-vue-next';

const { toggleSidebar } = useSidebar();
const page = usePage();
const user = page.props.auth.user;
const isAdmin = user && user.role === 'admin';

const mainNavItems = computed(() => {
    const items = [
        { title: 'Dashboard', href: '#', icon: LayoutGrid },
        { title: 'Configuración Mi tarjeta', href: '#', icon: User },
    ];

    if (isAdmin) {
        items.push(
            { 
                title: 'Generador de Tarjetas', 
                href: general(), 
                icon: IdCard 
            },
            { 
                title: 'Incidencias', 
                href: '#', // Pendiente de ruta
                icon: TriangleAlert 
            },
            { 
                // --- NUEVA PANTALLA DE SEMÁFOROS (SEPARADA) ---
                title: 'Disponibilidad', 
                href: disponibilidad ? disponibilidad() : '#', 
                icon: CalendarCheck 
            },
            { 
                title: 'Bitácora Descargas', 
                href: logsIndex ? logsIndex() : '#', 
                icon: FileClock 
            },
            { 
                // --- CRUD DE USUARIOS ORIGINAL (RESTAURADO) ---
                title: 'Usuarios', 
                href: usersIndex ? usersIndex() : '#', 
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