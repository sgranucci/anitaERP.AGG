<?php

declare(strict_types=1);

namespace App\Mail\Direccion;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultaInteligenteListadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $nombreArchivo,
        private readonly string $binario,
        private readonly string $titulo,
        private readonly int $filas,
        private readonly bool $recorte,
    ) {
    }

    public function build(): self
    {
        $cuerpo = 'Adjuntamos el Excel de Consulta Inteligente «'.$this->titulo.'» ('.$this->filas.' fila(s)).';
        if ($this->recorte) {
            $cuerpo .= ' Se recortó al tope de filas configurado.';
        }

        return $this->subject('Consulta Inteligente · '.$this->titulo)
            ->html('<p>'.$cuerpo.'</p><p>Generado '.now()->format('d/m/Y H:i').'</p>')
            ->attachData($this->binario, $this->nombreArchivo, [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
