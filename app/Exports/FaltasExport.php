<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class FaltasExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $faltas;
    protected $start;
    protected $end;

    public function __construct(array $faltas, $start, $end)
    {
        $this->faltas = $faltas;
        $this->start = $start;
        $this->end = $end;
    }

    public function array(): array
    {
        return $this->faltas;
    }

    public function headings(): array
    {
        return [
            ['REPORTE DETALLADO DE FALTAS ACUMULADAS'],
            ['Periodo:', $this->start . ' al ' . $this->end],
            [''],
            ['Nómina', 'Nombre del Empleado', 'Fecha de la Falta', 'Horario Asignado', 'Observaciones']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Combinar celdas del título
        $sheet->mergeCells('A1:E1');
        
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF991B1B']], // Rojo Oscuro
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
        ];
    }
}