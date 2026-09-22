<?php

namespace App\Support\Compras;

use App\Models\Compras\Condicionpago;
use RuntimeException;

/**
 * NC/ND no pueden llevar condición de pago ni plan de cuotas con más de un vencimiento.
 *
 * Evita heredar el plan de la OC (p. ej. CUOTAS VARIABLES) al precargar/grabar una
 * nota de débito o crédito, que debe figurar como un solo registro en proyección/CC.
 */
final class ComprobanteProveedorCondicionPagoNcNdSupport
{
    public static function esNotaCreditoODebitoPorTipoId(?int $tipotransaccionCompraId): bool
    {
        if (! $tipotransaccionCompraId || $tipotransaccionCompraId <= 0) {
            return false;
        }

        return self::debeRestringirAUnaCuota(
            OrdencompraLegajoDocumentoTipoSupport::desdeTipotransaccionId($tipotransaccionCompraId)
        );
    }

    public static function debeRestringirAUnaCuota(string $tipoGenerico): bool
    {
        return in_array(
            OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipoGenerico),
            ['NC', 'ND'],
            true
        );
    }

    public static function condicionTieneMasDeUnaCuota(?int $condicionpagoId): bool
    {
        return self::condicionEsMultiCuota($condicionpagoId);
    }

    /**
     * Condición multi-cuota: más de un renglón en el maestro, o nombre que implica
     * plan en cuotas (p. ej. «CUOTAS VARIABLES» con un solo renglón plantilla).
     */
    public static function condicionEsMultiCuota(?int $condicionpagoId): bool
    {
        if (! $condicionpagoId || $condicionpagoId <= 0) {
            return false;
        }

        if (Condicionpago::query()
            ->whereKey($condicionpagoId)
            ->has('condicionpagocuotas', '>', 1)
            ->exists()) {
            return true;
        }

        $nombre = self::nombreCondicion($condicionpagoId);
        if ($nombre === '') {
            return false;
        }

        if (preg_match('/\bcuotas\b/iu', $nombre) === 1) {
            return true;
        }

        return preg_match('/\d+\s*e-?cheqs?\b/iu', $nombre) === 1;
    }

    /**
     * @param  list<array<string, mixed>>|iterable<int, mixed>  $cuotas
     */
    public static function cantidadCuotasPlan(iterable $cuotas): int
    {
        $n = 0;
        foreach ($cuotas as $_) {
            $n++;
        }

        return $n;
    }

    /**
     * Si el tipo es NC/ND y la meta trae condición o plan multi-cuota, limpia
     * condición y cuotas (conserva ordencompra_comprobante_id).
     *
     * @param  array{
     *     condicionpago_id?: int|null,
     *     ordencompra_comprobante_id?: int|null,
     *     cuotas?: list<array<string, mixed>>,
     *     cuotas_escaladas?: bool,
     *     permite_edicion_cuotas?: bool
     * }  $meta
     * @return array{
     *     condicionpago_id: int|null,
     *     ordencompra_comprobante_id: int|null,
     *     cuotas: list<array<string, mixed>>,
     *     cuotas_escaladas: bool,
     *     permite_edicion_cuotas: bool
     * }
     */
    public static function sanitizarMetaCuotasParaNcNd(int $tipotransaccionCompraId, array $meta): array
    {
        $out = [
            'condicionpago_id' => isset($meta['condicionpago_id']) ? ((int) $meta['condicionpago_id'] ?: null) : null,
            'ordencompra_comprobante_id' => isset($meta['ordencompra_comprobante_id'])
                ? ((int) $meta['ordencompra_comprobante_id'] ?: null)
                : null,
            'cuotas' => array_values($meta['cuotas'] ?? []),
            'cuotas_escaladas' => (bool) ($meta['cuotas_escaladas'] ?? false),
            'permite_edicion_cuotas' => (bool) ($meta['permite_edicion_cuotas'] ?? true),
        ];

        if (! self::esNotaCreditoODebitoPorTipoId($tipotransaccionCompraId)) {
            return $out;
        }

        $nCuotas = count($out['cuotas']);
        if (self::condicionEsMultiCuota($out['condicionpago_id']) || $nCuotas > 1) {
            $out['condicionpago_id'] = null;
            $out['cuotas'] = [];
            $out['cuotas_escaladas'] = false;
            $out['permite_edicion_cuotas'] = true;
        }

        return $out;
    }

    public static function assertPermitida(
        int $tipotransaccionCompraId,
        ?int $condicionpagoId,
        int $cantidadCuotasPlan = 0,
    ): void {
        self::assertPermitidaParaTipoGenerico(
            OrdencompraLegajoDocumentoTipoSupport::desdeTipotransaccionId($tipotransaccionCompraId),
            $condicionpagoId,
            $cantidadCuotasPlan,
        );
    }

    public static function assertPermitidaParaTipoGenerico(
        string $tipoGenerico,
        ?int $condicionpagoId,
        int $cantidadCuotasPlan = 0,
    ): void {
        if (! self::debeRestringirAUnaCuota($tipoGenerico)) {
            return;
        }

        $multiPorCondicion = self::condicionEsMultiCuota($condicionpagoId);
        $multiPorPlan = $cantidadCuotasPlan > 1;
        if (! $multiPorCondicion && ! $multiPorPlan) {
            return;
        }

        $etiqueta = OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipoGenerico) === 'NC'
            ? 'nota de crédito'
            : 'nota de débito';
        $nombreCond = self::nombreCondicion($condicionpagoId);

        throw new RuntimeException(
            'Las notas de crédito y débito solo admiten una cuota (Contado, días fecha factura, etc.). '
            .'Esta '.$etiqueta
            .($nombreCond !== '' ? ' con condición "'.$nombreCond.'"' : '')
            .' quedó con más de un vencimiento. Elija una condición de pago de una sola cuota '
            .'y un único vencimiento.'
        );
    }

    private static function nombreCondicion(?int $condicionpagoId): string
    {
        if (! $condicionpagoId || $condicionpagoId <= 0) {
            return '';
        }

        $nombre = Condicionpago::query()->whereKey($condicionpagoId)->value('nombre');

        return is_string($nombre) ? trim($nombre) : '';
    }
}
