<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaFaltasCriticas extends Notification implements ShouldQueue
{
    use Queueable;

    protected $empleado;
    protected $cantidadFaltas;
    protected $periodo;

    /**
     * Recibimos los datos del empleado infractor.
     */
    public function __construct($empleado, $cantidadFaltas, $periodo)
    {
        $this->empleado = $empleado;
        $this->cantidadFaltas = $cantidadFaltas;
        $this->periodo = $periodo;
    }

    /**
     * ¿Por qué canales enviamos esto?
     */
    public function via(object $notifiable): array
    {
        // 'database' guarda la alerta para la campana y las tarjetas rojas.
        // Si quieres enviar correo, agrega 'mail' al array: return ['database', 'mail'];
        return ['database']; 
    }

    /**
     * Formato para el Correo Electrónico (Opcional, si activas 'mail')
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('⚠️ Alerta de Asistencia: ' . $this->empleado['nombre'])
                    ->greeting('Atención Administrador')
                    ->line("El empleado {$this->empleado['nombre']} ({$this->empleado['emp_code']}) ha acumulado {$this->cantidadFaltas} faltas en la quincena {$this->periodo}.")
                    // Usamos route() para que sea más seguro que url()
                    ->action('Ver Kárdex', route('kardex.index')) 
                    ->line('Se requiere su atención inmediata.');
    }

    /**
     * Formato para guardar en la Base de Datos (Para la campanita y tarjetas)
     */
    public function toArray(object $notifiable): array
    {
        return [
            // Este título es CLAVE, es el que usa AlertaBaja.vue para filtrar
            'titulo' => 'Límite de Faltas Excedido', 
            'mensaje' => "{$this->empleado['nombre']} tiene {$this->cantidadFaltas} faltas.",
            'emp_code' => $this->empleado['emp_code'],
            'faltas' => $this->cantidadFaltas,
            'fecha_alerta' => now()->toDateString(),
        ];
    }
}