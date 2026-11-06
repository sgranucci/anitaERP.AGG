<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El reemplazo de stock por Excel (20/09/2026) bajó cada bucket de lote/OT con un CONOT
 * calculado sobre los movimientos que existían en ese momento. Borrar o cambiar uno de
 * esos movimientos después le devuelve al bucket pares que el CONOT ya había descontado
 * (o se los saca dos veces): el saldo deja de coincidir con el conteo físico.
 *
 * Desde consola se bloquea. Una corrección legítima tiene que compensar el corte con un
 * movimiento cuyo concepto empiece con PREFIJO (así lo toma como parte del reemplazo) y
 * correr dentro de permitir().
 */
final class CorteReemplazoExcelFerliSupport
{
    public const PREFIJO = 'Reemplazo stock Excel Ferli L12';

    public const CAMPOS_BUCKET = ['cantidad', 'articulo_id', 'combinacion_id', 'deposito_id', 'lote', 'ordentrabajo_id'];

    private static int $permitidos = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function permitir(callable $callback): mixed
    {
        self::$permitidos++;
        try {
            return $callback();
        } finally {
            self::$permitidos--;
        }
    }

    public static function esReemplazo(?string $concepto): bool
    {
        return str_starts_with((string) $concepto, self::PREFIJO);
    }

    /**
     * Id del movimiento de reemplazo posterior del mismo bucket, o null.
     *
     * @param  array{id: int, articulo_id: mixed, combinacion_id: mixed, deposito_id: mixed, lote: mixed, ordentrabajo_id: mixed}  $mov
     */
    public static function reemplazoQueLoAbsorbe(array $mov): ?int
    {
        $articuloId = (int) ($mov['articulo_id'] ?? 0);
        $combinacionId = (int) ($mov['combinacion_id'] ?? 0);
        $depositoId = (int) ($mov['deposito_id'] ?? 0);
        $lote = $mov['lote'] ?? null;
        $otId = (int) ($mov['ordentrabajo_id'] ?? 0);
        if ($articuloId <= 0 || $combinacionId <= 0 || $depositoId <= 0 || ((int) $lote <= 0 && $otId <= 0)) {
            return null;
        }

        $clave = OtBucketStockSupport::claveBucket($lote, $otId, $articuloId, $combinacionId);
        $candidatos = DB::table('articulo_movimiento')
            ->where('articulo_id', $articuloId)
            ->where('combinacion_id', $combinacionId)
            ->where('deposito_id', $depositoId)
            ->where('id', '>', (int) $mov['id'])
            ->where('concepto', 'like', self::PREFIJO.'%')
            ->orderBy('id')
            ->get(['id', 'lote', 'ordentrabajo_id']);

        foreach ($candidatos as $c) {
            if (OtBucketStockSupport::claveBucket($c->lote, (int) ($c->ordentrabajo_id ?? 0), $articuloId, $combinacionId) === $clave) {
                return (int) $c->id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $original  valores del movimiento antes del cambio
     */
    public static function bloquearSiAbsorbido(array $original, string $accion): void
    {
        if (self::$permitidos > 0 || ! app()->runningInConsole() || ! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (self::esReemplazo($original['concepto'] ?? null)) {
            return;
        }

        $reemplazoId = self::reemplazoQueLoAbsorbe($original);
        if ($reemplazoId === null) {
            return;
        }

        throw new RuntimeException(sprintf(
            'No se puede %s el movimiento #%d desde consola: el reemplazo de Excel #%d ya lo descontó del lote %s. '
            .'Compensá con un movimiento "%s …" dentro de CorteReemplazoExcelFerliSupport::permitir().',
            $accion,
            (int) $original['id'],
            $reemplazoId,
            (string) ($original['lote'] ?? ''),
            self::PREFIJO,
        ));
    }

    public static function bloquearMovimiento(Articulo_Movimiento $movimiento, string $accion): void
    {
        self::bloquearSiAbsorbido(['id' => $movimiento->getKey()] + $movimiento->getRawOriginal(), $accion);
    }
}
