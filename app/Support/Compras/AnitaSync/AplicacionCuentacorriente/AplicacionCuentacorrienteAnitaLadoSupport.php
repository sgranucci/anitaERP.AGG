<?php

namespace App\Support\Compras\AnitaSync\AplicacionCuentacorriente;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Configuracion\MonedaAnitaCodigoSupport;

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
                0,
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
            'nro_cuota' => $nroCuota < 0 ? 1 : $nroCuota,
            'empresa' => $empresa,
            'etiqueta' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta($tipo, $letra, $sucursal, $numero),
            'cod_mon' => $codMon,
            'cotizacion' => $cotizacion,
        ];
    }

    public static function codMonDesdeCc(Proveedor_Cuentacorriente $cc): string
    {
        $moneda = $cc->monedas ?? $cc->comprobante_proveedores?->monedas;
        $monedaId = (int) ($cc->moneda_id
            ?: ($cc->comprobante_proveedores?->moneda_id ?? 0)
            ?: ($cc->pagoproveedores?->moneda_id ?? 0)
            ?: 1);

        return MonedaAnitaCodigoSupport::desdeMoneda($moneda, $monedaId);
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

    /**
     * La cabecera promov de la OP guarda el monto de la cabecera del pago.
     * Moneda y cotización tienen que ser las de ese mismo comprobante: la primera
     * CC puede estar en la moneda de la factura aplicada (dólares) y dejaría
     * un importe en pesos con prov_cod_mon de dólar.
     *
     * @param  Lado  $lado
     * @return Lado
     */
    public static function alinearMonedaDesdePago(array $lado, ?Pagoproveedor $pago): array
    {
        if ($pago === null) {
            return $lado;
        }

        $monedaId = (int) ($pago->moneda_id ?: 1);
        $lado['cod_mon'] = MonedaAnitaCodigoSupport::desdeMoneda($pago->monedas, $monedaId);
        $cot = (float) ($pago->cotizacion ?? 0);
        $lado['cotizacion'] = $cot > 0 ? $cot : 1.0;

        return $lado;
    }

    public static function tPagadoDesdeSumaAplicaciones(float $suma): float
    {
        return round(abs($suma), 4);
    }

    /**
     * Fuente de verdad Anita: prov_t_pagado = suma neta de aplmovp del comprobante.
     * AOP (anulación de OP) resta; el resto suma.
     *
     * @param  list<array<string, mixed>|object>  $filasAplmovp
     */
    public static function tPagadoDesdeFilasAplmovp(array $filasAplmovp): float
    {
        $suma = 0.0;
        foreach ($filasAplmovp as $fila) {
            $a = (array) $fila;
            $monto = abs((float) ($a['aplvp_monto'] ?? 0));
            $tipo = strtoupper(substr(trim((string) ($a['aplvp_tipo'] ?? '')), 0, 3));
            $tipoCob = strtoupper(substr(trim((string) ($a['aplvp_tipo_cob'] ?? '')), 0, 3));
            if ($tipo === 'AOP' || $tipoCob === 'AOP') {
                $suma -= $monto;
            } else {
                $suma += $monto;
            }
        }

        return round(max(0.0, $suma), 4);
    }

    /**
     * Última fecha Ymd (aplvp_fecha) entre filas aplmovp; '0' si no hay.
     *
     * @param  list<array<string, mixed>|object>  $filasAplmovp
     */
    public static function fechaPagoYmdDesdeFilasAplmovp(array $filasAplmovp): string
    {
        $max = 0;
        foreach ($filasAplmovp as $fila) {
            $a = (array) $fila;
            $ymd = (int) preg_replace('/\D/', '', (string) ($a['aplvp_fecha'] ?? '')) ?: 0;
            if ($ymd > $max) {
                $max = $ymd;
            }
        }

        return $max > 0 ? (string) $max : '0';
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

    /**
     * En aplicaciones de OP, Anita identifica el comprobante por tipo (FIS/CIS/OPA),
     * no por el signo del importe. La fila CC nacida de este pago tiene
     * pagoproveedor_id = id de la OP; la otra es el documento (aplvp_*).
     *
     * @return bool true si $propiaPagoproveedorId es el documento, false si es la fila OP
     */
    public static function propiaEsDocumentoDelPago(int $pagoId, int $propiaPagoproveedorId, int $otraPagoproveedorId): bool
    {
        if ($pagoId <= 0) {
            return $propiaPagoproveedorId <= 0;
        }

        return $propiaPagoproveedorId !== $pagoId;
    }
}
