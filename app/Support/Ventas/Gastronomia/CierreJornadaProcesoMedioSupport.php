<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryPaymentGatewaySupport;

/**
 * Resolución de medios de pago (QR/MP/Efectivo/TOTEM) para el proceso de cierre de jornada.
 */
final class CierreJornadaProcesoMedioSupport
{
    public const CLAVE_QR = 'qr';

    public const CLAVE_MP = 'mp';

    public const CLAVE_EFECTIVO = 'efectivo';

    public const CLAVE_TOTEM = 'totem';

    public const CLAVE_OTRO = 'otro';

    /**
     * @param  array{id:int,codigo?:string,nombre?:string}|null  $cuentaCaja
     */
    public static function claveDesdeCuentacaja(?array $cuentaCaja, int $empresaId): string
    {
        if ($cuentaCaja === null || (int) ($cuentaCaja['id'] ?? 0) <= 0) {
            return self::CLAVE_OTRO;
        }

        $id = (int) $cuentaCaja['id'];
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        if ($totem !== null && (int) $totem['id'] === $id) {
            return self::CLAVE_TOTEM;
        }

        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        if ($efectivoId !== null && $efectivoId === $id) {
            return self::CLAVE_EFECTIVO;
        }

        foreach (WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa($empresaId) as $tipo => $cuenta) {
            if ((int) ($cuenta['id'] ?? 0) === $id) {
                return match ($tipo) {
                    WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN => self::CLAVE_QR,
                    WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO => self::CLAVE_MP,
                    default => self::CLAVE_OTRO,
                };
            }
        }

        $codigo = mb_strtolower(trim((string) ($cuentaCaja['codigo'] ?? '')));

        return match (true) {
            str_contains($codigo, 'coin') || str_contains($codigo, 'qr') => self::CLAVE_QR,
            str_contains($codigo, 'mp') || str_contains($codigo, 'mercado') => self::CLAVE_MP,
            str_contains($codigo, 'efe') || $codigo === 'cash' => self::CLAVE_EFECTIVO,
            default => self::CLAVE_OTRO,
        };
    }

    public static function claveDesdeWaitryTipo(?string $waitryTipo, ?string $gateway = null): string
    {
        if (WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry($waitryTipo, $gateway)) {
            return self::CLAVE_QR;
        }

        $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($waitryTipo);

        if (WaitryMedioPagoCuentacajaSupport::esCreditCardPosnet($waitryTipo, $gateway)) {
            return self::CLAVE_MP;
        }

        if ($tipo === 'interface') {
            $clavePush = self::claveDesdeGatewayPush($gateway);
            if ($clavePush !== null) {
                return $clavePush;
            }
        }

        return match ($tipo) {
            WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO => self::CLAVE_MP,
            'cash' => self::CLAVE_EFECTIVO,
            'totem' => self::CLAVE_TOTEM,
            default => self::CLAVE_OTRO,
        };
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function claveDesdeWaitryLinea(array $linea): string
    {
        return self::claveDesdeWaitryTipo(
            $linea['waitry_tipo_pago'] ?? null,
            WaitryPaymentGatewaySupport::extraerGatewayDesdeLinea($linea),
        );
    }

    public static function claveDesdeGatewayPush(?string $gateway): ?string
    {
        return match (WaitryPaymentGatewaySupport::normalizarGateway($gateway)) {
            'cash' => self::CLAVE_EFECTIVO,
            'mercadopago' => self::CLAVE_MP,
            'totalcoin' => self::CLAVE_QR,
            default => null,
        };
    }

    public static function etiquetaClave(string $clave): string
    {
        return match ($clave) {
            self::CLAVE_QR => 'QR (Totalcoin)',
            self::CLAVE_MP => 'Mercado Pago',
            self::CLAVE_EFECTIVO => 'Efectivo',
            self::CLAVE_TOTEM => 'TOTEM (puente)',
            default => 'Otro',
        };
    }

    public static function esWaitryCash(?string $waitryTipo): bool
    {
        return WaitryMedioPagoCuentacajaSupport::normalizarTipo($waitryTipo) === 'cash';
    }

    public static function esWaitryQr(?string $waitryTipo, ?string $gateway = null): bool
    {
        return WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry($waitryTipo, $gateway);
    }

    /**
     * Mercado Pago Waitry: mercadopago o credit_card cobrado en Posnet del kiosco.
     */
    public static function esWaitryMp(?string $waitryTipo, ?string $gateway = null): bool
    {
        if (WaitryMedioPagoCuentacajaSupport::esCreditCardPosnet($waitryTipo, $gateway)) {
            return true;
        }

        return WaitryMedioPagoCuentacajaSupport::normalizarTipo($waitryTipo)
            === WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO;
    }

    /**
     * Sin facturar: elegible para redistribución Waitry → efectivo (QR o MP/Posnet).
     */
    public static function esWaitrySinFacturarRedistribuible(?string $waitryTipo, ?string $gateway = null): bool
    {
        return self::esWaitryQr($waitryTipo, $gateway) || self::esWaitryMp($waitryTipo, $gateway);
    }

    /**
     * Medios Waitry sin facturar que pueden quedar en la factura del proceso (QR o MP).
     *
     * @return list<string>
     */
    public static function clavesMedioFacturableSinFacturar(): array
    {
        return [self::CLAVE_QR, self::CLAVE_MP];
    }

    /**
     * Medio Waitry (clave cuadro) redistribuible a efectivo en el cierre.
     */
    public static function esMedioWaitryRedistribuibleAEfectivo(string $clave): bool
    {
        return in_array($clave, self::clavesMedioFacturableSinFacturar(), true);
    }
}
