<?php

namespace App\Support\Stock;

use App\Models\Contable\BienUso;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BienUsoAsignacionSupport
{
    public const EFECTO_ASIGNACION = 'ASIGNACION';

    public const EFECTO_DESASIGNACION = 'DESASIGNACION';

    /**
     * Artículos con saldo positivo actualmente asignados al bien.
     *
     * @return list<array{articulo_id: int, sku: string|null, descripcion: string|null, cantidad: float, ultima_fecha: string|null}>
     */
    public static function inventarioActual(int $bienUsoId): array
    {
        if ($bienUsoId <= 0) {
            return [];
        }

        $filas = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->where('am.bien_uso_id', $bienUsoId)
            ->whereNull('am.deleted_at')
            ->groupBy('am.articulo_id', 'a.sku', 'a.descripcion')
            ->havingRaw('SUM(am.cantidad) > 0.000001')
            ->selectRaw('am.articulo_id, a.sku, a.descripcion, SUM(am.cantidad) as cantidad, MAX(am.fecha) as ultima_fecha')
            ->orderBy('a.descripcion')
            ->get();

        return $filas->map(fn ($row) => [
            'articulo_id' => (int) $row->articulo_id,
            'sku' => $row->sku,
            'descripcion' => $row->descripcion,
            'cantidad' => (float) $row->cantidad,
            'ultima_fecha' => $row->ultima_fecha,
        ])->all();
    }

    /** @return Collection<int, object> */
    public static function historialMovimientos(int $bienUsoId): Collection
    {
        if ($bienUsoId <= 0) {
            return collect();
        }

        return self::queryMovimientos(['bien_uso_id' => $bienUsoId])
            ->orderByDesc('am.fecha')
            ->orderByDesc('am.id')
            ->get();
    }

    /** @return Collection<int, Transferencia_Mercaderia> */
    public static function transferenciasPendientesEntrada(int $bienUsoId): Collection
    {
        return self::transferenciasPendientes($bienUsoId);
    }

    /** Transferencias en stand-by que salen desde este bien hacia un depósito. */
    public static function transferenciasPendientesSalida(int $bienUsoId): Collection
    {
        if ($bienUsoId <= 0) {
            return collect();
        }

        return Transferencia_Mercaderia::query()
            ->with(['depositoDestino', 'usuarioOrigen', 'usuarioDestino', 'articulos.articuloOrigen'])
            ->where('bien_uso_origen_id', $bienUsoId)
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, Transferencia_Mercaderia> */
    public static function transferenciasPendientes(int $bienUsoId): Collection
    {
        if ($bienUsoId <= 0) {
            return collect();
        }

        return Transferencia_Mercaderia::query()
            ->with(['depositoOrigen', 'usuarioOrigen', 'usuarioDestino', 'articulos.articuloOrigen'])
            ->where('bien_uso_destino_id', $bienUsoId)
            ->where('estado', TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function queryMovimientos(array $filtros = [])
    {
        $query = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->leftJoin('tipotransaccion_stock as tts', 'tts.id', '=', 'am.tipotransaccion_stock_id')
            ->leftJoin('movimientostock as ms', 'ms.id', '=', 'am.movimientostock_id')
            ->leftJoin('bien_uso as bu', 'bu.id', '=', 'am.bien_uso_id')
            ->leftJoin('transferencia_mercaderia as tm_ent', 'tm_ent.movimientostock_entrada_id', '=', 'am.movimientostock_id')
            ->leftJoin('transferencia_mercaderia as tm_sal', 'tm_sal.movimientostock_salida_id', '=', 'am.movimientostock_id')
            ->whereNotNull('am.bien_uso_id')
            ->whereNull('am.deleted_at')
            ->select([
                'am.id',
                'am.fecha',
                'am.fechajornada',
                'am.articulo_id',
                'am.bien_uso_id',
                'am.cantidad',
                'am.concepto',
                'am.movimientostock_id',
                'am.deposito_id',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'tts.nombre as tipo_transaccion',
                'ms.codigo as movimiento_codigo',
                'bu.hostname as bien_hostname',
                'bu.codigo_inventario as bien_codigo_inventario',
                DB::raw('COALESCE(tm_ent.codigo, tm_sal.codigo) as transferencia_codigo'),
                DB::raw('COALESCE(tm_ent.id, tm_sal.id) as transferencia_id'),
            ]);

        if (! empty($filtros['bien_uso_id'])) {
            $query->where('am.bien_uso_id', (int) $filtros['bien_uso_id']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('am.fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('am.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['articulo_id'])) {
            $query->where('am.articulo_id', (int) $filtros['articulo_id']);
        }

        $efecto = (string) ($filtros['efecto'] ?? '');
        if ($efecto === self::EFECTO_ASIGNACION) {
            $query->where('am.cantidad', '>', 0);
        } elseif ($efecto === self::EFECTO_DESASIGNACION) {
            $query->where('am.cantidad', '<', 0);
        }

        return $query;
    }

    public static function etiquetaEfecto(float $cantidad): string
    {
        return $cantidad >= 0 ? 'Asignación' : 'Desasignación';
    }

    public static function codigoEfecto(float $cantidad): string
    {
        return $cantidad >= 0 ? self::EFECTO_ASIGNACION : self::EFECTO_DESASIGNACION;
    }

    public static function etiquetaBien(?object $row): string
    {
        if ($row === null) {
            return '—';
        }

        return TransferenciaBienUsoSupport::etiquetaBien(
            BienUso::query()->find((int) ($row->bien_uso_id ?? 0))
        );
    }
}
