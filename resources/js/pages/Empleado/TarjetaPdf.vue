<script setup>
// =================================================================================================
// IMPORTS
// =================================================================================================
import { computed } from 'vue';
import EncabezadoPdf from '@/pages/Tarjeta/EncabezadoPdf.vue'; 
import PieDePaginaPdf from '@/pages/Tarjeta/PieDePaginaPdf.vue'; // Componente modular para el pie de página del documento.

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

const monthName = computed(() => {
    return props.months ? (props.months[props.selectedMonth - 1] || '---') : '---';
});

// =================================================================================================
// CONTADORES DE INCIDENCIAS (Faltas, Justificaciones, Días Normales)
// =================================================================================================

/**
 * Unifica todos los registros del mes en un solo arreglo para facilitar el conteo.
 */
const allRecords = computed(() => {
  const first = props.firstFortnight || [];
  const second = props.secondFortnight || [];
  return [...first, ...second];
});

/**
 * Cuenta los días con calificación "OK"
 */
const totalOK = computed(() => {
  return allRecords.value.filter(r => r.calificacion && r.calificacion.trim().toUpperCase() === 'OK').length;
});

/**
 * Cuenta los días con calificación "F" (Faltas)
 */
const totalFaltas = computed(() => {
  return allRecords.value.filter(r => r.calificacion && r.calificacion.trim().toUpperCase() === 'F').length;
});

/**
 * Cuenta los días con calificación "J" (Justificaciones)
 */
const totalJustificaciones = computed(() => {
  return allRecords.value.filter(r => r.calificacion && r.calificacion.trim().toUpperCase() === 'J').length;
});

/**
 * Cuenta los días con calificación "RL" (Retardos Leves)
 */
const totalRL = computed(() => {
  return allRecords.value.filter(r => r.calificacion && r.calificacion.trim().toUpperCase() === 'RL').length;
});

/**
 * Cuenta los días con calificación "RG" (Retardos Graves)
 */
const totalRG = computed(() => {
  return allRecords.value.filter(r => r.calificacion && r.calificacion.trim().toUpperCase() === 'RG').length;
});

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

      <!-- TÍTULO OPTIMIZADO: Fusionado en una sola línea para ahorrar espacio -->
      <div class="kardex-header">
        <h2>KÁRDEX DE ASISTENCIA MENSUAL | PERIODO: {{ monthName.toUpperCase() }} {{ selectedYear }}</h2>
      </div>

      <!-- ÚNICO RECUADRO DE DATOS DEL EMPLEADO Y TOTALES -->
      <div class="employee-info-box">
        <!-- Fila de datos del empleado -->
        <div class="info-row">
          <div class="info-item">
            <strong>Número de empleado:</strong> {{ employee.emp_code }}
          </div>
          <div class="info-item">
            <strong>Nombre:</strong> {{ employee.first_name }} {{ employee.last_name }}
          </div>
          <div class="info-item">
            <strong>Horario:</strong> {{ schedule.horario }}
          </div>
        </div>
        
        <!-- Fila de totales del mes -->
        <div class="info-row summary-row">
          <div class="info-item">
            <strong>RESUMEN:</strong>
          </div>
          <div class="info-item">
            <strong>Asistencias:</strong> {{ totalOK }}
          </div>
          <div class="info-item">
            <strong>Faltas:</strong> {{ totalFaltas }}
          </div>
          <div class="info-item">
            <strong>Justificaciones:</strong> {{ totalJustificaciones }}
          </div>
          <div class="info-item">
            <strong>Retardos Leves:</strong> {{ totalRL }}
          </div>
          <div class="info-item">
            <strong>Retardos Graves:</strong> {{ totalRG }}
          </div>
        </div>
      </div>

      <!-- Contenedor Flex para las dos cuadrículas de asistencia. -->
      <div class="fortnights-container">
        
        <!-- Primera Quincena -->
        <div class="fortnight-wrapper">
          <p class="fortnight-subtitle"><strong>Quincena:</strong> {{ fortnightNumber.first }} ({{ formatDate(1) }} al {{ formatDate(15) }})</p>
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

        <!-- Segunda Quincena -->
        <div class="fortnight-wrapper">
          <p class="fortnight-subtitle"><strong>Quincena:</strong> {{ fortnightNumber.second }} ({{ formatDate(16) }} al {{ formatDate(daysInMonth) }})</p>
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

      <PieDePaginaPdf :year="selectedYear"/>
    </div>
  </div>
</template>

<style scoped>
/*
 * =========================================================================================
 * FIX PARA HTML2CANVAS
 * =========================================================================================
*/
*, *::before, *::after {
  border-color: transparent;
  outline-color: transparent;
}

/*
 * Define las dimensiones físicas de la hoja tamaño CARTA.
*/
#pdf-content {
  width: 215.9mm;
  height: 279.4mm;
  padding: 5mm;
  box-sizing: border-box;
  font-family: Arial, Helvetica, sans-serif;
  color: #000000;
  position: relative;
  background-color: #ffffff;
  border: 1px solid #ccc; /* Borde de debug */
  margin: 20px auto;
  z-index: 1;
}

/* MARCA DE AGUA AGRANDADA */
#pdf-content::before {
  content: 'SUBDIRECCIÓN DE ADMINISTRACIÓN \A DE CAPITAL HUMANO';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
  font-size: 55pt; /* Agrandado significativamente */
  color: rgba(0, 0, 0, 0.04);
  font-family: Arial, Helvetica, sans-serif;
  font-weight: bold;
  letter-spacing: 4px;
  line-height: 1.2;
  text-align: center;
  white-space: pre-wrap;
  z-index: -1;
  pointer-events: none;
  width: 120%; /* Asegura que no se corte por el ancho al estar rotada */
}

.pdf-card {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* TÍTULO PRINCIPAL */
.kardex-header {
  text-align: center;
  margin: 5px 0 10px 0;
  border-bottom: 2px solid #000000;
  padding-bottom: 5px;
}

.kardex-header h2 {
  margin: 0;
  font-size: 11pt;
  font-weight: bold;
  text-transform: uppercase;
  color: #000000;
}

/* RECUADRO ÚNICO DE DATOS DEL EMPLEADO Y TOTALES */
.employee-info-box {
  border: 1px solid #000000;
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
  font-size: 9pt;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 10px;
}

/* Fila de resumen de totales separada por una línea */
.summary-row {
  border-top: 1px solid #000000;
  flex-wrap: wrap; /* Permite que los elementos pasen al siguiente renglón si no caben */
  row-gap: 6px;
}

.info-item {
  margin: 0;
}

/* CONTENEDOR DE CUADRÍCULAS */
.fortnights-container {
  display: flex;
  justify-content: space-between;
  gap: 4px; /* Unos cuantos pixeles de separación */
  flex-grow: 1;
}

.fortnight-wrapper {
  width: calc(50% - 2px); /* Ajustado para compensar el gap de 4px */
  display: flex;
  flex-direction: column; 
}

/* TEXTO DE LA QUINCENA ARRIBA DE LA TABLA */
.fortnight-subtitle {
  font-size: 8.5pt;
  margin: 0 0 4px 0;
  text-align: left;
}

/* ESTILOS DE LA TABLA DE REGISTROS */
.schedule-table-pdf {
  width: 100%;
  border-collapse: collapse;
  font-size: 8.5pt;
  table-layout: fixed; 
}

.schedule-table-pdf .names-columns th {
  font-size: 8pt;
  background-color: #ffffff;
  color: #000000;
  border-bottom: 2px solid #000000;
}

.schedule-table-pdf th,
.schedule-table-pdf td {
  border: 1px solid #000000;
  padding: 4px;
  text-align: center;
  line-height: 1.3;
  overflow-wrap: break-word;
}

.schedule-table-pdf .col-dia { width: 9%; }
.schedule-table-pdf .col-hora { width: 17%; }
.schedule-table-pdf .col-calif { width: 15%; }
.schedule-table-pdf .col-obs { width: 52%; }

.schedule-table-pdf th {
  font-weight: bold;
}

/* Destacar la columna de "Día" con fondo gris claro y negrita */
.schedule-table-pdf .names-columns th.col-dia,
.schedule-table-pdf td:first-child {
  background-color: #e5e5e5;
  font-weight: bold;
}

.schedule-table-pdf td.obs {
  text-align: left;
}
</style>