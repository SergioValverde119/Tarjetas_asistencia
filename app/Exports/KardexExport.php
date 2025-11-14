<?php

namespace App\Exports;

use App\Repositories\KardexRepository;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
// --- IMPORTACIONES DE TABLA ELIMINADAS ---

class KardexExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    protected $filtros;
    protected $kardexRepo;
    protected $headings;
    protected $rangoDeDias;

    public function __construct(array $filtros, KardexRepository $kardexRepo)
    {
        $this->filtros = $filtros;
        $this->kardexRepo = $kardexRepo;

        // 1. Calcular el rango de días
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        $this->rangoDeDias = range($diaInicio, $diaFin);

        // 2. Construir los encabezados dinámicos
        $this->headings = ['ID Empleado', 'Nombre'];
        foreach ($this->rangoDeDias as $dia) {
            $this->headings[] = (string)$dia; 
        }
        $this->headings = array_merge($this->headings, ["Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas"]);
    }

    public function collection()
    {
        // 1. Obtener TODOS los empleados (sin paginación)
        $empleados = $this->kardexRepo->getEmpleadosTodos($this->filtros);
        $empleadoIDs = $empleados->pluck('id')->toArray();

        // 2. Obtener sus datos
        $fechaInicioMes = Carbon::createFromDate((int)$this->filtros['ano'], (int)$this->filtros['mes'], 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate((int)$this->filtros['ano'], (int)$this->filtros['mes'], 1)->endOfMonth()->endOfDay();
        
        $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);

        // 3. Procesar los datos
        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleados, 
            $payloadData,
            $permisos, 
            (int)$this->filtros['mes'], 
            (int)$this->filtros['ano'], 
            $this->rangoDeDias[0], // diaInicio
            end($this->rangoDeDias) // diaFin
        );

        // 4. Aplanar los datos para que Excel los entienda
        $collection = collect($datosKardex)->map(function ($fila) {
            $excelRow = [
                'emp_code' => $fila['emp_code'],
                'nombre' => $fila['nombre'],
            ];
            
            // Añadir las incidencias diarias
            foreach ($this->rangoDeDias as $dia) {
                $incidencia = $fila['incidencias_diarias'][$dia] ?? '';
                $excelRow[$dia] = $incidencia === '' ? '✓' : $incidencia;
            }

            // --- ¡CORREGIDO! Se mostrará el 0 (cero) ---
            $excelRow['total_vacaciones'] = $fila['total_vacaciones'];
            $excelRow['total_permisos'] = $fila['total_permisos'];
            $excelRow['total_retardos'] = $fila['total_retardos'];
            $excelRow['total_omisiones'] = $fila['total_omisiones'];
            $excelRow['total_faltas'] = $fila['total_faltas'];

            return $excelRow;
        });

        return $collection;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Poner en negritas y con fondo gris la fila 1 (encabezados)
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE0E0E0'); // Un gris claro

        // 2. Aplicar bordes a todas las celdas
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFBFBFBF'], // Un gris más suave
                ],
            ],
        ];

        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())
              ->applyFromArray($styleArray);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                
                $sheet = $event->sheet->getDelegate();

                // Inmoviliza la fila 1 y las columnas A y B (ID y Nombre)
                $sheet->freezePane('C2'); 
                
                // Columnas de Días (Inician en C (3) y terminan antes de las últimas 5)
                $firstDayCol = 3; 
                $lastDayCol = 2 + count($this->rangoDeDias);
                
                // Columnas de Resumen (Las últimas 5)
                $firstSummaryCol = $lastDayCol + 1;
                $lastSummaryCol = $firstSummaryCol + 4;

                // Definimos los colores (Formato ARGB: Alfa, Rojo, Verde, Azul)
                $colores = [
                    'Falto' => 'FFFEE2E2', // Rojo claro
                    'R' => 'FFFFEDD5', // Naranja claro
                    'Sin Entrada' => 'FFFEF9C3', // Amarillo claro
                    'Sin Salida' => 'FFFEF9C3', // Amarillo claro
                    '✓' => 'FFDCFCE7', // Verde claro
                    'Descanso' => 'FFE5E7EB', // Gris claro
                ];
                $colorPermiso = 'FFDBEAFE'; // Azul claro

                // Recorremos cada fila de datos (empezando en la fila 2)
                for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                    
                    // 1. Colorear celdas de Días
                    for ($col = $firstDayCol; $col <= $lastDayCol; $col++) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $value = $cell->getValue();

                        $fillColor = $colores[$value] ?? null;

                        // Si no es un color conocido (ej. V, P, etc.) usamos el azul de permiso
                        if ($fillColor === null && $value !== '✓' && $value !== 'Descanso') {
                            $fillColor = $colorPermiso;
                        }

                        if ($fillColor) {
                            $cell->getStyle()->getFill()
                                 ->setFillType(Fill::FILL_SOLID)
                                 ->getStartColor()->setARGB($fillColor);
                        }
                    }

                    // 2. Poner en negritas las celdas de Resumen
                    for ($col = $firstSummaryCol; $col <= $lastSummaryCol; $col++) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $cell->getStyle()->getFont()->setBold(true);

                        // Opcional: Colorear Faltas en rojo si son > 0
                        if ($col == $lastSummaryCol && (int)$cell->getValue() > 0) {
                             $cell->getStyle()->getFont()->setColor( new Color('FF991B1B') ); // Rojo oscuro
                        }
                    }
                }

                // --- CÓDIGO DE FORMATO DE TABLA ELIMINADO ---
                
            },
        ];
    }
}