<?php

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ControlAfipPedidosResumenMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        public array $informe,
        public string $pdfContenido,
        public string $nombrePdf,
        public string $excelContenido,
        public string $nombreExcel,
    ) {
    }

    public function build(): self
    {
        $fecha = (string) ($this->informe['fecha_control'] ?? '');
        $fechaTxt = $fecha !== '' ? (date('d/m/Y', strtotime($fecha)) ?: $fecha) : '';
        $n = (int) ($this->informe['problemas'] ?? 0);
        $asunto = sprintf('Clientes con problemas de AFIP %s (%d)', $fechaTxt, $n);

        $mail = $this->subject($asunto)
            ->view('mails.ventas.control_afip_pedidos_resumen');

        if ($this->pdfContenido !== '') {
            $mail->attachData($this->pdfContenido, $this->nombrePdf, ['mime' => 'application/pdf']);
        }
        if ($this->excelContenido !== '') {
            $mail->attachData(
                $this->excelContenido,
                $this->nombreExcel,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        return $mail;
    }
}
