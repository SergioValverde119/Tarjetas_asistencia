<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class IncidenciasResumenCategoriasSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected $rows;
    protected $start;
    protected $end;

    public function __construct(array $rows, $start, $end)
    {
        $this->rows = $rows;
        $this->start = $start;
        $this->end = $end;
    }

    public function array(): array { return $this->rows; }

    public function title(): string { return '1. Resumen por Permiso'; }

    public function headings(): array
    {
        return [
            ['REPORTE NIVEL 2: RESUMEN DE PERMISOS POR CATEGORÍA'],
            ["Periodo: {$this->start} al {$this->end}"],
            [''],
            ['Nómina', 'Nombre del Empleado', 'Tipo de Permiso', 'Días Totales', 'Veces Tomadas', 'Primera Fecha', 'Última Fecha']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF991B1B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            'A4:G' . ($sheet->getHighestRow()) => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]]
            ]
        ];
    }
}