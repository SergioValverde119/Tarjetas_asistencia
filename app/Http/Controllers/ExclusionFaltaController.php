<?php

namespace App\Http\Controllers;

use App\Models\ExclusionFalta;
use App\Repositories\FaltaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controlador para gestionar la lista negra del monitor de faltas.
 * Integra nombres de la réplica de BioTime y buscador dinámico.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class ExclusionFaltaController extends Controller
{
    protected $faltaRepo;

    public function __construct(FaltaRepository $faltaRepo)
    {
        $this->faltaRepo = $faltaRepo;
    }

    /**
     * Muestra la página de gestión de exclusiones.
     */
    public function index()
    {
        // 1. Obtener todas las exclusiones de la base local
        $exclusionesLocales = ExclusionFalta::orderBy('created_at', 'desc')->get();

        // 2. Obtener el catálogo de empleados de BioTime para el buscador y para cruzar nombres
        $empleadosBioTime = $this->faltaRepo->getAllEmployees();

        // 3. Mapear los nombres de BioTime a las exclusiones locales
        $exclusionesConNombre = $exclusionesLocales->map(function ($exc) use ($empleadosBioTime) {
            $emp = $empleadosBioTime->firstWhere('emp_code', $exc->emp_code);
            return [
                'id' => $exc->id,
                'emp_code' => $exc->emp_code,
                'nombre' => $emp ? "{$emp->first_name} {$emp->last_name}" : 'No encontrado en BioTime',
                'area' => $emp->area_name ?? 'N/A',
                'motivo' => $exc->motivo,
                'created_at' => $exc->created_at->format('Y-m-d H:i')
            ];
        });

        return Inertia::render('Faltas/Exclusiones', [
            'exclusiones' => $exclusionesConNombre,
            'cat_empleados' => $empleadosBioTime // Catálogo para el buscador dinámico
        ]);
    }

    /**
     * Registra una nueva nómina para excluir.
     */
    public function store(Request $request)
    {
        $request->validate([
            'emp_code' => 'required|string|unique:exclusiones_faltas,emp_code',
            'motivo'   => 'nullable|string|max:255'
        ], [
            'emp_code.unique' => 'Esta nómina ya se encuentra en la lista de exclusiones.'
        ]);

        try {
            ExclusionFalta::create($request->all());
            return redirect()->back()->with('success', 'Nómina excluida correctamente');
        } catch (\Exception $e) {
            Log::error("Error al crear exclusión: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error al procesar la solicitud');
        }
    }

    /**
     * Elimina una nómina de la lista de exclusiones.
     */
    public function destroy($id)
    {
        try {
            $exclusion = ExclusionFalta::findOrFail($id);
            $exclusion->delete();
            return redirect()->back()->with('success', 'Nómina reintegrada al monitor');
        } catch (\Exception $e) {
            Log::error("Error al eliminar exclusión: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar');
        }
    }
}