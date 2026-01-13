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
            <input
              type="text"
              v-model="searchTerm"
              placeholder="Buscar por nombre o # de empleado"
            />
          </div>
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
        </div>

        <!-- Vista de Horarios: Se muestra cuando se ha seleccionado un empleado. -->
        <div v-else class="viewer-container">
            <div class="mb-4">
                <button @click="resetSelection" class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Volver a la búsqueda
                </button>
            </div>
          <!-- Renderiza el componente hijo, pasándole el empleado seleccionado como prop. -->
          <ScheduleViewer :employee="selectedEmployee" />
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
  align-self: flex-start; /* Previene que la tarjeta se estire verticalmente en un contenedor flex. */
  margin-top: 40px; /* Un poco de margen superior */
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
    max-width: 1000px; /* Ancho máximo para el contenedor del visor de horarios. */
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