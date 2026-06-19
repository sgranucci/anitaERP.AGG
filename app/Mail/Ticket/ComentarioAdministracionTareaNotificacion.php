<?php

namespace App\Mail\Ticket;

use App\Models\Seguridad\Usuario;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\Ticket_Tarea;
use App\Models\Ticket\Ticket_Tarea_Comentario_Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComentarioAdministracionTareaNotificacion extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;

    public Ticket_Tarea $ticketTarea;

    public Ticket_Tarea_Comentario_Usuario $comentario;

    public Usuario $autor;

    public ?string $urlTicket;

    public function __construct(
        Ticket $ticket,
        Ticket_Tarea $ticketTarea,
        Ticket_Tarea_Comentario_Usuario $comentario,
        Usuario $autor,
        ?string $urlTicket = null
    ) {
        $this->ticket = $ticket;
        $this->ticketTarea = $ticketTarea;
        $this->comentario = $comentario;
        $this->autor = $autor;
        $this->urlTicket = $urlTicket;

        $this->subject('Nuevo comentario en su ticket #'.$ticket->id);
    }

    public function build(): self
    {
        return $this->view('mails.ticket.comentario_administracion_tarea');
    }
}
