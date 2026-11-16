<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cobranza;
use App\Models\Compras\Pagoproveedor;
use App\Models\Contable\Asiento;
use App\Models\Contable\ConciliacionBancariaPar;
use App\Services\Compras\PagoproveedorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Oleada 2: al persistir un par mayor↔banco, intenta clear del documento ERP (OP / cobranza / IE).
 */
final class ConciliacionBancariaClearingDocumentoSupport
{
    /**
     * @param  array<string, mixed>  $contable
     * @param  array<string, mixed>  $banco
     * @return array<string, mixed>|null  Resumen para par.clearing_json
     */
    public static function despuesDePar(
        array $contable,
        array $banco,
        ?ConciliacionBancariaPar $par = null,
    ): ?array {
        $ibId = (int) ($banco['id'] ?? 0);
        if ($ibId <= 0) {
            return null;
        }

        $resultado = [
            'interbanking_movimiento_id' => $ibId,
            'intentos' => [],
        ];

        $opId = self::resolverPagoproveedorId($contable);
        if ($opId > 0) {
            $r = self::clearPagoproveedor($opId, $ibId);
            $resultado['intentos'][] = $r;
            $resultado['tipo'] = 'pagoproveedor';
            $resultado['documento_id'] = $opId;
            $resultado['ok'] = (bool) ($r['ok'] ?? false);
            self::guardarClearingEnPar($par, $resultado);

            return $resultado;
        }

        $cobranzaId = self::resolverCobranzaId($contable);
        if ($cobranzaId > 0) {
            $r = self::clearCobranza($cobranzaId, $ibId);
            $resultado['intentos'][] = $r;
            $resultado['tipo'] = 'cobranza';
            $resultado['documento_id'] = $cobranzaId;
            $resultado['ok'] = (bool) ($r['ok'] ?? false);
            self::guardarClearingEnPar($par, $resultado);

            return $resultado;
        }

        $cmId = self::resolverCajaMovimientoId($contable);
        if ($cmId > 0) {
            $r = self::clearCajaMovimiento($cmId, $ibId);
            $resultado['intentos'][] = $r;
            $resultado['tipo'] = 'caja_movimiento';
            $resultado['documento_id'] = $cmId;
            $resultado['ok'] = (bool) ($r['ok'] ?? false);
            self::guardarClearingEnPar($par, $resultado);

            return $resultado;
        }

        return null;
    }

    /**
     * Revierte el clear si el par lo había aplicado (desconciliar).
     */
    public static function alDesconciliarPar(ConciliacionBancariaPar $par): void
    {
        $clear = is_array($par->clearing_json) ? $par->clearing_json : [];
        if ($clear === [] || empty($clear['ok'])) {
            return;
        }

        $tipo = (string) ($clear['tipo'] ?? '');
        $docId = (int) ($clear['documento_id'] ?? 0);
        $ibId = (int) ($clear['interbanking_movimiento_id'] ?? 0);
        if ($docId <= 0 || $ibId <= 0) {
            return;
        }

        try {
            match ($tipo) {
                'pagoproveedor' => self::desvincularPagoproveedor($docId, $ibId),
                'cobranza' => self::desvincularCobranza($docId, $ibId),
                'caja_movimiento' => self::desvincularCajaMovimiento($docId, $ibId),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('conciliacion.clearing.desvincular', [
                'tipo' => $tipo,
                'documento_id' => $docId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $contable
     */
    public static function resolverPagoproveedorId(array $contable): int
    {
        $id = (int) ($contable['pagoproveedor_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $asientoId = (int) ($contable['asiento_id'] ?? 0);
        if ($asientoId > 0) {
            $id = (int) (Asiento::query()->whereKey($asientoId)->value('pagoproveedor_id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $cmId = (int) ($contable['caja_movimiento_id'] ?? 0);
        if ($cmId > 0) {
            $id = (int) (Caja_Movimiento::query()->whereKey($cmId)->value('pagoproveedor_id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $tipo = strtoupper(trim((string) ($contable['tipo_comp'] ?? '')));
        $nro = (int) preg_replace('/\D+/', '', (string) ($contable['nro'] ?? '')) ?: 0;
        $empresaId = (int) ($contable['empresa_id'] ?? 0);
        if ($empresaId > 0 && $nro > 0 && in_array($tipo, ['OPP', 'OPA'], true)) {
            return (int) (Pagoproveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('numerotransaccion', $nro)
                ->where(function ($q) use ($tipo) {
                    $q->where('tipocomprobante', $tipo)
                        ->orWhereIn('tipocomprobante', ['OPP', 'OPA']);
                })
                ->value('id') ?? 0);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $contable
     */
    public static function resolverCobranzaId(array $contable): int
    {
        $id = (int) ($contable['cobranza_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $asientoId = (int) ($contable['asiento_id'] ?? 0);
        if ($asientoId > 0) {
            return (int) (Asiento::query()->whereKey($asientoId)->value('cobranza_id') ?? 0);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $contable
     */
    public static function resolverCajaMovimientoId(array $contable): int
    {
        $id = (int) ($contable['caja_movimiento_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $asientoId = (int) ($contable['asiento_id'] ?? 0);
        if ($asientoId > 0) {
            return (int) (Asiento::query()->whereKey($asientoId)->value('caja_movimiento_id') ?? 0);
        }

        return 0;
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    private static function clearPagoproveedor(int $opId, int $ibId): array
    {
        $pago = Pagoproveedor::query()->find($opId);
        if ($pago === null) {
            return ['ok' => false, 'mensaje' => 'OP no encontrada'];
        }
        if ((int) ($pago->interbanking_movimiento_id ?? 0) === $ibId) {
            return ['ok' => true, 'mensaje' => 'OP ya vinculada a este movimiento'];
        }
        if ((int) ($pago->interbanking_movimiento_id ?? 0) > 0) {
            return ['ok' => false, 'mensaje' => 'OP ya vinculada a otro movimiento IB'];
        }

        $res = app(PagoproveedorService::class)->vincularMovimientoInterbanking($opId, $ibId);
        if (! empty($res['errores'])) {
            return ['ok' => false, 'mensaje' => (string) $res['errores']];
        }

        return ['ok' => true, 'mensaje' => 'OP conciliada con extracto'];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    private static function clearCobranza(int $cobranzaId, int $ibId): array
    {
        if (! Schema::hasColumn('cobranza', 'interbanking_movimiento_id')) {
            return ['ok' => false, 'mensaje' => 'Falta columna cobranza.interbanking_movimiento_id'];
        }

        $cob = Cobranza::query()->find($cobranzaId);
        if ($cob === null) {
            return ['ok' => false, 'mensaje' => 'Cobranza no encontrada'];
        }
        if ((int) ($cob->interbanking_movimiento_id ?? 0) === $ibId) {
            return ['ok' => true, 'mensaje' => 'Cobranza ya vinculada'];
        }
        if ((int) ($cob->interbanking_movimiento_id ?? 0) > 0) {
            return ['ok' => false, 'mensaje' => 'Cobranza ya vinculada a otro IB'];
        }

        $ya = Cobranza::query()
            ->where('interbanking_movimiento_id', $ibId)
            ->where('id', '!=', $cobranzaId)
            ->exists();
        if ($ya) {
            return ['ok' => false, 'mensaje' => 'Movimiento IB ya usado en otra cobranza'];
        }

        $cob->interbanking_movimiento_id = $ibId;
        $cob->save();

        return ['ok' => true, 'mensaje' => 'Cobranza vinculada al extracto'];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    private static function clearCajaMovimiento(int $cmId, int $ibId): array
    {
        if (! Schema::hasColumn('caja_movimiento', 'interbanking_movimiento_id')) {
            return ['ok' => false, 'mensaje' => 'Falta columna caja_movimiento.interbanking_movimiento_id'];
        }

        $cm = Caja_Movimiento::query()->find($cmId);
        if ($cm === null) {
            return ['ok' => false, 'mensaje' => 'Movimiento de caja no encontrado'];
        }
        // Si el IE es espejo de OP, el clear manda por OP (ya intentado antes).
        if ((int) ($cm->pagoproveedor_id ?? 0) > 0) {
            return ['ok' => false, 'mensaje' => 'IE ligado a OP: clear vía pagoproveedor'];
        }
        if ((int) ($cm->interbanking_movimiento_id ?? 0) === $ibId) {
            return ['ok' => true, 'mensaje' => 'IE ya vinculado'];
        }
        if ((int) ($cm->interbanking_movimiento_id ?? 0) > 0) {
            return ['ok' => false, 'mensaje' => 'IE ya vinculado a otro IB'];
        }

        $ya = Caja_Movimiento::query()
            ->where('interbanking_movimiento_id', $ibId)
            ->where('id', '!=', $cmId)
            ->exists();
        if ($ya) {
            return ['ok' => false, 'mensaje' => 'Movimiento IB ya usado en otro IE'];
        }

        $cm->interbanking_movimiento_id = $ibId;
        $cm->save();

        return ['ok' => true, 'mensaje' => 'IE vinculado al extracto'];
    }

    private static function desvincularPagoproveedor(int $opId, int $ibId): void
    {
        $pago = Pagoproveedor::query()->find($opId);
        if ($pago === null || (int) ($pago->interbanking_movimiento_id ?? 0) !== $ibId) {
            return;
        }
        // Solo desvincula FK; deja PAGADA (no vuelve a CONFIRMADA).
        $pago->interbanking_movimiento_id = null;
        if ((string) $pago->estado === 'CONCILIADA') {
            $pago->estado = 'PAGADA';
        }
        $pago->save();
    }

    private static function desvincularCobranza(int $cobranzaId, int $ibId): void
    {
        if (! Schema::hasColumn('cobranza', 'interbanking_movimiento_id')) {
            return;
        }
        $cob = Cobranza::query()->find($cobranzaId);
        if ($cob === null || (int) ($cob->interbanking_movimiento_id ?? 0) !== $ibId) {
            return;
        }
        $cob->interbanking_movimiento_id = null;
        $cob->save();
    }

    private static function desvincularCajaMovimiento(int $cmId, int $ibId): void
    {
        if (! Schema::hasColumn('caja_movimiento', 'interbanking_movimiento_id')) {
            return;
        }
        $cm = Caja_Movimiento::query()->find($cmId);
        if ($cm === null || (int) ($cm->interbanking_movimiento_id ?? 0) !== $ibId) {
            return;
        }
        $cm->interbanking_movimiento_id = null;
        $cm->save();
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private static function guardarClearingEnPar(?ConciliacionBancariaPar $par, array $resultado): void
    {
        if ($par === null) {
            return;
        }
        if (! Schema::hasColumn('conciliacion_bancaria_par', 'clearing_json')) {
            return;
        }
        $par->clearing_json = $resultado;
        $par->save();
    }
}
