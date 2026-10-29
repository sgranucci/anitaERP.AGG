<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceComprobante;
use App\Models\Ventas\Venta;

/**
 * Liquidación de diferencia entre FAC reemplazo y NC/original.
 */
final class CambioDevolucionMarketplaceLiquidacionSupport
{
    public const SENTIDO_CLIENTE_DEBE = 'cliente_debe';

    public const SENTIDO_FERLI_ACREDITA = 'ferli_acredita';

    public const SENTIDO_SIN_DIFERENCIA = 'sin_diferencia';

    /** @var array<string, string> */
    public const ETIQUETAS_SENTIDO = [
        self::SENTIDO_CLIENTE_DEBE => 'Cliente debe',
        self::SENTIDO_FERLI_ACREDITA => 'Ferli acredita',
        self::SENTIDO_SIN_DIFERENCIA => 'Sin diferencia',
    ];

    /**
     * @return array{diferencia_importe:float,diferencia_sentido:string}
     */
    public static function calcular(?Venta $ventaReemplazo, ?Venta $ventaOriginalONc): array
    {
        $totalReemplazo = abs((float) ($ventaReemplazo->total ?? 0));
        $totalOriginal = abs((float) ($ventaOriginalONc->total ?? 0));
        $diff = round($totalReemplazo - $totalOriginal, 2);

        if (abs($diff) < 0.01) {
            return [
                'diferencia_importe' => 0.0,
                'diferencia_sentido' => self::SENTIDO_SIN_DIFERENCIA,
            ];
        }

        if ($diff > 0) {
            return [
                'diferencia_importe' => $diff,
                'diferencia_sentido' => self::SENTIDO_CLIENTE_DEBE,
            ];
        }

        return [
            'diferencia_importe' => abs($diff),
            'diferencia_sentido' => self::SENTIDO_FERLI_ACREDITA,
        ];
    }

    public static function aplicarALegajo(CambioDevolucionMarketplace $cambio): void
    {
        $cambio->loadMissing(['ventaOriginal', 'ventaNc', 'comprobantes']);
        $base = $cambio->ventaNc ?? $cambio->ventaOriginal;
        $reemplazo = new Venta();
        $reemplazo->total = self::totalReemplazosVigentes($cambio);
        $calc = self::calcular($reemplazo, $base);
        $cambio->diferencia_importe = $calc['diferencia_importe'];
        $cambio->diferencia_sentido = $calc['diferencia_sentido'];
    }

    /**
     * Suma las facturas de reemplazo que siguen vigentes (las anuladas por NC no entran).
     */
    public static function totalReemplazosVigentes(CambioDevolucionMarketplace $cambio): float
    {
        $cambio->loadMissing('comprobantes');
        $anuladas = [];
        foreach ($cambio->comprobantes as $comprobante) {
            if ((string) $comprobante->tipo !== CambioDevolucionMarketplaceComprobante::TIPO_NC_REEMPLAZO) {
                continue;
            }
            $anulaId = (int) ($comprobante->anula_venta_id ?? 0);
            if ($anulaId > 0) {
                $anuladas[] = $anulaId;
            }
        }

        $ids = [];
        foreach ($cambio->comprobantes as $comprobante) {
            if ((string) $comprobante->tipo !== CambioDevolucionMarketplaceComprobante::TIPO_FAC_REEMPLAZO) {
                continue;
            }
            $ventaId = (int) $comprobante->venta_id;
            if ($ventaId > 0 && ! in_array($ventaId, $anuladas, true)) {
                $ids[] = $ventaId;
            }
        }

        $pointer = (int) ($cambio->venta_reemplazo_id ?? 0);
        if ($pointer > 0 && ! in_array($pointer, $anuladas, true)) {
            $ids[] = $pointer;
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach (Venta::query()->whereIn('id', $ids)->get(['id', 'total']) as $venta) {
            $total += abs((float) $venta->total);
        }

        return round($total, 2);
    }

    public static function etiquetaSentido(?string $sentido): string
    {
        if ($sentido === null || $sentido === '') {
            return '';
        }

        return self::ETIQUETAS_SENTIDO[$sentido] ?? $sentido;
    }
}
