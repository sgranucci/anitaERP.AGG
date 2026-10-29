<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de crédito: el IVA no revierte la misma cuenta de la factura.
 *
 * Compra: la factura debita la cuenta Debe del concepto (IVA crédito fiscal).
 * La nota de crédito acredita la cuenta Haber cuando es otra (IVA débito fiscal).
 *
 * Venta: la factura acredita IVA débito fiscal. La nota de crédito debita
 * la cuenta automática ventas.iva_credito_fiscal.
 */
final class NotaCreditoIvaCuentaSupport
{
    public static function esNotaCreditoVenta(?Tipotransaccion $tipo): bool
    {
        if ($tipo === null || ! $tipo->esNotaCredito()) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (str_starts_with($abrev, 'NC')) {
            return true;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) ($tipo->codigo ?? ''));

        return $codigo === 3;
    }

    /**
     * En la NC de compra, si el renglón usa la cuenta Debe del concepto y la
     * cuenta Haber es otra, imputa la Haber. El asiento después invierte el lado.
     */
    public static function cuentaHaberNotaCreditoCompra(int $cuentaResuelta, int $debeMaestro, int $haberMaestro): int
    {
        if ($cuentaResuelta <= 0 || $debeMaestro <= 0 || $haberMaestro <= 0) {
            return $cuentaResuelta;
        }
        if ($haberMaestro === $debeMaestro || $cuentaResuelta !== $debeMaestro) {
            return $cuentaResuelta;
        }

        return $haberMaestro;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function remapearAsientoVenta(array $lineas, int $empresaId): array
    {
        $debitoId = self::cuentaDebitoFiscalVentaId($empresaId);
        $creditoId = self::cuentaCreditoFiscalVentaId($empresaId);
        if ($debitoId <= 0 || $creditoId <= 0 || $debitoId === $creditoId) {
            return $lineas;
        }

        foreach ($lineas as $i => $linea) {
            if ((int) ($linea['cuentacontable_id'] ?? 0) === $debitoId) {
                $lineas[$i]['cuentacontable_id'] = $creditoId;
            }
        }

        return $lineas;
    }

    public static function cuentaCreditoFiscalVentaId(int $empresaId): int
    {
        return (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL
        ) ?? 0);
    }

    public static function cuentaDebitoFiscalVentaId(int $empresaId): int
    {
        $id = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL
        ) ?? 0);
        if ($id > 0) {
            return $id;
        }

        $codigo = trim((string) config('facturacion.CUENTACONTABLE_IVA', ''));
        if ($codigo === '' || $empresaId <= 0) {
            return 0;
        }

        return (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);
    }

    /**
     * Cuentas Haber de conceptos de compra, cuando no son la misma que la Debe.
     * La nota de crédito imputa esas cuentas.
     *
     * @return list<int>
     */
    public static function cuentasHaberDistintasDebeIds(int $empresaId): array
    {
        if ($empresaId <= 0 || ! Schema::hasTable('concepto_ivacompra_empresa')) {
            return [];
        }

        return DB::table('concepto_ivacompra_empresa')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('cuentacontabledebe_id')
            ->whereNotNull('cuentacontablehaber_id')
            ->whereColumn('cuentacontablehaber_id', '!=', 'cuentacontabledebe_id')
            ->pluck('cuentacontablehaber_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Debe y Haber de esos conceptos. El libro de compras los suma juntos.
     *
     * @return list<int>
     */
    public static function cuentasConceptoCompraCruzadasIds(int $empresaId): array
    {
        if ($empresaId <= 0 || ! Schema::hasTable('concepto_ivacompra_empresa')) {
            return [];
        }

        $ids = [];
        $filas = DB::table('concepto_ivacompra_empresa')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('cuentacontabledebe_id')
            ->whereNotNull('cuentacontablehaber_id')
            ->whereColumn('cuentacontablehaber_id', '!=', 'cuentacontabledebe_id')
            ->get(['cuentacontabledebe_id', 'cuentacontablehaber_id']);
        foreach ($filas as $fila) {
            $debe = (int) $fila->cuentacontabledebe_id;
            $haber = (int) $fila->cuentacontablehaber_id;
            if ($debe > 0) {
                $ids[$debe] = $debe;
            }
            if ($haber > 0) {
                $ids[$haber] = $haber;
            }
        }

        return array_values($ids);
    }

    /**
     * IVA crédito de ventas que además es la cuenta Debe de un concepto de compra.
     * En el cuadre de ventas solo cuentan los asientos de venta.
     */
    public static function cuentaCreditoVentaCompartidaConCompraId(int $empresaId): int
    {
        $id = self::cuentaCreditoFiscalVentaId($empresaId);
        if ($id <= 0 || ! Schema::hasTable('concepto_ivacompra_empresa')) {
            return 0;
        }

        $esDebeCompra = DB::table('concepto_ivacompra_empresa')
            ->where('empresa_id', $empresaId)
            ->where('cuentacontabledebe_id', $id)
            ->exists();

        return $esDebeCompra ? $id : 0;
    }
}
