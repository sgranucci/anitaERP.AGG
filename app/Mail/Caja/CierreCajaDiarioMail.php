<?php

declare(strict_types=1);

namespace App\Mail\Caja;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CierreCajaDiarioMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{fecha: string, subtitulo: string, total_registros: int}  $resumen
     */
    public function __construct(
        public array $resumen,
        public string $nombreArchivo,
        public string $contenido,
        public string $mime,
    ) {
        $fecha = (string) ($resumen['fecha'] ?? '');
        $this->subject($fecha !== ''
            ? 'Tesorería día '.$fecha
            : 'Tesorería / cierre de caja');
    }

    public function build(): self
    {
        return $this->view('mails.caja.cierre_caja_diario', [
            'resumen' => $this->resumen,
        ])->attachData($this->contenido, $this->nombreArchivo, [
            'mime' => $this->mime,
        ]);
    }
}
