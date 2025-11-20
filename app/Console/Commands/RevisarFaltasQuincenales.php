<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\KardexRepository;
use App\Models\User; 
use App\Notifications\AlertaFaltasCriticas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // <-- Importante

class RevisarFaltasQuincenales extends Command
{
    // Quitamos el argumento {limite} porque ya no lo pediremos por consola
    protected $signature = 'app:revisar-faltas-quincenales'; 

    protected $description = 'Revisa faltas usando el límite configurado en la BD.';

    protected $kardexRepo;

    public function __construct(KardexRepository $kardexRepo)
    {
        parent::__construct();
        $this->kardexRepo = $kardexRepo;
    }

    public function handle()
    {
        // 1. LEER CONFIGURACIÓN DE LA BASE DE DATOS
        // Buscamos el valor, si no existe usamos 3 por defecto
        $limiteConfig = DB::table('settings')->where('key', 'limite_faltas')->value('value');
        $limite = (int) ($limiteConfig ?? 3);

        $this->info("Iniciando revisión. Límite configurado: {$limite} faltas.");

        $hoy = Carbon::now();
        $quincena = ($hoy->day <= 15) ? 1 : 2;
        
        // ... (El resto del código sigue IGUAL hasta el final) ...
        $filtros = [
            'mes' => $hoy->month,
            'ano' => $hoy->year,
            'quincena' => $quincena,
            'perPage' => 999999, 
            'search' => '',
        ];

        $this->line("Calculando Kárdex...");
        
        $empleados = $this->kardexRepo->getEmpleadosTodos($filtros);
        $empleadoIDs = $empleados->pluck('id')->toArray();
        
        $fechaInicioMes = Carbon::createFromDate($hoy->year, $hoy->month, 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate($hoy->year, $hoy->month, 1)->endOfMonth()->endOfDay();
        
        $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);

        $fechaBase = Carbon::createFromDate($hoy->year, $hoy->month, 1);
        $diaInicio = ($quincena == 2) ? 16 : 1;
        $diaFin = ($quincena == 1) ? 15 : $fechaBase->daysInMonth;

        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleados, 
            $payloadData,
            $permisos, 
            $hoy->month, 
            $hoy->year, 
            $diaInicio, 
            $diaFin
        );

        $infractores = 0;
        $adminUser = User::find(1); 

        foreach ($datosKardex as $fila) {
            if ($fila['total_faltas'] >= $limite) { // <-- Aquí usa el límite de la BD
                $this->error("ALERTA: {$fila['nombre']} tiene {$fila['total_faltas']} faltas.");
                
                if ($adminUser) {
                    $periodoTexto = ($quincena == 1 ? "1ra" : "2da") . " de " . $hoy->monthName;
                    
                    $adminUser->notify(new AlertaFaltasCriticas(
                        $fila, 
                        $fila['total_faltas'],
                        $periodoTexto
                    ));
                }
                $infractores++;
            }
        }

        $this->info("Revisión terminada. Se encontraron {$infractores} empleados.");
    }
}