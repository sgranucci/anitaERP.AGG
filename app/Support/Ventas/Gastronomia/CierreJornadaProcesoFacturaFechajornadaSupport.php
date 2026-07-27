<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FAC del proceso de cierre Waitry: fechajornada = día de la jornada/asiento del cierre.
 * La fecha fiscal (CbteFch / venta.fecha) puede elevarse por CAEA; la jornada no.
 */
final class CierreJornadaProcesoFacturaFechajornadaSupport
{
    public const LEYENDA_PREFIJO = 'Cierre Waitry ';

    /**
     * Tras emitir: si la venta no quedó con la jornada del cierre, la corrige.
     */
    public static function asegurarEnVenta(Venta $venta, string $fechaJornada): void
    {
        $fechaJornada = self::normalizarFecha($fechaJornada);
        if ($fechaJornada === '') {
            return;
        }

        $actual = self::normalizarFecha((string) ($venta->fechajornada ?? ''));
        if ($actual === $fechaJornada) {
            return;
        }

        DB::table('venta')->where('id', (int) $venta->id)->update([
            'fechajornada' => $fechaJornada,
            'updated_at' => now(),
        ]);

        $venta->fechajornada = $fechaJornada;

        Log::warning('cierre_jornada_waitry.fechajornada_forzada', [
            'venta_id' => (int) $venta->id,
            'codigo' => (string) ($venta->codigo ?? ''),
            'fechajornada_antes' => $actual,
            'fechajornada' => $fechaJornada,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{
     *   id:int,codigo:string,fecha:string,fechajornada:string,jornada_leyenda:string,total:string
     * }>
     */
    public static function listarDesalineadas(): \Illuminate\Support\Collection
    {
        $filas = DB::table('venta_gastronomia_emision as e')
            ->join('venta as v', 'v.id', '=', 'e.venta_id')
            ->whereNull('v.deleted_at')
            ->where(function ($q): void {
                $q->where('e.identificador_pc', GastronomiaCierreJornadaFacturaProcesoEmisionService::IDENTIFICADOR_PC_PROCESO)
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('e.cierre_jornada_proceso_lote')
                            ->where('e.cierre_jornada_proceso_lote', '>', 0);
                    });
            })
            ->where('v.leyenda', 'like', self::LEYENDA_PREFIJO.'%')
            ->orderBy('v.id')
            ->get([
                'v.id',
                'v.codigo',
                'v.fecha',
                'v.fechajornada',
                'v.leyenda',
                'v.total',
            ]);

        return $filas->map(function ($fila) {
            $jornadaLeyenda = self::fechaJornadaDesdeLeyenda((string) ($fila->leyenda ?? ''));
            if ($jornadaLeyenda === null) {
                return null;
            }
            $actual = self::normalizarFecha((string) ($fila->fechajornada ?? ''));
            if ($actual === $jornadaLeyenda) {
                return null;
            }

            return (object) [
                'id' => (int) $fila->id,
                'codigo' => (string) $fila->codigo,
                'fecha' => (string) $fila->fecha,
                'fechajornada' => $actual,
                'jornada_leyenda' => $jornadaLeyenda,
                'total' => (string) $fila->total,
            ];
        })->filter()->values();
    }

    /**
     * Restaura fechajornada desde la leyenda «Cierre Waitry YYYY-MM-DD» en FAC proceso desalineadas.
     * No modifica venta.fecha (CbteFch / correlatividad CAEA).
     *
     * @return array{revisadas:int,corregidas:int,ids:list<int>}
     */
    public static function restaurarDesdeLeyenda(): array
    {
        $desalineadas = self::listarDesalineadas();
        $corregidas = 0;
        $ids = [];

        foreach ($desalineadas as $fila) {
            DB::table('venta')->where('id', (int) $fila->id)->update([
                'fechajornada' => $fila->jornada_leyenda,
                'updated_at' => now(),
            ]);

            $corregidas++;
            $ids[] = (int) $fila->id;

            Log::info('cierre_jornada_waitry.fechajornada_restaurada', [
                'venta_id' => (int) $fila->id,
                'codigo' => (string) ($fila->codigo ?? ''),
                'fechajornada_antes' => (string) $fila->fechajornada,
                'fechajornada' => (string) $fila->jornada_leyenda,
            ]);
        }

        return [
            'revisadas' => $desalineadas->count(),
            'corregidas' => $corregidas,
            'ids' => $ids,
        ];
    }

    public static function fechaJornadaDesdeLeyenda(string $leyenda): ?string
    {
        if (! preg_match('/Cierre Waitry (\d{4}-\d{2}-\d{2})/', $leyenda, $m)) {
            return null;
        }

        return self::normalizarFecha($m[1]) ?: null;
    }

    private static function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha, $m)) {
            return $m[0];
        }

        $ts = strtotime($fecha);

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }
}
