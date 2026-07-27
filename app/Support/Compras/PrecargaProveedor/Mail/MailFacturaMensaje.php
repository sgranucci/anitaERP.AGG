<?php

namespace App\Support\Compras\PrecargaProveedor\Mail;

/**
 * Mensaje de la casilla de facturas con sus adjuntos PDF.
 *
 * Cada adjunto: ['nombre' => string, 'contenido' => string (binario), 'hash' => string sha256].
 */
final class MailFacturaMensaje
{
    /**
     * @param  list<array{nombre: string, contenido: string, hash: string}>  $adjuntosPdf
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $uid,
        public readonly string $remitente,
        public readonly string $asunto,
        public readonly string $cuerpoTexto,
        public readonly ?\DateTimeInterface $fechaMensaje,
        public readonly array $adjuntosPdf,
    ) {}

    public function tieneAdjuntosPdf(): bool
    {
        return $this->adjuntosPdf !== [];
    }
}
