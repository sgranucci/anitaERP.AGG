<?php

namespace App\Mail\Configuracion;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ModuloAvisoMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Reintentos ante fallos transitorios (p. ej. SMTP inaccesible). */
    public int $tries = 4;

    /**
     * Segundos de espera entre intentos: 1 min → 5 min → 15 min.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public string $textoCuerpo;

    public string $tituloEvento;

    public ?string $linkConsulta;

    /**
     * @param  array{contenido: string, nombre: string}|null  $pdfAdjunto
     */
    public function __construct(
        string $asunto,
        string $textoCuerpo,
        string $tituloEvento,
        ?string $linkConsulta,
        private ?array $pdfAdjunto = null,
    ) {
        $this->subject($asunto);
        $this->textoCuerpo = $textoCuerpo;
        $this->tituloEvento = $tituloEvento;
        $this->linkConsulta = $linkConsulta;
    }

    public function build(): self
    {
        $mail = $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.configuracion.modulo_aviso');

        if ($this->pdfAdjunto && ! empty($this->pdfAdjunto['contenido']) && ! empty($this->pdfAdjunto['nombre'])) {
            $mail->attachData(
                $this->pdfAdjunto['contenido'],
                $this->pdfAdjunto['nombre'],
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
