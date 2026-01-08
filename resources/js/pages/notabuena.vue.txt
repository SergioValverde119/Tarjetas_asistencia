<script setup>
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: null
});

const props = defineProps({
    empleado: {
        type: Object,
        default: () => ({
            nombre: 'SERGIO AXEL',
            paterno: 'VALVERDE',
            materno: 'HERRERA',
            area: 'SUBDIRECCIÓN DE DESARROLLO DE SISTEMAS',
            curp: 'VAHS901010HDFRRNA9',
            rfc: 'VAHS901010ABC',
            num_empleado: '123456'
        })
    },
    fechaSolicitud: { type: String, default: '07/01/2026' },
    folio: { type: String, default: '00123' },
    mesSeleccionado: { type: String, default: 'ENERO' },
    anio: { type: [String, Number], default: 2025 }
});

const meses = [
    'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
    'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'
];
</script>

<template>
    <Head title="Formato Premio Puntualidad" />

    <div class="min-h-screen bg-gray-100 flex justify-center items-start py-8 print:bg-white print:p-0">
        
        <div class="hoja-carta bg-white text-black font-sans text-[11px] leading-tight relative shadow-2xl print:shadow-none print:m-0">
            
            <div class="flex justify-between items-start pt-10 px-10 mb-6">
                <div class="w-[40%]">
                    <img src="/images/logo_cdmx.jpeg" alt="CDMX" class="w-56 object-contain" />
                </div>
                
                <div class="w-[45%] mt-2 ml-auto">
                    <div class="font-bold text-[9px] leading-snug text-justify uppercase tracking-tight">
                        SECRETARÍA DE ADMINISTRACIÓN Y FINANZAS DE LA CDMX DIRECCIÓN GENERAL DE ADMINISTRACIÓN Y FINANZAS EN LA SECRETARÍA DE DESARROLLO URBANO Y VIVIENDA SUBDIRECCIÓN DE ADMINISTRACIÓN DE CAPITAL HUMANO
                    </div>
                </div>
            </div>

            <div class="text-center mb-6 px-10">
                <h2 class="font-extrabold text-[12px] tracking-wide">FORMATO PARA SOLICITUD DE TRÁMITE DE PREMIO DE PUNTUALIDAD Y</h2>
                <h2 class="font-extrabold text-[12px] tracking-wide">ASISTENCIA</h2>
            </div>

            <div class="px-10 mb-6">
                <div class="border-2 border-black p-2">
                    <div class="text-center font-bold text-[11px] mb-3 border-b border-black pb-1">
                        DATOS DEL SERVIDOR PÚBLICO
                    </div>

                    <div class="space-y-3 px-1 pb-1">
                        <div class="flex justify-end gap-8">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-[10px]">FECHA DE SOLICITUD:</span>
                                <div class="border border-black w-28 h-6 flex items-center justify-center font-bold">
                                    {{ fechaSolicitud }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-[10px]">NÚMERO DE FOLIO:</span>
                                <div class="border border-black w-20 h-6 flex items-center justify-center text-red-600 font-bold text-[12px]">
                                    {{ folio }}
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="w-1/3">
                                <label class="block font-bold text-[9px] mb-1 ml-1">APELLIDO PATERNO</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[10px]">
                                    {{ empleado.paterno }}
                                </div>
                            </div>
                            <div class="w-1/3">
                                <label class="block font-bold text-[9px] mb-1 ml-1">APELLIDO MATERNO</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[10px]">
                                    {{ empleado.materno }}
                                </div>
                            </div>
                            <div class="w-1/3">
                                <label class="block font-bold text-[9px] mb-1 ml-1">NOMBRE(S)</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[10px]">
                                    {{ empleado.nombre }}
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="w-[50%]">
                                <label class="block font-bold text-[9px] mb-1 ml-1">UNIDAD DE ADSCRIPCIÓN</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[9px] truncate">
                                    {{ empleado.area }}
                                </div>
                            </div>
                            <div class="w-[25%]">
                                <label class="block font-bold text-[9px] mb-1 ml-1">C.U.R.P</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[10px]">
                                    {{ empleado.curp }}
                                </div>
                            </div>
                            <div class="w-[25%]">
                                <label class="block font-bold text-[9px] mb-1 ml-1">R.F.C</label>
                                <div class="border border-black h-7 flex items-center px-2 uppercase font-bold text-[10px]">
                                    {{ empleado.rfc }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-10 mb-4 text-center">
                <h3 class="font-bold text-[11px] mb-1">TABLA DE REQUISITOS</h3>
                <p class="text-[10px] italic mb-2">NOTA: RELLENAR CON UNA (X) EL MES A CONTINUACIÓN, COLOCAR EL AÑO</p>
                
                <div class="flex justify-center items-center gap-2 mt-2">
                    <span class="text-[10px] font-bold">EJEMPLO:</span>
                    <div class="w-5 h-5 border border-black flex items-center justify-center font-bold text-[11px]">X</div>
                    <span class="font-bold text-[11px] mx-2">ENERO</span>
                    <span class="font-bold text-[11px]">2025</span>
                </div>
            </div>

            <div class="flex justify-center mb-10 px-10">
                <div class="w-[60%]">
                    <h3 class="font-bold text-[11px] text-center mb-2">TABLA DE CONTROL</h3>
                    
                    <table class="w-full border-collapse border border-black text-[10px]">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-black py-1 w-14 text-center text-[9px]">SELECCIÓN</th>
                                <th class="border border-black py-1 px-4 text-left">MES</th>
                                <th class="border border-black py-1 w-16 text-center text-[9px]">AÑO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="mes in meses" :key="mes" class="h-6">
                                <td class="border border-black p-0 align-middle text-center">
                                    <div class="flex justify-center items-center h-full">
                                        <div class="w-4 h-4 border border-black flex items-center justify-center bg-white">
                                            <span v-if="mes === mesSeleccionado" class="font-bold text-[10px]">X</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="border border-black px-4 font-bold align-middle">
                                    {{ mes }}
                                </td>
                                <td class="border border-black text-center align-middle">
                                    {{ mes === mesSeleccionado ? anio : '202_' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-center mt-8 mb-12">
                <div class="text-center w-64">
                    <div class="border-t border-black pt-2"></div>
                    <p class="text-[9px] font-bold">(NOMBRE, FECHA Y FIRMA DEL SOLICITANTE)</p>
                </div>
            </div>

            <div class="absolute bottom-10 left-0 w-full px-16 text-[9px] font-bold leading-snug">
                <p>AMORES 1322. COL DEL VALLE</p>
                <p>CENTRO BENITO JUAREZ 03100</p>
                <p>CIUDAD DE MÉXICO. TEL. 55 5130 2010</p>
            </div>

        </div>
    </div>
</template>

<style scoped>
.hoja-carta {
    width: 21.59cm;
    height: 27.94cm;
}

@media print {
    @page {
        size: letter;
        margin: 0;
    }
    body {
        margin: 0;
        background-color: white;
    }
    .min-h-screen {
        display: block;
        height: auto;
        padding: 0;
        background-color: white;
    }
    .hoja-carta {
        box-shadow: none;
        margin: 0;
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }
}
</style>