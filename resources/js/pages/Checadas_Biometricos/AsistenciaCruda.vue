<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Search, Calendar, User, Clock, X, Info } from 'lucide-vue-next';
import { home } from '@/routes';

/**
 * Importación de rutas Wayfinder.
 * Gracias a Jehová Dios y a Jesús Rey por darnos la guía necesaria.
 */
import { buscar, exportar } from '@/routes/asistencia_cruda';

// Definición de tipos para las propiedades
interface Props {
    checadas: any[];
    filtros: {
        codigo_empleado?: string;
        fecha_inicio?: string;
        fecha_fin?: string;
        fecha_unica?: string;
    } | null;
}

const props = defineProps<Props>();

// Inicialización del formulario reactivo
const form = useForm({
    codigo_empleado: props.filtros?.codigo_empleado || '',
    fecha_inicio: props.filtros?.fecha_inicio || '',
    fecha_fin: props.filtros?.fecha_fin || '',
    fecha_unica: props.filtros?.fecha_unica || '',
});

/**
 * Lógica de limpieza mutua de campos:
 * Si se llena la fecha única (azul), se borra el rango (naranja).
 * Si se llena cualquier campo del rango (naranja), se borra la fecha única (azul).
 */
watch(() => form.fecha_unica, (newVal) => {
    if (newVal) {
        form.fecha_inicio = '';
        form.fecha_fin = '';
    }
});

watch([() => form.fecha_inicio, () => form.fecha_fin], ([newIni, newFin]) => {
    if (newIni || newFin) {
        form.fecha_unica = '';
    }
});

// --- Lógica del Modal de Horario ---
const mostrarModalHorario = ref(false);
const horarioSeleccionado = ref<any>(null);

const verDetalleHorario = (horario: any) => {
    horarioSeleccionado.value = horario;
    mostrarModalHorario.value = true;
};

const cerrarModal = () => {
    mostrarModalHorario.value = false;
    horarioSeleccionado.value = null;
};

const calcularSalida = (entrada: string, duracion: number) => {
    if (!entrada || !duracion) return '---';
    const [horas, minutos] = entrada.split(':').map(Number);
    const fecha = new Date();
    fecha.setHours(horas, minutos + duracion, 0);
    return fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
};

/**
 * Formatea la fecha incluyendo SEGUNDOS.
 */
const formatearFecha = (fechaRaw: string): string => {
    if (!fechaRaw) return '---';
    const objetoFecha = new Date(fechaRaw);
    return objetoFecha.toLocaleString('es-MX', {
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: false
    });
};

const consultar = (): void => {
    form.get(buscar().url, { preserveScroll: true, preserveState: true });
};

const descargarExcel = (): void => {
    const params = new URLSearchParams({
        codigo_empleado: form.codigo_empleado, 
        fecha_inicio: form.fecha_inicio,
        fecha_fin: form.fecha_fin,
        fecha_unica: form.fecha_unica
    });
    window.location.href = `${exportar().url}?${params.toString()}`;
};

// Breadcrumbs configurados apuntando a Home
const breadcrumbs = [
    { title: 'Home', href: home().url },
    { title: 'Asistencias Crudas', href: '#' }
];
</script>

<template>
    <Head title="Monitor de Asistencia Cruda" />

    <SidebarProvider>
        <AppSidebar>
            <div class="min-h-screen bg-slate-50 p-4 flex flex-col items-center font-sans">
                <div class="w-full max-w-[1200px] space-y-4">
                    
                    <!-- MIGAS DE PAN -->
                    <div class="w-full flex justify-start mb-2">
                        <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    </div>

                    <!-- PANEL DE FILTROS -->
                    <Card class="mb-6 border-none shadow-md bg-white">
                        <CardHeader class="border-b border-slate-100 py-4">
                            <CardTitle class="text-xs uppercase tracking-widest text-slate-500 font-bold">Filtros de Búsqueda</CardTitle>
                        </CardHeader>
                        <CardContent class="pt-6 pb-6">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                                <div class="grid gap-2">
                                    <Label for="emp_id" class="font-bold text-xs uppercase text-slate-600">Empleado (Nombre o ID)</Label>
                                    <Input id="emp_id" v-model="form.codigo_empleado" placeholder="Ej. Sergio o 1" class="h-10 border-slate-300" @keyup.enter="consultar" />
                                </div>
                                
                                <!-- DÍA ÚNICO (PALETA AZUL) -->
                                <div class="grid gap-2">
                                    <Label for="f_uni" class="font-bold text-xs uppercase text-blue-600">Un Solo Día</Label>
                                    <Input id="f_uni" type="date" v-model="form.fecha_unica" class="h-10 border-slate-300 text-blue-600 focus-visible:ring-blue-500" />
                                </div>

                                <!-- RANGO DE FECHAS (PALETA NARANJA) -->
                                <div class="grid gap-2">
                                    <Label for="f_ini" class="font-bold text-xs uppercase text-orange-600">Rango: Desde</Label>
                                    <Input id="f_ini" type="date" v-model="form.fecha_inicio" class="h-10 border-slate-300 text-orange-600 focus-visible:ring-orange-500" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="f_fin" class="font-bold text-xs uppercase text-orange-600">Hasta</Label>
                                    <Input id="f_fin" type="date" v-model="form.fecha_fin" class="h-10 border-slate-300 text-orange-600 focus-visible:ring-orange-500" />
                                </div>

                                <div class="flex gap-2">
                                    <Button @click="consultar" class="flex-1 h-10 font-bold bg-blue-600 text-white shadow-md shadow-blue-100" :disabled="form.processing">Consultar</Button>
                                    
                                    <Button @click="descargarExcel" variant="outline" class="h-10 px-4 font-bold border-slate-300 text-slate-700 hover:bg-slate-50" :disabled="!checadas.length || form.processing">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-600 mr-2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Excel
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- TABLA DE RESULTADOS -->
                    <div v-if="checadas.length > 0" class="bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden">
                        <div class="overflow-x-auto overflow-y-auto max-h-[600px] scrollbar-custom">
                            <table class="w-full text-left">
                                <thead class="bg-slate-100 border-b border-slate-200 text-xs font-bold uppercase text-slate-500">
                                    <tr>
                                        <th class="px-6 py-3">Fecha y Hora</th>
                                        <th class="px-6 py-3">Empleado</th>
                                        <th class="px-6 py-3 text-center">Horario</th>
                                        <th class="px-6 py-3 text-right">Biométrico</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="c in checadas" :key="c.id" class="hover:bg-blue-50 transition-colors group">
                                        <td class="px-6 py-4 font-mono text-sm text-slate-700 font-bold whitespace-nowrap">
                                            {{ formatearFecha(c.fecha_hora) }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800 text-sm group-hover:text-blue-700 transition-colors">{{ c.first_name }} {{ c.last_name }}</span>
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">Nómina: {{ c.user_id }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button 
                                                v-if="c.horario" 
                                                @click="verDetalleHorario(c.horario)"
                                                class="bg-blue-50 text-blue-700 px-3 py-1 rounded text-[10px] font-bold border border-blue-200 hover:bg-blue-600 hover:text-white transition-all shadow-sm"
                                            >
                                                {{ c.horario.nombre }}
                                            </button>
                                            <span v-else class="text-[10px] text-slate-300 italic font-bold">Sin Horario</span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex flex-col items-end">
                                                <span class="text-sm font-bold text-slate-700">{{ c.dispositivo_nombre || 'Equipo S/N' }}</span>
                                                <span class="text-[9px] text-slate-300 font-mono">SN: {{ c.sn }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">
                            Registros: <span class="text-blue-600 ml-1 font-black">{{ checadas.length }}</span>
                        </div>
                    </div>

                    <div v-else-if="filtros" class="text-center py-20 bg-white border border-dashed border-slate-300 rounded-lg">
                        <p class="text-slate-400 font-medium italic">No se hallaron resultados para esta consulta.</p>
                    </div>

                </div>
            </div>

            <!-- MODAL DETALLE DE HORARIO -->
            <div v-if="mostrarModalHorario" class="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4 animate-in fade-in duration-200" @click.self="cerrarModal">
                <Card class="w-full max-w-sm shadow-2xl border-none animate-in zoom-in-95 duration-200">
                    <CardHeader class="bg-blue-600 text-white rounded-t-xl py-3 px-6 flex flex-row justify-between items-center">
                        <CardTitle class="text-xs font-bold uppercase tracking-widest">Detalle de Horario</CardTitle>
                        <button @click="cerrarModal" class="text-white hover:text-blue-200">✕</button>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4 text-center">
                        <h3 class="text-lg font-black text-slate-800 border-b border-slate-100 pb-2">{{ horarioSeleccionado?.nombre }}</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Entrada</span>
                                <span class="text-lg font-black text-emerald-600">{{ horarioSeleccionado?.entrada?.substring(0,5) }}</span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Salida (Est.)</span>
                                <span class="text-lg font-black text-rose-600">{{ calcularSalida(horarioSeleccionado?.entrada, horarioSeleccionado?.duracion) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-100 text-left">
                            <span class="text-[10px] font-bold text-blue-800 uppercase flex items-center gap-1"><Info class="h-3 w-3" /> Tolerancia</span>
                            <span class="text-sm font-black text-blue-700">{{ horarioSeleccionado?.tolerancia }} min.</span>
                        </div>
                        <Button @click="cerrarModal" class="w-full bg-slate-900 text-white font-bold uppercase text-[10px] h-10 tracking-widest">Cerrar</Button>
                    </CardContent>
                </Card>
            </div>
        </AppSidebar>
    </SidebarProvider>
</template>

<style scoped>
.scrollbar-custom::-webkit-scrollbar { width: 6px; }
.scrollbar-custom::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>