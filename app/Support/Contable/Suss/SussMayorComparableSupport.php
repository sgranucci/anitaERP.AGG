<?php

declare(strict_types=1);

namespace App\Support\Contable\Suss;

use App\Support\Contable\Sicore\SicoreConciliacionAuditoriaSupport;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;

/**
 * Comparable SUSS vs mayor del período: generación de retención a terceros.
 * El pago a AFIP (proveedor 1299) de la quincena anterior no entra al cruce;
 * el saldo de ejercicio (acumulado) se calcula aparte y no usa esta partición.
 */
final class SussMayorComparableSupport
{
    /** Código de proveedor AFIP / ARCA en el maestro (Anita y ERP). */
    public const PROVEEDOR_AFIP = '1299';

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *     comparables: list<array<string, mixed>>,
     *     excluidos: list<array<string, mixed>>,
     *     total_comparable: float,
     *     total_excluido: float
     * }
     */
    public static function particionar(array $movimientos): array
    {
        $particion = SicoreMayorComparableSupport::particionar($movimientos, null);
        $asientosAfip = [];
        foreach (array_merge($particion['comparables'], $particion['excluidos']) as $mov) {
            if (! self::esPagoAfip($mov)) {
                continue;
            }
            $asientoId = (int) ($mov['asiento_id'] ?? 0);
            if ($asientoId > 0) {
                $asientosAfip[$asientoId] = true;
            }
        }

        $comparables = [];
        $excluidos = [];

        foreach ($particion['excluidos'] as $mov) {
            $excluidos[] = $mov;
        }

        foreach ($particion['comparables'] as $mov) {
            $asientoId = (int) ($mov['asiento_id'] ?? 0);
            if (self::esPagoAfip($mov) || ($asientoId > 0 && isset($asientosAfip[$asientoId]))) {
                $excluidos[] = array_merge($mov, [
                    'excluido_comparable' => true,
                    'motivo_exclusion' => 'pago_afip',
                ]);
                continue;
            }

            $comparables[] = $mov;
        }

        return [
            'comparables' => $comparables,
            'excluidos' => $excluidos,
            'total_comparable' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($comparables),
            'total_excluido' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($excluidos),
        ];
    }

    /**
     * Pago de la DDJJ / liquidación a AFIP (no es retención practicada a un tercero).
     *
     * @param  array<string, mixed>  $mov
     */
    public static function esPagoAfip(array $mov): bool
    {
        $emisor = SicoreMayorComparableSupport::normalizarEmisor((string) (
            $mov['subd_emisor'] ?? $mov['emisor'] ?? $mov['codigo_proveedor'] ?? ''
        ));
        if ($emisor !== '' && $emisor === SicoreMayorComparableSupport::normalizarEmisor(self::PROVEEDOR_AFIP)) {
            return true;
        }

        $detalle = trim((string) ($mov['detalle'] ?? ''));
        if ($detalle === '') {
            return false;
        }

        return preg_match('/\bPAGO\b.{0,60}\bA\.?F\.?I\.?P\.?\b|\bA\.?F\.?I\.?P\.?\b.{0,60}\bPAGO\b/iu', $detalle) === 1
            || preg_match('/\bADMINISTRACI[OÓ]N\s+FEDERAL\b/iu', $detalle) === 1;
    }
}
