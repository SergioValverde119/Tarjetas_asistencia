<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class IncidenciasTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Nómina (Opcional si hay Nombre)',  // Col A
            'Nombre Completo (Opcional si hay Nómina)', // Col B
            'Código Permiso (Ej. VAC)',         // Col C
            'Fecha Inicio (AAAA-MM-DD HH:MM)',  // Col D
            'Fecha Fin (AAAA-MM-DD HH:MM)',     // Col E
            'Motivo / Justificación'            // Col F
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Aplicar bordes a las filas de ejemplo (asumiendo que mandas pocas filas)
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);

        return [
            // Encabezado estilizado (Gris azulado institucional)
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF374151'] 
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}