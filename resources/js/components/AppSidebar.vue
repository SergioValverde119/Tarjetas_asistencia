<script setup>
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
import { general } from '@/routes/tarjetas';
// Asumimos que la ruta se llamará 'logs.index' y Wayfinder la exportará así
// Si te marca error, ejecuta 'php artisan wayfinder:generate' después de crear la ruta
import { index as logsIndex } from '@/routes/logs'; 
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, IdCard, Info, Command, FileClock } from 'lucide-vue-next'; // Agregamos FileClock

const { toggleSidebar } = useSidebar();

const mainNavItems = [
    {
        title: 'Dashboard',
        href: home(),
        icon: LayoutGrid,
    },
    {
        title: 'Tarjetas Generales',
        href: general(),
        icon: IdCard,
    },
    {
        title: 'Bitácora Descargas', // Nuevo módulo
        href: logsIndex ? logsIndex() : '#', // Protección por si no has generado rutas aún
        icon: FileClock,
    },
];

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
                    <SidebarMenuButton size="lg" @click="toggleSidebar" class="hover:bg-gray-100 transition-colors">
                        <div class="flex items-center gap-3">
                            <!-- Logo del Sistema -->
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>