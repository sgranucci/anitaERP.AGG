<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\Models\Ventas\DescuentoGastronomia;
use App\Support\Ventas\GastronomiaDescuentoClienteInternoSupport;
use stdClass;

/**
 * Descuento de cabecera y cliente interno desde resvta Anita (POS legacy).
 */
final class GastronomiaAnitaImportDescuentoSupport
{
    /**
     * @return array{
     *   descuento_gastronomia_id: ?int,
     *   cliente_interno_descuento_id: ?int,
     *   codigo_descuento: ?string,
     *   codigo_cliente: ?string
     * }
     */
    public static function resolverDesdeResvta(?stdClass $resvta, int $empresaId = 0): array
    {
        $vacío = [
            'descuento_gastronomia_id' => null,
            'cliente_interno_descuento_id' => null,
            'codigo_descuento' => null,
            'codigo_cliente' => null,
        ];

        if ($resvta === null) {
            return $vacío;
        }

        $codigoDescuento = self::normalizarCodigoAnita($resvta->resv_tipo_dto ?? null);
        $codigoCliente = self::normalizarCodigoAnita($resvta->resv_cliente ?? null);

        if ($codigoDescuento === null) {
            return array_merge($vacío, [
                'codigo_cliente' => $codigoCliente,
                'cliente_interno_descuento_id' => self::resolverClienteInternoId($codigoCliente, $empresaId, null),
            ]);
        }

        $descuento = self::buscarDescuentoPorCodigo($codigoDescuento);

        return [
            'descuento_gastronomia_id' => $descuento?->id !== null ? (int) $descuento->id : null,
            'cliente_interno_descuento_id' => self::resolverClienteInternoId($codigoCliente, $empresaId, $codigoDescuento),
            'codigo_descuento' => $codigoDescuento,
            'codigo_cliente' => $codigoCliente,
        ];
    }

    /**
     * Invitación / descuento de cabecera: un renglón ficticio con ven_monto_desc para el reporte de costos.
     */
    public static function debeUsarLineaFicticiaVenMontoDesc(?stdClass $resvta, stdClass $cab): bool
    {
        $montoDesc = self::montoDescDesdeCabecera($cab);
        if ($montoDesc <= 0) {
            return false;
        }

        return self::tieneCodigoDescuentoResvta($resvta);
    }

    public static function montoDescDesdeCabecera(stdClass $cab): float
    {
        return round(abs((float) ($cab->ven_monto_desc ?? 0)), 2);
    }

    public static function tieneCodigoDescuentoResvta(?stdClass $resvta): bool
    {
        return self::normalizarCodigoAnita($resvta->resv_tipo_dto ?? null) !== null;
    }

    private static function buscarDescuentoPorCodigo(string $codigo): ?DescuentoGastronomia
    {
        $descuento = DescuentoGastronomia::query()
            ->where('codigo', $codigo)
            ->first();

        if ($descuento !== null) {
            return $descuento;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return DescuentoGastronomia::query()->where('codigo', $alt)->first();
        }

        return null;
    }

    private static function resolverClienteInternoId(?string $codigoCliente, int $empresaId, ?string $codigoDescuento): ?int
    {
        return GastronomiaDescuentoClienteInternoSupport::resolverDesdeCodigoAnita(
            $codigoCliente,
            $empresaId,
            $codigoDescuento,
        );
    }

    private static function normalizarCodigoAnita(mixed $raw): ?string
    {
        $codigo = trim((string) ($raw ?? ''));
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return $codigo;
    }
}
