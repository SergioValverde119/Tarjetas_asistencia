<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class EmpleadoIndividualExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $datos;
    protected $empleado;
    protected $fechaInicio;

    // Ahora recibimos la fecha de inicio para reconstruir el calendario
    public function __construct($datos, $empleado, $fechaInicio)
    {
        $this->datos = $datos;
        $this->empleado = $empleado;
        $this->fechaInicio = Carbon::parse($fechaInicio);
    }

    public function collection()
    {
        $coleccion = collect();

        // Encabezados informativos
        $coleccion->push(['REPORTE INDIVIDUAL DE ASISTENCIA POR RANGO']);
        $coleccion->push(['Empleado:', $this->empleado->first_name . ' ' . $this->empleado->last_name]);
        $coleccion->push(['ID:', $this->empleado->emp_code]);
        $coleccion->push(['Nómina:', $this->empleado->nomina ?? 'Sin clasificar']);
        $coleccion->push(['Horario Actual:', $this->empleado->turno_actual ?? 'Sin Horario Asignado']);
        $coleccion->push(['']); 

        // Encabezados de tabla
        $coleccion->push(['Día', 'Fecha', 'Entrada', 'Salida', 'Estatus', 'Observaciones']);

        // Iteramos las incidencias
        foreach ($this->datos['incidencias_diarias'] as $dia => $info) {
            // Reconstruimos la fecha real sumando el día al mes de inicio
            // Esto evita el error de "ano" o "mes" no definido
            $fechaReal = $this->fechaInicio->copy()->day($dia)->format('d/m/Y');

            $coleccion->push([
                $dia,
                $fechaReal,
                $info['checkin'] ?: '---',
                $info['checkout'] ?: '---',
                $info['calificacion'],
                $info['observaciones']
            ]);
        }

        return $coleccion;
    }

    public function headings(): array { return []; }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            7 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFE0E0E0']]],
        ];
    }
}