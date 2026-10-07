<?php

namespace App\Support\Logistica;

use App\Models\Logistica\SolicitudLogistica;
use App\Models\Stock\Depmae;
use RuntimeException;

final class LogisticaCumplimientoSupport
{
    public static function aplicar(SolicitudLogistica $solicitud, string $accion, int $depositoId, int $depositoDestinoId, string $modo): void
    {
        $esTrabajo = $solicitud->trabajo_tipo_id !== null;
        $estado = (string) $solicitud->estado;

        if ($accion === 'rechazar') {
            if (! in_array($estado, ['enviada', 'pendiente_aprobacion', 'aprobada'], true)) {
                throw new RuntimeException('Esta solicitud ya no se puede rechazar.');
            }
            $solicitud->estado = 'rechazada';
            $solicitud->save();

            return;
        }

        if ($accion === 'aprobar') {
            if ($estado !== 'pendiente_aprobacion') {
                throw new RuntimeException('Esta solicitud no está pendiente de aprobación.');
            }
            $solicitud->estado = 'aprobada';
            $solicitud->save();

            return;
        }

        if ($accion === 'preparar') {
            if (! in_array($estado, ['enviada', 'aprobada'], true)) {
                throw new RuntimeException('Esta solicitud no se puede pasar a preparación.');
            }
            if (! $esTrabajo) {
                self::aplicarModoInsumos($solicitud, $modo, $depositoId, $depositoDestinoId);
            }
            $solicitud->estado = 'en_preparacion';
            $solicitud->save();

            return;
        }

        if ($accion === 'entregar') {
            if ($estado !== 'en_preparacion') {
                throw new RuntimeException('La solicitud tiene que estar en preparación.');
            }
            $solicitud->estado = $esTrabajo ? 'cerrada' : 'entregada';
            $solicitud->save();

            return;
        }

        throw new RuntimeException('Acción no reconocida.');
    }

    private static function aplicarModoInsumos(SolicitudLogistica $solicitud, string $modo, int $depositoId, int $depositoDestinoId): void
    {
        if (! in_array($modo, ['deposito', 'transferencia', 'compra'], true)) {
            throw new RuntimeException('Elegí si se cumple desde depósito, con transferencia o con una compra.');
        }
        if ($modo === 'deposito' || $modo === 'transferencia') {
            if (! Depmae::autorizadoParaUsuario($depositoId)) {
                throw new RuntimeException('Elegí un depósito de salida autorizado.');
            }
            $solicitud->deposito_id = $depositoId;
        }
        if ($modo === 'transferencia') {
            if ($depositoDestinoId === $depositoId || ! Depmae::autorizadoParaUsuario($depositoDestinoId)) {
                throw new RuntimeException('Elegí un depósito de destino distinto y autorizado.');
            }
            $solicitud->deposito_destino_id = $depositoDestinoId;
        }
        $solicitud->modo_cumplimiento = $modo;
    }
}
