<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Bierzo: tres cantidades en paralelo (como pedido y factura).
 * - kilos = `cantidad` (ATP / saldo oficial)
 * - caja y pieza = del movimiento, firmadas con el signo de `cantidad`
 * No se convierten entre sí (catch weight). AGG no tiene las columnas.
 */
final class UnidadesCajaPiezaSupport
{
    /**
     * Kardex en 3 unidades: solo El Bierzo y solo si existen las columnas.
     * AGG no selecciona ni muestra caja/pieza.
     */
    public static function mostrarEnKardex(): bool
    {
        return EntornoEmpresaSupport::esElBierzo() && self::articuloMovimientoTieneColumnas();
    }

    public static function mostrarEnTransferencia(): bool
    {
        return EntornoEmpresaSupport::esElBierzo() && self::transferenciaLineaTieneColumnas();
    }

    public static function mostrarEnExistencias(): bool
    {
        return self::mostrarEnKardex();
    }

    public static function sqlSumaFirmada(string $columnaCantidad, string $columnaUnidad): string
    {
        return 'SUM(CASE WHEN '.$columnaCantidad.' < 0 THEN -ABS(COALESCE('.$columnaUnidad.', 0))'
            .' ELSE ABS(COALESCE('.$columnaUnidad.', 0)) END)';
    }

    /**
     * Saldos paralelos caja/pieza por artículo y depósito (signo = signo de kilos).
     *
     * @param  list<int>  $articuloIds
     * @param  list<int>  $depositoIds
     * @return array<int, array<int, array{caja: float, pieza: float}>>
     */
    public static function saldosCajaPiezaPorArticuloDeposito(
        array $articuloIds,
        array $depositoIds,
        ?string $fechaHasta = null,
    ): array {
        if (! self::mostrarEnExistencias() || $articuloIds === [] || $depositoIds === []) {
            return [];
        }

        $query = DB::table('articulo_movimiento')
            ->whereIn('articulo_id', $articuloIds)
            ->whereIn('deposito_id', $depositoIds)
            ->groupBy('articulo_id', 'deposito_id')
            ->selectRaw(
                'articulo_id, deposito_id, '
                .self::sqlSumaFirmada('cantidad', 'caja').' as caja, '
                .self::sqlSumaFirmada('cantidad', 'pieza').' as pieza'
            );

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->where('fecha', '<=', $fechaHasta);
        }

        $map = [];
        foreach ($query->get() as $row) {
            $map[(int) $row->articulo_id][(int) $row->deposito_id] = [
                'caja' => (float) ($row->caja ?? 0),
                'pieza' => (float) ($row->pieza ?? 0),
            ];
        }

        return $map;
    }

    public static function articuloMovimientoTieneColumnas(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = Schema::hasColumn('articulo_movimiento', 'caja')
                && Schema::hasColumn('articulo_movimiento', 'pieza');
        }

        return $ok;
    }

    public static function transferenciaLineaTieneColumnas(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = Schema::hasTable('transferencia_mercaderia_articulo')
                && Schema::hasColumn('transferencia_mercaderia_articulo', 'caja')
                && Schema::hasColumn('transferencia_mercaderia_articulo', 'pieza');
        }

        return $ok;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array{caja: float, pieza: float}
     */
    public static function extraerDeLinea(array $linea): array
    {
        return [
            'caja' => max(0.0, (float) ($linea['caja'] ?? $linea['cajas'] ?? 0)),
            'pieza' => max(0.0, (float) ($linea['pieza'] ?? $linea['piezas'] ?? 0)),
        ];
    }
}
