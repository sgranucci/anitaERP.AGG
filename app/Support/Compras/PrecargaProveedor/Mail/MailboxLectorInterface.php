<?php

namespace App\Support\Compras\PrecargaProveedor\Mail;

/**
 * Lector de la casilla de facturas de proveedor.
 *
 * Implementación actual: IMAP (webklex/php-imap). La interfaz permite sumar
 * un driver Microsoft Graph a futuro cambiando solo la config.
 */
interface MailboxLectorInterface
{
    /**
     * Mensajes no leídos de la carpeta de entrada, más nuevos primero.
     *
     * @return list<MailFacturaMensaje>
     */
    public function mensajesNoLeidos(int $limite): array;

    /** Marca leído y mueve a la carpeta de procesados. */
    public function marcarProcesado(MailFacturaMensaje $mensaje): void;

    /** Marca leído y mueve a la carpeta de errores. */
    public function moverAError(MailFacturaMensaje $mensaje): void;

    /** Marca leído sin mover (mensajes ignorados, sin adjunto PDF). */
    public function marcarLeido(MailFacturaMensaje $mensaje): void;
}
