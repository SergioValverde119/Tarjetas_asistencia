<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function markAsRead(Request $request, $id)
    {
        error_log("--------------------------------------------------");
        error_log("🔔 ACCIÓN: MARCAR UNA NOTIFICACIÓN COMO LEÍDA");
        error_log(" - ID recibido desde Vue: " . $id);
        error_log(" - Usuario ID: " . $request->user()->id);

        // 1. Buscamos la notificación en TODAS (leídas o no) para ver si existe
        $notification = $request->user()
                                ->notifications()
                                ->where('id', $id)
                                ->first();
        
        if ($notification) {
            error_log("✅ Notificación encontrada en BD.");
            error_log(" - Estado actual (antes): " . ($notification->read_at ?? 'NULL (No leída)'));
            
            // 2. La marcamos
            $notification->markAsRead();
            
            error_log("✅ Método markAsRead() ejecutado.");
            error_log(" - Estado nuevo (después): " . $notification->fresh()->read_at);
        } else {
            error_log("❌ ERROR: No se encontró la notificación con ese ID para este usuario.");
            
            // Debug: Listar IDs reales disponibles para ver si hay discrepancia
            $idsReales = $request->user()->unreadNotifications()->pluck('id')->take(5)->toArray();
            error_log(" - Primeros 5 IDs reales pendientes en BD: " . implode(', ', $idsReales));
        }
        error_log("--------------------------------------------------");
        
        return back();
    }

    public function markAllAsRead(Request $request)
    {
        error_log("--------------------------------------------------");
        error_log("🔔 ACCIÓN: MARCAR TODO COMO LEÍDO");
        
        $cantidad = $request->user()->unreadNotifications()->count();
        error_log(" - Cantidad de notificaciones pendientes antes de marcar: " . $cantidad);
        
        if ($cantidad > 0) {
            $request->user()->unreadNotifications->markAsRead();
            error_log("✅ Se ejecutó markAsRead() masivo.");
            
            // Verificación
            $restantes = $request->user()->unreadNotifications()->count();
            error_log(" - Pendientes después de marcar: " . $restantes);
        } else {
            error_log("⚠️ No había nada que marcar.");
        }
        error_log("--------------------------------------------------");

        return back();
    }
}