<template>
  <div class="flex justify-center bg-gray-200 min-h-screen p-8 font-sans">
    
    <div class="bg-white w-[21.59cm] h-[27.94cm] px-12 py-12 shadow-lg text-black box-border relative leading-snug print:w-full print:h-full print:m-0 print:shadow-none print:p-8">
      
      <div class="flex justify-between items-start mb-8">
        <div class="w-1/3">
          <img src="/images/logo_cdmx.png" alt="LOGO CDMX" class="h-20 w-auto object-contain grayscale" />
        </div>

        <div class="w-[45%] text-[9px] font-bold tracking-tight text-right">
          <p class="text-justify mb-1">
            SECRETARÍA DE ADMINISTRACIÓN Y FINANZAS DE LA CDMX
          </p>
          <p class="text-justify mb-1">
            DIRECCIÓN GENERAL DE ADMINISTRACIÓN Y FINANZAS EN LA SECRETARÍA DE DESARROLLO URBANO Y VIVIENDA
          </p>
          <p class="uppercase mt-2">
            SUBDIRECCIÓN DE ADMINISTRACIÓN DE CAPITAL HUMANO
          </p>
        </div>
      </div>

      <div class="text-center font-bold text-sm mb-6 uppercase tracking-wide">
        FORMATO PARA SOLICITUD DE TRÁMITE DE PREMIO DE PUNTUALIDAD Y ASISTENCIA
      </div>

      <div class="border-2 border-black mb-6">
        <div class="bg-gray-200 text-center font-bold border-b-2 border-black py-2 text-[11px]">
          DATOS DEL SERVIDOR PÚBLICO
        </div>

        <div class="p-4 grid grid-cols-12 gap-x-4 gap-y-5">
          
          <div class="col-span-6">
            <label class="block font-bold mb-1 text-[10px]">FECHA DE SOLICITUD</label>
            <input type="date" class="input-form" />
          </div>
          <div class="col-span-6">
            <label class="block font-bold mb-1 text-[10px]">NÚMERO DE FOLIO</label>
            <input type="text" class="input-form" />
          </div>

          <div class="col-span-4">
            <label class="block font-bold mb-1 text-[10px]">APELLIDO PATERNO</label>
            <input type="text" class="input-form uppercase" />
          </div>
          <div class="col-span-4">
            <label class="block font-bold mb-1 text-[10px]">APELLIDO MATERNO</label>
            <input type="text" class="input-form uppercase" />
          </div>
          <div class="col-span-4">
            <label class="block font-bold mb-1 text-[10px]">NOMBRE(S)</label>
            <input type="text" class="input-form uppercase" />
          </div>

          <div class="col-span-12">
            <label class="block font-bold mb-1 text-[10px]">UNIDAD DE ADSCRIPCIÓN</label>
            <input type="text" class="input-form uppercase" />
          </div>

          <div class="col-span-6">
            <label class="block font-bold mb-1 text-[10px]">C.U.R.P</label>
            <input type="text" class="input-form uppercase" />
          </div>
          <div class="col-span-6">
            <label class="block font-bold mb-1 text-[10px]">R.F.C</label>
            <input type="text" class="input-form uppercase" />
          </div>
        </div>
      </div>

      <div class="mb-6">
        <div class="w-full border border-black bg-gray-100 text-center font-bold py-1 mb-2 text-[10px]">
          TABLA DE REQUISITOS
        </div>
        <p class="italic text-[10px] mb-3 ml-2">
          NOTA: RELLENAR CON UNA (X) EL MES A CONTINUACIÓN, COLOCAR EL AÑO.
        </p>
        <div class="flex items-center text-[10px] font-bold ml-6">
          <span class="mr-2">EJEMPLO:</span>
          <span class="mr-6">2025</span>
          <div class="w-4 h-4 border border-black flex items-center justify-center mr-2 text-xs">X</div>
          <span>ENERO</span>
        </div>
      </div>

      <div class="border-2 border-black mb-8">
        <div class="bg-gray-200 text-center font-bold border-b-2 border-black py-1 text-[11px]">
          TABLA DE CONTROL
        </div>
        
        <div class="grid grid-cols-2">
          
          <table class="w-full border-collapse border-r border-black text-[10px]">
            <thead>
              <tr class="bg-gray-50 border-b border-black">
                <th class="border-r border-black w-10 py-1">SEL</th>
                <th class="border-r border-black py-1 text-left px-2">MES</th>
                <th class="w-16 py-1 text-center">AÑO</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mes in ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO']" :key="mes" class="border-b border-black last:border-b-0 h-8">
                <td class="border-r border-black p-0 text-center align-middle">
                  <div class="w-4 h-4 border border-black mx-auto cursor-pointer hover:bg-gray-200"></div>
                </td>
                <td class="border-r border-black px-3 font-bold align-middle">{{ mes }}</td>
                <td class="text-center align-middle text-gray-500">202_</td>
              </tr>
            </tbody>
          </table>

          <table class="w-full border-collapse text-[10px]">
            <thead>
              <tr class="bg-gray-50 border-b border-black">
                <th class="border-r border-black w-10 py-1">SEL</th>
                <th class="border-r border-black py-1 text-left px-2">MES</th>
                <th class="w-16 py-1 text-center">AÑO</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mes in ['JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE']" :key="mes" class="border-b border-black last:border-b-0 h-8">
                <td class="border-r border-black p-0 text-center align-middle">
                  <div class="w-4 h-4 border border-black mx-auto cursor-pointer hover:bg-gray-200"></div>
                </td>
                <td class="border-r border-black px-3 font-bold align-middle">{{ mes }}</td>
                <td class="text-center align-middle text-gray-500">202_</td>
              </tr>
            </tbody>
          </table>

        </div>
      </div>

      <div class="flex justify-between items-end absolute bottom-12 left-12 right-12">
        <div class="text-[9px] text-gray-500 font-bold uppercase leading-normal">
          <p>AMORES 1322, COL. DEL VALLE</p>
          <p>CENTRO BENITO JUÁREZ 03100</p>
          <p>CIUDAD DE MÉXICO. TEL. 51302010</p>
        </div>

        <div class="text-center w-64">
          <div class="border-t border-black pt-2">
            <p class="text-[9px] font-bold uppercase">(NOMBRE, FECHA Y FIRMA DEL SOLICITANTE)</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Clase utilitaria para los inputs: más altos (h-8) y con borde definido */
.input-form {
  @apply w-full h-8 border border-gray-500 px-2 text-[11px] focus:outline-none focus:border-black rounded-none bg-transparent;
}

@media print {
  @page {
    margin: 0;
    size: letter;
  }
}
</style>