<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Services\Configuracion\ModuloAvisoService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Gate de Seguridad: Pendiente → Autorizado / Rechazado.
 */
final class IngresoProveedorAutorizacionSupport
{
    public static function autorizar(int $ticketId): IngresoProveedor
    {
        $ticket = IngresoProveedor::query()->findOrFail($ticketId);
        if (! IngresoProveedorEstados::puedeAutorizarORechazar((string) $ticket->estado)) {
            throw new RuntimeException(
                'Solo se puede autorizar un ticket Pendiente. Estado actual: '
                .IngresoProveedorEstados::etiqueta((string) $ticket->estado).'.'
            );
        }

        $ticket->forceFill([
            'estado' => IngresoProveedorEstados::AUTORIZADO,
            'usuario_autorizo_id' => Auth::id(),
            'autorizado_at' => now(),
        ])->save();

        return $ticket->fresh(['usuarios', 'usuarioAutorizo', 'proveedores', 'personas']);
    }

    public static function rechazar(int $ticketId, string $motivo): IngresoProveedor
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new RuntimeException('Indique el motivo del rechazo en el comentario.');
        }

        $ticket = IngresoProveedor::query()->findOrFail($ticketId);
        if (! IngresoProveedorEstados::puedeAutorizarORechazar((string) $ticket->estado)) {
            throw new RuntimeException(
                'Solo se puede rechazar un ticket Pendiente. Estado actual: '
                .IngresoProveedorEstados::etiqueta((string) $ticket->estado).'.'
            );
        }

        $comentario = trim((string) ($ticket->comentario ?? ''));
        $ticket->forceFill([
            'estado' => IngresoProveedorEstados::RECHAZADO,
            'usuario_autorizo_id' => Auth::id(),
            'autorizado_at' => now(),
            'comentario' => $comentario === ''
                ? 'Rechazo: '.$motivo
                : $comentario."\n\nRechazo: ".$motivo,
        ])->save();

        app(ModuloAvisoService::class)->enviar(
            'seguridad',
            'ingreso_proveedor_rechazado',
            (int) $ticket->id
        );

        return $ticket->fresh(['usuarios', 'usuarioAutorizo', 'proveedores', 'personas']);
    }
}
