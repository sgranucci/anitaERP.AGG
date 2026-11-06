<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * El neto / gasto de una factura de proveedor no puede imputarse a crédito fiscal de IVA.
 *
 * Incidente Telecentro FIS 34088: el gravado (241.884,93) quedó en 114010-002 en lugar
 * de la cuenta de gasto. La coherencia neto↔IVA de importes no alcanza: hay que rechazar
 * la cuenta.
 *
 * Rango 114010xxx = mismo criterio que {@see ComprobanteProveedorConceptogastoResolverSupport}
 * y el mayor por concepto (IVA crédito fiscal compras).
 */
final class ComprobanteProveedorNetoCuentaIvaSupport
{
    public static function esCodigoIvaCreditoFiscal(int|string|null $codigo): bool
    {
        $n = self::soloDigitos($codigo);

        return $n >= 114010000 && $n < 114020000;
    }

    /**
     * Conceptos cuyo DEBE es gasto/activo (no el IVA liquidado ni percepciones).
     */
    public static function aplicaAConcepto(?string $tipoconcepto, string|int|null $codigoConcepto = null): bool
    {
        if (ComprobanteProveedorConceptoIvaTipos::esImpuesto($tipoconcepto)
            && ! ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno($tipoconcepto, $codigoConcepto)) {
            return false;
        }

        return ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto($tipoconcepto, $codigoConcepto)
            || ComprobanteProveedorConceptoIvaTipos::esExento($tipoconcepto, $codigoConcepto);
    }

    public static function assertCodigoPermitidoParaNeto(int|string|null $codigo, string $etiqueta = 'el neto'): void
    {
        if (! self::esCodigoIvaCreditoFiscal($codigo)) {
            return;
        }

        $cod = self::formatoCodigo($codigo);

        throw new RuntimeException(
            'La cuenta '.$cod.' es de crédito fiscal de IVA y no puede usarse para '.$etiqueta.'. '
            .'Indique la cuenta de gasto o activo del servicio/compra.'
        );
    }

    public static function assertCuentaIdPermitidaParaNeto(int $cuentaId, string $etiqueta = 'el neto'): void
    {
        if ($cuentaId <= 0) {
            return;
        }

        $codigo = Cuentacontable::query()->whereKey($cuentaId)->value('codigo');
        self::assertCodigoPermitidoParaNeto($codigo, $etiqueta);
    }

    /**
     * @param  list<int>  $cuentaIds
     */
    public static function assertCuentasDebeGasto(array $cuentaIds): void
    {
        $ids = [];
        foreach ($cuentaIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return;
        }

        $porId = Cuentacontable::query()
            ->whereIn('id', array_values($ids))
            ->pluck('codigo', 'id');

        foreach ($ids as $id) {
            self::assertCodigoPermitidoParaNeto(
                $porId[$id] ?? null,
                'el reparto de gasto'
            );
        }
    }

    public static function assertComprobante(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_debe_gastos.cuentacontables',
        ]);

        $cuentaIds = [];
        foreach ($comprobante->comprobante_proveedor_conceptos ?? [] as $linea) {
            $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
            if ($cuentaId > 0) {
                $cuentaIds[$cuentaId] = $cuentaId;
            }
        }
        foreach ($comprobante->comprobante_proveedor_debe_gastos ?? [] as $fila) {
            $cuentaId = (int) ($fila->cuentacontable_id ?? 0);
            if ($cuentaId > 0) {
                $cuentaIds[$cuentaId] = $cuentaId;
            }
        }

        $codigos = $cuentaIds === []
            ? collect()
            : Cuentacontable::query()->whereIn('id', array_values($cuentaIds))->pluck('codigo', 'id');

        foreach ($comprobante->comprobante_proveedor_conceptos ?? [] as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if (! $concepto instanceof Concepto_Ivacompra) {
                continue;
            }
            if (! self::aplicaAConcepto(
                (string) ($concepto->tipoconcepto ?? ''),
                (string) ($concepto->codigo ?? '')
            )) {
                continue;
            }
            $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            self::assertCodigoPermitidoParaNeto(
                $codigos[$cuentaId] ?? null,
                'el neto «'.$concepto->nombre.'»'
            );
        }

        foreach ($comprobante->comprobante_proveedor_debe_gastos ?? [] as $fila) {
            $cuentaId = (int) ($fila->cuentacontable_id ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $codigo = $fila->cuentacontables?->codigo ?? ($codigos[$cuentaId] ?? null);
            self::assertCodigoPermitidoParaNeto($codigo, 'el reparto de gasto');
        }
    }

    private static function soloDigitos(int|string|null $codigo): int
    {
        return (int) preg_replace('/\D/', '', (string) ($codigo ?? ''));
    }

    private static function formatoCodigo(int|string|null $codigo): string
    {
        $raw = trim((string) ($codigo ?? ''));
        if ($raw !== '') {
            return $raw;
        }

        $n = self::soloDigitos($codigo);

        return $n > 0 ? (string) $n : '(sin código)';
    }
}
