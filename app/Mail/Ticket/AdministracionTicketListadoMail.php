<?php

namespace App\Mail\Ticket;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdministracionTicketListadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombreArchivo,
        public string $contenido,
        public int $filas,
        public bool $recorte,
    ) {}

    public function build(): self
    {
        return $this->subject('Listado de administración de tickets')
            ->view('mails.ticket.administracion_ticket_listado', [
                'filas' => $this->filas,
                'recorte' => $this->recorte,
            ])
            ->attachData($this->contenido, $this->nombreArchivo, [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
