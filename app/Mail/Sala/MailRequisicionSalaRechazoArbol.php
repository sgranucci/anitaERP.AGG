<?php

namespace App\Mail\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Seguridad\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailRequisicionSalaRechazoArbol extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Usuario $solicitante,
        public RequisicionSala $requisicion,
        public ?Usuario $rechazador,
        public string $motivoRechazo,
        public string $linkEditar,
    ) {
    }

    public function build()
    {
        $numero = $this->requisicion->numerorequisicion ?? $this->requisicion->id;

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Requisición de sala #'.$numero.' rechazada')
            ->view('mails.sala.requisicion_sala_rechazo_arbol');
    }
}
