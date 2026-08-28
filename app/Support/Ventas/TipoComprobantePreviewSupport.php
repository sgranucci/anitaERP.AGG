<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Tipo de comprobante FAC/FCE según cliente receptor MiPyME y monto.
 */
final class TipoComprobantePreviewSupport
{
    private static ?int $tipoFacturaId = null;

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

        $tipoId = $esFce
            ? self::idTipotransaccionPorCodigoAfip($codigoAfip)
            : self::idTipoFactura();

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

    /**
     * FAC/FCE según cliente y monto. NC/ND y otros tipos se dejan como están.
     */
    public static function resolverTipotransaccionId(
        int $tipoIdPedido,
        object $cliente,
        float $totalComprobante,
        string $letra,
        ?object $tipoPedido = null
    ): int {
        $preview = self::desdeCliente($cliente, $totalComprobante, $letra);
        $tipoPedido ??= $tipoIdPedido > 0
            ? Tipotransaccion::query()->find($tipoIdPedido)
            : null;

        return self::elegirId($tipoIdPedido, $preview, $tipoPedido);
    }

    /**
     * @param  array{tipotransaccion_sugerido_id?: int|null, es_fce?: bool}  $preview
     */
    public static function elegirId(int $tipoIdPedido, array $preview, ?object $tipoPedido): int
    {
        if ($tipoPedido && ! self::esFacturaVentaFacOFce($tipoPedido)) {
            return $tipoIdPedido;
        }

        $sugerido = (int) ($preview['tipotransaccion_sugerido_id'] ?? 0);
        if ($sugerido > 0) {
            return $sugerido;
        }

        return $tipoIdPedido > 0 ? $tipoIdPedido : 0;
    }

    public static function idTipoFactura(): ?int
    {
        if (self::$tipoFacturaId !== null) {
            return self::$tipoFacturaId > 0 ? self::$tipoFacturaId : null;
        }

        $abrev = strtoupper(trim((string) config('facturacion.TIPO_FACTURA_ABREVIATURA', 'FAC')));
        $id = (int) (Tipotransaccion::query()
            ->where('abreviatura', $abrev)
            ->where('operacion', 'V')
            ->orderBy('id')
            ->value('id') ?? 0);

        self::$tipoFacturaId = $id;

        return $id > 0 ? $id : null;
    }

    public static function esTipoFceId(?int $tipoId): bool
    {
        if (! $tipoId || $tipoId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion::query()->find($tipoId, ['id', 'codigo', 'abreviatura']);

        return self::esTipoFce($tipo);
    }

    public static function esTipoFce(?object $tipo): bool
    {
        if (! $tipo) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (in_array($abrev, ['FCE', 'NCE', 'DCE'], true)) {
            return true;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) ($tipo->codigo ?? ''));

        return $codigo >= 200 && $codigo < 300;
    }

    public static function esFacturaVentaFacOFce(?object $tipo): bool
    {
        if (! $tipo) {
            return true;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if (in_array($abrev, ['FAC', 'FCE'], true)) {
            return true;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) ($tipo->codigo ?? ''));

        return in_array($codigo, [1, 201, 206], true);
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
