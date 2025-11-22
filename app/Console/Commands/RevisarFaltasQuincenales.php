<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\KardexRepository;
use App\Models\User; 
use App\Notifications\AlertaFaltasCriticas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevisarFaltasQuincenales extends Command
{
    protected $signature = 'app:revisar-faltas-quincenales'; 
    protected $description = 'Revisa faltas usando el límite configurado en la BD (evita duplicados diarios).';

    protected $kardexRepo;

    public function __construct(KardexRepository $kardexRepo)
    {
        parent::__construct();
        $this->kardexRepo = $kardexRepo;
    }

    public function handle()
    {
        // 1. Configuración: Leemos el límite de la BD
        $limiteConfig = DB::table('settings')->where('key', 'limite_faltas')->value('value');
        $limite = (int) ($limiteConfig ?? 3);

        $this->info("Iniciando revisión. Límite: {$limite}.");

        $hoy = Carbon::now();
        $quincena = ($hoy->day <= 15) ? 1 : 2;
        
        // Filtros para obtener todos los empleados activos
        $filtros = [
            'mes' => $hoy->month,
            'ano' => $hoy->year,
            'quincena' => $quincena,
            'perPage' => 999999, 
            'search' => '',
        ];

        $empleados = $this->kardexRepo->getEmpleadosTodos($filtros);
        $empleadoIDs = $empleados->pluck('id')->toArray();
        
        $fechaInicioMes = Carbon::createFromDate($hoy->year, $hoy->month, 1)->startOfDay();
        $fechaFinMes = Carbon::createFromDate($hoy->year, $hoy->month, 1)->endOfMonth()->endOfDay();
        
        $payloadData = $this->kardexRepo->getPayloadData($empleadoIDs, $fechaInicioMes, $fechaFinMes);
        $permisos = $this->kardexRepo->getPermisos($empleadoIDs, $fechaInicioMes, $fechaFinMes);

        $fechaBase = Carbon::createFromDate($hoy->year, $hoy->month, 1);
        $diaInicio = ($quincena == 2) ? 16 : 1;
        $diaFin = ($quincena == 1) ? 15 : $fechaBase->daysInMonth;

        // Procesamos el Kárdex
        $datosKardex = $this->kardexRepo->procesarKardex(
            $empleados, 
            $payloadData,
            $permisos, 
            $hoy->month, 
            $hoy->year, 
            $diaInicio, 
            $diaFin
        );

        $nuevasAlertas = 0;
        $adminUser = User::find(1); 

        foreach ($datosKardex as $fila) {
            // Si supera el límite...
            if ($fila['total_faltas'] >= $limite) {
                
                if ($adminUser) {
                    // --- ¡CORRECCIÓN PARA POSTGRESQL! ---
                    // Usamos whereRaw para "castear" el texto a JSON antes de leerlo.
                    // data::json le dice a Postgres "Trata esto como JSON".
                    // ->> saca el valor como texto.
                    
                    $yaNotificado = DB::table('notifications')
                        ->where('notifiable_id', $adminUser->id)
                        // Fix: Casteamos explícitamente a JSON
                        ->whereRaw("CAST(data AS json)->>'emp_code' = ?", [(string)$fila['emp_code']])
                        ->whereRaw("CAST(data AS json)->>'fecha_alerta' = ?", [$hoy->toDateString()])
                        ->exists();

                    if (! $yaNotificado) {
                        // Si NO le hemos avisado hoy, enviamos la alerta
                        $periodoTexto = ($quincena == 1 ? "1ra" : "2da") . " de " . $hoy->monthName;
                        
                        $adminUser->notify(new AlertaFaltasCriticas(
                            $fila, 
                            $fila['total_faltas'],
                            $periodoTexto
                        ));
                        
                        $this->error("ALERTA CREADA: {$fila['nombre']} ({$fila['total_faltas']} faltas).");
                        $nuevasAlertas++;
                    } else {
                        // Si ya le avisamos, no hacemos nada
                    }
                }
            }
        }

        $this->info("Proceso terminado. Se crearon {$nuevasAlertas} notificaciones nuevas.");
    }
}