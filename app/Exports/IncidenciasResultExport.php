<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class IncidenciasResultExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
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
            'Nómina',
            'Nombre Empleado',
            'Código Permiso',
            'Fecha Inicio',
            'Fecha Fin',
            'Motivo',
            'ESTATUS',
            'DETALLE ERROR'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4B5563'] 
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $statusCell = $sheet->getCell('G' . $row);
                    $status = $statusCell->getValue();

                    if ($status === 'INGRESADO') {
                        $sheet->getStyle('G' . $row)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCFCE7']],
                            'font' => ['bold' => true, 'color' => ['argb' => 'FF166534']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    } elseif ($status === 'ERROR' || $status === 'NO INGRESADO') {
                        $sheet->getStyle('G' . $row)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']],
                            'font' => ['bold' => true, 'color' => ['argb' => 'FF991B1B']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        $sheet->getStyle('H' . $row)->getFont()->setColor(new Color(Color::COLOR_RED));
                    }
                }
            },
        ];
    }
}