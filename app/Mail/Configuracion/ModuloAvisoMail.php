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
     * El PDF viaja en base64. El binario crudo rompe json_encode al encolar el mail
     * (Malformed UTF-8) y el aviso nunca sale.
     *
     * @var array{contenido: string, nombre: string, mime: string}|null
     */
    private ?array $pdfAdjunto;

    /**
     * @param  array{bytes?: string, filename?: string, contenido?: string, nombre?: string, mime?: string}|null  $pdfAdjunto
     */
    public function __construct(
        string $asunto,
        string $textoCuerpo,
        string $tituloEvento,
        ?string $linkConsulta,
        ?array $pdfAdjunto = null,
    ) {
        $this->subject($this->textoUtf8($asunto));
        $this->textoCuerpo = $this->textoUtf8($textoCuerpo);
        $this->tituloEvento = $this->textoUtf8($tituloEvento);
        $this->linkConsulta = $linkConsulta !== null ? $this->textoUtf8($linkConsulta) : null;
        $this->pdfAdjunto = $this->normalizarPdf($pdfAdjunto);
    }

    public function build(): self
    {
        $mail = $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.configuracion.modulo_aviso');

        if ($this->pdfAdjunto !== null) {
            $bytes = base64_decode($this->pdfAdjunto['contenido'], true);
            if ($bytes !== false && $bytes !== '') {
                $mail->attachData(
                    $bytes,
                    $this->pdfAdjunto['nombre'],
                    ['mime' => $this->pdfAdjunto['mime']]
                );
            }
        }

        return $mail;
    }

    /**
     * @param  array{bytes?: string, filename?: string, contenido?: string, nombre?: string, mime?: string}|null  $pdf
     * @return array{contenido: string, nombre: string, mime: string}|null
     */
    private function normalizarPdf(?array $pdf): ?array
    {
        if ($pdf === null) {
            return null;
        }

        $bytes = (string) ($pdf['bytes'] ?? $pdf['contenido'] ?? '');
        $nombre = trim((string) ($pdf['filename'] ?? $pdf['nombre'] ?? ''));
        if ($bytes === '' || $nombre === '') {
            return null;
        }

        return [
            'contenido' => base64_encode($bytes),
            'nombre' => $this->textoUtf8($nombre),
            'mime' => $this->textoUtf8((string) ($pdf['mime'] ?? 'application/pdf')) ?: 'application/pdf',
        ];
    }

    private function textoUtf8(string $texto): string
    {
        if ($texto === '' || preg_match('//u', $texto) === 1) {
            return $texto;
        }

        $limpio = iconv('UTF-8', 'UTF-8//IGNORE', $texto);

        return is_string($limpio) ? $limpio : '';
    }
}
