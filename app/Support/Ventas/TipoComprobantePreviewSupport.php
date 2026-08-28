<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Tipo de comprobante a preseleccionar en el preview de factura (pedido / remito).
 */
final class TipoComprobantePreviewSupport
{
    /**
     * @return array{
     *     tipotransaccion_sugerido_id: int|null,
     *     codigo_afip_sugerido: int,
     *     es_fce: bool,
     *     aviso_fce: string|null
     * }
     */
    public static function desdeCliente(object $cliente, float $totalComprobante, string $letra = 'A'): array
    {
        $modo = trim((string) ($cliente->modofacturacion ?? $cliente->modoFacturacion ?? ''));
        $letra = strtoupper(trim($letra)) ?: 'A';
        $tope = ParametroSistemaSupport::limiteFce();
        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            '001',
            $letra,
            $modo !== '' ? $modo : null,
            $totalComprobante
        );
        $esFce = $modo === 'C' && $tope > 0 && $totalComprobante >= $tope && $codigoAfip >= 200;

        $tipoId = $esFce ? self::idTipotransaccionPorCodigoAfip($codigoAfip) : null;

        $aviso = null;
        if ($esFce) {
            $aviso = sprintf(
                'Cliente receptor FCE MiPyME y el total ($ %s) alcanza el tope ($ %s). Se selecciona Factura de Crédito Electrónica.',
                number_format($totalComprobante, 2, ',', '.'),
                number_format($tope, 2, ',', '.')
            );
        }

        return [
            'tipotransaccion_sugerido_id' => $tipoId,
            'codigo_afip_sugerido' => $codigoAfip,
            'es_fce' => $esFce,
            'aviso_fce' => $aviso,
        ];
    }

    private static function idTipotransaccionPorCodigoAfip(int $codigoAfip): ?int
    {
        if ($codigoAfip <= 0) {
            return null;
        }

        $padded = str_pad((string) $codigoAfip, 3, '0', STR_PAD_LEFT);
        $tipos = Tipotransaccion::query()
            ->where('operacion', 'V')
            ->where('estado', 'A')
            ->where(function ($q) use ($codigoAfip, $padded) {
                $q->where('codigo', $padded)
                    ->orWhere('codigo', (string) $codigoAfip)
                    ->orWhere('abreviatura', 'FCE');
            })
            ->get(['id', 'codigo', 'abreviatura']);

        $exacto = $tipos->first(static function ($tipo) use ($codigoAfip): bool {
            return (int) preg_replace('/\D+/', '', (string) $tipo->codigo) === $codigoAfip;
        });

        return $exacto?->id ?? $tipos->first()?->id;
    }
}
