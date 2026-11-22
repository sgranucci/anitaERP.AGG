<?php

namespace App\Support\Ventas;

/**
 * Aviso estándar al detectar cliente retenido / con problemas AFIP-ARCA
 * (reemplazo envia_mail_vendedor.fc). Destinatario principal: email del vendedor.
 */
final class ClienteRetenidoAfipAvisoSupport
{
    public const MODULO = 'ventas';

    public const CODIGO = 'cliente_retenido_afip';

    public const NOMBRE = 'Cliente retenido AFIP / ARCA';

    public const DESCRIPCION = 'Avisa al vendedor cuando el padrón ARCA bloquea la operación o el control AFIP detecta problemas.';

    /** @var list<string> Seed Bierzo: vacío — el vendedor se resuelve en runtime. */
    public const EMAILS_BIERZO_DEFAULT = [];
}
