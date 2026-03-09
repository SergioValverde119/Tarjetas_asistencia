<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\HorarioRepository;
use Inertia\Inertia;
use Exception;

class HorarioController extends Controller
{
    protected $repository;

    public function __construct(HorarioRepository $repository)
    {
        $this->repository = $repository;
    }

    // ========================================================================
    // 1. MÉTODOS DEL CATÁLOGO DE PLANTILLAS (Dashboard Index)
    // ========================================================================
    
    public function index(Request $request)
    {
        $search = $request->input('search');
        $horariosRaw = $this->repository->getHorarios($search);
        $turnos = $this->repository->getTurnosDisponibles();

        $horarios = $horariosRaw->map(function ($horario) {
            $detalles = $this->repository->getDetallesHorario($horario->id);
            $dias = [0 => '', 1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => ''];
            foreach ($detalles as $detalle) {
                $dias[$detalle->dia] = $detalle->turno_id;
            }
            $horario->dias = $dias;
            return $horario;
        });

        return Inertia::render('Horarios/Index', [
            'horarios' => $horarios,
            'turnos' => $turnos,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
            'dias' => 'required|array',
        ]);

        try {
            $this->repository->createHorario($validated);
            return redirect()->route('horarios.index')->with('success', 'Plantilla semanal creada con éxito.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al crear la plantilla: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
            'dias' => 'required|array',
        ]);

        try {
            $this->repository->updateHorario($id, $validated);
            return redirect()->route('horarios.index')->with('success', 'Plantilla actualizada correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->repository->deleteHorario($id);
            return redirect()->route('horarios.index')->with('success', 'Plantilla eliminada correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar: Es posible que esté asignada a empleados.');
        }
    }

    // ========================================================================
    // 2. MÉTODOS PARA BÚSQUEDA Y ASIGNACIÓN INDIVIDUAL
    // ========================================================================
    
    public function getHorarioEmpleado($nomina)
    {
        try {
            $data = $this->repository->getHorarioPorNomina($nomina);
            if (empty($data)) {
                return response()->json([], 200);
            }
            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * VISTA ACTUALIZADA: Envía los turnos con etiquetas descriptivas inteligentes incluyendo TOLERANCIA.
     * Facilita el filtrado en el nuevo selector de tarjetas del frontend.
     */
    public function historial($nomina)
    {
        $data = $this->repository->getHistorialHorarios($nomina);
        if (!$data) {
            return redirect()->route('horarios.index')->with('error', 'Empleado no encontrado en BioTime.');
        }

        // Obtenemos todos los turnos y sus detalles base
        $horariosRaw = $this->repository->getHorarios();
        $turnosBase = $this->repository->getTurnosDisponibles();

        $turnosConDetalle = $horariosRaw->map(function ($horario) use ($turnosBase) {
            $detalles = $this->repository->getDetallesHorario($horario->id);
            $dias = [];
            for ($i = 0; $i <= 6; $i++) { $dias[$i] = ['activo' => false]; }

            $toleranciaMax = 0;
            $horasResumen = "";
            $entradaResumen = "";
            $salidaResumen = "";
            $diasLaboralesCount = 0;

            foreach ($detalles as $det) {
                $tBase = $turnosBase->firstWhere('id', $det->turno_id);
                if ($tBase) {
                    $in = substr($tBase->starttime, 0, 5);
                    $out = substr($tBase->endtime, 0, 5);

                    $dias[$det->dia] = [
                        'activo' => true,
                        'in' => $in,
                        'out' => $out
                    ];
                    
                    if (empty($horasResumen)) {
                        $entradaResumen = $in;
                        $salidaResumen = $out;
                        $horasResumen = "{$in} a {$out}";
                    }
                    
                    if ($tBase->tolerancia > $toleranciaMax) $toleranciaMax = $tBase->tolerancia;
                    $diasLaboralesCount++;
                }
            }

            $horario->dias = $dias;
            $horario->tolerancia = (int)$toleranciaMax;
            $horario->entrada_ref = $entradaResumen;
            $horario->salida_ref = $salidaResumen;

            // --- MAGIA: Construimos el nombre descriptivo incluyendo TOLERANCIA ---
            $resumenDias = $diasLaboralesCount . " días";
            if ($diasLaboralesCount == 5 && $dias[1]['activo'] && $dias[5]['activo']) $resumenDias = "L-V";
            if ($diasLaboralesCount == 6 && $dias[1]['activo'] && $dias[6]['activo']) $resumenDias = "L-S";
            
            // Ejemplo: "15 (09:00 a 15:00 | L-V | Tol: 10m)"
            $horario->nombre_display = "{$horario->nombre} ({$horasResumen} | {$resumenDias} | Tol: {$horario->tolerancia}m)";

            return $horario;
        });

        return Inertia::render('Horarios/HorarioEmpleado', [
            'empleado' => $data['empleado'],
            'historial' => $data['historial'],
            'turnos' => $turnosConDetalle
        ]);
    }

    public function asignarHorario(Request $request, $nomina)
    {
        $validated = $request->validate([
            'shift_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->repository->asignarHorarioEmpleado(
                $nomina, 
                $validated['shift_id'], 
                $validated['start_date'], 
                $validated['end_date']
            );
            return redirect()->back()->with('success', 'El horario ha sido asignado correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al asignar el horario: ' . $e->getMessage());
        }
    }

    public function destroyAsignacion($id)
    {
        try {
            $this->repository->deleteAsignacion($id);
            return redirect()->back()->with('success', 'Asignación de horario eliminada correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar la asignación.');
        }
    }
}