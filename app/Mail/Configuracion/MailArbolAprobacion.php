<?php

namespace App\Mail\Configuracion;

use App\Services\Solicitudpago\SolicitudpagoMailAdjuntosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailArbolAprobacion extends Mailable
{
    use Queueable, SerializesModels;

    public $datosComprobante;
    public $tipoArbol;
    public $linkAprobacion, $linkRechazo;
    public $linkVisualizar;

    /** @var array<string, mixed>|null Datos extra (estado al aprobar, monto ítems, enlaces SP, etc.). */
    public $mailExtras;

    /**
     * Create a new message instance.
     *
     * @param  array<string, mixed>|null  $mailExtras
     * @return void
     */
    public function __construct($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras = null)
    {
        $this->datosComprobante = $ptrcomprobante;
        $this->tipoArbol = $tipoarbol;
        $this->linkAprobacion = $linkaprobacion;
        $this->linkRechazo = $linkrechazo;
        $this->linkVisualizar = $linkvisualizar;
        $this->mailExtras = $mailExtras;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.configuracion.arbolaprobacion');

        if ($this->tipoArbol === 'Solicitudes de pago') {
            $this->adjuntarDocumentosSolicitudpago($mail);
        }

        return $mail;
    }

    /**
     * Adjunta el PDF de la SP y cada archivo asociado (botones del cuerpo se mantienen).
     */
    private function adjuntarDocumentosSolicitudpago(self $mail): void
    {
        $id = (int) ($this->datosComprobante->id ?? 0);
        if ($id <= 0) {
            return;
        }

        try {
            $paquete = app(SolicitudpagoMailAdjuntosService::class)->armarParaMail($id);
        } catch (\Throwable $e) {
            report($e);
            $this->mailExtras = array_merge(is_array($this->mailExtras) ? $this->mailExtras : [], [
                'adjuntos_mail_omitidos' => ['No se pudieron generar los adjuntos del correo'],
            ]);

            return;
        }

        foreach ($paquete['adjuntos'] as $adj) {
            $nombre = (string) ($adj['nombre'] ?? 'adjunto');
            $mime = (string) ($adj['mime'] ?? 'application/octet-stream');
            if (($adj['modo'] ?? '') === 'data' && ! empty($adj['contenido'])) {
                $mail->attachData($adj['contenido'], $nombre, ['mime' => $mime]);

                continue;
            }
            if (($adj['modo'] ?? '') === 'path' && ! empty($adj['path']) && is_readable($adj['path'])) {
                $mail->attach($adj['path'], [
                    'as' => $nombre,
                    'mime' => $mime,
                ]);
            }
        }

        $this->mailExtras = array_merge(is_array($this->mailExtras) ? $this->mailExtras : [], [
            'adjuntos_mail_cantidad' => count($paquete['adjuntos']),
            'adjuntos_mail_omitidos' => $paquete['omitidos'] ?? [],
        ]);
    }
}
