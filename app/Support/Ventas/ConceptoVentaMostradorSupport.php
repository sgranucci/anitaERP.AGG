<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Concepto_Venta;

/**
 * Resuelve cuenta, GTIN y alícuota de un concepto de mostrador.
 * No usar en gastronomía, estacionamiento ni canje (esEmisionPos).
 */
final class ConceptoVentaMostradorSupport
{
    /**
     * WSMTXCA exige codigoMtx en cada ítem. Sin artículo hay que mandar el GTIN del concepto.
     */
    public static function obligatorioSinArticulo(?string $webservicePuntoventa = null): bool
    {
        if (filter_var(config('facturacion.CONCEPTO_OBLIGATORIO_SIN_ARTICULO', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return ArcaPuntoventaWebserviceSupport::esMtxca($webservicePuntoventa);
    }

    public static function mensajeObligatorioSinArticulo(): string
    {
        return 'El concepto de venta es obligatorio cuando el renglón no tiene artículo (WSMTXCA). Indíquelo en el tipo de transacción, en la cabecera o en el renglón.';
    }
    /**
     * @return array{
     *     concepto_venta_id: int,
     *     codigo: string,
     *     descripcion: string,
     *     codigo_gtin: string,
     *     unidades_mtx: int,
     *     impuesto_id: int|null,
     *     unidadmedida_codigo: int,
     *     cuentacontable_id: int|null,
     *     centrocosto_id: int|null,
     *     precio: float|null
     * }|null
     */
    public static function resolverLinea(
        ?int $conceptoId,
        int $empresaId,
        ?int $tipotransaccionId = null,
        ?string $fechaYmd = null,
    ): ?array {
        if ($conceptoId === null || $conceptoId <= 0) {
            return null;
        }

        $concepto = Concepto_Venta::query()
            ->with(['cuentas', 'unidadmedida', 'precios'])
            ->whereKey($conceptoId)
            ->where('activo', true)
            ->first();

        if ($concepto === null) {
            return null;
        }

        return self::aLinea($concepto, $empresaId, $tipotransaccionId, $fechaYmd);
    }

    /**
     * @return array{
     *     concepto_venta_id: int,
     *     codigo: string,
     *     descripcion: string,
     *     codigo_gtin: string,
     *     unidades_mtx: int,
     *     impuesto_id: int|null,
     *     unidadmedida_codigo: int,
     *     cuentacontable_id: int|null,
     *     centrocosto_id: int|null,
     *     precio: float|null
     * }
     */
    public static function aLinea(
        Concepto_Venta $concepto,
        int $empresaId,
        ?int $tipotransaccionId = null,
        ?string $fechaYmd = null,
    ): array {
        $cuenta = ConceptoVentaMatrizSupport::resolverCuenta(
            $concepto,
            $empresaId,
            $tipotransaccionId,
            $fechaYmd,
        );

        $descripcion = trim((string) $concepto->descripcion);
        if ($descripcion === '') {
            $descripcion = trim((string) $concepto->nombre);
        }

        return [
            'concepto_venta_id' => (int) $concepto->id,
            'codigo' => (string) $concepto->codigo,
            'descripcion' => $descripcion,
            'codigo_gtin' => trim((string) ($concepto->codigo_gtin ?? '')),
            'unidades_mtx' => max(1, (int) ($concepto->unidades_mtx ?? 1)),
            'impuesto_id' => $concepto->impuesto_id ? (int) $concepto->impuesto_id : null,
            'unidadmedida_codigo' => (int) ($concepto->unidadmedida->codigo ?? 1),
            'cuentacontable_id' => $cuenta['cuentacontable_id'],
            'centrocosto_id' => $cuenta['centrocosto_id'],
            'precio' => ConceptoVentaMatrizSupport::resolverPrecio($concepto, $fechaYmd),
        ];
    }

    /**
     * Logística El Bierzo: Anita FACEL lee concepto 5 (concod) para art_cbarra.
     *
     * @return array{
     *     concepto_venta_id: int,
     *     codigo: string,
     *     descripcion: string,
     *     codigo_gtin: string,
     *     unidades_mtx: int,
     *     impuesto_id: int|null,
     *     unidadmedida_codigo: int,
     *     cuentacontable_id: int|null
     * }|null
     */
    public static function resolverPorCodigoAnita(int $codigoAnita, int $empresaId = 0): ?array
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        try {
            $concepto = Concepto_Venta::query()
                ->with(['cuentas', 'unidadmedida', 'precios'])
                ->where('codigo_anita', $codigoAnita)
                ->where('activo', true)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if ($concepto === null) {
            return null;
        }

        return self::aLinea($concepto, $empresaId);
    }

    /**
     * GTIN de logística (concepto Anita 5). Si no hay fila o GTIN, el placeholder
     * que El Bierzo ya usaba en MTXCA: no corta la factura.
     */
    public static function codigoMtxLogistica(): string
    {
        return self::codigoMtxSintetico('logistica');
    }

    /**
     * Ítems sintéticos MTXCA (ajuste / bonificación / logística / informe CAEA).
     * Si el concepto no resuelve, el placeholder histórico: no corta el CAE.
     */
    public static function codigoMtxSintetico(string $uso): string
    {
        try {
            $codigoAnita = match ($uso) {
                'bonificacion' => (int) config('facturacion.CONCEPTO_ANITA_BONIFICACION', 1),
                'ajuste' => (int) config('facturacion.CONCEPTO_ANITA_AJUSTE', 2),
                'logistica' => (int) config('facturacion.CONCEPTO_ANITA_LOGISTICA', 5),
                default => 0,
            };
        } catch (\Throwable $e) {
            return GtinEan13Support::PLACEHOLDER_MTXCA;
        }

        return self::codigoMtxDesdeConceptoAnita($codigoAnita);
    }

    public static function codigoMtxDesdeConceptoAnita(int $codigoAnita): string
    {
        $linea = $codigoAnita > 0 ? self::resolverPorCodigoAnita($codigoAnita) : null;
        $gtin = GtinEan13Support::normalizar($linea['codigo_gtin'] ?? null);
        if ($gtin !== null && strlen($gtin) === 13) {
            return $gtin;
        }

        return GtinEan13Support::PLACEHOLDER_MTXCA;
    }

    public static function mensajeCuentaFaltante(array $linea): string
    {
        $codigo = trim((string) ($linea['codigo'] ?? $linea['concepto_venta_id'] ?? ''));

        return 'El concepto '.$codigo.' no tiene cuenta contable para esta empresa. Indicá la cuenta en el asiento del comprobante.';
    }

    public static function mensajeGtinInvalido(array $linea): string
    {
        $codigo = trim((string) ($linea['codigo'] ?? $linea['concepto_venta_id'] ?? ''));

        return 'El concepto '.$codigo.' no tiene un GTIN EAN-13 válido. WSMTXCA lo exige en ítems sin artículo.';
    }

    public static function cuentaParaEmpresa(
        Concepto_Venta $concepto,
        int $empresaId,
        ?int $tipotransaccionId = null,
        ?string $fechaYmd = null,
    ): ?int {
        return ConceptoVentaMatrizSupport::resolverCuenta(
            $concepto,
            $empresaId,
            $tipotransaccionId,
            $fechaYmd,
        )['cuentacontable_id'];
    }
}
