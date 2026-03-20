<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IncidenciasEstadisticasExport implements WithMultipleSheets
{
    protected $data;
    protected $start;
    protected $end;

    public function __construct(array $data, $start, $end)
    {
        $this->data  = $data; // Contiene 'resumen_categorias' y 'listado_detallado'
        $this->start = $start;
        $this->end   = $end;
    }

    /**
     * Define las pestañas del Excel
     */
    public function sheets(): array
    {
        return [
            // Pestaña 1: Resumen nivel 2
            new \App\Exports\Sheets\IncidenciasResumenCategoriasSheet($this->data['resumen_categorias'], $this->start, $this->end),
            // Pestaña 2: Listado nivel 3
            new \App\Exports\Sheets\IncidenciasDetalleCompletoSheet($this->data['listado_detallado'], $this->start, $this->end),
        ];
    }
}