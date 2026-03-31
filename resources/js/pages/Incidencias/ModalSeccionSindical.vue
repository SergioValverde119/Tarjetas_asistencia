<script setup lang="ts">
import { ref, computed, h } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Save, Users, Info, Calendar, Clock, FileText, 
    ArrowLeft, Undo2, Loader2, CheckCircle, AlertTriangle 
} from 'lucide-vue-next';

interface Props {
    departamentos: any[];
    categories: any[];
    flash: { success?: string | null; error?: string | null; };
}

const props = defineProps<Props>();

defineOptions({
    layout: (h: any, page: any) => h(AppLayout, {
        breadcrumbs: [
            { title: 'Incidencias', href: '/incidencias' },
            { title: 'Inyección por Sección', href: '#' },
        ]
    }, () => page),
});

const form = useForm({
    department_id: '',
    category_id: '',
    start_time: '',
    end_time: '',
    reason: 'Día otorgado por acuerdo de Sección Sindical'
});

const submit = () => {
    form.post('/incidencias/por-seccion', {
        onSuccess: () => form.reset('department_id', 'start_time', 'end_time'),
    });
};
</script>

<template>
    <Head title="Inyectar por Sección" />

    <div class="flex flex-col bg-slate-50 p-6 w-full min-w-0 font-sans pb-12">
        <div class="flex flex-col w-full min-w-0 space-y-6">
            
            <!-- EXPLICACIÓN DE JERARQUÍA -->
            <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded-r-xl shadow-sm">
                <div class="flex gap-3">
                    <Info class="h-6 w-6 text-indigo-600 shrink-0" />
                    <div>
                        <h4 class="text-sm font-black text-indigo-900 uppercase tracking-tight">Lógica de Respeto a Jerarquía</h4>
                        <p class="text-xs text-indigo-800 mt-1 leading-relaxed">
                            El sistema analizará a cada empleado de la sección. Si el empleado ya cuenta con un permiso registrado (Licencia, Económico, etc.), el sistema <strong>omitirá</strong> la inyección para esa persona, respetando su derecho previo.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TARJETA FORMULARIO -->
            <div class="bg-white shadow-xl rounded-2xl border border-slate-200 overflow-hidden">
                <div class="p-8">
                    <div class="flex items-center gap-4 mb-10 border-b border-slate-50 pb-6">
                        <div class="h-14 w-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                            <Users class="h-8 w-8" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Registro Masivo por Área</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Sincronización condicionada por Sección Sindical</p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-12 gap-8">
                        
                        <!-- SELECCIÓN DE ÁREA -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">1. Área / Sección Sindical</label>
                            <select v-model="form.department_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Seleccione el departamento...</option>
                                <option v-for="d in departamentos" :key="d.id" :value="d.id">{{ d.dept_name }}</option>
                            </select>
                            <p v-if="form.errors.department_id" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.department_id }}</p>
                        </div>

                        <!-- TIPO DE PERMISO -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">2. Tipo de Incidencia a Aplicar</label>
                            <select v-model="form.category_id" class="w-full h-12 rounded-xl border-slate-200 bg-blue-50/30 text-sm font-bold focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Seleccione tipo...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-red-600 text-[10px] mt-1 font-black uppercase">{{ form.errors.category_id }}</p>
                        </div>

                        <!-- MOTIVO -->
                        <div class="md:col-span-12">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">3. Motivo Justificante (Aparecerá en el Kárdex)</label>
                            <textarea v-model="form.reason" rows="3" class="w-full rounded-xl border-slate-200 bg-blue-50/30 text-sm font-medium p-4 focus:ring-indigo-500" placeholder="Justificación del descanso masivo..."></textarea>
                        </div>

                        <!-- FECHAS -->
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-indigo-600 uppercase mb-1 flex items-center gap-1"><Calendar class="h-3 w-3" /> Fecha y Hora Inicio</label>
                            <input type="datetime-local" v-model="form.start_time" class="w-full h-12 rounded-xl border-indigo-200 bg-indigo-50/20 text-sm font-bold focus:ring-indigo-500" />
                        </div>
                        <div class="md:col-span-6">
                            <label class="block text-[10px] font-black text-indigo-600 uppercase mb-1 flex items-center gap-1"><Calendar class="h-3 w-3" /> Fecha y Hora Final</label>
                            <input type="datetime-local" v-model="form.end_time" class="w-full h-12 rounded-xl border-indigo-200 bg-indigo-50/20 text-sm font-bold focus:ring-indigo-500" />
                        </div>
                    </form>
                </div>

                <!-- ACCIONES -->
                <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex justify-end items-center gap-4">
                    <Link href="/incidencias" class="inline-flex items-center px-8 py-3 text-[11px] font-black uppercase tracking-widest text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg transition-all active:scale-95 gap-2">
                        <Undo2 class="h-4 w-4" /> Cancelar
                    </Link>

                    <button 
                        @click="submit" 
                        :disabled="form.processing"
                        class="inline-flex items-center px-10 py-3 text-[11px] font-black uppercase tracking-[0.1em] rounded-xl shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-all disabled:opacity-50 active:scale-95 gap-2"
                    >
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Ejecutar Inyección Masiva
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>