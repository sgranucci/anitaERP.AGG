<?php

namespace App\Support\Compras;

use App\Models\Compras\LoteBancario;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\PropuestaPago;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;

/**
 * Excepciones post-aprobación: lock de OP enviadas al banco, reopen parcial, delta.
 */
class PropuestaPagoExcepcionSupport
{
    public static function opBloqueadaBanco(Pagoproveedor $op): bool
    {
        if ((bool) ($op->bloqueado_banco ?? false)) {
            return true;
        }
        if (! empty($op->interbanking_transferencia_id)) {
            return true;
        }
        if (! empty($op->interbanking_movimiento_id)) {
            return true;
        }
        $estado = mb_strtoupper((string) ($op->estado ?? ''));
        if (in_array($estado, ['PAGADA', 'CONCILIADA'], true)) {
            return true;
        }

        return false;
    }

    public static function propuestaTieneOpBloqueada(PropuestaPago $propuesta): bool
    {
        $propuesta->loadMissing('pagoproveedores');
        foreach ($propuesta->pagoproveedores as $op) {
            if (self::opBloqueadaBanco($op)) {
                return true;
            }
        }

        return false;
    }

    public static function loteEnviado(int $propuestaPagoId): ?LoteBancario
    {
        return LoteBancario::query()
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->where('estado', 'ENVIADO')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{ok:bool,mensaje:string}
     */
    public static function puedeReabrirTotal(PropuestaPago $propuesta): array
    {
        if ((string) $propuesta->estado !== 'AUTORIZADA') {
            return ['ok' => false, 'mensaje' => 'Solo se puede reabrir una propuesta AUTORIZADA (sin ejecutar).'];
        }
        $conOp = $propuesta->lineas->whereNotNull('pagoproveedor_id')->count();
        if ($conOp > 0) {
            return ['ok' => false, 'mensaje' => 'Ya hay OP generadas; use reopen parcial o propuesta delta.'];
        }
        if (self::loteEnviado((int) $propuesta->id)) {
            return ['ok' => false, 'mensaje' => 'Hay lote bancario marcado ENVIADO; no se puede reabrir.'];
        }

        return ['ok' => true, 'mensaje' => 'OK'];
    }

    /**
     * @return array{ok:bool,mensaje:string}
     */
    public static function puedeReabrirParcial(PropuestaPago $propuesta): array
    {
        if ((string) $propuesta->estado !== 'EJECUTADA_PARCIAL') {
            return ['ok' => false, 'mensaje' => 'Reabrir parcial solo aplica a EJECUTADA_PARCIAL.'];
        }
        $pendientes = $propuesta->lineas
            ->where('incluido', true)
            ->filter(fn ($l) => (float) $l->monto_propuesto > 0 && empty($l->pagoproveedor_id));
        if ($pendientes->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay líneas pendientes sin OP. Use propuesta delta para excluidas.'];
        }

        return ['ok' => true, 'mensaje' => 'OK'];
    }
}
