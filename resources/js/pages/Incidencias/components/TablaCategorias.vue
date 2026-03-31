<script setup>
const props = defineProps({
    categorias: Array
});
</script>

<template>
    <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden max-h-64 overflow-y-auto custom-scrollbar relative">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                <tr>
                    <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider w-24 text-[11px] bg-gray-50">ID</th>
                    <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Nombre del Permiso</th>
                    <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Código (Símbolo)</th>
                    <th class="px-6 py-3 text-left font-bold text-gray-500 uppercase tracking-wider text-[11px] bg-gray-50">Unidad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="cat in categorias" :key="cat.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ cat.id }}</td>
                    <td class="px-6 py-4 font-bold text-gray-900 uppercase">{{ cat.name }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded font-black text-[10px] border border-gray-200 uppercase tracking-widest">
                            {{ cat.code }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-xs font-medium uppercase">
                        {{ cat.unit === 3 ? 'Días' : (cat.unit === 2 ? 'Horas' : 'Minutos') }}
                    </td>
                </tr>
                <tr v-if="categorias.length === 0">
                    <td colspan="4" class="px-6 py-8 text-center text-gray-400 italic">No hay categorías registradas.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>