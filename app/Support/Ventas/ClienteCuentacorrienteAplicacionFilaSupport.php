<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use Carbon\Carbon;

/**
 * Arma el DTO de fila del workbench de aplicación CC cliente (crédito o deuda).
 */
final class ClienteCuentacorrienteAplicacionFilaSupport
{
    public const TIPO_NC = 'NC';

    public const TIPO_PAGO = 'PAGO';

    public const TIPO_FAC = 'FAC';

    public const TIPO_ND = 'ND';

    public const TIPO_OTRO = 'OTRO';

    /**
     * @return array{
     *   id:int,
     *   tipo:string,
     *   tipo_label:string,
     *   abreviatura:string,
     *   etiqueta:string,
     *   fecha:?string,
     *   vencimiento:?string,
     *   moneda:?string,
     *   moneda_id:int,
     *   cotizacion:float,
     *   total:float,
     *   aplicado:float,
     *   saldo:float,
     *   empresa:?string,
     *   empresa_id:int,
     *   cliente_id:int,
     *   dias_vencido:int,
     *   aging:string,
     *   aging_label:string,
     *   venta_id:?int,
     *   cobranza_id:?int,
     *   lado:string
     * }
     */
    public static function desdeModelo(Cliente_Cuentacorriente $fila, ?Carbon $hoy = null): array
    {
        $hoy = $hoy ?? Carbon::today();
        $aplicado = (float) ($fila->aplicado ?? 0);
        $total = (float) $fila->total;
        $saldoPendiente = ClienteCuentacorrienteGrillaSupport::saldoPendiente($total, $aplicado);
        $saldo = round(abs($saldoPendiente), 4);
        $lado = $total < 0 ? 'credito' : 'deuda';
        $tipo = self::tipo($fila, $lado);
        $vencimiento = optional($fila->fechavencimiento)->format('Y-m-d');
        $dias = 0;
        if ($lado === 'deuda' && $vencimiento) {
            $dias = (int) Carbon::parse($vencimiento)->startOfDay()
                ->diffInDays($hoy->copy()->startOfDay(), false);
        }
        $aging = self::aging($dias, $lado);

        return [
            'id' => (int) $fila->id,
            'tipo' => $tipo,
            'tipo_label' => self::tipoLabel($tipo),
            'abreviatura' => self::abreviatura($fila, $tipo),
            'etiqueta' => self::etiqueta($fila, $tipo),
            'fecha' => optional($fila->fecha)->format('Y-m-d'),
            'vencimiento' => $vencimiento,
            'moneda' => $fila->monedas->abreviatura ?? null,
            'moneda_id' => (int) $fila->moneda_id,
            'cotizacion' => (float) ($fila->cotizacion ?? 1),
            'total' => round($total, 4),
            'aplicado' => round($aplicado, 4),
            'saldo' => $saldo,
            'empresa' => $fila->empresas->nombre ?? null,
            'empresa_id' => (int) $fila->empresa_id,
            'cliente_id' => (int) $fila->cliente_id,
            'dias_vencido' => $dias,
            'aging' => $aging,
            'aging_label' => self::agingLabel($aging, $dias),
            'venta_id' => $fila->venta_id ? (int) $fila->venta_id : null,
            'cobranza_id' => $fila->cobranza_id ? (int) $fila->cobranza_id : null,
            'lado' => $lado,
        ];
    }

    public static function tipo(Cliente_Cuentacorriente $fila, string $lado): string
    {
        $venta = $fila->ventas;
        $tt = $venta?->tipotransacciones;
        $abrev = strtoupper(trim((string) ($tt?->abreviatura ?? '')));
        $signo = (string) ($tt?->signo ?? '');
        $nombre = strtoupper((string) ($tt?->nombre ?? ''));

        if ($lado === 'credito') {
            if ((int) ($fila->cobranza_id ?? 0) > 0) {
                return self::TIPO_PAGO;
            }
            if ($signo === 'R' || str_contains($abrev, 'NC')) {
                return self::TIPO_NC;
            }
            if ($venta) {
                return self::TIPO_NC;
            }

            return self::TIPO_PAGO;
        }

        if (str_contains($abrev, 'ND') || str_contains($nombre, 'DEBITO') || str_contains($nombre, 'DÉBITO')) {
            return self::TIPO_ND;
        }

        return $venta ? self::TIPO_FAC : self::TIPO_OTRO;
    }

    public static function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_NC => 'Nota de crédito',
            self::TIPO_PAGO => 'Cobranza a cuenta',
            self::TIPO_FAC => 'Factura',
            self::TIPO_ND => 'Nota de débito',
            default => 'Movimiento',
        };
    }

    /**
     * Abreviatura del comprobante (tipo de transacción o, en cobranzas, tipocomprobante/detalle).
     */
    public static function abreviatura(Cliente_Cuentacorriente $fila, string $tipo = ''): string
    {
        $venta = $fila->ventas;
        $cobranza = $fila->cobranzas;

        return self::abreviaturaDesdePartes(
            (string) ($venta?->tipotransacciones?->abreviatura ?? ''),
            (string) (
                $cobranza?->tipocomprobante
                ?? $cobranza?->tipotransaccioncajas?->abreviatura
                ?? $cobranza?->detalle
                ?? ''
            ),
            $tipo !== '' ? $tipo : self::tipo($fila, (float) $fila->total < 0 ? 'credito' : 'deuda')
        );
    }

    public static function abreviaturaDesdePartes(
        string $abreviaturaTipoTransaccion,
        string $tipocomprobantePago = '',
        string $tipoFallback = ''
    ): string {
        $abrev = strtoupper(trim($abreviaturaTipoTransaccion));
        if ($abrev !== '') {
            return $abrev;
        }
        $pago = strtoupper(trim($tipocomprobantePago));
        if ($pago !== '') {
            return $pago;
        }

        return strtoupper(trim($tipoFallback));
    }

    /**
     * @return array{etiqueta: string, tipo: string, abreviatura: string}
     */
    public static function resumenEtiqueta(Cliente_Cuentacorriente $fila, string $lado): array
    {
        $tipo = self::tipo($fila, $lado);

        return [
            'etiqueta' => self::etiqueta($fila, $tipo),
            'tipo' => $tipo,
            'abreviatura' => self::abreviatura($fila, $tipo),
        ];
    }

    public static function etiqueta(Cliente_Cuentacorriente $fila, string $tipo): string
    {
        if ((int) ($fila->venta_id ?? 0) > 0 && $fila->ventas) {
            return ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($fila);
        }
        if ((int) ($fila->cobranza_id ?? 0) > 0 && $fila->cobranzas) {
            return ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($fila);
        }
        if ($tipo === self::TIPO_PAGO) {
            return 'Pago a cuenta #'.(int) $fila->id;
        }
        if ((int) ($fila->cobranza_id ?? 0) > 0) {
            return 'Cobranza #'.(int) $fila->cobranza_id;
        }

        return ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($fila);
    }

    public static function aging(int $diasVencido, string $lado): string
    {
        if ($lado !== 'deuda') {
            return 'credito';
        }
        if ($diasVencido > 60) {
            return '60';
        }
        if ($diasVencido > 30) {
            return '30';
        }
        if ($diasVencido > 0) {
            return 'vencida';
        }
        if ($diasVencido === 0) {
            return 'hoy';
        }

        return 'a_vencer';
    }

    public static function agingLabel(string $aging, int $diasVencido): string
    {
        return match ($aging) {
            '60' => 'Vencida +60',
            '30' => 'Vencida +30',
            'vencida' => 'Vencida '.$diasVencido.'d',
            'hoy' => 'Vence hoy',
            'a_vencer' => 'A vencer',
            default => '',
        };
    }
}
