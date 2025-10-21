<script setup>
/**
 * @component EncabezadoPdf
 * @description Componente de presentación para el encabezado del documento PDF.
 * Su responsabilidad es únicamente el renderizado estático del layout y contenido del encabezado.
 */
</script>

<template>
  <!-- Contenedor principal del encabezado, utiliza Flexbox para la distribución de elementos. -->
  <div class="pdf-page-header">
    
    <!-- Sección izquierda: Contiene el logo principal de la entidad. -->
    <div class="header-left">
      <img src="/images/logo_cdmx.jpeg" alt="Logo CDMX" class="logo-cdmx">
    </div>

    <!-- Sección derecha: Contiene el bloque de texto con la información institucional. -->
    <div class="header-right">
      <div class="header-text-block">
        <p class="titulo-negritas">SECRETARÍA DE PLANEACIÓN, ORDENAMIENTO TERRITORIAL Y<br>COORDINACIÓN METROPOLITANA</p>
        <p>DIRECCIÓN GENERAL DE ADMINISTRACIÓN Y FINANZAS</p>
        <p>SUBDIRECCIÓN DE ADMINISTRACIÓN DE CAPITAL HUMANO</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/**
 * Estilos base del componente, optimizados para dos contextos:
 * 1. Renderizado en pantalla (para la generación de PDF vía html2canvas).
 * 2. Renderizado en el diálogo de impresión del navegador.
*/

.pdf-page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  /* Propiedad no estándar para forzar la impresión de colores/imágenes en navegadores WebKit. */
  print-color-adjust: exact;
}

/* Define el tamaño del logo para la vista por defecto (en pantalla). */
.pdf-page-header .header-left .logo-cdmx {
  width: 250px;
}

/* Define el layout del contenedor derecho. */
.pdf-page-header .header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end; /* Alinea sus hijos (el bloque de texto) a la derecha. */
  width: 65%;
}

/* Define los estilos del bloque de texto. */
.pdf-page-header .header-text-block {
  text-align: left; /* Alinea el texto dentro de este bloque a la izquierda. */
  font-size: 7.5pt;
  margin-bottom: 10px;
}

/* Estilo para el párrafo del título principal. */
.pdf-page-header .header-text-block .titulo-negritas {
  font-weight: bold;
}

.pdf-page-header .header-text-block p {
  margin: 0;
  margin-bottom: 4px;
}


/**
 * Media Query para sobrescribir estilos únicamente en el contexto de impresión (`window.print()`).
*/
@media print {
  /* Reduce el tamaño del logo para un mejor ajuste en la hoja impresa. */
  .pdf-page-header .header-left .logo-cdmx {
    width: 250px; 
  }

  /**
   * Directiva para forzar la renderización de imágenes al imprimir.
   * Navegadores como Chrome pueden ocultar imágenes por defecto para ahorrar tinta.
   * `!important` se utiliza para garantizar la prioridad sobre otros estilos del navegador.
  */
  img {
    visibility: visible !important;
    display: block !important;
  }
}
</style>