<script setup>
/**
 * @component PieDePaginaPdf
 * @description Componente de presentación puro para el pie de página del documento PDF.
 * Responsable únicamente del renderizado estático de la información de contacto y logos.
 * No contiene lógica de negocio.
 */
</script>

<template>
  <!-- Contenedor principal del pie de página, estructurado con Flexbox. -->
  <div class="pdf-page-footer">
    <!-- Contenedor para el bloque de texto izquierdo (información de contacto). -->
    <div class="footer-left">
      <p>
        Amores 1322, Del Valle Centro,<br>
        Benito Juárez, 03100, Ciudad de<br>
        México<br>
        Tel.55513002100 EXT 2159, 2133
      </p>
    </div>
    <!-- Contenedor para el logo derecho. -->
    <div class="footer-right">
      <img src="/images/logo_mujer_indigena.jpeg" alt="Ciudad Innovadora" class="logo-footer">
    </div>
  </div>
</template>

<style scoped>
/**
 * Estilo base del componente, optimizado para dos casos de uso:
 * 1. Renderizado en pantalla para la generación de PDF vía html2canvas.
 * 2. Renderizado en el diálogo de impresión del navegador.
*/

/*
 * Estilo por defecto (para pantalla y html2canvas).
 * Se posiciona de forma absoluta en la parte inferior del contenedor padre (#pdf-content),
 * que debe tener `position: relative`. Esto simula un pie de página fijo.
*/
.pdf-page-footer {
  position: absolute;
  bottom: 10mm;
  left: 10mm;
  right: 10mm;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  font-size: 8pt;
  /* Propiedad no estándar para forzar la impresión de colores/imágenes en navegadores WebKit. */
  print-color-adjust: exact;
}

.pdf-page-footer .footer-left {
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  color: #333;
  line-height: 1.4;
  text-align: left;
}

.pdf-page-footer p {
    margin: 0;
}

.pdf-page-footer .logo-footer {
    width: 200px;
}

/**
 * Sobrescribe los estilos base exclusivamente para el contexto de impresión (`window.print()`).
 * Estas reglas se activan cuando el navegador genera la vista previa de impresión.
*/
@media print {
  /**
   * Se cambia el posicionamiento a 'relative' para que el pie de página deje de estar
   * anclado a la parte inferior de la página y en su lugar fluya como parte del
   * contenido normal. Esto previene que el contenido de las tablas se renderice por detrás
   * del pie de página en casos de desbordamiento (overflow).
  */
  .pdf-page-footer {
    position: relative; 
    bottom: auto;
    left: auto;
    right: auto;
    margin-top: 20px;
    /* Sugiere al navegador que evite un salto de página dentro de este elemento. */
    page-break-inside: avoid;
  }
  
  /**
   * Directiva para forzar la renderización de imágenes en el modo de impresión.
   * Navegadores como Chrome pueden ocultar imágenes por defecto para ahorrar tinta.
   * `!important` se utiliza para garantizar la prioridad sobre otros posibles estilos.
  */
  img {
    visibility: visible !important;
    display: block !important;
  }
}
</style>