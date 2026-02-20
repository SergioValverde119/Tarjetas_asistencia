<?php

namespace App\Exports;

use App\Services\KardexService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class KardexExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $filtros;
    protected $kardexService;
    protected $headings;
    protected $rangoDeDias;

    // CAMBIO: Inyectamos el SERVICIO
    public function __construct(array $filtros, KardexService $kardexService)
    {
        $this->filtros = $filtros;
        $this->kardexService = $kardexService;

        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        $this->rangoDeDias = range($diaInicio, $diaFin);

        $this->headings = ['ID Empleado', 'Nombre', 'Nómina'];
        foreach ($this->rangoDeDias as $dia) {
            $this->headings[] = (string)$dia; 
        }
        $this->headings = array_merge($this->headings, ["Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas"]);
    }

    public function collection()
    {
        // CAMBIO: Usamos el método del servicio
        $datos = $this->kardexService->generarKardexExport($this->filtros);
        return collect($datos);
    }

    public function map($filaEmpleado): array
    {
        $datos = [
            $filaEmpleado['emp_code'],
            $filaEmpleado['nombre'],
            $filaEmpleado['nomina'] ?? '',
        ];

        foreach ($this->rangoDeDias as $dia) {
            $incidencia = $filaEmpleado['incidencias_diarias'][$dia] ?? '';
            // Ajuste visual para Excel
            if ($incidencia === 'OK') $valorCelda = '✓';
            elseif ($incidencia === 'DESC') $valorCelda = 'D';
            else $valorCelda = $incidencia;
            
            $datos[] = $valorCelda;
        }

        $datos[] = $filaEmpleado['total_vacaciones'];
        $datos[] = $filaEmpleado['total_permisos'];
        $datos[] = $filaEmpleado['total_retardos'];
        $datos[] = $filaEmpleado['total_omisiones'];
        $datos[] = $filaEmpleado['total_faltas'];

        return $datos;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        $styleArray = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]]];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray($styleArray);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('D2');
            },
        ];
    }
}