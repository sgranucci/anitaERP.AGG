<?php

namespace App\Support\Ventas;

/**
 * Aviso al emitir NCL (nota de crédito MiPyME por descuento en cobranza).
 * Reemplazo de envia_mail_ncl.fc (El Bierzo).
 */
final class CobranzaNclDescuentoAvisoSupport
{
    public const MODULO = 'ventas';

    public const CODIGO = 'ncl_descuento_cobranza';

    public const NOMBRE = 'NCL descuento cobranza';

    public const DESCRIPCION = 'Avisa al emitir NCL (crédito por descuento MiPyME) desde cobranza. Reemplaza envia_mail_ncl.fc.';

    /** Destinos legacy envia_mail_ncl.fc (solo seed El Bierzo). */
    public const EMAILS_BIERZO_DEFAULT = [
        'luisav@elbierzo.com.ar',
        'claudiam@elbierzo.com.ar',
    ];
}
