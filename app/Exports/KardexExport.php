<?php

namespace App\Exports;

use App\Repositories\KardexRepository;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Carbon;

// ¡ESTA ES LA ÚNICA CLASE QUE DEBE ESTAR EN ESTE ARCHIVO!
class KardexExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $filtros;
    protected $kardexRepo;
    protected $headings;
    protected $rangoDeDias;

    public function __construct(array $filtros, KardexRepository $kardexRepo)
    {
        $this->filtros = $filtros;
        $this->kardexRepo = $kardexRepo;

        // 1. Calcular el rango de días (igual que en el controller)
        // ¡LA CORRECCIÓN IMPORTANTE! Convertir a (int) para Carbon.
        $fechaBase = Carbon::createFromDate((int)$filtros['ano'], (int)$filtros['mes'], 1);
        $diasTotalesDelMes = $fechaBase->daysInMonth;
        $diaInicio = ((int)$filtros['quincena'] == 2) ? 16 : 1;
        $diaFin = ((int)$filtros['quincena'] == 1) ? 15 : $diasTotalesDelMes;
        $this->rangoDeDias = range($diaInicio, $diaFin);

        // 2. Construir los encabezados dinámicos
        $this->headings = ['ID Empleado', 'Nombre'];
        foreach ($this->rangoDeDias as $dia) {
            $this->headings[] = (string)$dia; // Encabezados de días: "1", "2", "3"...
        }
        $this->headings = array_merge($this->headings, ["Vacaciones", "Permisos", "Retardos", "Omisiones", "Faltas"]);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // 1. Obtener TODOS los empleados (sin paginación)
        $empleados = $this->kardexRepo->getEmpleadosTodos($this->filtros);
        $empleadoIDs = $empleados->pluck('id')->toArray();

        // 2. Obtener sus datos
        // ¡LA CORRECCIÓN IMPORTANTE! Convertir a (int) para Carbon.
        $fechaInicioMes = Carbon::createFromDate((int)$this->filtros['ano'], (int)$this->filtros['mes'], 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate((int)$this->filtros['ano'], (int)$this->filtros['mes'], 1)->endOfMonth()->endOfDay();
        
        $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);

        // 3. Procesar los datos (reutilizando la misma lógica del Kárdex)
        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleados, 
            $payloadData,
            $permisos, 
            (int)$this->filtros['mes'], // ¡LA CORRECCIÓN IMPORTANTE!
            (int)$this->filtros['ano'], // ¡LA CORRECCIÓN IMPORTANTE!
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
                // Usamos '✓' para asistencias (igual que en el HTML)
                $incidencia = $fila['incidencias_diarias'][$dia] ?? '';
                $excelRow[$dia] = $incidencia === '' ? '✓' : $incidencia;
            }

            // Añadir los totales
            $excelRow['total_vacaciones'] = $fila['total_vacaciones'];
            $excelRow['total_permisos'] = $fila['total_permisos'];
            $excelRow['total_retardos'] = $fila['total_retardos'];
            $excelRow['total_omisiones'] = $fila['total_omisiones'];
            $excelRow['total_faltas'] = $fila['total_faltas'];

            return $excelRow;
        });

        return $collection;
    }

    /**
    * @return array
    */
    public function headings(): array
    {
        // Esto le dice a Excel cuáles son los títulos de las columnas
        return $this->headings;
    }
}