<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Ticket\Ticket_Tarea;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketAsignacionTecnicoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $ticketTarea = $this->cargar($entityId);
        $tecnicoUsuario = optional($ticketTarea->tecnicos)->usuarios;
        $emailTecnico = strtolower(trim((string) ($tecnicoUsuario->email ?? '')));

        $emails = [];
        if ($emailTecnico !== '' && filter_var($emailTecnico, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $emailTecnico;
        } else {
            Log::warning('Ticket asignacion tecnico: técnico sin email de usuario', [
                'ticket_tarea_id' => $entityId,
                'ticket_id' => $ticketTarea->ticket_id,
                'tecnico_id' => $ticketTarea->tecnico_id,
            ]);
        }

        $emails = array_values(array_unique(array_merge(
            $emails,
            $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $this->contextoFiltro($entityId))
        )));

        if ($emails === []) {
            Log::info('Ticket asignacion tecnico: sin destinatarios', [
                'ticket_tarea_id' => $entityId,
            ]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail(
                    $asunto,
                    $texto,
                    $tipo->nombre,
                    $linkConsulta,
                    null
                );
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::error('Ticket asignacion tecnico: falló envío a destinatario', [
                    'email' => $email,
                    'ticket_tarea_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        $ticketTarea = $this->cargar($entityId);
        $ticket = $ticketTarea->tickets;

        return [
            'empresa_id' => (int) (optional(optional($ticket)->salas)->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $ticketTarea = $this->cargar($entityId);
        $ticket = $ticketTarea->tickets;
        $asignadoPor = Auth::user();

        return [
            'id' => (string) ($ticket->id ?? '—'),
            'numero' => (string) ($ticket->id ?? '—'),
            'titulo' => (string) ($ticket->titulo ?? '—'),
            'tarea' => (string) ($ticketTarea->detalle ?: optional($ticketTarea->tareas)->nombre ?: '—'),
            'tecnico' => (string) (optional($ticketTarea->tecnicos)->nombre ?? '—'),
            'turno' => (string) (optional($ticketTarea->turnos)->nombre ?? '—'),
            'asignado_por' => (string) (optional($asignadoPor)->nombre ?? optional($asignadoPor)->usuario ?? '—'),
            'usuario' => (string) (optional(optional($ticket)->usuarios)->nombre
                ?? optional(optional($ticket)->usuarios)->usuario
                ?? '—'),
            'sala' => (string) (optional(optional($ticket)->salas)->nombre ?? '—'),
            'sector' => (string) (optional(optional($ticket)->sectores)->nombre ?? '—'),
            'categoria' => (string) (optional(optional(optional($ticket)->subcategoria_tickets)->categoria_tickets)->nombre ?? '—'),
            'subcategoria' => (string) (optional(optional($ticket)->subcategoria_tickets)->nombre ?? '—'),
            'comentario' => (string) ($ticket->comentario ?? ''),
            'fecha' => optional($ticket)->fecha ? date('d/m/Y', strtotime((string) $ticket->fecha)) : '—',
            'fechaprogramacion' => $this->fechaLegible($ticketTarea->fechaprogramacion ?? null),
            'estado' => (string) ($ticket->estado_ticket ?? '—'),
            'area' => (string) (optional(optional($ticket)->areadestinos)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        $ticketTarea = $this->cargar($entityId);
        $ticketId = (int) ($ticketTarea->ticket_id ?? 0);
        if ($ticketId <= 0) {
            return null;
        }

        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'ticket/ticket/'.$ticketId.'/editar'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function cargar(int $entityId): Ticket_Tarea
    {
        return Ticket_Tarea::query()
            ->with([
                'tareas:id,nombre',
                'tecnicos.usuarios:id,nombre,usuario,email',
                'turnos:id,nombre',
                'tickets.usuarios:id,nombre,usuario,email',
                'tickets.salas:id,nombre,empresa_id',
                'tickets.sectores:id,nombre',
                'tickets.areadestinos:id,nombre',
                'tickets.subcategoria_tickets.categoria_tickets',
            ])
            ->findOrFail($entityId);
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = $placeholders;
        $mapa['link_consulta'] = $linkConsulta ?? ($placeholders['link_consulta'] ?? '');

        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            $clave = strtolower($m[1]);

            return $mapa[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }

    private function fechaLegible(?string $fecha): string
    {
        if (empty($fecha) || $fecha < '2000-01-01') {
            return '—';
        }

        return date('d/m/Y', strtotime($fecha));
    }
}
