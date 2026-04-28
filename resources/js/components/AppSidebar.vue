<script setup lang="ts">
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


import { general, mi_tarjeta, disponibilidad } from '@/routes/tarjetas';
import { index as usersIndex } from '@/routes/users'; 
import { index as logsIndex } from '@/routes/logs'; 
import * as incidencias from '@/routes/incidencias';
import { IdCard, ChartNoAxesCombined, Info, Command, FileClock,  Users, TriangleAlert, CalendarCheck, Trophy, ClockCheck, FingerprintPattern, BookAlert, CalendarClock, TableProperties } from 'lucide-vue-next';
import kardex from '@/routes/kardex';
import buscar from '@/routes/asistencia_cruda';
import faltas from '@/routes/faltas';
import horarios from '@/routes/horarios';
import listas from '@/routes/asistencia';

const { toggleSidebar } = useSidebar();
const page = usePage();
const user = page.props.auth.user;

// --- GESTIÓN DE ROLES ---
const role = user? user.role : 'empleado';
// const isAdmin = role === 'admin';
// const isSupervisor = role === 'supervisor';
// const isCapturista = role === 'capturista';

const mainNavItems = computed(() => {
    

    if(role=='empleado'){
        return [
            { 
                title: 'Mi Tarjeta', 
                href: mi_tarjeta ? mi_tarjeta().url : '#', 
                icon: IdCard 
            },
            { 
                title: 'Mis checadas', 
                href: mi_tarjeta ? mi_tarjeta().url : '#', 
                icon: ClockCheck
            },
            { 
                title: 'Solicitud de Premio', 
                href: mi_tarjeta ? mi_tarjeta().url : '#', 
                icon: Trophy
            },
            { 
                title: 'Solicitud de Incidencia', 
                href: mi_tarjeta ? mi_tarjeta().url : '#', 
                icon: TriangleAlert
            },
        ];
    }
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
    const items = [];

    if (role === 'capturista') {
        items.push({ 
            title: 'Incidencias', 
            href: incidencias.index ? incidencias.index().url : '#', 
            icon: TriangleAlert 
        });
    }

    
    if (role === 'asistencia') {
        items.push(
            { 
                title: 'Kardex', 
                href: kardex.index ? kardex.index().url : '#', 
                icon: ChartNoAxesCombined 
            },
            { 
                title: 'Checadas Biometricos', 
                href: buscar.index? buscar.index().url : '#', 
                icon: FingerprintPattern
            }
        );
    }

    // Módulos EXCLUSIVOS DE ADMIN
    if (role === 'admin') {
        items.push(
            { 
                title: 'Kardex', 
                href: kardex.index ? kardex.index().url : '#', 
                icon: ChartNoAxesCombined 
            },
            { 
                title: 'Checadas Biometricos', 
                href: buscar.index? buscar.index().url : '#', 
                icon: FingerprintPattern
            },
            {
                title: 'Incidencias',
                href: incidencias.index ? incidencias.index().url : '#',
                icon: TriangleAlert
            },
            {
                title: 'Horarios',
                href: horarios.index ? horarios.index().url : '#',
                icon: CalendarClock
            },
            {
                title: 'Reporte Faltas',
                href: faltas.index ? faltas.index().url : '#',
                icon: BookAlert
            },
            {
                title: 'Listas de asistencia',
                href: listas.index ? listas.index().url : '#',
                icon: TableProperties
            },
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
            },
            
        );
    }

    return items;
});

const footerNavItems = [
    
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