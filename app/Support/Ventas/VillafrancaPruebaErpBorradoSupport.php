<?php

namespace App\Support\Ventas;

use App\Models\Contable\Asiento;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaAnitaReplica;
use App\Services\Ventas\FacturacionService;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Borra en ERP (no Anita) facturas Villafranca de prueba: origen A 8 y sin venta en /usr2/villafranca.
 */
final class VillafrancaPruebaErpBorradoSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function candidatas(): array
    {
        $filas = [];
        foreach (VillafrancaPruebaVsRealSupport::generar()['erp'] as $f) {
            if ($f['origen'] === VillafrancaPruebaVsRealSupport::ORIGEN_8 && $f['en_anita_vf'] === 'NO') {
                $filas[] = $f;
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<string, int>
     */
    public static function contarHijas(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [
                'venta' => 0,
                'venta_impuesto' => 0,
                'venta_emision' => 0,
                'cliente_cuentacorriente' => 0,
                'asiento' => 0,
                'asiento_movimiento' => 0,
                'articulo_movimiento' => 0,
                'venta_anita_replica' => 0,
            ];
        }

        $asientoIds = DB::table('asiento')->whereIn('venta_id', $ventaIds)->pluck('id');

        return [
            'venta' => count($ventaIds),
            'venta_impuesto' => (int) DB::table('venta_impuesto')->whereIn('venta_id', $ventaIds)->count(),
            'venta_emision' => (int) DB::table('venta_emision')->whereIn('venta_id', $ventaIds)->count(),
            'cliente_cuentacorriente' => (int) DB::table('cliente_cuentacorriente')->whereIn('venta_id', $ventaIds)->count(),
            'asiento' => $asientoIds->count(),
            'asiento_movimiento' => $asientoIds->isEmpty()
                ? 0
                : (int) DB::table('asiento_movimiento')->whereIn('asiento_id', $asientoIds)->count(),
            'articulo_movimiento' => (int) DB::table('articulo_movimiento')->whereIn('venta_id', $ventaIds)->count(),
            'venta_anita_replica' => (int) DB::table('venta_anita_replica')->whereIn('venta_id', $ventaIds)->count(),
        ];
    }

    public static function eliminarUna(int $ventaId, bool $tambienAnita = false): void
    {
        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        if ($tambienAnita) {
            app(FacturacionService::class)->borraAnitaDesdeVenta($venta, false);
        }

        EloquentAuditDeleteSupport::each(
            VentaAnitaReplica::query()->where('venta_id', $ventaId)
        );
        EloquentAuditDeleteSupport::each(
            Articulo_Movimiento::query()->where('venta_id', $ventaId)
        );
        EloquentAuditDeleteSupport::each(
            Asiento::query()->where('venta_id', $ventaId)
        );
        $venta->delete();
    }
}
