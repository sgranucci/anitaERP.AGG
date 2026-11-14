<?php

declare(strict_types=1);

namespace App\Mail\Compras;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoproveedorEnvioMasivoResultadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{
     *   ok: bool,
     *   ok_proceso: bool,
     *   enviadas: int,
     *   fallidas: int,
     *   total: int,
     *   errores: list<array{etiqueta: string, mensaje: string}>,
     *   detalle: list<array{etiqueta: string, ok: bool, mensaje: string}>,
     *   error_fatal?: string|null,
     *   usuario_nombre?: string,
     *   empresa_id?: int,
     *   fecha_desde?: string,
     *   fecha_hasta?: string,
     *   medio?: string
     * }  $datos
     */
    public function __construct(
        public readonly array $datos,
    ) {
        $enviadas = (int) ($datos['enviadas'] ?? 0);
        $fallidas = (int) ($datos['fallidas'] ?? 0);
        $ok = (bool) ($datos['ok'] ?? false);
        if ($ok) {
            $this->subject('Envío de OP a proveedores listo — '.$enviadas.' correo(s)');
        } elseif ($enviadas > 0) {
            $this->subject('Envío de OP a proveedores parcial — '.$enviadas.' ok, '.$fallidas.' con error');
        } else {
            $this->subject('Envío de OP a proveedores con errores');
        }
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.compras.pagoproveedor_envio_masivo_resultado', [
                'datos' => $this->datos,
            ]);
    }
}
