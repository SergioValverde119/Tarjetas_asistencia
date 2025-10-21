<script setup>
// =================================================================================================
// IMPORTS
// =================================================================================================
import { computed } from 'vue';
import EncabezadoPdf from './EncabezadoPdf.vue'; // Componente para el encabezado del documento.
import PieDePaginaPdf from './PieDePaginaPdf.vue'; // Componente para el pie de página del documento.

// =================================================================================================
// PROPS
// =================================================================================================
/**
 * @component TarjetaPdf
 * @description Componente de presentación que renderiza la estructura completa de la tarjeta de asistencia.
 * Recibe todos los datos necesarios como props y los formatea para su correcta visualización.
 * Este componente está optimizado tanto para la generación de PDF vía html2canvas como para la impresión nativa.
 */
const props = defineProps({
  employee: Object,       // Objeto con los datos del empleado.
  schedule: Object,       // Objeto con los datos del horario, incluyendo los registros.
  months: Array,          // Array con los nombres de los meses.
  firstFortnight: Array,  // Array de registros filtrados para la primera quincena.
  secondFortnight: Array, // Array de registros filtrados para la segunda quincena.
  daysInMonth: Number,    // Número de días en el mes seleccionado.
  selectedMonth: Number,  // El mes seleccionado (numérico, 1-12).
  selectedYear: Number    // El año seleccionado.
});

// =================================================================================================
// FUNCIONES DE AYUDA (Helpers)
// =================================================================================================

/**
 * Extrae y formatea el día de un string de fecha 'YYYY-MM-DD'.
 * @param {string} dateString - La fecha de entrada.
 * @returns {string} - El día del mes, con padding (ej. '01').
 */
const getDayFromDateString = (dateString) => {
  if (!dateString || typeof dateString !== 'string') return '';
  const parts = dateString.split('-');
  return String(parseInt(parts[2], 10)).padStart(2, '0');
};

/**
 * Recorta los segundos de un string de hora 'HH:MM:SS'.
 * @param {string} timeString - La hora de entrada.
 * @returns {string} - La hora formateada como 'HH:MM'.
 */
const formatTimeWithoutSeconds = (timeString) => {
  if (!timeString || typeof timeString !== 'string') return '';
  const parts = timeString.split(':');
  if (parts.length >= 2) {
    return `${parts[0]}:${parts[1]}`;
  }
  return timeString;
};

// =================================================================================================
// PROPIEDADES COMPUTADAS
// =================================================================================================

/**
 * Calcula el número ordinal de la quincena en el año.
 * @returns {{first: string, second: string}} - Los números de la primera y segunda quincena del mes.
 */
const fortnightNumber = computed(() => {
  if (!props.selectedMonth) return { first: '', second: '' };
  const first = (props.selectedMonth * 2) - 1;
  const second = props.selectedMonth * 2;
  return {
    first: String(first).padStart(2, '0'),
    second: String(second).padStart(2, '0')
  };
});

/**
 * Formatea un número de día a un string de fecha completo 'DD/MM/YYYY'.
 * @param {number} day - El número del día.
 * @returns {string} - La fecha formateada.
 */
const formatDate = (day) => {
  if (!day || !props.selectedMonth || !props.selectedYear) return '';
  const paddedDay = String(day).padStart(2, '0');
  const paddedMonth = String(props.selectedMonth).padStart(2, '0');
  return `${paddedDay}/${paddedMonth}/${props.selectedYear}`;
};

</script>

<template>
  <!-- Contenedor raíz para la generación del PDF. Oculto por defecto. -->
  <div id="pdf-content" style="position: absolute; left: -9999px; background-color: white; color: #000000;">
    <!-- Contenedor principal que simula la hoja tamaño carta. -->
    <div class="pdf-card">
      <EncabezadoPdf />

      <!-- Contenedor Flex para las dos columnas de quincenas. -->
      <div class="fortnights-container">
        
        <!-- Tarjeta de la Primera Quincena -->
        <div class="fortnight-card">
          <div class="fortnight-header">DATOS DEL TRABAJADOR</div>
          <div class="fortnight-body">
            <p><strong>Expediente:</strong> {{ employee.emp_code }}</p>
            <p><strong>Nombre:</strong> {{ employee.first_name }} {{ employee.last_name }}</p>
            <p><strong>Departamento:</strong> {{ employee.department_name }}</p>
            <p><strong>Horario:</strong> {{ schedule.horario }}</p>
            <p><strong>Quincena:</strong> {{ fortnightNumber.first }} del {{ selectedYear }} del {{ formatDate(1) }} al {{ formatDate(15) }}</p>
            
            <table class="schedule-table-pdf">
              <thead>
                <tr>
                  <th class="col-dia">Día</th>
                  <th class="col-hora">Entrada</th>
                  <th class="col-hora">Salida</th>
                  <th class="col-calif">Calif</th>
                  <th class="col-obs">Observaciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="registro in firstFortnight" :key="`fn1-${registro.dia}`">
                  <td>{{ getDayFromDateString(registro.dia) }}</td>
                  <td>{{ formatTimeWithoutSeconds(registro.checkin) }}</td>
                  <td>{{ formatTimeWithoutSeconds(registro.checkout) }}</td>
                  <td>{{ registro.calificacion }}</td>
                  <td class="obs">{{ registro.observaciones }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="signature-area">
            <p>{{ employee.first_name }} {{ employee.last_name }}</p>
          </div>
        </div>

        <!-- Tarjeta de la Segunda Quincena -->
        <div class="fortnight-card">
          <div class="fortnight-header">DATOS DEL TRABAJADOR</div>
          <div class="fortnight-body">
            <p><strong>Expediente:</strong> {{ employee.emp_code }}</p>
            <p><strong>Nombre:</strong> {{ employee.first_name }} {{ employee.last_name }}</p>
            <p><strong>Departamento:</strong> {{ employee.department_name }}</p>
            <p><strong>Horario:</strong> {{ schedule.horario }}</p>
            <p><strong>Quincena:</strong> {{ fortnightNumber.second }} del {{ selectedYear }} del {{ formatDate(16) }} al {{ formatDate(daysInMonth) }}</p>

            <table class="schedule-table-pdf">
              <thead>
                <tr>
                  <th class="col-dia">Día</th>
                  <th class="col-hora">Entrada</th>
                  <th class="col-hora">Salida</th>
                  <th class="col-calif">Calif</th>
                  <th class="col-obs">Observaciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="registro in secondFortnight" :key="`fn2-${registro.dia}`">
                  <td>{{ getDayFromDateString(registro.dia) }}</td>
                  <td>{{ formatTimeWithoutSeconds(registro.checkin) }}</td>
                  <td>{{ formatTimeWithoutSeconds(registro.checkout) }}</td>
                  <td>{{ registro.calificacion }}</td>
                  <td class="obs">{{ registro.observaciones }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="signature-area">
            <p>{{ employee.first_name }} {{ employee.last_name }}</p>
          </div>
        </div>
      </div>

      <PieDePaginaPdf />
    </div>
  </div>
</template>

<style scoped>
/* Define las dimensiones y el layout base del contenedor de la tarjeta del PDF. */
#pdf-content {
  width: 215.9mm; /* Ancho de hoja tamaño CARTA */
  height: 279.4mm; /* Alto de hoja tamaño CARTA */
  padding: 10mm;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
  color: #000;
  position: relative;
  background-color: white;
  border: 1px solid #ccc; /* Borde visible para facilitar el diseño en la vista de prueba. */
  margin: 20px auto; /* Centra la hoja en la página de prueba. */
}

/* Contenedor interno que utiliza Flexbox para distribuir verticalmente el encabezado, cuerpo y pie de página. */
.pdf-card {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Contenedor para las dos tarjetas de quincena, alineadas horizontalmente. */
.fortnights-container {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-top: 20px;
}

/* Estilo individual para cada tarjeta de quincena. */
.fortnight-card {
  width: 49%;
  border: 1px solid #000;
  border-radius: 8px;
  overflow: hidden; /* Asegura que el border-radius se aplique al contenido interno. */
  font-size: 8pt;
  display: flex;
  flex-direction: column; /* Apila el header, body y signature verticalmente. */
}

/* Encabezado gris de cada tarjeta de quincena. */
.fortnight-header {
  background-color: #6c757d;
  color: white;
  font-weight: bold;
  padding: 6px 10px;
  font-size: 10pt;
}

/* Cuerpo principal de la tarjeta. `flex-grow` es clave para empujar la firma hacia abajo. */
.fortnight-body {
  padding: 10px;
  background-color: white;
  flex-grow: 1;
}

.fortnight-body p {
  margin: 0 0 5px 0;
}
.fortnight-body p:last-child {
  margin-bottom: 0;
}

/* Estilos para la tabla de registros. */
.schedule-table-pdf {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  font-size: 7.5pt;
  table-layout: fixed; /* Previene que el contenido de las celdas altere el ancho de las columnas. */
}
.schedule-table-pdf th,
.schedule-table-pdf td {
  border-bottom: 1px solid #d3d3d3;
  padding: 4px;
  text-align: center;
  overflow-wrap: break-word; /* Permite que el texto largo salte a la siguiente línea. */
}

/* Definición de anchos de columna para optimizar el espacio. */
.schedule-table-pdf .col-dia { width: 8%; }
.schedule-table-pdf .col-hora { width: 15%; }
.schedule-table-pdf .col-calif { width: 31%; }
.schedule-table-pdf .col-obs { width: 31%; }

.schedule-table-pdf th {
  font-weight: bold;
  border-bottom-width: 2px;
}

/* Alinea el texto de las observaciones a la izquierda para mejor legibilidad. */
.schedule-table-pdf td.obs {
  text-align: left;
}

/* Elimina el borde inferior de la última fila de la tabla. */
.schedule-table-pdf tr:last-child td {
  border-bottom: none;
}

/* Contenedor para la línea y el nombre de la firma. */
.signature-area {
  padding: 20px 10px;
}
.signature-area p {
  border-top: 1px solid black;
  margin: 0 auto;
  padding-top: 5px;
  text-align: center;
  font-size: 8pt;
  width: 70%;
}
</style>