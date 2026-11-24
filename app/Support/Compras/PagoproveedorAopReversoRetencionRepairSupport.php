<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa NC (retenciones negativas) en AOP de reversión que quedaron sin filas en ERP.
 * Idempotente: solo toca AOP sin pagoproveedor_retencion.
 */
final class PagoproveedorAopReversoRetencionRepairSupport
{
    public const DESDE_DEFAULT = '2026-10-01';

    /**
     * @return array{aops: int, retenciones: int, detalle: list<array<string, mixed>>}
     */
    public static function reparar(?string $fechaDesde = self::DESDE_DEFAULT, ?int $empresaId = null, bool $dryRun = false): array
    {
        if (! Schema::hasTable('pagoproveedor') || ! Schema::hasTable('pagoproveedor_retencion')) {
            return ['aops' => 0, 'retenciones' => 0, 'detalle' => []];
        }

        $q = Pagoproveedor::query()
            ->where('tipocomprobante', 'AOP')
            ->whereNotNull('pagoproveedor_origen_id')
            ->whereDoesntHave('pagoproveedor_retenciones')
            ->whereHas('pagoproveedor_origen.pagoproveedor_retenciones', static function ($r): void {
                $r->where('importe', '>', 0);
            })
            ->with(['pagoproveedor_origen.pagoproveedor_retenciones']);

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $q->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $aops = $q->orderBy('empresa_id')->orderBy('id')->get();
        $detalle = [];
        $retenciones = 0;

        foreach ($aops as $aop) {
            $origen = $aop->pagoproveedor_origen;
            if ($origen === null) {
                continue;
            }
            $positivas = $origen->pagoproveedor_retenciones->filter(
                static fn ($r) => (float) $r->importe > 0.001
            );
            $item = [
                'empresa_id' => (int) $aop->empresa_id,
                'aop_id' => (int) $aop->id,
                'numero' => (string) $aop->numerotransaccion,
                'origen_numero' => (string) $origen->numerotransaccion,
                'retenciones' => $positivas->count(),
            ];
            $detalle[] = $item;

            if ($dryRun || $positivas->isEmpty()) {
                continue;
            }

            $creadas = DB::transaction(static function () use ($aop, $positivas) {
                return PagoproveedorDevolucionRetencionSupport::clonarComoDevolucionEnPago(
                    $aop,
                    $positivas,
                    'reverso_op',
                );
            });
            $retenciones += count($creadas);
        }

        return [
            'aops' => count($detalle),
            'retenciones' => $retenciones,
            'detalle' => $detalle,
        ];
    }
}
