<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon; // IMPORTANTE: Corregido error de Clase no encontrada

class ReporteAsistenciasExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithMapping, WithCustomStartCell, WithEvents
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function collection()
    {
        return $this->datos;
    }

    public function startCell(): string
    {
        return 'A8';
    }

    public function map($row): array
    {
        $tipoTexto = match ((int)$row->tipo_verificacion) {
            1 => 'Huella',
            15 => 'Rostro',
            3 => 'Contraseña',
            4 => 'Tarjeta',
            default => 'Otro (' . $row->tipo_verificacion . ')',
        };

        // Corrección: Usamos la clase Carbon global correctamente importada
        $fechaObj = Carbon::parse($row->fecha_hora);

        return [
            $row->user_id,
            $fechaObj->format('Y-m-d'),
            $fechaObj->format('H:i:s'),
            $tipoTexto,
            $row->dispositivo_nombre ?? $row->sn,
        ];
    }

    public function headings(): array
    {
        return [
            'ID Empleado',
            'Fecha',
            'Hora',
            'Método',
            'Dispositivo',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            8 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $primerRegistro = $this->datos->first();
                
                if ($primerRegistro) {
                    $sheet->mergeCells('A1:B1');
                    $sheet->setCellValue('A1', 'RESUMEN DEL EMPLEADO');
                    $sheet->getStyle('A1:B1')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '1F2937']
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    $sheet->setCellValue('A2', 'Número de Empleado:');
                    $sheet->setCellValue('B2', $primerRegistro->user_id);

                    $sheet->setCellValue('A3', 'Nombre:');
                    $sheet->setCellValue('B3', "{$primerRegistro->first_name} {$primerRegistro->last_name}");

                    $sheet->setCellValue('A4', 'Horario Asignado:');
                    $sheet->setCellValue('B4', $primerRegistro->schedule_name ?? 'Sin Horario');

                    $sheet->setCellValue('A5', 'Entrada Programada:');
                    $sheet->setCellValue('B5', $primerRegistro->schedule_in ?? '--');

                    $sheet->setCellValue('A6', 'Salida Programada:');
                    $sheet->setCellValue('B6', $primerRegistro->schedule_out ?? '--');

                    $sheet->getStyle('A2:A6')->getFont()->setBold(true);
                    $sheet->getStyle('A2:A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle('A1:B6')->applyFromArray([
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '1F2937'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}