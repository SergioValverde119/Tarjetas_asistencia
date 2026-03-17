<?php

namespace App\Exports;

use App\Services\KardexService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmpleadoHorarioExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $kardexService;

    public function __construct(KardexService $kardexService) {
        $this->kardexService = $kardexService;
    }

    public function collection() {
        
        return collect($this->kardexService->generarDirectorioHorarios());
    }

    public function headings(): array {
        return [
            'Nómina', 
            'Nombre(s)', 
            'Apellido(s)', 
            'Área / Nómina', 
            'Nombre del Turno',
            'Hora de Entrada', 
            'Hora de Salida', 
            'Días a Laborar'
        ];
    }

    public function styles(Worksheet $sheet) {
        return [1 => ['font' => ['bold' => true]]];
    }
}