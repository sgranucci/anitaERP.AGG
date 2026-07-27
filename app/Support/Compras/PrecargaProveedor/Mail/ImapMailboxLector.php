<?php

namespace App\Support\Compras\PrecargaProveedor\Mail;

use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Lector IMAP de la casilla de facturas (webklex/php-imap, PHP puro sin ext-imap).
 */
final class ImapMailboxLector implements MailboxLectorInterface
{
    private ?Client $client = null;

    /** @var array<string, Message> */
    private array $mensajesPorUid = [];

    public function mensajesNoLeidos(int $limite): array
    {
        $carpeta = $this->carpetaEntrada();

        $mensajes = $carpeta->messages()
            ->unseen()
            ->setFetchOrder('desc')
            ->limit(max(1, $limite))
            ->get();

        $out = [];
        foreach ($mensajes as $mensaje) {
            /** @var Message $mensaje */
            $dto = $this->aDto($mensaje);
            $this->mensajesPorUid[$dto->uid] = $mensaje;
            $out[] = $dto;
        }

        return $out;
    }

    public function marcarProcesado(MailFacturaMensaje $mensaje): void
    {
        $this->marcarYMover($mensaje, (string) config('precarga_comprobante_mail.carpeta_procesados'));
    }

    public function moverAError(MailFacturaMensaje $mensaje): void
    {
        $this->marcarYMover($mensaje, (string) config('precarga_comprobante_mail.carpeta_errores'));
    }

    public function marcarLeido(MailFacturaMensaje $mensaje): void
    {
        $original = $this->buscarMensajeOriginal($mensaje);
        if ($original === null) {
            return;
        }
        $original->setFlag('Seen');
    }

    private function marcarYMover(MailFacturaMensaje $mensaje, string $carpetaDestino): void
    {
        $original = $this->buscarMensajeOriginal($mensaje);
        if ($original === null) {
            return;
        }

        $original->setFlag('Seen');

        if ($carpetaDestino === '') {
            return;
        }

        $this->asegurarCarpeta($carpetaDestino);
        $original->move($carpetaDestino);
        unset($this->mensajesPorUid[$mensaje->uid]);
    }

    /**
     * Busca el mensaje en la conexión actual o, si el DTO vino de otro proceso
     * (job en cola), lo reencuentra en la carpeta de entrada por Message-ID.
     */
    private function buscarMensajeOriginal(MailFacturaMensaje $mensaje): ?Message
    {
        $enMemoria = $this->mensajesPorUid[$mensaje->uid] ?? null;
        if ($enMemoria !== null) {
            return $enMemoria;
        }

        if (str_starts_with($mensaje->messageId, 'uid-')) {
            return null;
        }

        try {
            $encontrados = $this->carpetaEntrada()->messages()
                ->whereHeader('Message-ID', $mensaje->messageId)
                ->limit(1)
                ->get();
        } catch (Throwable) {
            return null;
        }

        foreach ($encontrados as $encontrado) {
            /** @var Message $encontrado */
            $this->mensajesPorUid[$mensaje->uid] = $encontrado;

            return $encontrado;
        }

        return null;
    }

    private function aDto(Message $mensaje): MailFacturaMensaje
    {
        $adjuntos = [];
        foreach ($mensaje->getAttachments() as $adjunto) {
            $nombre = trim((string) $adjunto->getName());
            $mime = strtolower((string) $adjunto->getMimeType());
            $esPdf = $mime === 'application/pdf'
                || str_ends_with(strtolower($nombre), '.pdf');
            if (! $esPdf) {
                continue;
            }
            $contenido = (string) $adjunto->getContent();
            if ($contenido === '') {
                continue;
            }
            $adjuntos[] = [
                'nombre' => $nombre !== '' ? $nombre : 'factura.pdf',
                'contenido' => $contenido,
                'hash' => hash('sha256', $contenido),
            ];
        }

        $remitente = '';
        $from = $mensaje->getFrom();
        if ($from !== null) {
            $primero = collect($from->all() ?? [])->first();
            $remitente = (string) ($primero->mail ?? '');
        }

        $fecha = null;
        $fechaAttr = $mensaje->getDate();
        if ($fechaAttr !== null) {
            $valor = $fechaAttr->toDate();
            if ($valor instanceof \DateTimeInterface) {
                $fecha = $valor;
            }
        }

        $cuerpo = '';
        if ($mensaje->hasTextBody()) {
            $cuerpo = (string) $mensaje->getTextBody();
        } elseif ($mensaje->hasHTMLBody()) {
            $cuerpo = strip_tags((string) $mensaje->getHTMLBody());
        }

        return new MailFacturaMensaje(
            messageId: trim((string) $mensaje->getMessageId()) ?: 'uid-'.$mensaje->getUid(),
            uid: (string) $mensaje->getUid(),
            remitente: $remitente,
            asunto: trim((string) $mensaje->getSubject()),
            cuerpoTexto: $cuerpo,
            fechaMensaje: $fecha,
            adjuntosPdf: $adjuntos,
        );
    }

    private function carpetaEntrada(): Folder
    {
        $nombre = (string) config('precarga_comprobante_mail.carpeta', 'INBOX');
        $carpeta = $this->client()->getFolderByPath($nombre, false, true);
        if ($carpeta === null) {
            throw new RuntimeException("No existe la carpeta IMAP de entrada: {$nombre}");
        }

        return $carpeta;
    }

    private function asegurarCarpeta(string $nombre): void
    {
        $client = $this->client();
        try {
            if ($client->getFolderByPath($nombre, false, true) !== null) {
                return;
            }
        } catch (Throwable) {
            // Carpeta inexistente: se crea abajo.
        }
        $client->createFolder($nombre);
    }

    private function client(): Client
    {
        if ($this->client !== null && $this->client->isConnected()) {
            return $this->client;
        }

        $imap = (array) config('precarga_comprobante_mail.imap', []);
        $manager = new ClientManager();
        $this->client = $manager->make([
            'host' => (string) ($imap['host'] ?? 'outlook.office365.com'),
            'port' => (int) ($imap['port'] ?? 993),
            'encryption' => $imap['encryption'] ?? 'ssl',
            'validate_cert' => (bool) ($imap['validate_cert'] ?? true),
            'username' => (string) ($imap['username'] ?? ''),
            'password' => (string) ($imap['password'] ?? ''),
            'protocol' => 'imap',
        ]);
        $this->client->connect();

        return $this->client;
    }
}
