<script setup>
// =================================================================================================
// IMPORTS
// =================================================================================================
import { computed } from 'vue';
import EncabezadoPdf from './EncabezadoPdf.vue'; // Componente modular para el encabezado del documento.
import PieDePaginaPdf from './PieDePaginaPdf.vue'; // Componente modular para el pie de página del documento.

// =================================================================================================
// PROPS (Propiedades)
// =================================================================================================
/**
 * @component TarjetaPdf
 * @description Componente de presentación puro (dumb component) para el renderizado visual de la tarjeta de asistencia.
 * Es el target de los métodos de generación de PDF (html2canvas, window.print).
 */
const props = defineProps({
  /** Objeto: Datos del empleado (emp_code, first_name, last_name, department_name). */
  employee: Object,
  /** Objeto: Respuesta de la API (horario, registros[]). */
  schedule: Object,
  /** Array: Nombres de los meses. */
  months: Array,
  /** Array: Registros filtrados para los días 1-15. */
  firstFortnight: Array,
  /** Array: Registros filtrados para los días 16-fin de mes. */
  secondFortnight: Array,
  /** Number: Total de días en el mes seleccionado. */
  daysInMonth: Number,
  /** Number: Mes seleccionado (1-12). */
  selectedMonth: Number,
  /** Number: Año seleccionado (ej. 2024). */
  selectedYear: Number
});

// =================================================================================================
// FUNCIONES DE AYUDA (Helpers)
// =================================================================================================

/**
 * Parsea un string 'YYYY-MM-DD' y retorna el día con padding '00'.
 * @param {string} dateString - La fecha de entrada.
 * @returns {string} - El día del mes (ej. '01').
 */
const getDayFromDateString = (dateString) => {
  if (!dateString || typeof dateString !== 'string') return '';
  const parts = dateString.split('-');
  return String(parseInt(parts[2], 10)).padStart(2, '0');
};

/**
 * Formatea un string 'HH:MM:SS' a 'HH:MM'.
 * @param {string} timeString - La hora de entrada.
 * @returns {string} - La hora formateada ('HH:MM') o un string vacío.
 */
const formatTimeWithoutSeconds = (timeString) => {
 
 // if (!timeString || typeof timeString !== 'string') return '';
 // const parts = timeString.split(':');
 // if (parts.length >= 2) {
 //   return `${parts[0]}:${parts[1]}`;
 // }
  return timeString;
};

// =================================================================================================
// PROPIEDADES COMPUTADAS
// =================================================================================================

/**
 * Calcula el número ordinal de la quincena en el año (ej. 01, 02... 24).
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
 * Formatea un número de día a un string de fecha 'DD/MM/YYYY'.
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
  <!-- 
    Contenedor raíz para la generación del PDF.
    Posicionado off-screen para renderizado en el DOM sin ser visible.
    Target para html2canvas y window.print().
  -->
  <div id="pdf-content" style="position: absolute; left: -9999px; background-color: white;">
    <!-- Contenedor principal que simula las dimensiones de la hoja (ver CSS). -->
    <div class="pdf-card">
      <EncabezadoPdf />

      <!-- Contenedor Flex para el layout de dos columnas de quincenas. -->
      <div class="fortnights-container">
        
        <!-- Contenedor de la Primera Quincena + Firma (Nuevo) -->
        <div class="fortnight-wrapper">
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
                <thead class="names-columns">
                  <tr>
                    <th class="col-dia">Día</th>
                    <th class="col-hora">Entrada</th>
                    <th class="col-hora">Salida</th>
                    <th class="col-calif">Calif</th>
                    <th class="col-obs">Observaciones</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Itera sobre los registros de la primera quincena. -->
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
          </div>
          <!-- Área de firma primer tarjeta -->
          <div class="signature-block-bottom">
            <p>{{ employee.first_name }} {{ employee.last_name }}</p>
          </div>
        </div>

        <!-- Contenedor de la Segunda Quincena  -->
        <div class="fortnight-wrapper">
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
                <thead class="names-columns">
                  <tr>
                    <th class="col-dia">Día</th>
                    <th class="col-hora">Entrada</th>
                    <th class="col-hora">Salida</th>
                    <th class="col-calif">Calif</th>
                    <th class="col-obs">Observaciones</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Itera sobre los registros de la segunda quincena. -->
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
          </div>
          <!-- Área de firma segunda tarjeta -->
          <div class="signature-block-bottom">
            <p>.</p>
          </div>
        </div>
      </div>

      <PieDePaginaPdf :year="selectedYear"/>
    </div>
  </div>
</template>

<style scoped>
/*
 * Define las dimensiones físicas de la hoja tamaño CARTA.
 * Requerido para que la captura de html2canvas tenga las proporciones correctas.
 * `position: relative` es el ancla para el pie de página (`position: absolute`).
*/
#pdf-content {
  width: 215.9mm;
  height: 279.4mm;
  padding: 10mm;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
  color: #000;
  position: relative;
  background-color: white;
  border: 1px solid #ccc; /* Borde de debug para la vista de /test-tarjeta */
  margin: 20px auto; /* Centrado para la vista de /test-tarjeta */
}

/*
 * Contenedor flex principal. Organiza verticalmente [header, body, footer].
 * `height: 100%` es requerido para que el `flex-grow` del body y el `position: absolute` del footer funcionen.
*/
.pdf-card {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Contenedor de las dos columnas de quincena. */
.fortnights-container {
  display: flex;
  justify-content: space-between;
  gap: 5px;
  margin-top: 10px;
}

/*
 * Contenedor que agrupa la tarjeta de quincena y el bloque de firma debajo. 
*/
.fortnight-wrapper {
  width: 50%;
  display: flex;
  flex-direction: column; 
  gap: 5px;
}

/*
 * Contenedor individual de cada tarjeta de quincena.
 * Utiliza flex-direction: column para organizar verticalmente [header, body].
*/
.fortnight-card {
  border: 1px solid #000;
  border-radius: 8px;
  overflow: hidden;
  font-size: 8.5pt;
  display: flex;
  flex-direction: column;
  flex-grow: 1; /* Permite que las tarjetas crezcan hasta la misma altura. */
}

/* Encabezado gris de la tarjeta. */
.fortnight-header {
  background-color: #51575c;
  color: white;
  font-weight: bold;
  padding: 6px 10px;
  font-size: 10pt;
}

/*
 * Cuerpo principal de la tarjeta.
 * `flex-grow: 1` asegura que ocupe el espacio disponible.
*/
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

/* Estilos de la tabla de registros. */
.schedule-table-pdf {
  width: 100%;
  border-collapse: collapse;
  margin-top: 6px;
  font-size: 8.5pt;
  /* `table-layout: fixed` previene que el ancho de las columnas se altere por el contenido. */
  table-layout: fixed; 
}

.schedule-table-pdf .names-columns{

  font-size: 8pt;
  background-color: white;

}

.schedule-table-pdf th,
.schedule-table-pdf td {
  border: 1px solid #2c2c2c; /* Borde de cuadrícula completa. */
  padding: 4px;
  text-align: center;
  line-height: 1.3;  /*Interlineado dentro de las celdas. */
  /* `overflow-wrap: break-word` fuerza el salto de línea en texto largo. */
  overflow-wrap: break-word;
}

/* Anchos de columna fijos para optimizar el layout. */
.schedule-table-pdf .col-dia { width: 9%; }
.schedule-table-pdf .col-hora { width: 17%; }
.schedule-table-pdf .col-calif { width: 15%; }
.schedule-table-pdf .col-obs { width: 52%; }

.schedule-table-pdf th {
  font-weight: bold;
}

/* Alineación especial para la celda de observaciones. */
.schedule-table-pdf td.obs {
  text-align: left;
}

/* Bloque de firma que va debajo de la tarjeta */
.signature-block-bottom {
  padding: 90px 0;
  text-align: center;
}

.signature-block-bottom p {
  border-top: 1px solid black;
  margin: 0 auto;
  padding-top: 5px;
  text-align: center;
  font-size: 9pt;
  width: 70%;
}
</style>