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

class IncidenciasDetalleCompletoSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
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

    public function title(): string { return '2. Listado Detallado'; }

    public function headings(): array
    {
        return [
            ['REPORTE NIVEL 3: BITÁCORA INDIVIDUAL DE PERMISOS'],
            ["Periodo: {$this->start} al {$this->end}"],
            [''],
            ['Nómina', 'Nombre del Empleado', 'Tipo de Permiso', 'Fecha Inicio', 'Fecha Final', 'Motivo del Permiso']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF991B1B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            'A4:F' . ($sheet->getHighestRow()) => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
                'alignment' => ['wrapText' => true]
            ]
        ];
    }
}