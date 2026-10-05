<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Una factura de ventas de mostrador admite una sola nota de crédito completa.
 * Completa = anulación FCE (S) o importe ≥ 99,9 % del total de la factura.
 * Las notas parciales siguen permitidas.
 */
final class NotaCreditoCompletaUnicaSupport
{
    public const FACTOR_COMPLETA = 0.999;

    public static function esCompleta(float $totalNc, float $totalFactura, ?string $anulacionSn = null): bool
    {
        if (strtoupper(trim((string) $anulacionSn)) === 'S') {
            return true;
        }

        $factura = abs($totalFactura);
        $nc = abs($totalNc);
        if ($factura < 0.01) {
            return false;
        }

        return ($nc / $factura) >= self::FACTOR_COMPLETA;
    }

    public static function mensaje(string $codigoFactura, string $codigoNc): string
    {
        $factura = trim($codigoFactura) !== '' ? trim($codigoFactura) : 'indicada';
        $nc = trim($codigoNc) !== '' ? trim($codigoNc) : 'existente';

        return 'La factura '.$factura.' ya tiene la nota de crédito completa '.$nc
            .'. No se puede emitir otra nota de crédito completa sobre la misma factura.';
    }

    /**
     * @return array{id:int,codigo:string}|null
     */
    public static function completaExistente(int $ventaFacturaId): ?array
    {
        if ($ventaFacturaId <= 0) {
            return null;
        }

        $map = self::codigosCompletasPorFactura([$ventaFacturaId]);
        $codigo = $map[$ventaFacturaId] ?? null;
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return [
            'id' => 0,
            'codigo' => $codigo,
        ];
    }

    public static function errorSiSegundaCompleta(int $ventaFacturaId, float $totalNc, ?string $anulacionSn): ?string
    {
        if ($ventaFacturaId <= 0) {
            return null;
        }

        $factura = Venta::query()->find($ventaFacturaId, ['id', 'codigo', 'total']);
        if ($factura === null) {
            return null;
        }

        if (! self::esCompleta($totalNc, (float) $factura->total, $anulacionSn)) {
            return null;
        }

        $existente = self::completaExistente($ventaFacturaId);
        if ($existente === null) {
            return null;
        }

        return self::mensaje((string) ($factura->codigo ?? ''), $existente['codigo']);
    }

    public static function idFacturaPorCodigo(string $codigo): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) (Venta::query()->where('codigo', $codigo)->value('id') ?? 0);
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, string> id de factura => código de la NC completa
     */
    public static function codigosCompletasPorFactura(array $ventaIds): array
    {
        $ventaIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ventaIds),
            static fn (int $id) => $id > 0
        )));
        if ($ventaIds === []) {
            return [];
        }

        $facturas = Venta::query()
            ->whereIn('id', $ventaIds)
            ->get(['id', 'total'])
            ->keyBy(static fn ($row) => (int) $row->id);

        $out = [];
        foreach (self::notasPorFactura($ventaIds) as $facturaId => $notas) {
            $factura = $facturas->get($facturaId);
            if ($factura === null) {
                continue;
            }
            foreach ($notas as $nota) {
                $anulacion = ArcaFceNcMostradorSupport::leerAnulacionDesdeLeyenda($nota['leyenda']);
                if (! self::esCompleta((float) $nota['total'], (float) $factura->total, $anulacion)) {
                    continue;
                }
                $codigo = trim((string) $nota['codigo']);
                $out[$facturaId] = $codigo !== '' ? $codigo : ('#'.$nota['id']);
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $facturaIds
     * @return array<int, array<int, array{id:int,codigo:string,total:float,leyenda:string}>>
     */
    private static function notasPorFactura(array $facturaIds): array
    {
        $porFactura = [];

        $porOrigen = Venta::query()
            ->from('venta as nc')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'nc.tipotransaccion_id')
            ->where('tt.operacion', 'C')
            ->whereIn('nc.venta_origen_id', $facturaIds)
            ->get([
                'nc.id',
                'nc.codigo',
                'nc.total',
                'nc.leyenda',
                'nc.venta_origen_id',
            ]);

        foreach ($porOrigen as $row) {
            self::acumularNota($porFactura, (int) $row->venta_origen_id, $row);
        }

        $porAplicacion = DB::table('cliente_cuentacorriente_aplicacion as a')
            ->join('cliente_cuentacorriente as cc', 'cc.id', '=', 'a.cliente_cuentacorriente_id')
            ->join('venta as nc', 'nc.id', '=', 'cc.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'nc.tipotransaccion_id')
            ->where('tt.operacion', 'C')
            ->whereIn('a.ventaaplicado_id', $facturaIds)
            ->whereColumn('nc.id', '!=', 'a.ventaaplicado_id')
            ->get([
                'nc.id',
                'nc.codigo',
                'nc.total',
                'nc.leyenda',
                'a.ventaaplicado_id as factura_id',
            ]);

        foreach ($porAplicacion as $row) {
            self::acumularNota($porFactura, (int) $row->factura_id, $row);
        }

        return $porFactura;
    }

    /**
     * @param  array<int, array<int, array{id:int,codigo:string,total:float,leyenda:string}>>  $porFactura
     */
    private static function acumularNota(array &$porFactura, int $facturaId, object $row): void
    {
        if ($facturaId <= 0) {
            return;
        }
        $id = (int) ($row->id ?? 0);
        if ($id <= 0) {
            return;
        }
        $porFactura[$facturaId][$id] = [
            'id' => $id,
            'codigo' => trim((string) ($row->codigo ?? '')),
            'total' => (float) ($row->total ?? 0),
            'leyenda' => (string) ($row->leyenda ?? ''),
        ];
    }
}
