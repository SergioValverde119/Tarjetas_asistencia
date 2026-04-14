<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class FaltasExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithMapping
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

    /**
     * Retornamos el array crudo de faltas.
     */
    public function array(): array
    {
        return $this->faltas;
    }

    /**
     * MAPEADO: Aseguramos que las columnas del Excel sigan el orden correcto
     * basado en las llaves que envía el FaltaController.
     */
    public function map($falta): array
    {
        return [
            $falta['nomina'],
            $falta['nombre'],
            $falta['area'],
            $falta['fecha'],
            $falta['checkin'] ?? '--:--',
            $falta['checkout'] ?? '--:--',
            $falta['horario'],
            $falta['observaciones']
        ];
    }

    /**
     * ENCABEZADOS: Definimos la estructura visual del reporte.
     */
    public function headings(): array
    {
        return [
            ['REPORTE DETALLADO DE FALTAS ACUMULADAS'],
            ['Periodo:', $this->start . ' al ' . $this->end],
            [''],
            [
                'Nómina', 
                'Nombre del Empleado', 
                'Área / Sección',
                'Fecha de la Falta', 
                'Entrada Detectada', 
                'Salida Detectada', 
                'Horario Asignado', 
                'Observaciones'
            ]
        ];
    }

    /**
     * ESTILOS: Aplicamos el formato institucional.
     */
    public function styles(Worksheet $sheet)
    {
        // Combinar celdas del título principal (Ahora son 7 columnas: A a G)
        $sheet->mergeCells('A1:G1');
        
        return [
            // Estilo para el Título
            1 => [
                'font' => ['bold' => true, 'size' => 14], 
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            
            // Estilo para la barra de títulos de columna (Fila 4)
            4 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['argb' => 'FFFFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID, 
                    'startColor' => ['argb' => 'FF991B1B'] // Rojo Institucional
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
            ],

            // Bordes para los datos
            'A4:G' . ($sheet->getHighestRow()) => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCCCCCC'],
                    ],
                ],
            ],
        ];
    }
}