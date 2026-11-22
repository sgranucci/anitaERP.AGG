<?php

namespace App\Support\Ventas;

/**
 * Aviso estándar al suspender un cliente (reemplazo envia_mail_suspension).
 * Destinatario principal: email del vendedor. ABM puede sumar CC (seed solo El Bierzo).
 */
final class ClienteSuspensionAvisoSupport
{
    public const MODULO = 'ventas';

    public const CODIGO = 'cliente_suspension';

    public const NOMBRE = 'Suspensión de cliente';

    public const DESCRIPCION = 'Avisa al vendedor (y CC del ABM) cuando un cliente pasa a Suspendido.';

    /** @var list<string> Seed Bierzo: vacío — el vendedor se resuelve en runtime. */
    public const EMAILS_BIERZO_DEFAULT = [];
}
