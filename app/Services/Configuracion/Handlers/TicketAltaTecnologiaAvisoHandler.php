<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Ticket\Ticket;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Auth;

class TicketAltaTecnologiaAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $ticket = $this->cargar($entityId);

        return [
            'empresa_id' => (int) (optional($ticket->salas)->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $ticket = $this->cargar($entityId);
        $cargadoPor = Auth::user();

        return [
            'id' => (string) $ticket->id,
            'numero' => (string) $ticket->id,
            'titulo' => (string) ($ticket->titulo ?? '—'),
            'sala' => (string) (optional($ticket->salas)->nombre ?? '—'),
            'sector' => (string) (optional($ticket->sectores)->nombre ?? '—'),
            'categoria' => (string) (optional(optional($ticket->subcategoria_tickets)->categoria_tickets)->nombre ?? '—'),
            'subcategoria' => (string) (optional($ticket->subcategoria_tickets)->nombre ?? '—'),
            'usuario' => (string) (optional($ticket->usuarios)->nombre ?? optional($ticket->usuarios)->usuario ?? '—'),
            'cargado_por' => (string) (optional($cargadoPor)->nombre ?? optional($cargadoPor)->usuario ?? '—'),
            'comentario' => (string) ($ticket->comentario ?? ''),
            'fecha' => $ticket->fecha ? date('d/m/Y', strtotime((string) $ticket->fecha)) : '—',
            'estado' => (string) ($ticket->estado_ticket ?? '—'),
            'area' => (string) (optional($ticket->areadestinos)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'ticket/administracion_ticket/'.$entityId.'/editar'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function cargar(int $entityId): Ticket
    {
        return Ticket::query()
            ->with([
                'usuarios:id,nombre,usuario,email',
                'salas:id,nombre,empresa_id',
                'sectores:id,nombre',
                'areadestinos:id,nombre',
                'subcategoria_tickets.categoria_tickets',
            ])
            ->findOrFail($entityId);
    }
}
