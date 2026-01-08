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

// --- RUTAS (Wayfinder) ---
import { home } from '@/routes';
import { general, mi_tarjeta } from '@/routes/tarjetas';
// CAMBIO: Importamos el 'index' (la lista) en lugar de 'create'
import { index as usersIndex } from '@/routes/users'; 
// import { index as logsIndex } from '@/routes/logs'; 

import { Link } from '@inertiajs/vue3';
// Agregamos el icono 'Users' para el menú de gestión
import { BookOpen, Folder, LayoutGrid, IdCard, Info, Command, FileClock, Archive, User, Users } from 'lucide-vue-next';

const { toggleSidebar } = useSidebar();
const page = usePage();
const user = page.props.auth.user;

// Verificamos si el usuario tiene rol de administrador
const isAdmin = user && user.role === 'admin';

// Construimos el menú dinámicamente según el rol
const mainNavItems = computed(() => {
    // 1. Ítems comunes (Visibles para Empleados y Admins)
    const items = [
        {
            title: 'Dashboard',
            href: home(),
            icon: LayoutGrid,
        },
        {
            title: 'Mi Tarjeta',
            href: mi_tarjeta ? mi_tarjeta() : '#',
            icon: User,
        },
    ];

    // 2. Ítems EXCLUSIVOS de Admin
    if (isAdmin) {
        items.push(
            {
                title: 'Tarjetas Generales',
                href: general(),
                icon: IdCard,
            },
            {
                title: 'Bitácora Descargas',
                href: '#', // Cambiar por logsIndex() cuando esté lista
                icon: FileClock,
            },
            {
                // CAMBIO: Ahora apunta a la lista general de usuarios
                title: 'Usuarios', 
                href: usersIndex ? usersIndex() : '#',
                icon: Users,
            }
        );
    }

    return items;
});

const footerNavItems = [
    {
        title: 'Sistema Tarjetas',
        href: '#',
        icon: Folder,
    },
    {
        title: 'Ayuda / Soporte',
        href: '#',
        icon: Info,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <!-- Botón para colapsar/expandir el menú -->
                    <SidebarMenuButton size="lg" @click="toggleSidebar" class="hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <!-- Logo del Sistema (Icono Institucional Guinda) -->
                            <div class="flex items-center justify-center h-8 w-8 rounded-md bg-red-900 text-white overflow-hidden border border-red-950">
                                <Command class="h-5 w-5" />
                            </div>
                            <!-- Texto del Sistema -->
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
            <!-- El menú se renderiza con los ítems calculados -->
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>