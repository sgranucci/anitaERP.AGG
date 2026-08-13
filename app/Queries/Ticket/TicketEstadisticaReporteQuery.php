<?php

namespace App\Queries\Ticket;

use App\Models\Ticket\Tecnico_Ticket;
use App\Models\Ticket\Ticket;
use App\Support\Ticket\TicketEstadisticaReporteFiltros;
use App\Support\Ticket\TicketEstadisticaSupport;
use Illuminate\Support\Collection;

class TicketEstadisticaReporteQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: Collection<int, array<string, mixed>>,
     *     totales: array<string, mixed>,
     *     modo_tiempo: string
     * }
     */
    public function generar(array $filtros): array
    {
        $tecnicoId = (int) ($filtros['tecnico_id'] ?? 0);
        $porTecnico = $tecnicoId > 0;
        $areaId = (int) config('ticket.administracion_sistemas_areadestino_id', 1);

        $query = Ticket::query()
            ->with([
                'ticket_tareas.tecnicos',
                'salas',
                'sectores',
                'usuarios',
                'subcategoria_tickets.categoria_tickets',
            ])
            ->where('ticket.areadestino_id', $areaId);

        $this->aplicarRangoFechas($query, $filtros);

        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('ticket.sala_id', $salaId);
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('ticket.estado_ticket', $estado);
        }

        if ($porTecnico) {
            $query->whereHas('ticket_tareas', function ($q) use ($tecnicoId) {
                $q->where('tecnico_id', $tecnicoId);
            });
        }

        $tickets = $query->orderBy('ticket.fecha')->orderBy('ticket.id')->get();

        $filas = $tickets->map(function (Ticket $ticket) use ($porTecnico, $tecnicoId) {
            return $this->mapearFila($ticket, $porTecnico, $tecnicoId);
        })->values();

        return [
            'filas' => $filas,
            'totales' => $this->totales($filas),
            'modo_tiempo' => $porTecnico ? 'tecnico' : 'ticket',
        ];
    }

    /**
     * Técnicos operativos del área Sistemas / Tecnología.
     *
     * @return Collection<int, Tecnico_Ticket>
     */
    public function tecnicosArea(): Collection
    {
        $areaId = (int) config('ticket.administracion_sistemas_areadestino_id', 1);

        return Tecnico_Ticket::query()
            ->where('areadestino_id', $areaId)
            ->whereHas('usuarios', static fn ($q) => $q->soloActivos())
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'usuario_id', 'areadestino_id']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarRangoFechas($query, array $filtros): void
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return;
        }

        $criterio = (string) ($filtros['criterio_fecha'] ?? TicketEstadisticaReporteFiltros::CRITERIO_FECHA_ALTA);
        if ($criterio === TicketEstadisticaReporteFiltros::CRITERIO_FECHA_RESOLUCION) {
            $query->whereDate('ticket.fecha_resolucion', '>=', $desde)
                ->whereDate('ticket.fecha_resolucion', '<=', $hasta);

            return;
        }

        $query->whereDate('ticket.fecha', '>=', $desde)
            ->whereDate('ticket.fecha', '<=', $hasta);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearFila(Ticket $ticket, bool $porTecnico, int $tecnicoId): array
    {
        $tareas = $ticket->ticket_tareas ?? collect();
        $soloId = $porTecnico ? $tecnicoId : null;
        $minutosInsumidos = $porTecnico
            ? TicketEstadisticaSupport::tiempoInsumidoDeTecnico($tareas, $tecnicoId)
            : TicketEstadisticaSupport::tiempoInsumidoDesdeTareas($tareas);

        $apertura = TicketEstadisticaSupport::momentoApertura($ticket);
        $asignacion = TicketEstadisticaSupport::momentoAsignacion($tareas);
        $cierre = TicketEstadisticaSupport::momentoCierre($ticket);
        $minAsignar = TicketEstadisticaSupport::minutosEntre($apertura, $asignacion);
        $minResolver = TicketEstadisticaSupport::minutosEntre($apertura, $cierre);

        return [
            'id' => (int) $ticket->id,
            'fecha_alta' => TicketEstadisticaSupport::formatearFechaDisplay($ticket->fecha ?? null),
            'apertura' => TicketEstadisticaSupport::formatearFechaHoraDisplay($apertura),
            'sala' => (string) ($ticket->salas?->nombre ?? ''),
            'sector' => (string) ($ticket->sectores?->nombre ?? ''),
            'categoria' => (string) ($ticket->subcategoria_tickets?->categoria_tickets?->nombre ?? ''),
            'titulo' => (string) ($ticket->titulo ?? ''),
            'estado' => (string) ($ticket->estado_ticket ?? ''),
            'tecnicos' => implode(', ', TicketEstadisticaSupport::nombresTecnicos($tareas, $soloId)),
            'usuario' => (string) ($ticket->usuarios?->nombre ?? ''),
            'asignacion' => TicketEstadisticaSupport::formatearFechaHoraDisplay($asignacion),
            'resolucion' => TicketEstadisticaSupport::formatearFechaHoraDisplay($cierre),
            'minutos_insumidos' => $minutosInsumidos,
            'minutos_insumidos_fmt' => TicketEstadisticaSupport::formatearTiempoInsumido($minutosInsumidos),
            'minutos_asignacion' => $minAsignar,
            'minutos_asignacion_fmt' => TicketEstadisticaSupport::formatearDuracion($minAsignar),
            'minutos_resolucion' => $minResolver,
            'minutos_resolucion_fmt' => TicketEstadisticaSupport::formatearDuracion($minResolver),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function totales(Collection $filas): array
    {
        $cantidad = $filas->count();
        $sumaInsumido = round((float) $filas->sum('minutos_insumidos'), 2);
        $conAsig = $filas->filter(fn ($f) => $f['minutos_asignacion'] !== null);
        $conRes = $filas->filter(fn ($f) => $f['minutos_resolucion'] !== null);
        $promAsig = $conAsig->isEmpty() ? null : (int) round($conAsig->avg('minutos_asignacion'));
        $promRes = $conRes->isEmpty() ? null : (int) round($conRes->avg('minutos_resolucion'));
        $promInsumido = $cantidad > 0 ? round($sumaInsumido / $cantidad, 2) : 0.0;

        return [
            'cantidad' => $cantidad,
            'suma_insumido' => $sumaInsumido,
            'suma_insumido_fmt' => TicketEstadisticaSupport::formatearTiempoInsumido($sumaInsumido),
            'promedio_insumido' => $promInsumido,
            'promedio_insumido_fmt' => TicketEstadisticaSupport::formatearTiempoInsumido($promInsumido),
            'cantidad_con_asignacion' => $conAsig->count(),
            'promedio_asignacion' => $promAsig,
            'promedio_asignacion_fmt' => TicketEstadisticaSupport::formatearDuracion($promAsig),
            'cantidad_con_resolucion' => $conRes->count(),
            'promedio_resolucion' => $promRes,
            'promedio_resolucion_fmt' => TicketEstadisticaSupport::formatearDuracion($promRes),
        ];
    }
}
