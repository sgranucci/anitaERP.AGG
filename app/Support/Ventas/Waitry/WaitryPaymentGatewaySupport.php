<?php

namespace App\Support\Ventas\Waitry;

/**
 * Gateway en payment.payments (Control Z / identificación de medio en Waitry).
 *
 * @see docs/waitry/README.md — pushExternalOrder
 */
final class WaitryPaymentGatewaySupport
{
    public const GATEWAY_DEFAULT = 'ANITA';

    /** QR Mercado Pago en kiosco Waitry (payment.payments[].gateway). */
    public const GATEWAY_KIOSK_MPQR = 'KIOSK MPQR';

    /** Terminal Posnet junto al kiosco (nueva config Waitry). */
    public const GATEWAY_KIOSK_MP = 'KIOSK MP';

    /** Prefijo external_reference_id de órdenes inyectadas desde Anita (pushExternalOrder). */
    public const PREFIJO_REFERENCIA_PUSH_ERP = 'E-';

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return list<array{gateway: string, amount: float}>
     */
    public function armarPaymentsDesdeMediosPago(array $mediosPago, int $empresaId): array
    {
        $payments = [];

        foreach ($mediosPago as $medio) {
            if (! is_array($medio)) {
                continue;
            }
            $monto = round((float) ($medio['monto'] ?? 0), 2);
            if ($monto <= 0.) {
                continue;
            }

            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $gateway = $cuentacajaId > 0
                ? $this->resolverGatewayPorCuentacajaId($cuentacajaId, $empresaId)
                : self::GATEWAY_DEFAULT;

            $payments[] = [
                'gateway' => $gateway,
                'amount' => $monto,
            ];
        }

        if ($payments === []) {
            return [];
        }

        return $payments;
    }

    public function resolverGatewayPorTipoWaitry(string $tipoWaitry): string
    {
        $tipoNorm = WaitryMedioPagoCuentacajaSupport::normalizarTipo($tipoWaitry);
        if ($tipoNorm === null) {
            return self::GATEWAY_DEFAULT;
        }

        $mapa = config('waitry.pago_gateway_por_tipo', []);
        if (is_array($mapa) && isset($mapa[$tipoNorm])) {
            $gateway = trim((string) $mapa[$tipoNorm]);

            return $gateway !== '' ? $gateway : self::GATEWAY_DEFAULT;
        }

        return match ($tipoNorm) {
            WaitryPaymentTypeSupport::TIPO_MERCADOPAGO => 'MERCADOPAGO',
            WaitryPaymentTypeSupport::TIPO_TOTALCOIN => 'TOTALCOIN',
            WaitryPaymentTypeSupport::TIPO_CASH => 'CASH',
            WaitryPaymentTypeSupport::TIPO_CREDIT_CARD => 'CREDIT_CARD',
            WaitryPaymentTypeSupport::TIPO_DEBIT_CARD => 'DEBIT_CARD',
            default => self::GATEWAY_DEFAULT,
        };
    }

    private function resolverGatewayPorCuentacajaId(int $cuentacajaId, int $empresaId): string
    {
        $paymentType = new WaitryPaymentTypeSupport;
        $tipo = $paymentType->resolverPorCuentacajaId($cuentacajaId, $empresaId);

        return $this->resolverGatewayPorTipoWaitry($tipo);
    }

    /**
     * Normaliza gateway Waitry para comparación (sin espacios/guiones, minúsculas).
     */
    public static function normalizarGateway(?string $gateway): ?string
    {
        if ($gateway === null) {
            return null;
        }

        $gateway = mb_strtolower(trim($gateway));
        if ($gateway === '') {
            return null;
        }

        $gateway = str_replace([' ', '-', '_'], '', $gateway);

        return $gateway !== '' ? $gateway : null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerGatewayDesdeOrden(array $orden): ?string
    {
        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return null;
        }

        return self::extraerGatewayDesdePayment($payment);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    public static function extraerGatewayDesdePayment(array $payment): ?string
    {
        $payments = $payment['payments'] ?? null;
        if (! is_array($payments) || $payments === []) {
            return null;
        }

        foreach ($payments as $pago) {
            if (! is_array($pago)) {
                continue;
            }
            $gateway = trim((string) ($pago['gateway'] ?? ''));
            if ($gateway !== '') {
                return $gateway;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function extraerGatewayDesdeLinea(array $linea): ?string
    {
        $desdeLinea = trim((string) ($linea['waitry_payment_gateway'] ?? ''));
        if ($desdeLinea !== '') {
            return $desdeLinea;
        }

        $payment = $linea['payment'] ?? null;
        if (is_array($payment)) {
            $desdePayment = self::extraerGatewayDesdePayment($payment);
            if ($desdePayment !== null) {
                return $desdePayment;
            }
        }

        return null;
    }

    /**
     * Cobro QR MP en kiosco: credit_card + gateway KIOSK MPQR.
     */
    public static function esGatewayQrKiosko(?string $gateway): bool
    {
        return self::normalizarGateway($gateway) === self::normalizarGateway(self::GATEWAY_KIOSK_MPQR);
    }

    /**
     * Cobro Posnet del kiosco: credit_card sin payments o gateway terminal (KIOSK MP, MERCADOPAGO, …).
     */
    public static function esGatewayPosnetKiosko(?string $gateway): bool
    {
        if (self::esGatewayQrKiosko($gateway)) {
            return false;
        }

        $norm = self::normalizarGateway($gateway);
        if ($norm === null) {
            return true;
        }

        return in_array($norm, [
            self::normalizarGateway(self::GATEWAY_KIOSK_MP),
            'mercadopago',
            'kiosk',
            'creditcard',
            'credit_card',
        ], true);
    }

    /**
     * Orden originada en Anita y replicada a Waitry (pushExternalOrder / mostrador).
     * No confundir con {@code payment.type=interface} de cobro QR por celular en Waitry.
     *
     * @param  array<string, mixed>  $ordenOLinea
     */
    public static function esOrdenPushErp(array $ordenOLinea): bool
    {
        foreach (['external_reference_id', 'display_id', 'referencia_waitry'] as $campo) {
            $ref = trim((string) ($ordenOLinea[$campo] ?? ''));
            if ($ref !== '' && str_starts_with(strtoupper($ref), self::PREFIJO_REFERENCIA_PUSH_ERP)) {
                return true;
            }
        }

        $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo(
            WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($ordenOLinea)
                ?? ($ordenOLinea['waitry_tipo_pago'] ?? null),
        );
        if ($tipo !== WaitryMedioPagoCuentacajaSupport::normalizarTipo(WaitryPaymentTypeSupport::TIPO_INTERFACE)) {
            return false;
        }

        if (empty($ordenOLinea['facturada_erp'])) {
            return false;
        }

        if (! empty($ordenOLinea['anita_es_totem']) || ! empty($ordenOLinea['waitry_cobro_totem'])) {
            return false;
        }

        return true;
    }
}
