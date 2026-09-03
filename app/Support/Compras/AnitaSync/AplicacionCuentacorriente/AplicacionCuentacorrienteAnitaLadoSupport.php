<?php

namespace App\Support\Compras\AnitaSync\AplicacionCuentacorriente;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;

/**
 * Identidad Anita de un movimiento de CC (factura/NC en compra o OPA en pagoproveedor).
 *
 * @phpstan-type Lado array{
 *   proveedor: string,
 *   tipo: string,
 *   letra: string,
 *   sucursal: int,
 *   numero: int,
 *   nro_interno: int,
 *   nro_cuota: int,
 *   empresa: int,
 *   etiqueta: string,
 *   cod_mon: string,
 *   cotizacion: float
 * }
 */
final class AplicacionCuentacorrienteAnitaLadoSupport
{
    /**
     * @return Lado|null
     */
    public static function desdeCc(Proveedor_Cuentacorriente $cc): ?array
    {
        $proveedor = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita(
            (string) ($cc->proveedores?->codigo ?? '')
        );
        if ($proveedor === '') {
            return null;
        }

        $pago = $cc->pagoproveedores;
        if ($pago !== null && (int) ($cc->pagoproveedor_id ?? 0) > 0) {
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $pago->tipocomprobante);
            $numero = (int) $pago->numerotransaccion;
            if ($tipo === '' || $numero <= 0) {
                return null;
            }

            return self::armar(
                $proveedor,
                $tipo,
                (string) $pago->letra,
                (int) $pago->sucursal,
                $numero,
                0,
                1,
                (int) ($cc->empresas?->codigo ?? $pago->empresa_id ?? 0),
                self::codMonDesdeCc($cc),
                self::cotizacionDesdeCc($cc),
            );
        }

        $comp = $cc->comprobante_proveedores;
        if ($comp !== null && (int) ($cc->comprobante_proveedor_id ?? 0) > 0) {
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo(
                (string) ($comp->tipotransaccion_compras?->abreviatura ?? '')
            );
            $numero = (int) $comp->numerocomprobante;
            if ($tipo === '' || $numero <= 0) {
                return null;
            }

            return self::armar(
                $proveedor,
                $tipo,
                (string) $comp->letra,
                (int) $comp->sucursal,
                $numero,
                (int) ($comp->anita_nro_interno ?? 0),
                (int) ($cc->comprobante_proveedor_cuotas?->numero_cuota ?? 1) ?: 1,
                (int) ($cc->empresas?->codigo ?? $cc->empresa_id ?? 0),
                self::codMonDesdeCc($cc),
                self::cotizacionDesdeCc($cc),
            );
        }

        return null;
    }

    /**
     * @return Lado
     */
    public static function armar(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $nroInterno = 0,
        int $nroCuota = 1,
        int $empresa = 0,
        string $codMon = '1',
        float $cotizacion = 1.0,
    ): array {
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);
        $letra = ComprobanteProveedorAnitaImportClaveSupport::letra($letra);
        $cotizacion = $cotizacion > 0 ? $cotizacion : 1.0;
        $codMon = trim($codMon) !== '' ? $codMon : '1';

        return [
            'proveedor' => ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedor),
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'nro_interno' => $nroInterno,
            'nro_cuota' => $nroCuota > 0 ? $nroCuota : 1,
            'empresa' => $empresa,
            'etiqueta' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta($tipo, $letra, $sucursal, $numero),
            'cod_mon' => $codMon,
            'cotizacion' => $cotizacion,
        ];
    }

    public static function codMonDesdeCc(Proveedor_Cuentacorriente $cc): string
    {
        $codigo = trim((string) ($cc->monedas?->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }
        $codigo = trim((string) ($cc->comprobante_proveedores?->monedas?->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        return (string) ((int) ($cc->moneda_id ?? 1) ?: 1);
    }

    public static function cotizacionDesdeCc(Proveedor_Cuentacorriente $cc): float
    {
        $cot = (float) ($cc->cotizacion ?? 0);
        if ($cot > 0) {
            return $cot;
        }
        $cot = (float) ($cc->comprobante_proveedores?->cotizacion ?? 0);

        return $cot > 0 ? $cot : 1.0;
    }

    public static function tPagadoDesdeSumaAplicaciones(float $suma): float
    {
        return round(abs($suma), 4);
    }

    public static function decimal(float $valor): string
    {
        return number_format($valor, 4, '.', '');
    }

    public static function esc(string $valor, int $maxLen = 0): string
    {
        $texto = str_replace("'", '', $valor);
        if ($maxLen > 0) {
            $texto = mb_substr($texto, 0, $maxLen);
        }

        return $texto;
    }
}
