<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

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

    public static function claveDesdeWaitryTipo(?string $waitryTipo): string
    {
        $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($waitryTipo);

        if (WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry($waitryTipo)) {
            return self::CLAVE_QR;
        }

        return match ($tipo) {
            WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO => self::CLAVE_MP,
            'cash' => self::CLAVE_EFECTIVO,
            'totem' => self::CLAVE_TOTEM,
            default => self::CLAVE_OTRO,
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

    public static function esWaitryQr(?string $waitryTipo): bool
    {
        return WaitryMedioPagoCuentacajaSupport::esTipoQrWaitry($waitryTipo);
    }
}
