<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use Carbon\Carbon;

final class RecuentoModoCierreSupport
{
    public const MODO_FECHA_RECUENTO = 'FECHA_RECUENTO';

    public const MODO_SALDO_ACTUAL = 'SALDO_ACTUAL';

    /** @var list<string> */
    public const MODOS_VALIDOS = [
        self::MODO_FECHA_RECUENTO,
        self::MODO_SALDO_ACTUAL,
    ];

    public static function resolverModo(?string $modo): string
    {
        $modo = strtoupper(trim((string) $modo));

        return in_array($modo, self::MODOS_VALIDOS, true)
            ? $modo
            : self::MODO_SALDO_ACTUAL;
    }

    public static function modoPorDefecto(Recuento $recuento): string
    {
        $fecha = $recuento->fecha;
        if ($fecha instanceof Carbon && $fecha->copy()->startOfDay()->lt(now()->startOfDay())) {
            return self::MODO_FECHA_RECUENTO;
        }

        return self::MODO_SALDO_ACTUAL;
    }

    public static function etiqueta(?string $modo): string
    {
        return match (self::resolverModo($modo)) {
            self::MODO_FECHA_RECUENTO => 'A fecha del recuento',
            self::MODO_SALDO_ACTUAL => 'Al saldo actual',
            default => (string) $modo,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function textosImplicancias(): array
    {
        return [
            self::MODO_FECHA_RECUENTO => 'Compara lo contado con el saldo del sistema calculado a la fecha del recuento '
                .'(suma de movimientos con fecha ≤ fecha del recuento). '
                .'El ajuste se registra con esa fecha. '
                .'Use esta opción si el conteo corresponde a un cierre de período (ej. inventario al 31/5) '
                .'y hubo movimientos de stock posteriores: el stock vigente quedará coherente con '
                .'“conteo correcto en esa fecha + movimientos posteriores”.',
            self::MODO_SALDO_ACTUAL => 'Compara lo contado con el saldo vigente hoy en el depósito. '
                .'El ajuste se registra con la fecha de hoy. '
                .'Use esta opción si el conteo refleja lo que hay físicamente ahora y desea '
                .'igualar el sistema al conteo actual, sin reconstruir el saldo histórico.',
        ];
    }

    public static function saldoReferencia(
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        int $articuloId,
        int $depositoId,
        string $modoCierre,
        ?Carbon $fechaRecuento,
    ): float {
        if (self::resolverModo($modoCierre) === self::MODO_FECHA_RECUENTO && $fechaRecuento) {
            return $saldoRepository->saldoAFecha(
                $articuloId,
                $depositoId,
                $fechaRecuento->toDateString()
            );
        }

        return $saldoRepository->saldoAFecha(
            $articuloId,
            $depositoId,
            now()->toDateString()
        );
    }

    public static function fechaMovimientoCierre(Recuento $recuento, string $modoCierre): string
    {
        if (self::resolverModo($modoCierre) === self::MODO_FECHA_RECUENTO && $recuento->fecha) {
            return $recuento->fecha->toDateString();
        }

        return now()->toDateString();
    }
}
