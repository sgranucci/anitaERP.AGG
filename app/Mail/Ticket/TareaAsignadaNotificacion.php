<?php

namespace App\Mail\Ticket;

use App\Models\Ticket\Ticket;
use App\Models\Seguridad\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TareaAsignadaNotificacion extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;

    /** @var array<int, array<string, mixed>> */
    public array $tareasNuevas;

    public Usuario $asignadoPor;

    public ?string $urlTicket;

    /**
     * @param  array<int, array<string, mixed>>  $tareasNuevas
     */
    public function __construct(Ticket $ticket, array $tareasNuevas, Usuario $asignadoPor, ?string $urlTicket = null)
    {
        $this->ticket = $ticket;
        $this->tareasNuevas = $tareasNuevas;
        $this->asignadoPor = $asignadoPor;
        $this->urlTicket = $urlTicket;

        $this->subject('Nueva tarea asignada en su ticket #'.$ticket->id);
    }

    public function build(): self
    {
        return $this->view('mails.ticket.tarea_asignada');
    }
}
