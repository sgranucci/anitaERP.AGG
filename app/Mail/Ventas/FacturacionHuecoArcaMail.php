<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturacionHuecoArcaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{cargados: list<array<string, mixed>>, errores: list<array<string, mixed>>, omitidos: list<array<string, mixed>>}  $informe
     */
    public function __construct(
        public readonly array $informe,
    ) {
        $cargados = count($informe['cargados'] ?? []);
        $errores = count($informe['errores'] ?? []);
        $fecha = now()->format('d/m/Y');
        if ($cargados > 0 && $errores === 0) {
            $asunto = 'Comprobantes de ARCA recuperados en el ERP — '.$fecha;
        } elseif ($cargados > 0) {
            $asunto = 'Comprobantes de ARCA recuperados, con errores — '.$fecha;
        } else {
            $asunto = 'No se pudieron cargar comprobantes autorizados en ARCA — '.$fecha;
        }

        $this->subject($asunto);
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.ventas.facturacion_hueco_arca');
    }
}
