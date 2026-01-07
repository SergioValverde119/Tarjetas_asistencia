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
import { general, mi_tarjeta } from '@/routes/tarjetas';
// Reportes removido temporalmente
import { create as createUser } from '@/routes/users'; // Importar nueva ruta (Wayfinder)
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, IdCard, Info, Command, FileClock, User, UserPlus } from 'lucide-vue-next';

const { toggleSidebar } = useSidebar();
const page = usePage();
const user = page.props.auth.user;

const isAdmin = user && user.role === 'admin';

const mainNavItems = computed(() => {
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

    if (isAdmin) {
        items.push(
            {
                title: 'Tarjetas Generales',
                href: general(),
                icon: IdCard,
            },
            {
                title: 'Bitácora Descargas',
                href: '#', // Ajustar con logsIndex()
                icon: FileClock,
            },
            // Reporte Masivo removido temporalmente
            {
                title: 'Alta de Usuarios', // NUEVO BOTÓN
                href: createUser ? createUser() : '#',
                icon: UserPlus,
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