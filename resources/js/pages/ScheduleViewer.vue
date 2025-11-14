<script setup>
// =================================================================================================
// IMPORTS
// =================================================================================================
import { ref, onMounted, computed, nextTick } from 'vue';
import axios from 'axios';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import TarjetaPdf from './TarjetaPdf.vue'; // Componente de presentación para el formato del PDF.

// =================================================================================================
// PROPS Y ESTADO REACTIVO
// =================================================================================================

// --- PROPS ---
// Define las propiedades que este componente espera recibir de su componente padre.
const props = defineProps({
  // Objeto que contiene la información básica del empleado seleccionado.
  employee: Object
});

// --- ESTADO ---
// Almacena la respuesta de la API de horarios. Inicializado con una estructura vacía.
const schedule = ref({ horario: '', registros: [] });
// Controla la visibilidad del componente TarjetaPdf y el estado de los botones de descarga.
const generandoPdf = ref(false);
// Controla la visibilidad del indicador de carga mientras se obtienen los datos.
const loading = ref(false);
// Almacena el mes seleccionado por el usuario. Inicializado con el mes actual.
const selectedMonth = ref(new Date().getMonth() + 1);
// Almacena el año seleccionado por el usuario. Inicializado con el año actual.
const selectedYear = ref(new Date().getFullYear());
// Array estático para el selector de meses.
const months = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

// =================================================================================================
// FUNCIONES DE AYUDA (Helpers)
// =================================================================================================

/**
 * Extrae el número del día de un string de fecha en formato 'YYYY-MM-DD'.
 * @param {string} dateString - La fecha completa.
 * @returns {number | string} - El número del día, o un string vacío si la entrada es inválida.
 */
const getDayFromDateString = (dateString) => {
  if (!dateString || typeof dateString !== 'string') return '';
  const parts = dateString.split('-');
  return parseInt(parts[2], 10);
};

/**
 * Formatea un string de hora 'HH:MM:SS' para que solo muestre 'HH:MM'.
 * @param {string} timeString - La hora completa.
 * @returns {string} - La hora formateada sin segundos.
 */
const formatTimeWithoutSeconds = (timeString) => {
  if (!timeString || typeof timeString !== 'string') return '';
  const parts = timeString.split(':');
  if (parts.length >= 2) {
    return `${parts[0]}:${parts[1]}`;
  }
  return timeString;
};

/**
 * Ajusta dinámicamente la altura de un textarea para que coincida con su contenido.
 * Se dispara en el evento @input del textarea.
 * @param {Event} event - El evento del DOM.
 */
const autoResizeTextarea = (event) => {
  const textarea = event.target;
  textarea.style.height = 'auto'; // Resetea la altura para recalcular correctamente.
  textarea.style.height = `${textarea.scrollHeight}px`; // Ajusta a la nueva altura del contenido.
};

// =================================================================================================
// PROPIEDADES COMPUTADAS
// =================================================================================================

// Calcula el número total de días en el mes y año seleccionados.
const daysInMonth = computed(() => new Date(selectedYear.value, selectedMonth.value, 0).getDate());

// Filtra y devuelve solo los registros de la primera quincena (días 1-15).
const firstFortnight = computed(() => {
  if (!schedule.value.registros || !Array.isArray(schedule.value.registros)) return [];
  return schedule.value.registros.filter(registro => {
    if (!registro || typeof registro.dia === 'undefined') return false;
    const diaNum = getDayFromDateString(registro.dia);
    return !isNaN(diaNum) && diaNum <= 15;
  });
});

// Filtra y devuelve solo los registros de la segunda quincena (días 16 en adelante).
const secondFortnight = computed(() => {
  if (!schedule.value.registros || !Array.isArray(schedule.value.registros)) return [];
  return schedule.value.registros.filter(registro => {
    if (!registro || typeof registro.dia === 'undefined') return false;
    const diaNum = getDayFromDateString(registro.dia);
    return !isNaN(diaNum) && diaNum > 15;
  });
});


// =================================================================================================
// LÓGICA PRINCIPAL
// =================================================================================================

/**
 * Realiza una petición a la API para obtener los registros de asistencia del empleado
 * para el mes y año seleccionados. Formatea los datos recibidos antes de guardarlos.
 */
const fetchSchedule = async () => {
  loading.value = true;
  try {
    const response = await axios.post(`http://10.37.1.6:5000/api/schedules`, {
      emp_id: props.employee.id,
      month: selectedMonth.value,
      year: selectedYear.value
    });
    
    const data = response.data;
    // Pre-formatea los datos de hora antes de asignarlos al estado,
    // para que la interfaz y los componentes hijos los reciban ya limpios.
    if (data.registros && Array.isArray(data.registros)) {
      data.registros = data.registros.map(registro => ({
        ...registro,
        //checkin: formatTimeWithoutSeconds(registro.checkin),
       // checkout: formatTimeWithoutSeconds(registro.checkout),
      }));
    }
    
    schedule.value = data;

    await nextTick();

    
    const textareas = document.querySelectorAll('.editable-textarea');
    textareas.forEach(textarea => {
        
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
    });


  } catch (error) {
    console.error('Error al obtener el horario:', error);
    schedule.value = { horario: '', registros: [] };
  } finally {
    loading.value = false;
  }
};

/**
 * MÉTODO 1: Genera un PDF tomando una "captura de pantalla" del componente TarjetaPdf.
 * Es rápido y garantiza fidelidad visual, pero el resultado es una imagen sin texto seleccionable.
 */
const generatePDF = async () => {
  generandoPdf.value = true;
  try {
    await nextTick(); // Espera a que Vue renderice el componente TarjetaPdf (v-if="generandoPdf").
    const element = document.getElementById('pdf-content');
    if (!element) throw new Error("No se encontró el elemento #pdf-content.");
    
    // Usa html2canvas para convertir el div en una imagen (canvas).
    const canvas = await html2canvas(element, { scale: 2, useCORS: true });
    const imgData = canvas.toDataURL('image/jpeg', 0.98);
    
    // Crea un nuevo documento PDF y pega la imagen, ajustándola al tamaño de la hoja.
    const pdf = new jsPDF('p', 'mm', 'letter');
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
    
    pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
    pdf.save(`${props.employee.emp_code}_${months[selectedMonth.value - 1]}.pdf`);

  } catch (error) {
    console.error("Error al generar el PDF:", error);
    alert("Hubo un error al generar el PDF. Revisa la consola.");
  } finally {
    generandoPdf.value = false;
  }
};

/**
 * MÉTODO 2: Utiliza la función de impresión nativa del navegador.
 * El resultado es un PDF de alta calidad con texto seleccionable y capacidad multi-página.
 * La apariencia final depende del motor de renderizado de PDF del navegador.
 */
const printPDF = async () => {
    generandoPdf.value = true;
    await nextTick(); // Espera a que Vue renderice el componente TarjetaPdf.
    
    const printElement = document.getElementById('pdf-content');
    if (!printElement) {
        console.error("No se pudo encontrar el elemento para imprimir.");
        generandoPdf.value = false;
        return;
    }
    
    // Mueve temporalmente el elemento al body. Esto, junto con el CSS @media print,
    // asegura que solo este elemento sea visible para el diálogo de impresión.
    document.body.appendChild(printElement);
    
    window.print(); // Abre el diálogo de impresión del navegador.
    
    // El v-if se encargará de eliminar el elemento del DOM una vez que 'generandoPdf' sea false.
    // El timeout asegura que el componente no se oculte antes de que el navegador procese la impresión.
    setTimeout(() => {
        generandoPdf.value = false;
    }, 500);
};


// =================================================================================================
// HOOK DE CICLO DE VIDA
// =================================================================================================

// Llama a fetchSchedule() una vez que el componente se ha montado en el DOM,
// para realizar la carga inicial de datos.
onMounted(fetchSchedule);
</script>

<template>
  <div class="schedule-card">
    <div class="header">
      <img src="/images/logo_cdmx.jpeg" alt="Logo CDMX" class="logo">
      <div class="header-text">
          <h2 class="font-bold text-2xl text-gray-800">{{ employee.first_name }} {{ employee.last_name }}</h2>
          <p class="text-sm text-gray-500">Expediente: {{ employee.emp_code }} | Departamento: {{ employee.department_name }}</p>
      </div>
      <img src="/images/logo_mujer_indigena.jpeg" alt="Logo Mujer Indígena" class="logo">
    </div>

    <div class="controls">
      <select v-model="selectedMonth" class="control-input">
        <option v-for="(name, index) in months" :key="index" :value="index + 1">
          {{ name }}
        </option>
      </select>
      <input type="number" v-model="selectedYear" class="control-input year-input" />
      <button @click="fetchSchedule" class="control-button">Cargar Horario</button>
    </div>

    <div v-if="loading" class="loading-indicator">
      <p>Cargando horario...</p>
    </div>

    <div v-else-if="schedule.registros && schedule.registros.length > 0">
      <div class="schedule-table-visible">
        <h3 class="text-lg font-semibold text-gray-700">Horario: {{ schedule.horario }}</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Día</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Calificación</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="registro in schedule.registros" :key="registro.dia">
                <td class="text-center">{{ getDayFromDateString(registro.dia) }}</td>
                <td><textarea v-model="registro.checkin" @input="autoResizeTextarea" rows="1" class="editable-textarea"></textarea></td>
                <td><textarea v-model="registro.checkout" @input="autoResizeTextarea" rows="1" class="editable-textarea"></textarea></td>
                <td><textarea v-model="registro.calificacion" @input="autoResizeTextarea" rows="1" class="editable-textarea"></textarea></td>
                <td><textarea v-model="registro.observaciones" @input="autoResizeTextarea" rows="1" class="editable-textarea"></textarea></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="download-buttons">
        <button @click="generatePDF" :disabled="generandoPdf" class="download-button">
          {{ generandoPdf ? 'Generando...' : 'Descargar PDF (Rápido)' }}
        </button>
        <button @click="printPDF" :disabled="generandoPdf" class="print-button">
          {{ generandoPdf ? 'Generando...' : 'Imprimir / Guardar PDF' }}
        </button>
      </div>
      <p class="print-note">
        Si el contenido es muy largo, use la opción "Imprimir / Guardar PDF" para generar un documento con múltiples páginas.
      </p>
    </div>
    <div v-else class="no-records">
      <p>No se encontraron registros de asistencia para el mes y año seleccionados.</p>
    </div>
  </div>

  <TarjetaPdf 
    v-if="generandoPdf" 
    :employee="employee" 
    :schedule="schedule"
    :months="months"
    :first-fortnight="firstFortnight"
    :second-fortnight="secondFortnight"
    :days-in-month="daysInMonth"
    :selected-month="selectedMonth"
    :selected-year="selectedYear"
  />
</template>

<!-- ESTILOS GLOBALES PARA LA IMPRESIÓN (ROBUSTOS, NO LLEVAN 'SCOPED') -->
<style>
@media print {
  /* Oculta todo lo del body, EXCEPTO el elemento que queremos imprimir */
  body > *:not(#pdf-content) {
    display: none !important;
  }
  
  /* Posiciona nuestro elemento para que ocupe toda la página */
  #pdf-content {
    display: block !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
  }

  /* Define el tamaño de la hoja de impresión */
  @page {
    size: letter;
    margin: 10mm;
  }
}
</style>


<style scoped>
/* NOTE: The .page-container class was removed as this component
   is now intended to be displayed within the parent's layout. */
.schedule-card {
  max-width: 1000px;
  margin: 0 auto;
  padding: 32px;
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-align: center;
  margin-bottom: 24px;
}
.header .logo {
    height: 90px; 
    width: auto;
}
.header .header-text {
    flex-grow: 1;
}
.controls {
  display: flex;
  justify-content: center;
  gap: 12px;
  background-color: #f9fafb;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}
.control-input {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background-color: white;
}
.year-input {
    width: 100px; /* Ancho fijo para el campo de año */
}
.control-button {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    background-color: #4f46e5;
    color: white;
    border: none;
    border-radius: 6px;
    transition: background-color 0.2s;
}
.control-button:hover {
    background-color: #4338ca;
}
.schedule-table-visible {
  margin-top: 24px;
}
.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-top: 16px;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}
th {
    background-color: #f9fafb;
    font-weight: 600;
    color: #374151;
    text-align: center;
}
td {
    color: #4b5563;
    vertical-align: top; /* Alinea el contenido al inicio de la celda */
}
.text-center {
    text-align: center;
}
tr:last-child td {
    border-bottom: none;
}
.editable-textarea {
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 4px;
  resize: none; /* Oculta el manejador de tamaño */
  overflow: hidden; /* Oculta la barra de scroll */
  box-sizing: border-box;
}
.editable-textarea:focus {
    outline: 1px solid #c7d2fe;
    background-color: #f0f5ff;
}
.download-buttons {
  display: flex;
  gap: 16px;
  justify-content: center;
  margin-top: 24px;
}
.download-button, .print-button {
  padding: 12px 24px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  border-radius: 8px;
  transition: background-color 0.2s;
}
.download-button {
  background-color: #16a34a;
  color: white;
}
.print-button {
  background-color: #5b21b6;
  color: white;
}
.download-button:disabled, .print-button:disabled {
  background-color: #9ca3af;
  cursor: not-allowed;
}
.download-button:hover:not(:disabled) {
  background-color: #15803d;
}
.print-button:hover:not(:disabled) {
  background-color: #4c1d95;
}
.no-records {
    background-color: #fef2f2;
    color: #991b1b;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #fecaca;
}
.print-note {
    text-align: center;
    margin-top: 16px;
    font-size: 12px;
    color: #6b7280;
}
</style>