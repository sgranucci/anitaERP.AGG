<?php

declare(strict_types=1);

namespace App\Mail\Contable;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MayorPlanoCuentaListoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   ok: bool,
     *   periodo: string,
     *   empresas: string,
     *   lineas: int,
     *   mensaje: string,
     *   url_descarga?: string,
     *   nombre_archivo?: string,
     *   adjunto_path?: string,
     *   usuario_nombre?: string
     * }  $datos
     */
    public function __construct(
        public readonly array $datos,
    ) {
        $ok = (bool) ($datos['ok'] ?? false);
        $periodo = trim((string) ($datos['periodo'] ?? ''));
        $this->subject(
            ($ok ? 'Mayor plano listo' : 'Mayor plano con error')
            .($periodo !== '' ? ' — '.$periodo : '')
        );
    }

    public function build(): self
    {
        $mail = $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.contable.mayor_plano_cuenta_listo', [
                'datos' => $this->datos,
            ]);

        $adjunto = (string) ($this->datos['adjunto_path'] ?? '');
        $nombre = (string) ($this->datos['nombre_archivo'] ?? 'mayor_plano.csv');
        if ($adjunto !== '' && is_file($adjunto)) {
            $mail->attach($adjunto, [
                'as' => $nombre !== '' ? $nombre : 'mayor_plano.csv',
                'mime' => 'text/csv',
            ]);
        }

        return $mail;
    }
}
