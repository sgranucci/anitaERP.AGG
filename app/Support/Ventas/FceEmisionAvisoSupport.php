<?php

namespace App\Support\Ventas;

/**
 * Aviso estándar al emitir FCE / NCE / NDE (reemplazo FACEL_envia_mail_aviso_fce).
 * Sin gate de entorno: el tipo existe en todos los clientes; destinatarios se cargan por ABM.
 */
final class FceEmisionAvisoSupport
{
    public const MODULO = 'ventas';

    public const CODIGO = 'fce_emision';

    /** @var list<string> */
    public const ABREVIATURAS = ['FCE', 'NCE', 'NDE'];

    /** Destinos legacy a-comprob (solo seed El Bierzo). */
    public const EMAILS_BIERZO_DEFAULT = [
        'claudiam@elbierzo.com.ar',
        'luisav@elbierzo.com.ar',
        'carolinal@elbierzo.com.ar',
    ];

    public static function esAbreviaturaFce(string $abreviatura): bool
    {
        return in_array(strtoupper(trim($abreviatura)), self::ABREVIATURAS, true);
    }
}
