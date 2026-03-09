<script setup>
// =================================================================================================
// IMPORTS
// =================================================================================================
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
// Se asegura de importar el componente hijo que mostrará los detalles del horario.
import ScheduleViewer from './ScheduleViewer.vue';

// IMPORTAR EL LAYOUT (Única modificación de imports necesaria)
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { home } from '@/routes';
import { general } from '@/routes/tarjetas';

// Logos utilizados
import { FileUp, X, Loader2, CheckCircle, AlertCircle } from 'lucide-vue-next';

// =================================================================================================
// ESTADO REACTIVO (State)
// =================================================================================================

// Almacena el texto introducido por el usuario en la barra de búsqueda.
const searchTerm = ref('');
// Almacena la lista completa de empleados obtenida de la API.
const employees = ref([]);
// Controla la visibilidad del indicador de carga mientras se obtienen los datos iniciales.
const loading = ref(true);
// Almacena el objeto del empleado que el usuario ha seleccionado. Si es 'null', se muestra la lista de búsqueda.
const selectedEmployee = ref(null);

// --- Estados para la importación ---
const showImportModal = ref(false);
const isImporting = ref(false);
const showErrorModal = ref(false);
const errorMessage = ref('');
const showSuccessModal = ref(false);
const successMessage = ref('');

// =================================================================================================
// CONFIGURACIÓN ADICIONAL (Layout)
// =================================================================================================

// Configuración de Breadcrumbs para el Layout
const breadcrumbs = computed(() => {
  const crumbs = [
    { title: 'Dashboard', href: home() },
    { title: 'Generador de Tarjetas', href: general() }
  ];
  if (selectedEmployee.value) {
    crumbs.push({
      title: `${selectedEmployee.value.first_name} ${selectedEmployee.value.last_name}`,
      href: '#'
    });
  }
  return crumbs;
});

// =================================================================================================
// HOOK DE CICLO DE VIDA (Lifecycle Hook)
// =================================================================================================

/**
 * Se ejecuta una vez que el componente ha sido montado en el DOM.
 * Realiza la llamada inicial a la API para obtener la lista completa de empleados.
 * Este enfoque (cargar todo al inicio) es viable para una cantidad moderada de datos.
 * Para listas muy grandes, sería más eficiente hacer la búsqueda en el backend.
 */
onMounted(async () => {
  try {
    const response = await axios.get('/api/internal/users');
    employees.value = response.data.users;
  } catch (error) {
    console.error('Error al obtener todos los empleados:', error);
  } finally {
    loading.value = false;
  }
});

// =================================================================================================
// PROPIEDADES COMPUTADAS (Computed Properties)
// =================================================================================================

/**
 * Filtra la lista de 'employees' basándose en el 'searchTerm'.
 * Esta propiedad es reactiva: se recalcula automáticamente cada vez que 'searchTerm' cambia.
 * Esto elimina la necesidad de una función @input en el campo de búsqueda.
 * @returns {Array} - La lista de empleados filtrada.
 */
const filteredEmployees = computed(() => {
  // Si no hay término de búsqueda, devuelve la lista completa.
  if (!searchTerm.value) {
    return employees.value;
  }

  const searchLower = searchTerm.value.toLowerCase();

  // Filtra la lista comparando el término de búsqueda con el nombre completo y el código de empleado.
  return employees.value.filter(employee => {
    const fullName = `${employee.first_name} ${employee.last_name}`.toLowerCase();
    const empCode = employee.emp_code.toString().toLowerCase();

    return fullName.includes(searchLower) || empCode.includes(searchLower);
  });
});

// =================================================================================================
// MÉTODOS (Methods)
// =================================================================================================

/**
 * Asigna el empleado seleccionado al estado 'selectedEmployee',
 * lo que provoca que la vista cambie del buscador al visor de horarios.
 * @param {Object} employee - El objeto del empleado al que se le hizo clic.
 */
const selectEmployee = (employee) => {
  selectedEmployee.value = employee;
};

/**
 * Resetea el estado 'selectedEmployee' a 'null',
 * lo que provoca que la vista regrese del visor de horarios al buscador.
 */
const resetSelection = () => {
  selectedEmployee.value = null;
};

/**
 *  Procesa la subida del Excel para rescatar la información
 * 
 */
const handleImportSubmit = async (event) => {
  const formElement = event.target;
  const formData = new FormData(formElement);
  isImporting.value = true;

  try {
    const response = await axios.post('/tarjetas/importar-registros', formData, {
      responseType: 'blob',
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    // Forzamos la descarga del Excel de Resultados
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `Resultados_Rescate_Registros_${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);

    successMessage.value = "Proceso finalizado. Revise el Excel descargado para ver el estatus de cada registro.";
    showSuccessModal.value = true;
    formElement.reset();
    showImportModal.value = false;

  } catch (error) {
    errorMessage.value = "Hubo un error al leer el archivo. Asegúrese de que sea un Excel válido";
    showErrorModal.value = true;
  } finally {
    isImporting.value = false;
  }
};
</script>

<template>

  <Head title="Generador de Tarjetas" />

  <!-- Envolvemos todo en el AppLayout para mantener la barra lateral -->
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="page-container">
      <!-- Vista de Búsqueda: Se muestra si 'selectedEmployee' es nulo. -->
      <div v-if="!selectedEmployee" class="search-card">
        <h1 class="text-3xl font-bold text-gray-800">Sistema de Control de Asistencia</h1>
        <div class="search-bar">
          <input type="text" v-model="searchTerm" placeholder="Buscar por nombre o # de empleado" />
        </div>



        <!-- Lista de empleados -->
        <div class="employee-list">
          <p v-if="loading" class="p-4 text-center text-gray-500">Cargando empleados...</p>
          <ul v-else>
            <li v-for="employee in filteredEmployees" :key="employee.id" @click="selectEmployee(employee)">
              {{ employee.emp_code }} - {{ employee.first_name }} {{ employee.last_name }}
            </li>
          </ul>
          <p v-if="!loading && filteredEmployees.length === 0" class="p-4 text-center text-gray-500">
            No se encontraron empleados.
          </p>
        </div>

        <!-- BOTÓN DE IMPORTACIÓN (Sólido y más grande) -->
        <div class="mt-6 pt-5 border-t border-gray-100 flex justify-end">
          <button @click="showImportModal = true"
            class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 active:scale-95">
            <FileUp class="h-5 w-5 mr-2" /> Importar Excel
          </button>
        </div>
      </div>

      <!-- Vista de Horarios: Se muestra cuando se ha seleccionado un empleado. -->
      <div v-else class="viewer-container">
        <div class="mb-4">
          <button @click="resetSelection" class="back-button">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd"
                d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
                clip-rule="evenodd" />
            </svg>
            Volver a la búsqueda
          </button>
        </div>
        <!-- Renderiza el componente hijo, pasándole el empleado seleccionado como prop. -->
        <ScheduleViewer :employee="selectedEmployee" />
      </div>
    </div>

    <!-- ================= MODALES DE IMPORTACIÓN ================= -->

    <ErrorModal :show="showErrorModal" :message="errorMessage" title="Atención en Importación"
      @close="showErrorModal = false" />

    <!-- Modal de Éxito -->
    <div v-if="showSuccessModal"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity">
      <div
        class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center animate-in fade-in zoom-in duration-200">
        <div
          class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-green-100 mb-4 border-4 border-green-50">
          <CheckCircle class="h-8 w-8 text-green-600" />
        </div>
        <h3 class="text-lg font-black text-gray-900 mb-2 uppercase tracking-tight">Proceso Terminado</h3>
        <p class="text-sm text-gray-600 mb-6 font-medium leading-relaxed">{{ successMessage }}</p>
        <button @click="showSuccessModal = false"
          class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-xl transition-colors shadow-md">
          Entendido
        </button>
      </div>
    </div>

    <!-- MODAL PRINCIPAL: IMPORTAR REGISTROS -->
    <div v-if="showImportModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity">
      <div
        class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative animate-in fade-in zoom-in-95 duration-200">
        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
          <h3 class="text-lg font-black text-gray-900 flex items-center gap-2">
            <FileUp class="h-5 w-5 text-blue-600" /> Importar Registros
          </h3>
          <button @click="showImportModal = false"
            class="text-gray-400 hover:text-gray-600 transition-colors bg-gray-100 rounded-full p-1">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="mb-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg flex items-start gap-3 shadow-sm">
          <AlertTriangle class="h-6 w-6 text-amber-600 shrink-0 mt-0.5" />
          <div>
            <p class="text-xs text-amber-900 font-bold mb-1 uppercase tracking-tight">Instrucción</p>
            <p class="text-xs text-amber-800 leading-relaxed">
              Asegúrese de que el archivo <strong>SOLO tenga los datos</strong> a partir de la fila 2. Elimine cualquier
              fila de ejemplo o instrucciones de la plantilla original.
            </p>
          </div>
        </div>

        <div
          class="mb-5 flex items-center gap-3 p-3 bg-blue-50 text-blue-800 rounded-xl border border-blue-100 shadow-sm">
          <Info class="h-5 w-5 shrink-0" />
          <p class="text-[10px] font-medium leading-tight italic">
            Nota técnica: El sistema saltará automáticamente la primer fila de encabezados. Las columnas deben ser:
            Nómina, Nombre, Entrada, Salida.
          </p>
        </div>

        <form @submit.prevent="handleImportSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Archivo Excel (.xlsx)</label>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
              class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-xl p-1 cursor-pointer transition-colors" />
          </div>

          <div class="flex justify-center pb-2">
            <a href="/tarjetas/plantilla-registros" target="_blank"
              class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1.5 transition-colors">
              <Download class="h-4 w-4" /> Descargar plantilla oficial
            </a>
          </div>

          <div class="flex justify-end pt-2 border-t border-gray-100">
            <button type="submit" :disabled="isImporting"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-4 rounded-xl shadow-md shadow-blue-600/20 disabled:opacity-50 flex justify-center items-center gap-2 transition-all active:scale-95 uppercase text-xs tracking-wide">
              <Loader2 v-if="isImporting" class="h-4 w-4 animate-spin" />
              {{ isImporting ? 'Cargando...' : 'Subir y Procesar Registros' }}
            </button>
          </div>
        </form>
      </div>


    </div>


  </AppLayout>
</template>

<style scoped>
.page-container {
  background-color: #f3f4f6;
  padding: 40px;
  /* MODIFICADO: Se elimina min-height: 100vh para evitar el doble scroll con el Layout */
  /* min-height: 100vh; */
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.search-card {
  width: 100%;
  max-width: 600px;
  background-color: white;
  padding: 32px;
  border-radius: 12px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  text-align: center;
  align-self: flex-start;
  /* Previene que la tarjeta se estire verticalmente en un contenedor flex. */
  margin-top: 40px;
  /* Un poco de margen superior */
}

.search-bar {
  margin: 20px 0;
}

input {
  width: 100%;
  padding: 12px 15px;
  font-size: 16px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.employee-list {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  height: 500px;
  overflow-y: auto;
  text-align: left;
  background-color: white;
}

li {
  padding: 12px 15px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  transition: background-color 0.2s;
}

li:last-child {
  border-bottom: none;
}

li:hover {
  background-color: #f0f5ff;
}

.viewer-container {
  width: 100%;
  max-width: 1000px;
  /* Ancho máximo para el contenedor del visor de horarios. */
  margin-top: 20px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  padding: 8px 16px;
  background-color: white;
  color: #4f46e5;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.back-button:hover {
  background-color: #f9fafb;
  border-color: #d1d5db;
}

.mb-4 {
  margin-bottom: 1rem;
}
</style>